<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RestBinder Demo</title>
    <link rel="stylesheet" href="/assets/css/restbinder.css">
</head>
<body>
    <main class="rb-shell">
        <header class="rb-hero">
            <p class="rb-kicker">RestBinder</p>
            <h1>Three views into object state: staged mutation, live feedback, and sequenced plotting.</h1>
            <p>The demos below show three different ways an interface can push changes into a durable object: a protocol walkthrough, a fast slider loop, and a 10-stage graphable sequence with timer and coordinate data.</p>
        </header>

        <section class="rb-grid">
            <div class="rb-demo-block">
                <div class="rb-demo-intro">
                    <p class="rb-kicker">Surface Demo</p>
                    <h2>RestBinder Surface</h2>
                    <p>Open a database-backed content grid with scope switching, square cards, origin metadata, upvotes, polling, and two-axis loading.</p>
                    <p><a href="/restbinder/demo/grid/">Launch the RestBinder Surface demo.</a></p>
                </div>
            </div>
            <div class="rb-demo-block">
                <div class="rb-demo-intro">
                    <p class="rb-kicker">Protocol Demo</p>
                    <h2>Seed to sprout</h2>
                    <p>Step the dandelion through its first two accepted mutations and inspect the persisted state history beside it.</p>
                </div>
                <div data-rb-resource="dandelion:seed-sprout-demo"></div>
            </div>
            <div class="rb-demo-block">
                <div class="rb-demo-intro">
                    <p class="rb-kicker">Feedback Loop Demo</p>
                    <h2>Live slider stream</h2>
                    <p>Move two sliders to tune circle size and color in a real-time loop between interface input and object state.</p>
                </div>
                <div data-rb-resource="feedback-circle:live-stream-demo"></div>
            </div>
            <div class="rb-demo-block">
                <div class="rb-demo-intro">
                    <p class="rb-kicker">Sequence Demo</p>
                    <h2>Ten-stage cartesian path</h2>
                    <p>Enter timer and `x`/`y` values for up to ten stages, then press `start` to plot the graph over time as the object state advances stage by stage.</p>
                </div>
                <div data-rb-resource="stage-plot:ten-stage-demo"></div>
            </div>
        </section>
    </main>

    <script src="/assets/js/restbinder.js"></script>
</body>
</html>
