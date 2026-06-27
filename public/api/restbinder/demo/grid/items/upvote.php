<?php
require __DIR__ . '/../../../../../../src/bootstrap.php';

use RestBinder\Demo\Grid\GridDatabase;
use RestBinder\Demo\Grid\GridRepository;
use RestBinder\Demo\Grid\GridSession;

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Method not allowed.');
    }

    GridSession::start();
    $uuid = trim((string) ($_GET['uuid'] ?? $_POST['uuid'] ?? ''));
    if ($uuid === '') {
        throw new RuntimeException('Missing grid item uuid.');
    }

    $scope = ($_GET['scope'] ?? $_POST['scope'] ?? 'all') === 'mine' ? 'mine' : 'all';
    $config = require __DIR__ . '/../../../../../../config/app.php';
    $repository = new GridRepository(
        GridDatabase::connect($config['db']),
        $config['demo_grid'],
        $config['timezone']
    );
    $repository->ensureSchema();

    $updated = $repository->upvote($uuid, GridSession::voterKey());
    $scopedUpdated = $repository->findByUuidInScope($updated['id'], $scope, GridSession::currentUserId(), GridSession::sessionKey()) ?? $updated;

    echo json_encode([
        'ok' => true,
        'message' => 'Square upvoted.',
        'refresh_required' => true,
        'resource' => $scopedUpdated['resource'],
        'cursor' => $repository->latestUpdatedAt($scope, GridSession::currentUserId(), GridSession::sessionKey()),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    http_response_code(422);
    echo json_encode(['error' => $exception->getMessage()], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
