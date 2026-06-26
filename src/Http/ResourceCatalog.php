<?php
namespace RestBinder\Http;

use RestBinder\Core\ResourceDefinition;
use RestBinder\Examples\DandelionFactory;
use RestBinder\Examples\FeedbackCircleFactory;
use RestBinder\Examples\StagePlotFactory;
use RestBinder\Examples\WidgetFactory;

final class ResourceCatalog
{
    public function createDemo(string $id): ?ResourceDefinition
    {
        return match ($id) {
            'widget:demo' => WidgetFactory::create($id),
            'dandelion:seed-sprout-demo' => DandelionFactory::create($id),
            'feedback-circle:live-stream-demo' => FeedbackCircleFactory::create($id),
            'stage-plot:ten-stage-demo' => StagePlotFactory::create($id, true),
            default => null,
        };
    }

    public function createKnown(string $id): ?ResourceDefinition
    {
        return match (true) {
            str_starts_with($id, 'dandelion:') => DandelionFactory::create($id),
            str_starts_with($id, 'feedback-circle:') => FeedbackCircleFactory::create($id),
            str_starts_with($id, 'stage-plot:') => StagePlotFactory::create($id, true),
            str_starts_with($id, 'widget:') => WidgetFactory::create($id),
            default => null,
        };
    }
}
