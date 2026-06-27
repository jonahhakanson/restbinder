<?php
return [
    'store_path' => __DIR__ . '/../storage/resources',
    'usrb_version' => '1.0',
    'timezone' => getenv('RB_APP_TIMEZONE') ?: 'America/Los_Angeles',
    'db' => [
        'driver' => getenv('RB_DB_DRIVER') ?: 'mysql',
        'host' => getenv('RB_DB_HOST') ?: '127.0.0.1',
        'port' => (int) (getenv('RB_DB_PORT') ?: 3306),
        'database' => getenv('RB_DB_NAME') ?: 'restbinder',
        'username' => getenv('RB_DB_USER') ?: 'root',
        'password' => getenv('RB_DB_PASS') ?: '',
        'charset' => getenv('RB_DB_CHARSET') ?: 'utf8mb4',
    ],
    'demo_grid' => [
        'upload_path' => __DIR__ . '/../public/uploads/restbinder-demo',
        'upload_url' => '/uploads/restbinder-demo',
        'recency_band_size' => (int) (getenv('RB_DEMO_GRID_RECENCY_BAND_SIZE') ?: 24),
        'poll_interval_ms' => (int) (getenv('RB_DEMO_GRID_POLL_INTERVAL_MS') ?: 15000),
        'max_upload_bytes' => (int) (getenv('RB_DEMO_GRID_MAX_UPLOAD_BYTES') ?: 5242880),
    ],
];
