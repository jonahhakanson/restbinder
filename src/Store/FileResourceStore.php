<?php
namespace RestBinder\Store;

use RestBinder\Core\ResourceDefinition;

final class FileResourceStore implements ResourceStoreInterface
{
    public function __construct(private readonly string $path)
    {
        if (!is_dir($this->path)) {
            mkdir($this->path, 0775, true);
        }
    }

    public function get(string $id): ?ResourceDefinition
    {
        $file = $this->existingFileFor($id);
        if ($file === null) {
            return null;
        }
        $data = json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
        return ResourceDefinition::fromArray($data);
    }

    public function save(ResourceDefinition $resource): void
    {
        file_put_contents(
            $this->fileFor($resource->identity->id),
            json_encode($resource->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
    }

    public function delete(string $id): void
    {
        foreach ([$this->fileFor($id), $this->legacyFileFor($id)] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    private function fileFor(string $id): string
    {
        return $this->path . '/' . rawurlencode($id) . '.json';
    }

    private function existingFileFor(string $id): ?string
    {
        $canonical = $this->fileFor($id);
        if (is_file($canonical)) {
            return $canonical;
        }

        $legacy = $this->legacyFileFor($id);
        if (is_file($legacy)) {
            return $legacy;
        }

        return null;
    }

    private function legacyFileFor(string $id): string
    {
        return $this->path . '/' . preg_replace('/[^a-zA-Z0-9_.:-]/', '_', $id) . '.json';
    }
}
