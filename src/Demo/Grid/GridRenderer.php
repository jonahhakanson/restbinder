<?php
namespace RestBinder\Demo\Grid;

final class GridRenderer
{
    public function renderPage(array $window): string
    {
        if (($window['grid']['total_items'] ?? 0) === 0 && ($window['page']['x'] ?? 0) === 0 && ($window['page']['y'] ?? 0) === 0) {
            return $this->renderEmptyState($window['collection']['resource']['id'] ?? 'all');
        }

        return implode(
            '',
            array_map(
                fn (array $item): string => $this->renderSquare($item, $window['page']['x'], $window['page']['y']),
                $window['items'] ?? []
            )
        );
    }

    public function renderSquare(array $item, int $pageX = 0, int $pageY = 0): string
    {
        $contentType = $item['state']['content_type'] === 'image' ? 'image' : 'text';
        $title = trim((string) ($item['state']['title'] ?? ''));
        if ($title === '' && $contentType === 'text') {
            $title = 'Untitled square';
        }

        $preview = trim((string) ($item['state']['preview'] ?? ''));
        if ($preview === '' && $contentType === 'text') {
            $preview = 'Open this square to read more.';
        }
        if ($contentType === 'image') {
            $preview = '';
        }

        $image = $item['state']['content_type'] === 'image' && $item['state']['image_path']
            ? sprintf(
                '<div class="rb-square-image"><img src="%s" alt="%s"></div>',
                $this->escape($item['state']['image_path']),
                $this->escape($item['state']['image_alt'] ?: ($title !== '' ? $title : ''))
            )
            : '';
        $copy = '';
        if ($title !== '' && $contentType !== 'image') {
            $copy .= sprintf('<span class="rb-square-title">%s</span>', $this->escape($title));
        }
        if ($preview !== '') {
            $copy .= sprintf('<span class="rb-square-preview">%s</span>', $this->escape($preview));
        }

        return sprintf(
            <<<HTML
            <article
              class="rb-grid-square is-%s"
              data-page-key="%s:%s"
              data-content-type="%s"
              data-resource-type="demo_grid_item"
              data-resource-id="%s"
              style="--rb-col-start:%d;--rb-row-start:%d;">
              <button
                class="rb-square-open"
                data-phlx-click="expand"
                data-phlx-url="%s">
                %s
                <span class="rb-square-copy">
                  %s
                </span>
              </button>
              <footer class="rb-square-footer">
                <span class="rb-square-age">%s</span>
                <button
                  class="rb-upvote"
                  data-phlx-post="%s"
                  type="button">▲ %d</button>
              </footer>
            </article>
            HTML,
            $this->escape($contentType),
            $pageX,
            $pageY,
            $this->escape($contentType),
            $this->escape($item['id']),
            $item['display']['column'] + 1,
            $item['display']['row'] + 1,
            $this->escape($item['links']['self']),
            $image,
            $copy,
            $this->escape($item['state']['age_label']),
            $this->escape($item['links']['upvote']),
            (int) $item['state']['upvote_count']
        );
    }

    public function renderExpandedPanel(array $item): string
    {
        $title = $item['state']['title'] ?: ($item['state']['content_type'] === 'image' ? 'Image square' : 'Untitled square');
        $body = $item['state']['content_type'] === 'image'
            ? sprintf(
                '<figure class="rb-expanded-figure"><img src="%s" alt="%s"><figcaption>%s</figcaption></figure>',
                $this->escape($item['state']['image_path'] ?: ''),
                $this->escape($item['state']['image_alt'] ?: $title),
                $this->escape($item['state']['image_alt'] ?: 'Image square')
            )
            : sprintf('<div class="rb-expanded-body">%s</div>', nl2br($this->escape($item['state']['body'] ?: '')));

        $originBits = [];
        if ($item['origin']['row'] !== null && $item['origin']['column'] !== null) {
            $originBits[] = sprintf('Created from grid row %d, column %d.', $item['origin']['row'], $item['origin']['column']);
        }
        if ($item['origin']['context']) {
            $originBits[] = sprintf('Origin context: %s.', $item['origin']['context']);
        }
        if ($item['origin']['created_from']) {
            $originBits[] = sprintf('Created from: %s.', $item['origin']['created_from']);
        }
        $originBits[] = 'Now displayed by current recency and upvote rank.';

        return sprintf(
            <<<HTML
            <div class="rb-expanded-card" data-expanded-item-id="%s">
              <div class="rb-expanded-topline">
                <span class="rb-badge">Demo Grid Item</span>
                <button type="button" class="rb-expanded-close" data-rb-close-expanded>Close</button>
              </div>
              <h2>%s</h2>
              <p class="rb-expanded-meta">%s - %d votes</p>
              %s
              <dl class="rb-expanded-stats">
                <div><dt>Resource ID</dt><dd>%s</dd></div>
                <div><dt>Display row</dt><dd>%d</dd></div>
                <div><dt>Display column</dt><dd>%d</dd></div>
              </dl>
              <p class="rb-expanded-origin">%s</p>
            </div>
            HTML,
            $this->escape($item['id']),
            $this->escape($title),
            $this->escape($item['state']['age_label']),
            (int) $item['state']['upvote_count'],
            $body,
            $this->escape($item['id']),
            $item['display']['row'] + 1,
            $item['display']['column'] + 1,
            $this->escape(implode(' ', $originBits))
        );
    }

    public function renderEmptyState(string $scope): string
    {
        if ($scope === 'mine') {
            return <<<HTML
            <section class="rb-grid-empty" data-page-key="0:0">
              <h3>You have not added any squares yet.</h3>
              <p>Add a text or image square from anywhere in the grid. Your squares will appear here, arranged by the same recency and upvote rules as the main surface.</p>
              <button type="button" class="rb-empty-action" data-rb-open-composer data-origin-context="empty-state" data-origin-created-from="empty-state-button">Add My First Square</button>
            </section>
            HTML;
        }

        return <<<HTML
        <section class="rb-grid-empty" data-page-key="0:0">
          <h3>No squares have been added yet.</h3>
          <p>Add the first text or image square to start the RestBinder surface.</p>
        </section>
        HTML;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
