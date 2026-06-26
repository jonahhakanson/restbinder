<?php
namespace RestBinder\Core;

final class ResourceHasher
{
    public static function hash(ResourceDefinition $resource): string
    {
        $canonical = [
            'identity' => $resource->identity->toArray(),
            'schema_version' => $resource->schema->version,
            'protocol_version' => $resource->protocol->version,
            'state' => self::sortRecursive($resource->state),
            'history' => self::sortRecursive($resource->history),
        ];

        return hash('sha256', json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private static function sortRecursive(array $data): array
    {
        ksort($data);
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::sortRecursive($value);
            }
        }
        return $data;
    }
}
