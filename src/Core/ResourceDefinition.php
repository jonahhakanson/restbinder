<?php
namespace RestBinder\Core;

final class ResourceDefinition
{
    public function __construct(
        public ResourceIdentity $identity,
        public ResourceSchema $schema,
        public ResourceProtocol $protocol,
        public array $state,
        public array $history = [],
        public ?string $hash = null
    ) {
        $this->schema->validate($this->state);
        $this->hash = $hash ?: ResourceHasher::hash($this);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            ResourceIdentity::fromArray($data['identity']),
            new ResourceSchema($data['schema']['version'], $data['schema']['definition']),
            new ResourceProtocol($data['protocol']['version'], $data['protocol']['commands']),
            $data['state'],
            $data['history'] ?? [],
            $data['hash'] ?? null
        );
    }

    public function recalculateHash(): void
    {
        $this->hash = ResourceHasher::hash($this);
    }

    public function toArray(): array
    {
        return [
            'identity' => $this->identity->toArray(),
            'schema' => $this->schema->toArray(),
            'protocol' => $this->protocol->toArray(),
            'state' => $this->state,
            'history' => $this->history,
            'hash' => $this->hash,
        ];
    }
}
