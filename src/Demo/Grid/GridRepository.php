<?php
namespace RestBinder\Demo\Grid;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;

final class GridRepository
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly array $config,
        private readonly string $timezone = 'America/Los_Angeles'
    ) {}

    public function ensureSchema(): void
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver !== 'mysql') {
            throw new RuntimeException('The RestBinder Surface demo currently expects a MySQL connection.');
        }

        $this->pdo->exec(
            <<<SQL
            CREATE TABLE IF NOT EXISTS rb_demo_grid_items (
              id INT AUTO_INCREMENT PRIMARY KEY,
              item_uuid CHAR(36) NOT NULL UNIQUE,
              content_type ENUM('text', 'image') NOT NULL DEFAULT 'text',
              title VARCHAR(160) NULL,
              body TEXT NULL,
              image_path VARCHAR(255) NULL,
              image_alt VARCHAR(255) NULL,
              upvote_count INT NOT NULL DEFAULT 0,
              origin_x INT NULL,
              origin_y INT NULL,
              origin_column INT NULL,
              origin_row INT NULL,
              origin_context VARCHAR(80) NULL,
              origin_created_from VARCHAR(120) NULL,
              created_by INT NULL,
              created_by_session_key VARCHAR(128) NULL,
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              is_deleted TINYINT(1) NOT NULL DEFAULT 0,
              INDEX idx_grid_scope_user (created_by, created_at),
              INDEX idx_grid_scope_session (created_by_session_key, created_at),
              INDEX idx_grid_sort (is_deleted, created_at, upvote_count),
              INDEX idx_grid_origin (origin_column, origin_row),
              INDEX idx_grid_created_at (created_at),
              INDEX idx_grid_upvotes (upvote_count)
            )
            SQL
        );

        $this->pdo->exec(
            <<<SQL
            CREATE TABLE IF NOT EXISTS rb_demo_grid_votes (
              id INT AUTO_INCREMENT PRIMARY KEY,
              item_id INT NOT NULL,
              voter_key VARCHAR(128) NOT NULL,
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              UNIQUE KEY uniq_item_voter (item_id, voter_key),
              INDEX idx_grid_votes_item (item_id),
              CONSTRAINT fk_grid_vote_item
                FOREIGN KEY (item_id)
                REFERENCES rb_demo_grid_items(id)
                ON DELETE CASCADE
            )
            SQL
        );
    }

    public function fetchWindow(string $scope, ?int $userId, string $sessionKey, int $pageY, int $pageX, int $rows, int $columns): array
    {
        $items = $this->fetchScopedItems($scope, $userId, $sessionKey);
        $rowGroups = $this->buildRowGroups($items);
        $rowStart = $pageY * $rows;
        $rowEnd = $rowStart + $rows - 1;
        $columnStart = $pageX * $columns;
        $columnEnd = $columnStart + $columns - 1;

        $windowItems = [];
        $maxColumns = 0;
        $hasMoreX = false;

        for ($rowIndex = $rowStart; $rowIndex <= $rowEnd; $rowIndex += 1) {
            $group = $rowGroups[$rowIndex] ?? [];
            $maxColumns = max($maxColumns, count($group));
            if (count($group) > $columnEnd + 1) {
                $hasMoreX = true;
            }

            foreach (array_slice($group, $columnStart, $columns) as $columnOffset => $item) {
                $item['display']['row'] = $rowIndex;
                $item['display']['column'] = $columnStart + $columnOffset;
                $windowItems[] = $item;
            }
        }

        return [
            'collection' => $this->buildCollectionResource($scope, count($items)),
            'items' => $windowItems,
            'page' => [
                'x' => $pageX,
                'y' => $pageY,
                'rows' => $rows,
                'columns' => $columns,
            ],
            'grid' => [
                'total_items' => count($items),
                'total_rows' => count($rowGroups),
                'max_columns' => $maxColumns,
            ],
            'has_more_x' => $hasMoreX,
            'has_more_y' => $rowEnd + 1 < count($rowGroups),
            'next_page_x' => $hasMoreX ? $pageX + 1 : null,
            'next_page_y' => $rowEnd + 1 < count($rowGroups) ? $pageY + 1 : null,
            'cursor' => $this->latestUpdatedAt($scope, $userId, $sessionKey),
        ];
    }

    public function createItem(array $payload, ?int $userId, string $sessionKey): array
    {
        $uuid = $this->generateUuid();
        $statement = $this->pdo->prepare(
            <<<SQL
            INSERT INTO rb_demo_grid_items (
              item_uuid,
              content_type,
              title,
              body,
              image_path,
              image_alt,
              origin_x,
              origin_y,
              origin_column,
              origin_row,
              origin_context,
              origin_created_from,
              created_by,
              created_by_session_key
            ) VALUES (
              :item_uuid,
              :content_type,
              :title,
              :body,
              :image_path,
              :image_alt,
              :origin_x,
              :origin_y,
              :origin_column,
              :origin_row,
              :origin_context,
              :origin_created_from,
              :created_by,
              :created_by_session_key
            )
            SQL
        );

        $statement->execute([
            'item_uuid' => $uuid,
            'content_type' => $payload['content_type'],
            'title' => $this->nullIfBlank($payload['title'] ?? null),
            'body' => $this->nullIfBlank($payload['body'] ?? null),
            'image_path' => $this->nullIfBlank($payload['image_path'] ?? null),
            'image_alt' => $this->nullIfBlank($payload['image_alt'] ?? null),
            'origin_x' => $payload['origin_x'],
            'origin_y' => $payload['origin_y'],
            'origin_column' => $payload['origin_column'],
            'origin_row' => $payload['origin_row'],
            'origin_context' => $payload['origin_context'],
            'origin_created_from' => $payload['origin_created_from'],
            'created_by' => $userId,
            'created_by_session_key' => $sessionKey,
        ]);

        return $this->findByUuid($uuid) ?? throw new RuntimeException('Unable to load created grid item.');
    }

    public function findByUuid(string $uuid): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM rb_demo_grid_items WHERE item_uuid = :uuid AND is_deleted = 0 LIMIT 1'
        );
        $statement->execute(['uuid' => $uuid]);
        $row = $statement->fetch();

        return $row ? $this->hydrateItem($row) : null;
    }

    public function findByUuidInScope(string $uuid, string $scope, ?int $userId, string $sessionKey): ?array
    {
        $rowGroups = $this->buildRowGroups($this->fetchScopedItems($scope, $userId, $sessionKey));

        foreach ($rowGroups as $rowIndex => $group) {
            foreach ($group as $columnIndex => $item) {
                if ($item['id'] !== $uuid) {
                    continue;
                }

                $item['display']['row'] = $rowIndex;
                $item['display']['column'] = $columnIndex;

                return $item;
            }
        }

        return null;
    }

    public function upvote(string $uuid, string $voterKey): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM rb_demo_grid_items WHERE item_uuid = :uuid AND is_deleted = 0 LIMIT 1'
        );
        $statement->execute(['uuid' => $uuid]);
        $row = $statement->fetch();
        if (!$row) {
            throw new RuntimeException('Grid item not found.');
        }

        $this->pdo->beginTransaction();

        try {
            $voteStatement = $this->pdo->prepare(
                'INSERT IGNORE INTO rb_demo_grid_votes (item_id, voter_key) VALUES (:item_id, :voter_key)'
            );
            $voteStatement->execute([
                'item_id' => $row['id'],
                'voter_key' => $voterKey,
            ]);

            if ($voteStatement->rowCount() > 0) {
                $updateStatement = $this->pdo->prepare(
                    'UPDATE rb_demo_grid_items SET upvote_count = upvote_count + 1, updated_at = NOW() WHERE id = :id'
                );
                $updateStatement->execute(['id' => $row['id']]);
            }

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        return $this->findByUuid($uuid) ?? throw new RuntimeException('Grid item not found after upvote.');
    }

    public function latestUpdatedAt(string $scope, ?int $userId, string $sessionKey): ?string
    {
        [$whereSql, $params] = $this->scopeWhere($scope, $userId, $sessionKey);
        $statement = $this->pdo->prepare(
            sprintf(
                'SELECT MAX(COALESCE(updated_at, created_at)) AS latest_at FROM rb_demo_grid_items WHERE %s',
                $whereSql
            )
        );
        $statement->execute($params);
        $latest = $statement->fetchColumn();

        if (!$latest) {
            return null;
        }

        return $this->toIso8601((string) $latest);
    }

    private function fetchScopedItems(string $scope, ?int $userId, string $sessionKey): array
    {
        [$whereSql, $params] = $this->scopeWhere($scope, $userId, $sessionKey);

        $statement = $this->pdo->prepare(
            sprintf(
                'SELECT * FROM rb_demo_grid_items WHERE %s ORDER BY created_at DESC, upvote_count DESC, id DESC',
                $whereSql
            )
        );
        $statement->execute($params);

        return array_map(fn (array $row): array => $this->hydrateItem($row), $statement->fetchAll());
    }

    private function scopeWhere(string $scope, ?int $userId, string $sessionKey): array
    {
        if ($scope === 'mine') {
            if ($userId !== null) {
                return ['is_deleted = 0 AND created_by = :created_by', ['created_by' => $userId]];
            }

            return [
                'is_deleted = 0 AND created_by_session_key = :created_by_session_key',
                ['created_by_session_key' => $sessionKey],
            ];
        }

        return ['is_deleted = 0', []];
    }

    private function buildRowGroups(array $items): array
    {
        $bandSize = max(4, (int) ($this->config['recency_band_size'] ?? 24));
        $groups = [];

        foreach (array_chunk($items, $bandSize) as $rowIndex => $chunk) {
            usort(
                $chunk,
                function (array $left, array $right): int {
                    $upvoteCompare = $right['state']['upvote_count'] <=> $left['state']['upvote_count'];
                    if ($upvoteCompare !== 0) {
                        return $upvoteCompare;
                    }

                    $createdCompare = strcmp($right['state']['created_at'], $left['state']['created_at']);
                    if ($createdCompare !== 0) {
                        return $createdCompare;
                    }

                    return strcmp($left['id'], $right['id']);
                }
            );

            $groups[$rowIndex] = array_values($chunk);
        }

        return $groups;
    }

    private function buildCollectionResource(string $scope, int $count): array
    {
        return [
            'resource' => [
                'type' => 'demo_grid_collection',
                'id' => $scope,
                'state' => [
                    'scope' => $scope,
                    'count' => $count,
                    'sort' => [
                        'vertical_axis' => 'recency',
                        'horizontal_axis' => 'upvotes',
                    ],
                ],
                'controls' => [
                    'create' => true,
                    'refresh' => true,
                    'switch_to_all' => true,
                    'switch_to_mine' => true,
                ],
                'links' => [
                    'self' => sprintf('/api/restbinder/demo/grid/?scope=%s', rawurlencode($scope)),
                    'all' => '/api/restbinder/demo/grid/?scope=all',
                    'mine' => '/api/restbinder/demo/grid/?scope=mine',
                    'create' => '/api/restbinder/demo/grid/items/',
                ],
            ],
        ];
    }

    private function hydrateItem(array $row): array
    {
        $createdAtIso = $this->toIso8601((string) $row['created_at']);
        $preview = $row['content_type'] === 'image'
            ? $this->imagePreview((string) ($row['title'] ?? ''), (string) ($row['image_alt'] ?? ''))
            : $this->previewText((string) ($row['body'] ?? ''), 96);

        return [
            'db_id' => (int) $row['id'],
            'resource' => [
                'type' => 'demo_grid_item',
                'id' => $row['item_uuid'],
                'state' => [
                    'content_type' => $row['content_type'],
                    'title' => $row['title'],
                    'body' => $row['body'],
                    'image_path' => $row['image_path'],
                    'image_alt' => $row['image_alt'],
                    'preview' => $preview,
                    'upvote_count' => (int) $row['upvote_count'],
                    'created_at' => $createdAtIso,
                    'age_label' => $this->ageLabel($createdAtIso),
                ],
                'origin' => [
                    'x' => $row['origin_x'] !== null ? (int) $row['origin_x'] : null,
                    'y' => $row['origin_y'] !== null ? (int) $row['origin_y'] : null,
                    'column' => $row['origin_column'] !== null ? (int) $row['origin_column'] : null,
                    'row' => $row['origin_row'] !== null ? (int) $row['origin_row'] : null,
                    'context' => $row['origin_context'],
                    'created_from' => $row['origin_created_from'],
                ],
                'display' => [
                    'row' => 0,
                    'column' => 0,
                    'sort_basis' => [
                        'recency' => 'new',
                        'upvotes' => (int) $row['upvote_count'],
                    ],
                ],
                'controls' => [
                    'expand' => true,
                    'collapse' => true,
                    'upvote' => true,
                ],
                'links' => [
                    'self' => sprintf('/api/restbinder/demo/grid/items/show.php?uuid=%s', rawurlencode($row['item_uuid'])),
                    'upvote' => sprintf('/api/restbinder/demo/grid/items/upvote.php?uuid=%s', rawurlencode($row['item_uuid'])),
                ],
            ],
            'id' => $row['item_uuid'],
            'state' => [
                'content_type' => $row['content_type'],
                'title' => $row['title'],
                'body' => $row['body'],
                'image_path' => $row['image_path'],
                'image_alt' => $row['image_alt'],
                'preview' => $preview,
                'upvote_count' => (int) $row['upvote_count'],
                'created_at' => $createdAtIso,
                'age_label' => $this->ageLabel($createdAtIso),
            ],
            'origin' => [
                'x' => $row['origin_x'] !== null ? (int) $row['origin_x'] : null,
                'y' => $row['origin_y'] !== null ? (int) $row['origin_y'] : null,
                'column' => $row['origin_column'] !== null ? (int) $row['origin_column'] : null,
                'row' => $row['origin_row'] !== null ? (int) $row['origin_row'] : null,
                'context' => $row['origin_context'],
                'created_from' => $row['origin_created_from'],
            ],
            'display' => [
                'row' => 0,
                'column' => 0,
                'sort_basis' => [
                    'recency' => 'new',
                    'upvotes' => (int) $row['upvote_count'],
                ],
            ],
            'links' => [
                'self' => sprintf('/api/restbinder/demo/grid/items/show.php?uuid=%s', rawurlencode($row['item_uuid'])),
                'upvote' => sprintf('/api/restbinder/demo/grid/items/upvote.php?uuid=%s', rawurlencode($row['item_uuid'])),
            ],
        ];
    }

    private function toIso8601(string $timestamp): string
    {
        $date = new DateTimeImmutable($timestamp, new DateTimeZone($this->timezone));

        return $date->format(DATE_ATOM);
    }

    private function ageLabel(string $iso8601): string
    {
        $created = new DateTimeImmutable($iso8601);
        $now = new DateTimeImmutable('now', $created->getTimezone());
        $seconds = max(0, $now->getTimestamp() - $created->getTimestamp());

        if ($seconds < 60) {
            return 'just now';
        }
        if ($seconds < 3600) {
            return sprintf('%dm ago', (int) floor($seconds / 60));
        }
        if ($seconds < 86400) {
            return sprintf('%dh ago', (int) floor($seconds / 3600));
        }

        return sprintf('%dd ago', (int) floor($seconds / 86400));
    }

    private function previewText(string $text, int $limit): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim($text)) ?? '';
        if ($normalized === '') {
            return 'Open this square to read more.';
        }

        if (mb_strlen($normalized) <= $limit) {
            return $normalized;
        }

        return rtrim(mb_substr($normalized, 0, $limit - 3)) . '...';
    }

    private function imagePreview(string $title, string $alt): string
    {
        $alt = preg_replace('/\s+/', ' ', trim($alt)) ?? '';
        if ($alt === '') {
            return '';
        }

        if (mb_strlen($alt) <= 72) {
            return $alt;
        }

        return rtrim(mb_substr($alt, 0, 69)) . '...';
    }

    private function nullIfBlank(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function generateUuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
