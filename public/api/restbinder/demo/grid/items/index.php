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
    $config = require __DIR__ . '/../../../../../../config/app.php';
    $repository = new GridRepository(
        GridDatabase::connect($config['db']),
        $config['demo_grid'],
        $config['timezone']
    );
    $repository->ensureSchema();

    $contentType = ($_POST['content_type'] ?? 'text') === 'image' ? 'image' : 'text';
    $title = trim((string) ($_POST['title'] ?? ''));
    $body = trim((string) ($_POST['body'] ?? ''));
    $imagePath = null;
    $imageAlt = trim((string) ($_POST['image_alt'] ?? ''));

    if ($contentType === 'text' && $title === '' && $body === '') {
        throw new RuntimeException('Add a title or body before creating a text square.');
    }

    if ($contentType === 'image') {
        if (!isset($_FILES['image']) || (int) ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Choose an image before creating an image square.');
        }

        $tmpPath = (string) $_FILES['image']['tmp_name'];
        $mimeType = mime_content_type($tmpPath) ?: '';
        if (!str_starts_with($mimeType, 'image/')) {
            throw new RuntimeException('Only image uploads are supported for image squares.');
        }

        $uploadDir = $config['demo_grid']['upload_path'];
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $extension = strtolower(pathinfo((string) $_FILES['image']['name'], PATHINFO_EXTENSION));
        $extension = $extension !== '' ? $extension : 'jpg';
        $fileName = sprintf('rb-demo-%s.%s', bin2hex(random_bytes(8)), preg_replace('/[^a-z0-9]+/', '', $extension));
        $destination = rtrim($uploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileName;

        if (!move_uploaded_file($tmpPath, $destination)) {
            throw new RuntimeException('Unable to store the uploaded image.');
        }

        $imagePath = rtrim($config['demo_grid']['upload_url'], '/') . '/' . $fileName;
    }

    $scope = ($_POST['scope'] ?? 'all') === 'mine' ? 'mine' : 'all';
    $created = $repository->createItem([
        'content_type' => $contentType,
        'title' => $title,
        'body' => $body,
        'image_path' => $imagePath,
        'image_alt' => $imageAlt,
        'origin_x' => (int) ($_POST['origin_x'] ?? 0),
        'origin_y' => (int) ($_POST['origin_y'] ?? 0),
        'origin_column' => (int) ($_POST['origin_column'] ?? 1),
        'origin_row' => (int) ($_POST['origin_row'] ?? 1),
        'origin_context' => trim((string) ($_POST['origin_context'] ?? 'viewport-center')),
        'origin_created_from' => trim((string) ($_POST['origin_created_from'] ?? 'floating-add-button')),
    ], GridSession::currentUserId(), GridSession::sessionKey());

    $scopedCreated = $repository->findByUuidInScope($created['id'], $scope, GridSession::currentUserId(), GridSession::sessionKey()) ?? $created;

    echo json_encode([
        'ok' => true,
        'message' => 'Square created.',
        'refresh_required' => true,
        'resource' => $scopedCreated['resource'],
        'cursor' => $repository->latestUpdatedAt($scope, GridSession::currentUserId(), GridSession::sessionKey()),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    http_response_code(422);
    echo json_encode(['error' => $exception->getMessage()], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
