<?php
namespace RestBinder\Core;

use InvalidArgumentException;

final class ResourceSchema
{
    public function __construct(
        public readonly string $version,
        public readonly array $definition
    ) {}

    public function validate(array $state): void
    {
        foreach (($this->definition['required'] ?? []) as $required) {
            if (!array_key_exists($required, $state)) {
                throw new InvalidArgumentException("Missing required property: {$required}");
            }
        }

        foreach (($this->definition['properties'] ?? []) as $name => $rules) {
            if (!array_key_exists($name, $state)) {
                continue;
            }

            $value = $state[$name];
            $type = $rules['type'] ?? null;

            if ($type && !$this->matchesType($value, $type)) {
                throw new InvalidArgumentException("Property {$name} must be {$type}");
            }

            if (isset($rules['enum']) && !in_array($value, $rules['enum'], true)) {
                throw new InvalidArgumentException("Property {$name} has invalid value");
            }

            if (($type === 'integer' || $type === 'number') && isset($rules['min']) && $value < $rules['min']) {
                throw new InvalidArgumentException("Property {$name} is below minimum");
            }
        }
    }

    private function matchesType(mixed $value, string $type): bool
    {
        return match ($type) {
            'string' => is_string($value),
            'integer' => is_int($value),
            'number' => is_int($value) || is_float($value),
            'boolean' => is_bool($value),
            'array' => is_array($value),
            default => true,
        };
    }

    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'definition' => $this->definition,
        ];
    }
}
