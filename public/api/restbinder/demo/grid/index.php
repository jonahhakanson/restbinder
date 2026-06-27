<?php
require __DIR__ . '/../../../../../src/bootstrap.php';

use RestBinder\Demo\Grid\GridDatabase;
use RestBinder\Demo\Grid\GridDemoSeeder;
use RestBinder\Demo\Grid\GridRenderer;
use RestBinder\Demo\Grid\GridRepository;
use RestBinder\Demo\Grid\GridSession;

header('Content-Type: application/json');

try {
    GridSession::start();
    $config = require __DIR__ . '/../../../../../config/app.php';
    $pdo = GridDatabase::connect($config['db']);
    $repository = new GridRepository(
        $pdo,
        $config['demo_grid'],
        $config['timezone']
    );
    $repository->ensureSchema();
    (new GridDemoSeeder(
        $pdo,
        $config['demo_grid'],
        $config['timezone']
    ))->seedMinimum(150);
    $renderer = new GridRenderer();

    $scope = ($_GET['scope'] ?? 'all') === 'mine' ? 'mine' : 'all';
    $pageY = max(0, (int) ($_GET['page_y'] ?? 0));
    $pageX = max(0, (int) ($_GET['page_x'] ?? 0));
    $rows = min(12, max(1, (int) ($_GET['rows'] ?? 6)));
    $columns = min(12, max(1, (int) ($_GET['columns'] ?? 4)));

    $window = $repository->fetchWindow(
        $scope,
        GridSession::currentUserId(),
        GridSession::sessionKey(),
        $pageY,
        $pageX,
        $rows,
        $columns
    );

    echo json_encode([
        'resource' => $window['collection']['resource'],
        'page' => $window['page'],
        'grid' => $window['grid'],
        'html' => $renderer->renderPage($window),
        'items' => array_map(fn (array $item): array => $item['resource'], $window['items']),
        'has_more_x' => $window['has_more_x'],
        'has_more_y' => $window['has_more_y'],
        'next_page_x' => $window['next_page_x'],
        'next_page_y' => $window['next_page_y'],
        'cursor' => $window['cursor'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
