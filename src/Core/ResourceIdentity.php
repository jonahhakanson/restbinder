<?php
namespace RestBinder\Core;

final class ResourceIdentity
{
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $createdAt
    ) {}

    public static function create(string $id, string $type): self
    {
        return new self($id, $type, gmdate('c'));
    }

    public static function fromArray(array $data): self
    {
        return new self($data['id'], $data['type'], $data['created_at'] ?? gmdate('c'));
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'created_at' => $this->createdAt,
        ];
    }
}
