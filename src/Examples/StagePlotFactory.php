<?php
namespace RestBinder\Examples;

use RestBinder\Core\ResourceDefinition;
use RestBinder\Core\ResourceIdentity;
use RestBinder\Core\ResourceProtocol;
use RestBinder\Core\ResourceSchema;

final class StagePlotFactory
{
    public static function create(string $id = 'stage-plot:ten-stage-demo', bool $blank = true): ResourceDefinition
    {
        return new ResourceDefinition(
            ResourceIdentity::create($id, 'StagePlot'),
            new ResourceSchema('1.0.0', [
                'required' => [
                    'sequence_mode',
                    'display_shape',
                    'stage_count',
                    'stages',
                    'plotted_stages',
                    'run_status',
                    'active_stage',
                    'elapsed_display',
                    'stream_revision',
                    'last_input_at',
                ],
                'properties' => [
                    'sequence_mode' => ['type' => 'string'],
                    'display_shape' => ['type' => 'string'],
                    'stage_count' => ['type' => 'integer', 'min' => 10],
                    'stages' => ['type' => 'array'],
                    'plotted_stages' => ['type' => 'array'],
                    'run_status' => ['type' => 'string'],
                    'active_stage' => ['type' => 'integer', 'min' => 0],
                    'elapsed_display' => ['type' => 'string'],
                    'stream_revision' => ['type' => 'integer', 'min' => 0],
                    'last_input_at' => ['type' => 'string'],
                ],
            ]),
            new ResourceProtocol('1.0.0', [
                'configure' => [
                    'stage_property' => 'sequence_mode',
                    'from' => 'streaming',
                    'to' => 'streaming',
                    'changes' => [
                        'stages' => [
                            'op' => 'input',
                            'source' => 'stages',
                        ],
                        'plotted_stages' => ['op' => 'set', 'value' => []],
                        'run_status' => ['op' => 'set', 'value' => 'idle'],
                        'active_stage' => ['op' => 'set', 'value' => 0],
                        'elapsed_display' => ['op' => 'set', 'value' => '00:00'],
                        'stream_revision' => ['op' => 'add', 'value' => 1],
                        'last_input_at' => ['op' => 'timestamp'],
                    ],
                ],
                'playback' => [
                    'stage_property' => 'sequence_mode',
                    'from' => 'streaming',
                    'to' => 'streaming',
                    'changes' => [
                        'plotted_stages' => [
                            'op' => 'input',
                            'source' => 'plotted_stages',
                        ],
                        'run_status' => [
                            'op' => 'input',
                            'source' => 'run_status',
                            'type' => 'string',
                        ],
                        'active_stage' => [
                            'op' => 'input',
                            'source' => 'active_stage',
                            'type' => 'integer',
                            'min' => 0,
                            'default' => 0,
                        ],
                        'elapsed_display' => [
                            'op' => 'input',
                            'source' => 'elapsed_display',
                            'type' => 'string',
                        ],
                        'stream_revision' => ['op' => 'add', 'value' => 1],
                        'last_input_at' => ['op' => 'timestamp'],
                    ],
                ],
            ]),
            [
                'sequence_mode' => 'streaming',
                'display_shape' => 'cartesian-stage-path',
                'stage_count' => 10,
                'stages' => $blank ? self::blankStages() : self::defaultStages(),
                'plotted_stages' => [],
                'run_status' => 'idle',
                'active_stage' => 0,
                'elapsed_display' => '00:00',
                'stream_revision' => 0,
                'last_input_at' => gmdate('c'),
            ]
        );
    }

    private static function defaultStages(): array
    {
        return [
            ['stage' => 1, 'timer' => '00:00', 'x' => 0, 'y' => 0],
            ['stage' => 2, 'timer' => '00:15', 'x' => 8, 'y' => 10],
            ['stage' => 3, 'timer' => '00:30', 'x' => 14, 'y' => 18],
            ['stage' => 4, 'timer' => '00:45', 'x' => 18, 'y' => 12],
            ['stage' => 5, 'timer' => '01:00', 'x' => 24, 'y' => 28],
            ['stage' => 6, 'timer' => '01:15', 'x' => 30, 'y' => 22],
            ['stage' => 7, 'timer' => '01:30', 'x' => 38, 'y' => 34],
            ['stage' => 8, 'timer' => '01:45', 'x' => 44, 'y' => 26],
            ['stage' => 9, 'timer' => '02:00', 'x' => 52, 'y' => 40],
            ['stage' => 10, 'timer' => '02:15', 'x' => 60, 'y' => 32],
        ];
    }

    private static function blankStages(): array
    {
        $stages = [];
        for ($index = 1; $index <= 10; $index += 1) {
            $stages[] = ['stage' => $index, 'timer' => '00:00', 'x' => null, 'y' => null];
        }

        return $stages;
    }
}
