<?php
namespace RestBinder\Store;

use RestBinder\Core\ResourceDefinition;

interface ResourceStoreInterface
{
    public function get(string $id): ?ResourceDefinition;
    public function save(ResourceDefinition $resource): void;
    public function delete(string $id): void;
}
