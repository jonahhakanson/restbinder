<?php
namespace RestBinder\Examples;

use RestBinder\Core\ResourceDefinition;
use RestBinder\Core\ResourceIdentity;
use RestBinder\Core\ResourceProtocol;
use RestBinder\Core\ResourceSchema;

final class FeedbackCircleFactory
{
    public static function create(string $id = 'feedback-circle:live-stream-demo'): ResourceDefinition
    {
        return new ResourceDefinition(
            ResourceIdentity::create($id, 'FeedbackCircle'),
            new ResourceSchema('1.0.0', [
                'required' => [
                    'loop_mode',
                    'diameter_px',
                    'radius_px',
                    'hue_degrees',
                    'fill_hsl',
                    'display_shape',
                    'stream_revision',
                    'last_input_at',
                ],
                'properties' => [
                    'loop_mode' => ['type' => 'string'],
                    'diameter_px' => ['type' => 'integer', 'min' => 48],
                    'radius_px' => ['type' => 'integer', 'min' => 24],
                    'hue_degrees' => ['type' => 'integer', 'min' => 0],
                    'fill_hsl' => ['type' => 'string'],
                    'display_shape' => ['type' => 'string'],
                    'stream_revision' => ['type' => 'integer', 'min' => 0],
                    'last_input_at' => ['type' => 'string'],
                ],
            ]),
            new ResourceProtocol('1.0.0', [
                'tune' => [
                    'stage_property' => 'loop_mode',
                    'from' => 'streaming',
                    'to' => 'streaming',
                    'changes' => [
                        'diameter_px' => [
                            'op' => 'input',
                            'source' => 'diameter_px',
                            'type' => 'integer',
                            'min' => 48,
                            'max' => 180,
                            'default' => 96,
                        ],
                        'radius_px' => [
                            'op' => 'scale',
                            'source' => 'diameter_px',
                            'factor' => 0.5,
                        ],
                        'hue_degrees' => [
                            'op' => 'input',
                            'source' => 'hue_degrees',
                            'type' => 'integer',
                            'min' => 0,
                            'max' => 360,
                            'default' => 210,
                        ],
                        'fill_hsl' => [
                            'op' => 'hsl',
                            'source' => 'hue_degrees',
                            'saturation' => 78,
                            'lightness' => 52,
                        ],
                        'stream_revision' => ['op' => 'add', 'value' => 1],
                        'last_input_at' => ['op' => 'timestamp'],
                    ],
                ],
            ]),
            [
                'loop_mode' => 'streaming',
                'diameter_px' => 96,
                'radius_px' => 48,
                'hue_degrees' => 210,
                'fill_hsl' => 'hsl(210, 78%, 52%)',
                'display_shape' => 'circle',
                'stream_revision' => 0,
                'last_input_at' => gmdate('c'),
            ]
        );
    }
}
