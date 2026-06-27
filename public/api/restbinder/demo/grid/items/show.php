<?php
require __DIR__ . '/../../../../../../src/bootstrap.php';

use RestBinder\Demo\Grid\GridDatabase;
use RestBinder\Demo\Grid\GridRenderer;
use RestBinder\Demo\Grid\GridRepository;
use RestBinder\Demo\Grid\GridSession;

header('Content-Type: application/json');

try {
    GridSession::start();
    $uuid = trim((string) ($_GET['uuid'] ?? ''));
    if ($uuid === '') {
        throw new RuntimeException('Missing grid item uuid.');
    }

    $scope = ($_GET['scope'] ?? 'all') === 'mine' ? 'mine' : 'all';
    $config = require __DIR__ . '/../../../../../../config/app.php';
    $repository = new GridRepository(
        GridDatabase::connect($config['db']),
        $config['demo_grid'],
        $config['timezone']
    );
    $repository->ensureSchema();

    $item = $repository->findByUuidInScope($uuid, $scope, GridSession::currentUserId(), GridSession::sessionKey())
        ?? $repository->findByUuid($uuid);
    if (!$item) {
        throw new RuntimeException('Grid item not found.');
    }

    $renderer = new GridRenderer();

    echo json_encode([
        'resource' => $item['resource'],
        'html' => $renderer->renderExpandedPanel($item),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    http_response_code(404);
    echo json_encode(['error' => $exception->getMessage()], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
