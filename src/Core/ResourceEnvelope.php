<?php
namespace RestBinder\Core;

final class ResourceEnvelope
{
    public static function wrap(ResourceDefinition $resource, string $usrbVersion = '1.0'): array
    {
        return [
            'usrb' => $usrbVersion,
            'resource' => [
                'id' => $resource->identity->id,
                'type' => $resource->identity->type,
                'created_at' => $resource->identity->createdAt,
                'schema_version' => $resource->schema->version,
                'protocol_version' => $resource->protocol->version,
                'state' => $resource->state,
                'history' => $resource->history,
                'hash' => $resource->hash,
            ],
            'commands' => array_keys($resource->protocol->commands),
            'display' => [
                'component' => strtolower($resource->identity->type) . '-card',
                'label' => $resource->identity->type,
            ],
        ];
    }
}
