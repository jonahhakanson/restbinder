<?php
namespace RestBinder\Demo\Grid;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;

final class GridDemoSeeder
{
    private array $textTitles = [
        'Soft launch note',
        'Rain check',
        'Pocket observation',
        'Blue-hour memo',
        'Queue thought',
        'Late train summary',
        'Studio scrap',
        'Field report',
        'Margin note',
        'Night desk',
        'Pattern sample',
        'Roadside caption',
    ];

    private array $textFragments = [
        'A tiny resource surface can still feel durable when the state is clear and the viewport does not jump.',
        'The point of this square is to prove that even short copy can read like an object instead of a feed item.',
        'Someone dropped this note in from the right edge, gave it a quick vote, and let the server decide where it belonged next.',
        'The surface should feel thumb-ready on a phone but still hold together when a desktop window stretches out wide.',
        'A little provenance goes a long way once the UI starts moving content around on behalf of the ranking rules.',
        'This text is intentionally longer so the square has to negotiate scale, line count, and breathing room without becoming mush.',
        'There is no mystery about who owns the truth here; the browser keeps the comfort state and the server keeps the canonical state.',
        'A square that began at row three column six can end up somewhere else entirely, and that is part of the story rather than a bug.',
        'Sometimes the best demo data is just enough texture to make the layout decisions visible at a glance.',
        'PHLX-style rebinding works better when the viewport itself stays put and only the content inside it is asked to move.',
        'This one is short on purpose.',
        'If the interface can keep your place while votes reshuffle the front edge of the grid, the demo has done real work.',
    ];

    private array $imageTitles = [
        'Amber signal',
        'Sea glass field',
        'Orbit study',
        'Night ladder',
        'Citrus band',
        'Cobalt room',
        'Signal bloom',
        'Paper horizon',
        'Static weather',
        'Low sun panel',
        'Glass stripe',
        'Quiet voltage',
    ];

    private array $imageAlts = [
        'Layered gradient poster with bold contrast and a single floating disc.',
        'Striped abstract field with warm highlights and dark corner falloff.',
        'Sharp geometric collage in a narrow contrast range with a bright edge accent.',
        'Soft cloud of color over a rigid grid, built to test light text over busy art.',
        'Tall atmospheric bands with a hard line of light running through the middle.',
        'Circular cutout floating over a dense dusk palette and a few faint guide marks.',
        'Bright paper-like shapes stacked over a dark base to exercise overlay legibility.',
        'A smooth two-tone blend with a punchy secondary color and a subtle mesh pattern.',
    ];

    public function __construct(
        private readonly PDO $pdo,
        private readonly array $config,
        private readonly string $timezone = 'America/Los_Angeles'
    ) {}

    public function seedMinimum(int $minimum = 150): int
    {
        $current = $this->countLiveItems();
        if ($current >= $minimum) {
            return 0;
        }

        $records = $this->buildSeedRecords($minimum - $current, $current);
        if ($records === []) {
            return 0;
        }

        $statement = $this->pdo->prepare(
            <<<SQL
            INSERT INTO rb_demo_grid_items (
              item_uuid,
              content_type,
              title,
              body,
              image_path,
              image_alt,
              upvote_count,
              origin_x,
              origin_y,
              origin_column,
              origin_row,
              origin_context,
              origin_created_from,
              created_by,
              created_by_session_key,
              created_at,
              updated_at
            ) VALUES (
              :item_uuid,
              :content_type,
              :title,
              :body,
              :image_path,
              :image_alt,
              :upvote_count,
              :origin_x,
              :origin_y,
              :origin_column,
              :origin_row,
              :origin_context,
              :origin_created_from,
              NULL,
              :created_by_session_key,
              :created_at,
              :updated_at
            )
            SQL
        );

        $this->pdo->beginTransaction();

        try {
            foreach ($records as $record) {
                $statement->execute($record);
            }

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        return count($records);
    }

    public function refreshSeedAssets(): int
    {
        $directory = rtrim($this->config['upload_path'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'seed';
        if (!is_dir($directory)) {
            return 0;
        }

        $files = glob($directory . DIRECTORY_SEPARATOR . 'rb-demo-seed-*.svg') ?: [];
        $refreshed = 0;

        foreach ($files as $path) {
            if (!preg_match('/rb-demo-seed-(\d+)\.svg$/', basename($path), $matches)) {
                continue;
            }

            $index = max(0, ((int) $matches[1]) - 1);
            file_put_contents($path, $this->buildSeedSvg($index));
            $refreshed += 1;
        }

        return $refreshed;
    }

    private function countLiveItems(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM rb_demo_grid_items WHERE is_deleted = 0')->fetchColumn();
    }

    private function buildSeedRecords(int $count, int $offset): array
    {
        $records = [];
        $now = new DateTimeImmutable('now', new DateTimeZone($this->timezone));

        for ($index = 0; $index < $count; $index += 1) {
            $absolute = $offset + $index;
            $imageSeed = ($absolute % 5 === 0) || ($absolute % 5 === 3);
            $createdAt = $now
                ->sub(new DateInterval(sprintf('PT%dM', 11 + ($absolute * 13))))
                ->sub(new DateInterval(sprintf('PT%dS', ($absolute * 37) % 57)));
            $updatedAt = $createdAt->add(new DateInterval(sprintf('PT%dM', ($absolute * 7) % 43)));
            $upvotes = $this->seedUpvotes($absolute);

            $records[] = $imageSeed
                ? $this->buildImageRecord($absolute, $createdAt, $updatedAt, $upvotes)
                : $this->buildTextRecord($absolute, $createdAt, $updatedAt, $upvotes);
        }

        return $records;
    }

    private function buildTextRecord(int $index, DateTimeImmutable $createdAt, DateTimeImmutable $updatedAt, int $upvotes): array
    {
        $title = $this->textTitles[$index % count($this->textTitles)];
        if ($index % 9 === 0) {
            $title = '';
        } elseif ($index % 7 === 0) {
            $title = $title . ': ' . ucfirst($this->pickTextFragment($index + 3, 26));
        }

        $body = $this->buildTextBody($index);

        return [
            'item_uuid' => $this->generateUuid(),
            'content_type' => 'text',
            'title' => $title,
            'body' => $body,
            'image_path' => null,
            'image_alt' => null,
            'upvote_count' => $upvotes,
            'origin_x' => 48 + (($index * 37) % 920),
            'origin_y' => 56 + (($index * 61) % 1400),
            'origin_column' => 1 + ($index % 8),
            'origin_row' => 1 + (($index * 3) % 12),
            'origin_context' => 'demo-seed-grid',
            'origin_created_from' => 'demo-seeder',
            'created_by_session_key' => sprintf('demo-seed:text:%02d', ($index % 12) + 1),
            'created_at' => $createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $updatedAt->format('Y-m-d H:i:s'),
        ];
    }

    private function buildImageRecord(int $index, DateTimeImmutable $createdAt, DateTimeImmutable $updatedAt, int $upvotes): array
    {
        $fileName = sprintf('rb-demo-seed-%03d.svg', $index + 1);
        $imagePath = $this->ensureSeedImage($fileName, $index);
        $title = $this->imageTitles[$index % count($this->imageTitles)];
        if ($index % 6 === 0) {
            $title = '';
        } elseif ($index % 4 === 0) {
            $title .= ' / ' . ucfirst($this->pickTextFragment($index + 1, 18));
        }

        $imageAlt = $this->imageAlts[$index % count($this->imageAlts)];
        if ($index % 3 === 0) {
            $imageAlt .= ' Bright accent pushed against a darker floor for overlay contrast.';
        }

        return [
            'item_uuid' => $this->generateUuid(),
            'content_type' => 'image',
            'title' => $title,
            'body' => null,
            'image_path' => $imagePath,
            'image_alt' => $imageAlt,
            'upvote_count' => $upvotes,
            'origin_x' => 72 + (($index * 29) % 960),
            'origin_y' => 64 + (($index * 47) % 1320),
            'origin_column' => 1 + (($index * 2) % 8),
            'origin_row' => 1 + (($index * 5) % 12),
            'origin_context' => 'demo-seed-grid',
            'origin_created_from' => 'demo-seeder',
            'created_by_session_key' => sprintf('demo-seed:image:%02d', ($index % 10) + 1),
            'created_at' => $createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $updatedAt->format('Y-m-d H:i:s'),
        ];
    }

    private function buildTextBody(int $index): string
    {
        $count = match ($index % 5) {
            0 => 1,
            1 => 2,
            2 => 3,
            3 => 4,
            default => 5,
        };

        $parts = [];
        for ($offset = 0; $offset < $count; $offset += 1) {
            $parts[] = $this->pickTextFragment($index + ($offset * 2));
        }

        return implode(' ', $parts);
    }

    private function pickTextFragment(int $index, int $limit = 0): string
    {
        $fragment = $this->textFragments[$index % count($this->textFragments)];
        if ($limit > 0) {
            $fragment = mb_substr($fragment, 0, $limit);
        }

        return $fragment;
    }

    private function seedUpvotes(int $index): int
    {
        $base = ($index * 7) % 29;
        if ($index % 11 === 0) {
            $base += 22;
        }
        if ($index % 17 === 0) {
            $base += 14;
        }

        return min(96, $base);
    }

    private function ensureSeedImage(string $fileName, int $index): string
    {
        $directory = rtrim($this->config['upload_path'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'seed';
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create demo seed image directory.');
        }

        $path = $directory . DIRECTORY_SEPARATOR . $fileName;
        $svg = $this->buildSeedSvg($index);
        if (!is_file($path) || file_get_contents($path) !== $svg) {
            file_put_contents($path, $svg);
        }

        return rtrim($this->config['upload_url'], '/') . '/seed/' . $fileName;
    }

    private function buildSeedSvg(int $index): string
    {
        return match ($index % 5) {
            0 => $this->buildPhotoLandscapeSvg($index),
            1 => $this->buildPhotoStillLifeSvg($index),
            2 => $this->buildPhotoCitySvg($index),
            default => $this->buildAbstractSeedSvg($index),
        };
    }

    private function buildAbstractSeedSvg(int $index): string
    {
        $hueA = ($index * 29) % 360;
        $hueB = ($hueA + 56 + (($index * 13) % 80)) % 360;
        $hueC = ($hueA + 170 + (($index * 17) % 90)) % 360;
        $satA = 62 + ($index % 18);
        $satB = 54 + (($index * 3) % 22);
        $lightA = 26 + (($index * 5) % 18);
        $lightB = 58 + (($index * 7) % 14);
        $lightC = 76 - (($index * 4) % 18);
        $shapeX = 90 + (($index * 41) % 240);
        $shapeY = 72 + (($index * 31) % 260);
        $shapeR = 82 + (($index * 9) % 84);
        $stripeOpacity = number_format(0.14 + (($index % 5) * 0.06), 2, '.', '');

        return sprintf(
            <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" role="img" aria-hidden="true">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%%" stop-color="hsl(%d %d%% %d%%)" />
      <stop offset="100%%" stop-color="hsl(%d %d%% %d%%)" />
    </linearGradient>
    <linearGradient id="overlay" x1="1" y1="0" x2="0" y2="1">
      <stop offset="0%%" stop-color="hsla(%d 88%% %d%% / 0.92)" />
      <stop offset="100%%" stop-color="hsla(%d 72%% %d%% / 0.76)" />
    </linearGradient>
    <pattern id="mesh" width="64" height="64" patternUnits="userSpaceOnUse">
      <path d="M0 0H64V64" fill="none" stroke="rgba(255,255,255,%s)" stroke-width="1"/>
      <path d="M0 0L64 64M64 0L0 64" fill="none" stroke="rgba(255,255,255,%s)" stroke-width="0.9"/>
    </pattern>
  </defs>
  <rect width="512" height="512" fill="url(#bg)" />
  <rect width="512" height="512" fill="url(#mesh)" />
  <circle cx="%d" cy="%d" r="%d" fill="url(#overlay)" />
  <rect x="%d" y="%d" width="%d" height="22" rx="11" fill="hsla(%d 96%% 88%% / 0.9)" />
  <rect x="%d" y="%d" width="%d" height="%d" rx="28" fill="hsla(%d 94%% %d%% / 0.28)" />
  <path d="M0 400C96 348 188 472 280 430S430 280 512 318V512H0Z" fill="hsla(%d 92%% 92%% / 0.18)" />
</svg>
SVG,
            $hueA,
            $satA,
            $lightA,
            $hueB,
            $satB,
            $lightB,
            $hueC,
            $lightC,
            $hueA,
            $lightA + 12,
            $stripeOpacity,
            number_format(max(0.08, (float) $stripeOpacity - 0.04), 2, '.', ''),
            $shapeX,
            $shapeY,
            $shapeR,
            56 + (($index * 19) % 180),
            70 + (($index * 11) % 240),
            140 + (($index * 7) % 220),
            $hueC,
            48 + (($index * 23) % 320),
            46 + (($index * 29) % 240),
            180 + (($index * 5) % 180),
            132 + (($index * 13) % 170),
            $hueB,
            70 + (($index * 3) % 18),
            $hueC
        );
    }

    private function buildPhotoLandscapeSvg(int $index): string
    {
        $skyHue = 190 + (($index * 11) % 58);
        $sunHue = 28 + (($index * 7) % 20);
        $waterHue = 200 + (($index * 5) % 34);
        $landHue = 80 + (($index * 3) % 52);
        $sunX = 96 + (($index * 37) % 320);
        $sunY = 86 + (($index * 19) % 110);
        $peak1 = 250 + (($index * 17) % 60);
        $peak2 = 330 + (($index * 13) % 70);

        return sprintf(
            <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" role="img" aria-hidden="true">
  <defs>
    <linearGradient id="sky" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0%%" stop-color="hsl(%d 74%% 78%%)" />
      <stop offset="52%%" stop-color="hsl(%d 68%% 60%%)" />
      <stop offset="100%%" stop-color="hsl(%d 58%% 38%%)" />
    </linearGradient>
    <linearGradient id="water" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%%" stop-color="hsl(%d 56%% 44%%)" />
      <stop offset="100%%" stop-color="hsl(%d 52%% 22%%)" />
    </linearGradient>
    <filter id="blurGlow">
      <feGaussianBlur stdDeviation="22" />
    </filter>
  </defs>
  <rect width="512" height="512" fill="url(#sky)" />
  <circle cx="%d" cy="%d" r="56" fill="hsl(%d 92%% 82%% / 0.98)" />
  <circle cx="%d" cy="%d" r="112" fill="hsl(%d 98%% 88%% / 0.44)" filter="url(#blurGlow)" />
  <path d="M0 278C76 238 106 250 154 220S246 164 314 196 422 282 512 242V512H0Z" fill="hsl(%d 34%% 22%% / 0.92)" />
  <path d="M0 316C88 286 136 300 %d 258S402 284 512 268V512H0Z" fill="hsl(%d 28%% 18%% / 0.94)" />
  <path d="M0 360C70 330 138 346 212 320S352 286 512 328V512H0Z" fill="url(#water)" />
  <path d="M0 398C98 372 152 388 232 370S398 360 512 390" fill="none" stroke="hsl(%d 72%% 84%% / 0.32)" stroke-width="10" stroke-linecap="round" />
  <rect x="0" y="0" width="512" height="512" fill="url(#grain)" opacity="0"/>
</svg>
SVG,
            $skyHue,
            ($skyHue + 18) % 360,
            ($skyHue + 42) % 360,
            $waterHue,
            ($waterHue + 12) % 360,
            $sunX,
            $sunY,
            $sunHue,
            $sunX - 18,
            $sunY + 12,
            $sunHue,
            $landHue,
            $peak1,
            ($landHue + 30) % 360,
            $waterHue
        );
    }

    private function buildPhotoStillLifeSvg(int $index): string
    {
        $bgHue = 18 + (($index * 9) % 42);
        $objHue = 150 + (($index * 13) % 120);
        $accentHue = 260 + (($index * 5) % 58);
        $shadowHue = ($bgHue + 200) % 360;
        $rectX = 82 + (($index * 29) % 80);
        $rectY = 108 + (($index * 17) % 72);
        $circleX = 330 + (($index * 11) % 54);
        $circleY = 216 + (($index * 7) % 48);

        return sprintf(
            <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" role="img" aria-hidden="true">
  <defs>
    <linearGradient id="wall" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%%" stop-color="hsl(%d 32%% 90%%)" />
      <stop offset="100%%" stop-color="hsl(%d 28%% 68%%)" />
    </linearGradient>
    <linearGradient id="table" x1="0" y1="0" x2="1" y2="0">
      <stop offset="0%%" stop-color="hsl(%d 28%% 42%%)" />
      <stop offset="100%%" stop-color="hsl(%d 22%% 24%%)" />
    </linearGradient>
    <filter id="softBlur">
      <feGaussianBlur stdDeviation="18" />
    </filter>
  </defs>
  <rect width="512" height="512" fill="url(#wall)" />
  <rect y="332" width="512" height="180" fill="url(#table)" />
  <ellipse cx="246" cy="350" rx="188" ry="34" fill="hsl(%d 18%% 18%% / 0.16)" filter="url(#softBlur)" />
  <rect x="%d" y="%d" width="178" height="134" rx="26" fill="hsl(%d 44%% 42%%)" />
  <rect x="%d" y="%d" width="178" height="134" rx="26" fill="hsl(%d 48%% 94%% / 0.18)" />
  <circle cx="%d" cy="%d" r="76" fill="hsl(%d 56%% 52%%)" />
  <circle cx="%d" cy="%d" r="38" fill="hsl(%d 84%% 74%% / 0.86)" />
  <path d="M118 148C178 108 232 120 290 166" fill="none" stroke="hsl(%d 24%% 98%% / 0.38)" stroke-width="24" stroke-linecap="round" />
  <path d="M64 86C136 70 220 78 282 114" fill="none" stroke="hsl(%d 88%% 92%% / 0.52)" stroke-width="10" stroke-linecap="round" />
</svg>
SVG,
            $bgHue,
            ($bgHue + 22) % 360,
            $shadowHue,
            ($shadowHue + 18) % 360,
            $shadowHue,
            $rectX,
            $rectY,
            $objHue,
            $rectX + 18,
            $rectY + 14,
            ($objHue + 140) % 360,
            $circleX,
            $circleY,
            $accentHue,
            $circleX - 12,
            $circleY - 18,
            ($accentHue + 48) % 360,
            ($bgHue + 24) % 360,
            $accentHue
        );
    }

    private function buildPhotoCitySvg(int $index): string
    {
        $skyHue = 214 + (($index * 7) % 32);
        $lightHue = 36 + (($index * 11) % 16);
        $glassHue = 188 + (($index * 13) % 26);
        $towerX = 74 + (($index * 19) % 48);
        $tower2X = 236 + (($index * 17) % 56);
        $tower3X = 366 + (($index * 7) % 46);

        return sprintf(
            <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" role="img" aria-hidden="true">
  <defs>
    <linearGradient id="citySky" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0%%" stop-color="hsl(%d 54%% 82%%)" />
      <stop offset="100%%" stop-color="hsl(%d 38%% 28%%)" />
    </linearGradient>
    <linearGradient id="glass" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%%" stop-color="hsl(%d 44%% 72%%)" />
      <stop offset="100%%" stop-color="hsl(%d 38%% 34%%)" />
    </linearGradient>
  </defs>
  <rect width="512" height="512" fill="url(#citySky)" />
  <circle cx="408" cy="98" r="42" fill="hsl(%d 94%% 86%% / 0.84)" />
  <rect x="%d" y="126" width="124" height="286" rx="10" fill="url(#glass)" />
  <rect x="%d" y="94" width="118" height="318" rx="10" fill="hsl(%d 20%% 20%% / 0.92)" />
  <rect x="%d" y="150" width="98" height="262" rx="10" fill="hsl(%d 16%% 16%% / 0.9)" />
  <g fill="hsl(%d 92%% 80%% / 0.78)">
    <rect x="%d" y="154" width="14" height="20" rx="3" />
    <rect x="%d" y="154" width="14" height="20" rx="3" />
    <rect x="%d" y="154" width="14" height="20" rx="3" />
    <rect x="%d" y="184" width="14" height="20" rx="3" />
    <rect x="%d" y="184" width="14" height="20" rx="3" />
    <rect x="%d" y="184" width="14" height="20" rx="3" />
    <rect x="%d" y="214" width="14" height="20" rx="3" />
    <rect x="%d" y="214" width="14" height="20" rx="3" />
    <rect x="%d" y="214" width="14" height="20" rx="3" />
  </g>
  <path d="M0 368C92 338 164 378 248 348S402 306 512 344V512H0Z" fill="hsl(%d 20%% 18%% / 0.96)" />
  <path d="M0 402C100 380 212 428 312 400S438 360 512 386" fill="none" stroke="hsl(%d 22%% 94%% / 0.24)" stroke-width="8" stroke-linecap="round" />
</svg>
SVG,
            $skyHue,
            ($skyHue + 28) % 360,
            $glassHue,
            ($glassHue + 18) % 360,
            $lightHue,
            $towerX,
            $tower2X,
            ($skyHue + 190) % 360,
            $tower3X,
            $lightHue,
            $towerX + 18,
            $towerX + 42,
            $towerX + 66,
            $towerX + 18,
            $towerX + 42,
            $towerX + 66,
            $towerX + 18,
            $towerX + 42,
            $towerX + 66,
            ($skyHue + 200) % 360,
            $lightHue
        );
    }

    private function generateUuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
