<?php
require __DIR__ . '/../src/bootstrap.php';

use RestBinder\Core\MutationEngine;
use RestBinder\Examples\DandelionFactory;
use RestBinder\Examples\FeedbackCircleFactory;
use RestBinder\Examples\StagePlotFactory;
use RestBinder\Http\ApiController;
use RestBinder\Http\ResourceCatalog;
use RestBinder\Store\FileResourceStore;

function runGet(ApiController $controller, string $resourceId): array
{
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET = ['resource' => $resourceId];
    http_response_code(200);

    ob_start();
    $controller->handle();
    $body = ob_get_clean();

    return [
        'status' => http_response_code(),
        'body' => json_decode($body, true, flags: JSON_THROW_ON_ERROR),
    ];
}

$engine = new MutationEngine();

$dandelion = DandelionFactory::create('dandelion:seed-sprout-test');
$hashBefore = $dandelion->hash;
$engine->apply($dandelion, 'germinate', ['moisture' => true, 'temperature_above_minimum' => true], $hashBefore);
$engine->apply($dandelion, 'sprout', [], $dandelion->hash);
assert($dandelion->state['life_stage'] === 'sprout');
assert($dandelion->state['leaf_count'] === 2);
assert($dandelion->hash !== $hashBefore);

$storePath = sys_get_temp_dir() . '/restbinder-smoke-' . bin2hex(random_bytes(4));
mkdir($storePath, 0775, true);
$store = new FileResourceStore($storePath);
$store->save($dandelion);
assert(is_file($storePath . '/' . rawurlencode($dandelion->identity->id) . '.json'));
$roundTrip = $store->get($dandelion->identity->id);
assert($roundTrip !== null);
assert($roundTrip->hash === $dandelion->hash);
assert(count($roundTrip->history) === 2);
$store->delete($dandelion->identity->id);
assert($store->get($dandelion->identity->id) === null);

$fresh = DandelionFactory::create($dandelion->identity->id);
assert($fresh->state['life_stage'] === 'seed');
assert($fresh->history === []);

$circle = FeedbackCircleFactory::create('feedback-circle:smoke-demo');
$circleHashBefore = $circle->hash;
$engine->apply($circle, 'tune', ['diameter_px' => 144, 'hue_degrees' => 36], $circleHashBefore);
assert($circle->state['diameter_px'] === 144);
assert($circle->state['radius_px'] === 72);
assert($circle->state['hue_degrees'] === 36);
assert($circle->state['fill_hsl'] === 'hsl(36, 78%, 52%)');
assert($circle->state['stream_revision'] === 1);
assert($circle->hash !== $circleHashBefore);

$store->save($circle);
$circleRoundTrip = $store->get($circle->identity->id);
assert($circleRoundTrip !== null);
assert($circleRoundTrip->state['diameter_px'] === 144);
assert(count($circleRoundTrip->history) === 1);
$store->delete($circle->identity->id);
assert($store->get($circle->identity->id) === null);

$plot = StagePlotFactory::create('stage-plot:smoke-demo');
$plotHashBefore = $plot->hash;
$stages = $plot->state['stages'];
$stages[0]['timer'] = '00:05';
$stages[0]['x'] = 4;
$stages[0]['y'] = 6;
$stages[9]['timer'] = '02:45';
$stages[9]['x'] = 64;
$stages[9]['y'] = 44;
$engine->apply($plot, 'configure', ['stages' => $stages], $plotHashBefore);
assert($plot->state['stage_count'] === 10);
assert($plot->state['stages'][0]['timer'] === '00:05');
assert($plot->state['stages'][9]['x'] === 64);
assert($plot->state['plotted_stages'] === []);
assert($plot->state['run_status'] === 'idle');
assert($plot->state['stream_revision'] === 1);
assert($plot->hash !== $plotHashBefore);

$engine->apply($plot, 'playback', [
    'plotted_stages' => [$plot->state['stages'][0], $plot->state['stages'][1]],
    'run_status' => 'running',
    'active_stage' => 2,
    'elapsed_display' => '00:15',
], $plot->hash);
assert(count($plot->state['plotted_stages']) === 2);
assert($plot->state['run_status'] === 'running');
assert($plot->state['active_stage'] === 2);
assert($plot->state['elapsed_display'] === '00:15');

$store->save($plot);
$plotRoundTrip = $store->get($plot->identity->id);
assert($plotRoundTrip !== null);
assert($plotRoundTrip->state['stages'][9]['y'] === 44);
assert(count($plotRoundTrip->history) === 2);
$store->delete($plot->identity->id);
assert($store->get($plot->identity->id) === null);

$blankPlot = StagePlotFactory::create($plot->identity->id, true);
assert($blankPlot->state['stages'][0]['timer'] === '00:00');
assert($blankPlot->state['stages'][0]['x'] === null);
assert($blankPlot->state['plotted_stages'] === []);

$catalog = new ResourceCatalog();
assert($catalog->createDemo('widget:demo') !== null);
assert($catalog->createDemo('widget:smoke-demo') === null);
assert($catalog->createKnown('widget:smoke-demo') !== null);
assert($catalog->createKnown('mystery:smoke-demo') === null);

$apiStorePath = sys_get_temp_dir() . '/restbinder-api-smoke-' . bin2hex(random_bytes(4));
mkdir($apiStorePath, 0775, true);
$apiStore = new FileResourceStore($apiStorePath);
$controller = new ApiController($apiStore);

$demoGet = runGet($controller, 'widget:demo');
assert($demoGet['status'] === 200);
assert(($demoGet['body']['resource']['id'] ?? null) === 'widget:demo');
assert(is_file($apiStorePath . '/' . rawurlencode('widget:demo') . '.json'));

$knownMissingGet = runGet($controller, 'widget:smoke-demo');
assert($knownMissingGet['status'] === 404);
assert(($knownMissingGet['body']['error'] ?? null) === 'Resource "widget:smoke-demo" was not found.');
assert(!is_file($apiStorePath . '/' . rawurlencode('widget:smoke-demo') . '.json'));

$unknownMissingGet = runGet($controller, 'mystery:smoke-demo');
assert($unknownMissingGet['status'] === 404);
assert(($unknownMissingGet['body']['error'] ?? null) === 'Resource "mystery:smoke-demo" was not found.');
assert(!is_file($apiStorePath . '/' . rawurlencode('mystery:smoke-demo') . '.json'));

$apiStore->delete('widget:demo');
rmdir($apiStorePath);
rmdir($storePath);

print "RestBinder smoke tests passed.\n";
