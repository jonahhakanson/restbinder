<?php
namespace RestBinder\Http;

use RestBinder\Core\MutationEngine;
use RestBinder\Core\ResourceDefinition;
use RestBinder\Core\ResourceEnvelope;
use RestBinder\Examples\StagePlotFactory;
use RestBinder\Store\ResourceStoreInterface;
use Throwable;

final class ApiController
{
    public function __construct(
        private readonly ResourceStoreInterface $store,
        private readonly string $usrbVersion = '1.0',
        private readonly ResourceCatalog $catalog = new ResourceCatalog()
    ) {}

    public function handle(): void
    {
        header('Content-Type: application/json');

        try {
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                $this->get();
                return;
            }

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $this->post();
                return;
            }

            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        } catch (ResourceNotFoundException $e) {
            http_response_code(404);
            echo json_encode(['error' => $e->getMessage()], JSON_PRETTY_PRINT);
        } catch (Throwable $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()], JSON_PRETTY_PRINT);
        }
    }

    private function get(): void
    {
        $id = $_GET['resource'] ?? null;
        if (!$id) {
            throw new \InvalidArgumentException('Missing resource id.');
        }

        $resource = $this->load($id) ?? $this->seedDemo($id);
        if ($resource === null) {
            throw new ResourceNotFoundException(sprintf('Resource "%s" was not found.', $id));
        }

        echo json_encode(ResourceEnvelope::wrap($resource, $this->usrbVersion), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function post(): void
    {
        $payload = json_decode(file_get_contents('php://input'), true, flags: JSON_THROW_ON_ERROR);
        $id = $payload['resource'] ?? null;
        $action = $payload['action'] ?? null;
        $command = $payload['command'] ?? null;
        $input = $payload['input'] ?? [];
        $expectHash = $payload['expect_hash'] ?? null;

        if (!$id) {
            throw new \InvalidArgumentException('POST requires resource.');
        }

        if ($action === 'reset') {
            $this->store->delete($id);
            $resource = $this->createKnownResource($id);
            $this->store->save($resource);
            echo json_encode(ResourceEnvelope::wrap($resource, $this->usrbVersion), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            return;
        }

        if (!$command) {
            throw new \InvalidArgumentException('POST requires command or action.');
        }

        $resource = $this->load($id) ?? $this->createKnownResource($id);
        $engine = new MutationEngine();
        $updated = $engine->apply($resource, $command, $input, $expectHash);
        $this->store->save($updated);

        echo json_encode(ResourceEnvelope::wrap($updated, $this->usrbVersion), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function load(string $id): ?ResourceDefinition
    {
        $existing = $this->store->get($id);
        if ($existing === null) {
            return null;
        }

        $normalized = $this->normalizeResource($existing);
        if ($normalized !== $existing) {
            $this->store->save($normalized);
        }

        return $normalized;
    }

    private function seedDemo(string $id): ?ResourceDefinition
    {
        $resource = $this->catalog->createDemo($id);
        if ($resource !== null) {
            $this->store->save($resource);
        }

        return $resource;
    }

    private function createKnownResource(string $id): ResourceDefinition
    {
        $resource = $this->catalog->createKnown($id);
        if ($resource === null) {
            throw new ResourceNotFoundException(sprintf('Resource "%s" is not a supported resource type.', $id));
        }

        return $resource;
    }

    private function normalizeResource(ResourceDefinition $resource): ResourceDefinition
    {
        if ($resource->identity->type !== 'StagePlot') {
            return $resource;
        }

        if (
            $resource->identity->id === 'stage-plot:ten-stage-demo' &&
            $resource->history === [] &&
            ($resource->state['plotted_stages'] ?? []) === [] &&
            !$this->isBlankStagePlotState($resource->state['stages'] ?? [])
        ) {
            return StagePlotFactory::create($resource->identity->id, true);
        }

        $hasPlayback = isset($resource->protocol->commands['playback']);
        $requiredState = ['plotted_stages', 'run_status', 'active_stage', 'elapsed_display'];
        $hasState = !array_diff($requiredState, array_keys($resource->state));

        if ($hasPlayback && $hasState) {
            return $resource;
        }

        $replacement = StagePlotFactory::create($resource->identity->id, true);
        $replacement->state['stages'] = $this->normalizeStageInputs($resource->state['stages'] ?? []);
        $replacement->recalculateHash();

        return $replacement;
    }

    private function normalizeStageInputs(array $stages): array
    {
        $normalized = [];

        for ($index = 0; $index < 10; $index += 1) {
            $source = $stages[$index] ?? [];
            $timer = $source['timer'] ?? '00:00';
            if (!preg_match('/^\d{2}:\d{2}$/', (string) $timer)) {
                $timer = '00:00';
            }

            $normalized[] = [
                'stage' => $index + 1,
                'timer' => $timer,
                'x' => is_numeric($source['x'] ?? null) ? (int) $source['x'] : null,
                'y' => is_numeric($source['y'] ?? null) ? (int) $source['y'] : null,
            ];
        }

        return $normalized;
    }

    private function isBlankStagePlotState(array $stages): bool
    {
        if (count($stages) !== 10) {
            return false;
        }

        foreach ($stages as $index => $stage) {
            if (($stage['stage'] ?? null) !== $index + 1) {
                return false;
            }
            if (($stage['timer'] ?? null) !== '00:00') {
                return false;
            }
            if (($stage['x'] ?? null) !== null) {
                return false;
            }
            if (($stage['y'] ?? null) !== null) {
                return false;
            }
        }

        return true;
    }
}
