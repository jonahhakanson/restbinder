<?php
namespace RestBinder\Core;

use InvalidArgumentException;

final class MutationEngine
{
    public function apply(ResourceDefinition $resource, string $commandName, array $input = [], ?string $expectHash = null): ResourceDefinition
    {
        if ($expectHash !== null && $expectHash !== $resource->hash) {
            throw new InvalidArgumentException('Resource hash conflict. Reload before mutating.');
        }

        $command = $resource->protocol->command($commandName);
        $stageProperty = $command['stage_property'] ?? 'life_stage';

        if (isset($command['from'])) {
            $allowed = is_array($command['from']) ? $command['from'] : [$command['from']];
            $current = $resource->state[$stageProperty] ?? null;
            if (!in_array($current, $allowed, true)) {
                throw new InvalidArgumentException("Command {$commandName} cannot run from {$current}");
            }
        }

        foreach (($command['requires'] ?? []) as $requirement) {
            if (!$this->requirementPasses($requirement, $resource->state, $input)) {
                throw new InvalidArgumentException("Requirement failed: {$requirement}");
            }
        }

        $before = $resource->state;
        $after = $before;

        if (isset($command['to'])) {
            $after[$stageProperty] = $command['to'];
        }

        foreach (($command['changes'] ?? []) as $property => $change) {
            $after[$property] = $this->applyChange($after[$property] ?? null, $change, $input, $after);
        }

        $resource->schema->validate($after);
        $resource->state = $after;

        $resource->history[] = [
            'command' => $commandName,
            'timestamp' => gmdate('c'),
            'input' => $input,
            'before' => $before,
            'after' => $after,
        ];

        $resource->recalculateHash();
        $last = array_key_last($resource->history);
        $resource->history[$last]['hash_after'] = $resource->hash;
        $resource->recalculateHash();

        return $resource;
    }

    private function requirementPasses(string $requirement, array $state, array $input): bool
    {
        if (array_key_exists($requirement, $input)) {
            return (bool) $input[$requirement];
        }
        if (array_key_exists($requirement, $state)) {
            return (bool) $state[$requirement];
        }

        if (preg_match('/^([a-zA-Z0-9_]+)\s*(>=|<=|>|<|==)\s*([0-9.]+)$/', $requirement, $m)) {
            $left = $state[$m[1]] ?? $input[$m[1]] ?? null;
            if (!is_numeric($left)) {
                return false;
            }
            $right = (float) $m[3];
            return match ($m[2]) {
                '>=' => $left >= $right,
                '<=' => $left <= $right,
                '>' => $left > $right,
                '<' => $left < $right,
                '==' => (float) $left === $right,
                default => false,
            };
        }

        return false;
    }

    private function applyChange(mixed $current, mixed $change, array $input = [], array $state = []): mixed
    {
        if (!is_array($change) || !isset($change['op'])) {
            return $change;
        }

        return match ($change['op']) {
            'set' => $change['value'] ?? null,
            'add' => ($current ?? 0) + ($change['value'] ?? 0),
            'append' => array_merge(is_array($current) ? $current : [], [$change['value'] ?? null]),
            'input' => $this->inputValue($change, $input, $current),
            'scale' => $this->scaledValue($change, $input, $state),
            'hsl' => $this->hslValue($change, $input, $state),
            'timestamp' => gmdate('c'),
            default => $current,
        };
    }

    private function inputValue(array $change, array $input, mixed $fallback): mixed
    {
        $source = $change['source'] ?? null;
        $value = $source !== null && array_key_exists($source, $input)
            ? $input[$source]
            : $fallback;

        return $this->normalizeScalar($value, $change);
    }

    private function scaledValue(array $change, array $input, array $state): float|int
    {
        $source = $change['source'] ?? null;
        $value = $source !== null
            ? ($state[$source] ?? $input[$source] ?? 0)
            : 0;

        $scaled = (float) $value * (float) ($change['factor'] ?? 1);
        $precision = (int) ($change['precision'] ?? 0);
        $scaled = round($scaled, $precision);

        return $precision === 0 ? (int) $scaled : $scaled;
    }

    private function hslValue(array $change, array $input, array $state): string
    {
        $source = $change['source'] ?? 'hue_degrees';
        $hue = $state[$source] ?? $input[$source] ?? 0;
        $hue = max(0, min(360, (int) round((float) $hue)));
        $saturation = (int) ($change['saturation'] ?? 78);
        $lightness = (int) ($change['lightness'] ?? 52);

        return "hsl({$hue}, {$saturation}%, {$lightness}%)";
    }

    private function normalizeScalar(mixed $value, array $change): mixed
    {
        $type = $change['type'] ?? null;

        if ($type === 'integer' || $type === 'number') {
            if (!is_numeric($value)) {
                $value = $change['default'] ?? 0;
            }

            $numeric = (float) $value;
            if (isset($change['min'])) {
                $numeric = max((float) $change['min'], $numeric);
            }
            if (isset($change['max'])) {
                $numeric = min((float) $change['max'], $numeric);
            }

            return $type === 'integer'
                ? (int) round($numeric)
                : $numeric;
        }

        if ($type === 'string') {
            return (string) $value;
        }

        if ($type === 'boolean') {
            return (bool) $value;
        }

        return $value;
    }
}
