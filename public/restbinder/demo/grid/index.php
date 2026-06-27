<?php
require __DIR__ . '/../../../../src/bootstrap.php';

use RestBinder\Demo\Grid\GridDatabase;
use RestBinder\Demo\Grid\GridDemoSeeder;
use RestBinder\Demo\Grid\GridRepository;
use RestBinder\Demo\Grid\GridSession;

GridSession::start();

$config = require __DIR__ . '/../../../../config/app.php';
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

$pollInterval = (int) ($config['demo_grid']['poll_interval_ms'] ?? 15000);
$surfaceCssVersion = (string) filemtime(__DIR__ . '/../../../assets/css/restbinder-surface-demo.css');
$surfaceJsVersion = (string) filemtime(__DIR__ . '/../../../assets/js/restbinder-surface-demo.js');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RestBinder Surface Demo</title>
    <link rel="stylesheet" href="/assets/css/restbinder.css">
    <link rel="stylesheet" href="/assets/css/restbinder-surface-demo.css?v=<?= htmlspecialchars($surfaceCssVersion, ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
    <main class="rb-shell rb-surface-shell">
        <section
            class="rb-demo-view"
            data-rb-surface-demo
            data-grid-endpoint="/api/restbinder/demo/grid/"
            data-grid-create-endpoint="/api/restbinder/demo/grid/items/"
            data-grid-expand-endpoint="/api/restbinder/demo/grid/items/show.php"
            data-grid-upvote-endpoint="/api/restbinder/demo/grid/items/upvote.php"
            data-grid-poll-endpoint="/api/restbinder/demo/grid/poll.php"
            data-grid-poll-interval="<?= htmlspecialchars((string) $pollInterval, ENT_QUOTES, 'UTF-8') ?>">
            <header class="rb-surface-chrome">
                <div class="rb-chrome-row rb-chrome-bar">
                    <div class="rb-chrome-brand">
                        <p class="rb-kicker">RestBinder Demo</p>
                        <h1>RestBinder Surface</h1>
                    </div>
                    <div class="rb-chrome-actions">
                        <button type="button" class="rb-chrome-button" data-rb-toggle-explainer aria-expanded="false" aria-controls="rb-demo-explainer">About Surface</button>
                        <button
                            type="button"
                            class="rb-chrome-button rb-chrome-button-primary"
                            data-rb-open-composer
                            data-origin-context="viewport-center"
                            data-origin-created-from="top-chrome-button">
                            Add Square
                        </button>
                    </div>
                </div>

                <div class="rb-chrome-row rb-chrome-controls">
                    <nav class="rb-grid-scope-tabs" aria-label="Grid scope">
                        <button
                            type="button"
                            class="rb-scope-tab is-active"
                            data-rb-scope-tab="all"
                            data-phlx-click="load"
                            data-phlx-url="/api/restbinder/demo/grid/?scope=all&page_y=0&page_x=0&rows=8&columns=4">
                            All Squares
                        </button>
                        <button
                            type="button"
                            class="rb-scope-tab"
                            data-rb-scope-tab="mine"
                            data-phlx-click="load"
                            data-phlx-url="/api/restbinder/demo/grid/?scope=mine&page_y=0&page_x=0&rows=8&columns=4">
                            My Squares
                        </button>
                    </nav>

                    <div class="rb-demo-status" data-rb-status>
                        Tap empty space in the grid or use the add control to create a square from the current viewport location.
                    </div>
                </div>

                <section id="rb-demo-explainer" class="rb-demo-explainer" aria-label="RestBinder principles" hidden>
                    <p class="rb-demo-copy">This grid is a small live resource surface. Each square is stored as a database-backed RestBinder resource. Add text or image content, upvote squares, and open any square to read the full content. The grid sorts itself in two directions: newer content appears higher, and higher-voted content appears farther left.</p>

                    <div class="rb-demo-principles">
                        <article class="rb-principle-card">
                            <h2>Content state</h2>
                            <p>Titles, bodies, image paths, votes, and timestamps live in MySQL and survive reloads.</p>
                        </article>
                        <article class="rb-principle-card">
                            <h2>Origin state</h2>
                            <p>Every new square stores where it was created from in the viewport, including row and column hints.</p>
                        </article>
                        <article class="rb-principle-card">
                            <h2>View state</h2>
                            <p>Scope, scroll position, swipe position, loaded pages, and the expanded item stay in session storage per scope.</p>
                        </article>
                    </div>
                </section>
            </header>

            <div
                id="rb-grid-viewport"
                class="rb-grid-viewport"
                data-rb-scope="all">
                <div id="rb-grid-region" class="rb-grid" aria-live="polite"></div>
            </div>

            <button
                type="button"
                class="rb-add-fab"
                data-rb-open-composer
                data-origin-context="viewport-center"
                data-origin-created-from="floating-add-button">
                Add Square
            </button>

            <div id="rb-grid-composer" class="rb-composer" hidden>
                <div class="rb-overlay-backdrop" data-rb-close-composer></div>
                <div class="rb-composer-sheet" role="dialog" aria-modal="true" aria-labelledby="rb-composer-title">
                    <div class="rb-composer-topline">
                        <h2 id="rb-composer-title">Add a Square</h2>
                        <button type="button" class="rb-sheet-close" data-rb-close-composer>Close</button>
                    </div>
                    <form class="rb-composer-form" data-rb-composer-form enctype="multipart/form-data">
                        <input type="hidden" name="scope" value="all" data-rb-scope-input>
                        <input type="hidden" name="origin_x" value="0" data-rb-origin-x>
                        <input type="hidden" name="origin_y" value="0" data-rb-origin-y>
                        <input type="hidden" name="origin_column" value="1" data-rb-origin-column>
                        <input type="hidden" name="origin_row" value="1" data-rb-origin-row>
                        <input type="hidden" name="origin_context" value="viewport-center" data-rb-origin-context>
                        <input type="hidden" name="origin_created_from" value="floating-add-button" data-rb-origin-created-from>

                        <fieldset class="rb-composer-type">
                            <legend>Square type</legend>
                            <label><input type="radio" name="content_type" value="text" checked> Text</label>
                            <label><input type="radio" name="content_type" value="image"> Image</label>
                        </fieldset>

                        <label class="rb-field">
                            <span>Title</span>
                            <input type="text" name="title" maxlength="160" placeholder="Optional title">
                        </label>

                        <label class="rb-field" data-rb-body-field>
                            <span>Text</span>
                            <textarea name="body" rows="5" placeholder="Add a square with a short note, idea, or observation."></textarea>
                        </label>

                        <label class="rb-field" data-rb-image-field hidden>
                            <span>Image</span>
                            <input type="file" name="image" accept="image/*">
                        </label>

                        <label class="rb-field" data-rb-alt-field hidden>
                            <span>Image alt text</span>
                            <input type="text" name="image_alt" maxlength="255" placeholder="Describe the image">
                        </label>

                        <div class="rb-composer-actions">
                            <button type="submit" class="rb-primary-action">Create Square</button>
                        </div>
                    </form>
                </div>
            </div>

            <div id="rb-expanded-panel" class="rb-expanded-panel" hidden>
                <div class="rb-overlay-backdrop" data-rb-close-expanded></div>
                <div class="rb-expanded-sheet" data-rb-expanded-content></div>
            </div>
        </section>
    </main>

    <script src="/assets/js/restbinder-surface-demo.js?v=<?= htmlspecialchars($surfaceJsVersion, ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
