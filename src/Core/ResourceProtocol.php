<?php
namespace RestBinder\Core;

use InvalidArgumentException;

final class ResourceProtocol
{
    public function __construct(
        public readonly string $version,
        public readonly array $commands
    ) {}

    public function command(string $name): array
    {
        if (!isset($this->commands[$name])) {
            throw new InvalidArgumentException("Unknown command: {$name}");
        }
        return $this->commands[$name];
    }

    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'commands' => $this->commands,
        ];
    }
}
