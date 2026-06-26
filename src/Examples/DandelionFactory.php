<?php
namespace RestBinder\Examples;

use RestBinder\Core\ResourceDefinition;
use RestBinder\Core\ResourceIdentity;
use RestBinder\Core\ResourceProtocol;
use RestBinder\Core\ResourceSchema;

final class DandelionFactory
{
    public static function create(string $id = 'dandelion:yard-bed-04:0001'): ResourceDefinition
    {
        $stages = ['seed', 'germinating', 'sprout', 'vegetative', 'flowering', 'seeding', 'spent'];

        return new ResourceDefinition(
            ResourceIdentity::create($id, 'Dandelion'),
            new ResourceSchema('1.0.0', [
                'required' => ['life_stage', 'root_depth_mm', 'leaf_count', 'stem_height_mm', 'flower_state', 'seed_head_state', 'seed_count_estimate', 'color', 'display_shape'],
                'properties' => [
                    'life_stage' => ['type' => 'string', 'enum' => $stages],
                    'root_depth_mm' => ['type' => 'number', 'min' => 0],
                    'leaf_count' => ['type' => 'integer', 'min' => 0],
                    'stem_height_mm' => ['type' => 'number', 'min' => 0],
                    'flower_state' => ['type' => 'string'],
                    'seed_head_state' => ['type' => 'string'],
                    'seed_count_estimate' => ['type' => 'integer', 'min' => 0],
                    'color' => ['type' => 'string'],
                    'display_shape' => ['type' => 'string'],
                ],
            ]),
            new ResourceProtocol('1.0.0', [
                'germinate' => [
                    'from' => 'seed',
                    'to' => 'germinating',
                    'requires' => ['moisture', 'temperature_above_minimum'],
                    'changes' => [
                        'root_depth_mm' => ['op' => 'add', 'value' => 2],
                        'seed_head_state' => ['op' => 'set', 'value' => 'opened'],
                        'display_shape' => ['op' => 'set', 'value' => 'split-seed-with-root'],
                    ],
                ],
                'sprout' => [
                    'from' => 'germinating',
                    'to' => 'sprout',
                    'requires' => ['root_depth_mm >= 2'],
                    'changes' => [
                        'leaf_count' => ['op' => 'add', 'value' => 2],
                        'color' => ['op' => 'set', 'value' => 'pale green'],
                        'display_shape' => ['op' => 'set', 'value' => 'two-leaf-sprout'],
                    ],
                ],
                'growLeaves' => [
                    'from' => 'sprout',
                    'to' => 'vegetative',
                    'requires' => ['sunlight', 'water'],
                    'changes' => [
                        'leaf_count' => ['op' => 'add', 'value' => 8],
                        'root_depth_mm' => ['op' => 'add', 'value' => 40],
                        'color' => ['op' => 'set', 'value' => 'green'],
                        'display_shape' => ['op' => 'set', 'value' => 'low-leaf-rosette'],
                    ],
                ],
                'flower' => [
                    'from' => 'vegetative',
                    'to' => 'flowering',
                    'requires' => ['maturity', 'sufficient_light'],
                    'changes' => [
                        'stem_height_mm' => ['op' => 'add', 'value' => 120],
                        'flower_state' => ['op' => 'set', 'value' => 'yellow_flower'],
                        'color' => ['op' => 'set', 'value' => 'yellow'],
                        'display_shape' => ['op' => 'set', 'value' => 'yellow-flowering-dandelion'],
                    ],
                ],
                'seed' => [
                    'from' => 'flowering',
                    'to' => 'seeding',
                    'requires' => ['flower_maturation'],
                    'changes' => [
                        'flower_state' => ['op' => 'set', 'value' => 'spent'],
                        'seed_head_state' => ['op' => 'set', 'value' => 'white_pappus_seed_head'],
                        'seed_count_estimate' => ['op' => 'set', 'value' => 150],
                        'color' => ['op' => 'set', 'value' => 'white'],
                        'display_shape' => ['op' => 'set', 'value' => 'white-puff-seed-head'],
                    ],
                ],
            ]),
            [
                'life_stage' => 'seed',
                'root_depth_mm' => 0,
                'leaf_count' => 0,
                'stem_height_mm' => 0,
                'flower_state' => 'none',
                'seed_head_state' => 'dormant',
                'seed_count_estimate' => 1,
                'color' => 'brown',
                'display_shape' => 'small-oval-seed',
            ]
        );
    }
}
