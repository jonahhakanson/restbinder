<?php
namespace RestBinder\Examples;

use RestBinder\Core\ResourceDefinition;
use RestBinder\Core\ResourceIdentity;
use RestBinder\Core\ResourceProtocol;
use RestBinder\Core\ResourceSchema;

final class WidgetFactory
{
    public static function create(string $id = 'widget:demo'): ResourceDefinition
    {
        return new ResourceDefinition(
            ResourceIdentity::create($id, 'Widget'),
            new ResourceSchema('1.0.0', [
                'required' => ['shape', 'sides', 'side_length', 'fill', 'rotation_degrees', 'display_shape'],
                'properties' => [
                    'shape' => ['type' => 'string', 'enum' => ['diamond', 'pentagon']],
                    'sides' => ['type' => 'integer', 'min' => 3],
                    'side_length' => ['type' => 'number', 'min' => 1],
                    'fill' => ['type' => 'string'],
                    'rotation_degrees' => ['type' => 'number'],
                    'display_shape' => ['type' => 'string'],
                ],
            ]),
            new ResourceProtocol('1.0.0', [
                'reshape' => [
                    'stage_property' => 'shape',
                    'from' => 'diamond',
                    'to' => 'pentagon',
                    'requires' => ['approved'],
                    'changes' => [
                        'sides' => ['op' => 'set', 'value' => 5],
                        'side_length' => ['op' => 'set', 'value' => 64],
                        'fill' => ['op' => 'set', 'value' => 'green'],
                        'rotation_degrees' => ['op' => 'set', 'value' => 12],
                        'display_shape' => ['op' => 'set', 'value' => 'green-pentagon'],
                    ],
                ],
                'reset' => [
                    'stage_property' => 'shape',
                    'from' => ['diamond', 'pentagon'],
                    'to' => 'diamond',
                    'requires' => [],
                    'changes' => [
                        'sides' => ['op' => 'set', 'value' => 4],
                        'side_length' => ['op' => 'set', 'value' => 48],
                        'fill' => ['op' => 'set', 'value' => 'blue'],
                        'rotation_degrees' => ['op' => 'set', 'value' => 45],
                        'display_shape' => ['op' => 'set', 'value' => 'blue-diamond'],
                    ],
                ],
            ]),
            [
                'shape' => 'diamond',
                'sides' => 4,
                'side_length' => 48,
                'fill' => 'blue',
                'rotation_degrees' => 45,
                'display_shape' => 'blue-diamond',
            ]
        );
    }
}
