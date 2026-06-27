<?php
require __DIR__ . '/../../../../../src/bootstrap.php';

use RestBinder\Demo\Grid\GridDatabase;
use RestBinder\Demo\Grid\GridRepository;
use RestBinder\Demo\Grid\GridSession;

header('Content-Type: application/json');

try {
    GridSession::start();
    $config = require __DIR__ . '/../../../../../config/app.php';
    $repository = new GridRepository(
        GridDatabase::connect($config['db']),
        $config['demo_grid'],
        $config['timezone']
    );
    $repository->ensureSchema();

    $scope = ($_GET['scope'] ?? 'all') === 'mine' ? 'mine' : 'all';
    $cursor = trim((string) ($_GET['cursor'] ?? ''));
    $latest = $repository->latestUpdatedAt($scope, GridSession::currentUserId(), GridSession::sessionKey());

    echo json_encode([
        'cursor' => $latest,
        'has_updates' => $latest !== null && ($cursor === '' || strcmp($latest, $cursor) > 0),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
