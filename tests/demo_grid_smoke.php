<?php
require __DIR__ . '/../src/bootstrap.php';

use RestBinder\Demo\Grid\GridDatabase;
use RestBinder\Demo\Grid\GridRenderer;
use RestBinder\Demo\Grid\GridRepository;

$config = require __DIR__ . '/../config/app.php';
$pdo = GridDatabase::connect($config['db']);
$repository = new GridRepository($pdo, $config['demo_grid'], $config['timezone']);
$renderer = new GridRenderer();
$repository->ensureSchema();

$sessionKey = 'smoke-' . bin2hex(random_bytes(6));
$voterKey = 'smoke-voter-' . bin2hex(random_bytes(6));

$createdIds = [];

try {
    $first = $repository->createItem([
        'content_type' => 'text',
        'title' => 'Smoke square',
        'body' => 'First grid smoke record.',
        'image_path' => null,
        'image_alt' => null,
        'origin_x' => 120,
        'origin_y' => 240,
        'origin_column' => 2,
        'origin_row' => 3,
        'origin_context' => 'smoke-test',
        'origin_created_from' => 'php-smoke',
    ], null, $sessionKey);
    $createdIds[] = $first['db_id'];

    $second = $repository->createItem([
        'content_type' => 'text',
        'title' => 'Second smoke square',
        'body' => 'Second grid smoke record.',
        'image_path' => null,
        'image_alt' => null,
        'origin_x' => 260,
        'origin_y' => 420,
        'origin_column' => 3,
        'origin_row' => 4,
        'origin_context' => 'smoke-test',
        'origin_created_from' => 'php-smoke',
    ], null, $sessionKey);
    $createdIds[] = $second['db_id'];

    $allWindow = $repository->fetchWindow('all', null, $sessionKey, 0, 0, 6, 4);
    $mineWindow = $repository->fetchWindow('mine', null, $sessionKey, 0, 0, 6, 4);

    assert(($mineWindow['collection']['resource']['state']['count'] ?? 0) >= 2);
    assert(count($mineWindow['items']) >= 2);
    assert(str_contains($renderer->renderPage($mineWindow), 'rb-grid-square'));

    $updated = $repository->upvote($first['id'], $voterKey);
    assert(($updated['state']['upvote_count'] ?? 0) === 1);

    $updatedAgain = $repository->upvote($first['id'], $voterKey);
    assert(($updatedAgain['state']['upvote_count'] ?? 0) === 1);

    $expanded = $repository->findByUuidInScope($first['id'], 'mine', null, $sessionKey);
    assert($expanded !== null);
    assert(str_contains($renderer->renderExpandedPanel($expanded), 'current recency and upvote rank'));

    $cursor = $repository->latestUpdatedAt('mine', null, $sessionKey);
    assert($cursor !== null);
    assert(($allWindow['grid']['total_items'] ?? 0) >= ($mineWindow['grid']['total_items'] ?? 0));

    print "RestBinder demo grid smoke tests passed.\n";
} finally {
    if ($createdIds !== []) {
        $placeholders = implode(',', array_fill(0, count($createdIds), '?'));
        $statement = $pdo->prepare("DELETE FROM rb_demo_grid_items WHERE id IN ($placeholders)");
        $statement->execute($createdIds);
    }
}
