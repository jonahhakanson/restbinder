class RestBinderSurfaceDemo {
  constructor(root) {
    this.root = root;
    this.viewport = root.querySelector('#rb-grid-viewport');
    this.region = root.querySelector('#rb-grid-region');
    this.status = root.querySelector('[data-rb-status]');
    this.explainer = root.querySelector('#rb-demo-explainer');
    this.composer = root.querySelector('#rb-grid-composer');
    this.composerForm = root.querySelector('[data-rb-composer-form]');
    this.expandedPanel = root.querySelector('#rb-expanded-panel');
    this.expandedContent = root.querySelector('[data-rb-expanded-content]');
    this.scopeTabs = Array.from(root.querySelectorAll('[data-rb-scope-tab]'));
    this.scopeInput = root.querySelector('[data-rb-scope-input]');
    this.bodyField = root.querySelector('[data-rb-body-field]');
    this.imageField = root.querySelector('[data-rb-image-field]');
    this.altField = root.querySelector('[data-rb-alt-field]');
    this.gridEndpoint = root.dataset.gridEndpoint;
    this.createEndpoint = root.dataset.gridCreateEndpoint;
    this.expandEndpoint = root.dataset.gridExpandEndpoint;
    this.upvoteEndpoint = root.dataset.gridUpvoteEndpoint;
    this.pollEndpoint = root.dataset.gridPollEndpoint;
    this.pollInterval = Number.parseInt(root.dataset.gridPollInterval || '15000', 10);
    this.rows = 8;
    this.columns = 4;
    this.scope = 'all';
    this.state = this.defaultState('all');
    this.loadedPages = new Set();
    this.loadedPageX = new Set();
    this.loadedPageY = new Set();
    this.pendingPages = new Map();
    this.moreXAvailable = false;
    this.moreYAvailable = false;
    this.loadingAxisX = false;
    this.loadingAxisY = false;
    this.isRefreshing = false;
    this.isMutating = false;
    this.pollTimer = null;
    this.hasMounted = false;
    this.layoutSignature = '';
    this.scheduleSquareEnhancements = this.debounce(() => this.applySquareEnhancements(), 20);
    this.handleViewportScroll = this.debounce(() => {
      this.captureViewport();
      this.persistState();
      this.maybeAutoLoad();
    }, 120);
    this.handleResize = this.debounce(async () => {
      const changed = this.applyResponsiveLayout();
      if (changed && this.hasMounted) {
        this.captureViewport();
        this.seedViewportPageState();
        this.persistState();
        await this.restoreScope(this.scope);
        return;
      }

      this.updateGridColumns();
      this.scheduleSquareEnhancements();
    }, 140);
  }

  async mount() {
    this.bind();
    this.applyResponsiveLayout();
    await this.restoreScope(this.scope);
    this.startPolling();
    this.hasMounted = true;
    this.scheduleSquareEnhancements();
  }

  bind() {
    this.viewport.addEventListener('scroll', this.handleViewportScroll);
    this.viewport.addEventListener('click', event => this.handleViewportClick(event));
    this.root.addEventListener('click', event => this.handleClick(event));
    window.addEventListener('resize', this.handleResize);
    this.composerForm.addEventListener('submit', event => this.handleComposerSubmit(event));
    this.composerForm.querySelectorAll('input[name="content_type"]').forEach(input => {
      input.addEventListener('change', () => this.syncComposerFields());
    });
    this.syncComposerFields();
  }

  async restoreScope(scope) {
    this.scope = scope === 'mine' ? 'mine' : 'all';
    this.applyResponsiveLayout();
    this.scopeInput.value = this.scope;
    this.state = this.loadStoredState(this.scope);
    this.loadedPages = new Set();
    this.loadedPageX = new Set(this.sortedNumbers(this.state.loadedPageX));
    this.loadedPageY = new Set(this.sortedNumbers(this.state.loadedPageY));
    this.pendingPages = new Map();
    this.moreXAvailable = false;
    this.moreYAvailable = false;
    this.viewport.dataset.rbScope = this.scope;
    this.region.innerHTML = '';
    this.updateScopeTabs();
    this.updateGridColumns();
    this.setStatus(this.scope === 'mine' ? 'Loading your scoped squares...' : 'Loading the full surface...');

    const pageXs = this.sortedNumbers(this.loadedPageX.size ? [...this.loadedPageX] : [0]);
    const pageYs = this.sortedNumbers(this.loadedPageY.size ? [...this.loadedPageY] : [0]);

    for (const pageY of pageYs) {
      for (const pageX of pageXs) {
        await this.fetchPage(pageX, pageY, { force: true, silent: true });
      }
    }

    this.restoreViewport();

    if (this.state.expandedItem) {
      await this.openExpanded(this.state.expandedItem, { silent: true });
    } else {
      this.closeExpanded(false);
    }

    this.setStatus(this.scope === 'mine'
      ? 'My Squares is live. Scroll vertically for recency and horizontally for vote strength.'
      : 'All Squares is live. Scroll vertically for recency and horizontally for vote strength.');
  }

  async fetchPage(pageX, pageY, options = {}) {
    const key = this.pageKey(pageX, pageY);
    if (this.pendingPages.has(key)) {
      return this.pendingPages.get(key);
    }

    if (!options.force && this.loadedPages.has(key)) {
      return null;
    }

    const request = (async () => {
      const url = new URL(this.gridEndpoint, window.location.origin);
      url.searchParams.set('scope', this.scope);
      url.searchParams.set('page_x', String(pageX));
      url.searchParams.set('page_y', String(pageY));
      url.searchParams.set('rows', String(this.rows));
      url.searchParams.set('columns', String(this.columns));

      const response = await fetch(url);
      const data = await response.json();
      if (!response.ok) {
        throw new Error(data.error || 'Unable to load grid page.');
      }

      if (options.force) {
        this.removePage(pageX, pageY);
      }

      if (data.html) {
        this.region.insertAdjacentHTML('beforeend', data.html);
      }

      this.loadedPages.add(key);
      this.loadedPageX.add(pageX);
      this.loadedPageY.add(pageY);
      this.moreXAvailable = this.moreXAvailable || Boolean(data.has_more_x);
      this.moreYAvailable = this.moreYAvailable || Boolean(data.has_more_y);
      this.state.cursor = data.cursor || this.state.cursor;
      this.updateGridColumns();
      this.scheduleSquareEnhancements();
      this.captureViewport();
      this.persistState();

      return data;
    })().catch(error => {
      this.setStatus(error.message, true);
      throw error;
    }).finally(() => {
      this.pendingPages.delete(key);
    });

    this.pendingPages.set(key, request);
    return request;
  }

  async refreshLoadedWindow(options = {}) {
    if (this.isRefreshing) {
      return;
    }

    this.isRefreshing = true;
    const reopenExpanded = options.reopenExpanded !== false ? this.state.expandedItem : null;
    this.captureViewport();
    this.moreXAvailable = false;
    this.moreYAvailable = false;

    const pageXs = this.sortedNumbers(this.loadedPageX.size ? [...this.loadedPageX] : [0]);
    const pageYs = this.sortedNumbers(this.loadedPageY.size ? [...this.loadedPageY] : [0]);

    this.region.innerHTML = '';
    this.loadedPages = new Set();
    this.pendingPages = new Map();

    try {
      for (const pageY of pageYs) {
        for (const pageX of pageXs) {
          await this.fetchPage(pageX, pageY, { force: true, silent: true });
        }
      }

      this.restoreViewport();
      this.scheduleSquareEnhancements();

      if (reopenExpanded) {
        await this.openExpanded(reopenExpanded, { silent: true });
      }
    } finally {
      this.isRefreshing = false;
    }
  }

  async maybeAutoLoad() {
    if (this.isRefreshing || this.isMutating) {
      return;
    }

    const nearBottom = this.viewport.scrollTop + this.viewport.clientHeight >= this.viewport.scrollHeight - 600;
    if (nearBottom && this.moreYAvailable && !this.loadingAxisY) {
      await this.loadNextPageY();
    }

    const nearRight = this.viewport.scrollLeft + this.viewport.clientWidth >= this.viewport.scrollWidth - 400;
    if (nearRight && this.moreXAvailable && !this.loadingAxisX) {
      await this.loadNextPageX();
    }
  }

  async loadNextPageY() {
    this.loadingAxisY = true;
    const nextPageY = (this.sortedNumbers([...this.loadedPageY]).pop() ?? 0) + 1;
    const pageXs = this.sortedNumbers(this.loadedPageX.size ? [...this.loadedPageX] : [0]);
    let hasMore = false;

    try {
      for (const pageX of pageXs) {
        const data = await this.fetchPage(pageX, nextPageY);
        hasMore = hasMore || Boolean(data?.has_more_y);
      }
      this.moreYAvailable = hasMore;
    } finally {
      this.loadingAxisY = false;
    }
  }

  async loadNextPageX() {
    this.loadingAxisX = true;
    const nextPageX = (this.sortedNumbers([...this.loadedPageX]).pop() ?? 0) + 1;
    const pageYs = this.sortedNumbers(this.loadedPageY.size ? [...this.loadedPageY] : [0]);
    let hasMore = false;

    try {
      for (const pageY of pageYs) {
        const data = await this.fetchPage(nextPageX, pageY);
        hasMore = hasMore || Boolean(data?.has_more_x);
      }
      this.moreXAvailable = hasMore;
    } finally {
      this.loadingAxisX = false;
    }
  }

  handleViewportClick(event) {
    if (!event.target.closest('#rb-grid-region')) {
      return;
    }

    if (event.target.closest('.rb-grid-square, .rb-grid-empty, button, a, input, textarea, label, form')) {
      return;
    }

    const origin = this.resolveOrigin(event, {
      context: 'grid-viewport',
      createdFrom: 'viewport-tap'
    });
    this.openComposer(origin);
  }

  async handleClick(event) {
    const toggleExplainer = event.target.closest('[data-rb-toggle-explainer]');
    if (toggleExplainer) {
      event.preventDefault();
      this.toggleExplainer(toggleExplainer);
      this.applyResponsiveLayout();
      this.updateGridColumns();
      this.scheduleSquareEnhancements();
      return;
    }

    const scopeTab = event.target.closest('[data-rb-scope-tab]');
    if (scopeTab) {
      event.preventDefault();
      const scope = scopeTab.dataset.rbScopeTab || 'all';
      if (scope !== this.scope) {
        this.captureViewport();
        this.persistState();
        await this.restoreScope(scope);
      }
      return;
    }

    const openComposer = event.target.closest('[data-rb-open-composer]');
    if (openComposer) {
      event.preventDefault();
      const origin = this.resolveOrigin(null, {
        context: openComposer.dataset.originContext || 'viewport-center',
        createdFrom: openComposer.dataset.originCreatedFrom || 'floating-add-button'
      });
      this.openComposer(origin);
      return;
    }

    if (event.target.closest('[data-rb-close-composer]')) {
      event.preventDefault();
      this.closeComposer();
      return;
    }

    if (event.target.closest('[data-rb-close-expanded]')) {
      event.preventDefault();
      this.closeExpanded();
      return;
    }

    const expandButton = event.target.closest('[data-phlx-click="expand"]');
    if (expandButton) {
      event.preventDefault();
      const url = new URL(expandButton.dataset.phlxUrl || this.expandEndpoint, window.location.origin);
      if (!url.searchParams.has('scope')) {
        url.searchParams.set('scope', this.scope);
      }
      const uuid = expandButton.closest('[data-resource-id]')?.dataset.resourceId || '';
      await this.openExpanded(uuid, { url: url.toString() });
      return;
    }

    const upvoteButton = event.target.closest('[data-phlx-post]');
    if (upvoteButton) {
      event.preventDefault();
      await this.postUpvote(upvoteButton.dataset.phlxPost);
    }
  }

  async handleComposerSubmit(event) {
    event.preventDefault();
    if (this.isMutating) {
      return;
    }

    this.isMutating = true;
    this.scopeInput.value = this.scope;
    const submitButton = this.composerForm.querySelector('[type="submit"]');
    const previousLabel = submitButton.textContent;
    submitButton.disabled = true;
    submitButton.textContent = 'Creating...';
    this.setStatus('Creating square...');

    try {
      const response = await fetch(this.createEndpoint, {
        method: 'POST',
        body: new FormData(this.composerForm)
      });
      const data = await response.json();
      if (!response.ok) {
        throw new Error(data.error || 'Unable to create square.');
      }

      this.closeComposer();
      this.state.expandedItem = data.resource?.id || null;
      await this.refreshLoadedWindow({ reopenExpanded: true });
      this.setStatus('Square created and rebound from server state.');
      this.resetComposer();
    } catch (error) {
      this.setStatus(error.message, true);
    } finally {
      this.isMutating = false;
      submitButton.disabled = false;
      submitButton.textContent = previousLabel;
    }
  }

  async postUpvote(endpoint) {
    if (this.isMutating) {
      return;
    }

    this.isMutating = true;
    const url = new URL(endpoint || this.upvoteEndpoint, window.location.origin);
    url.searchParams.set('scope', this.scope);
    const payload = new FormData();
    payload.set('scope', this.scope);
    this.setStatus('Saving upvote...');

    try {
      const response = await fetch(url, {
        method: 'POST',
        body: payload
      });
      const data = await response.json();
      if (!response.ok) {
        throw new Error(data.error || 'Unable to upvote square.');
      }

      await this.refreshLoadedWindow({ reopenExpanded: true });
      this.setStatus('Upvote saved and current window rebound.');
    } catch (error) {
      this.setStatus(error.message, true);
    } finally {
      this.isMutating = false;
    }
  }

  async openExpanded(uuid, options = {}) {
    if (!uuid && !options.url) {
      return;
    }

    const url = new URL(options.url || this.expandEndpoint, window.location.origin);
    if (!url.searchParams.has('uuid') && uuid) {
      url.searchParams.set('uuid', uuid);
    }
    url.searchParams.set('scope', this.scope);

    try {
      const response = await fetch(url);
      const data = await response.json();
      if (!response.ok) {
        throw new Error(data.error || 'Unable to load square details.');
      }

      this.expandedContent.innerHTML = data.html || '';
      this.expandedPanel.hidden = false;
      this.state.expandedItem = data.resource?.id || uuid || null;
      this.persistState();
      if (!options.silent) {
        this.setStatus('Expanded square loaded.');
      }
    } catch (error) {
      this.closeExpanded(false);
      if (!options.silent) {
        this.setStatus(error.message, true);
      }
    }
  }

  closeComposer() {
    this.composer.hidden = true;
  }

  closeExpanded(persist = true) {
    this.expandedPanel.hidden = true;
    this.expandedContent.innerHTML = '';
    this.state.expandedItem = null;
    if (persist) {
      this.persistState();
    }
  }

  openComposer(origin) {
    this.fillOriginInputs(origin);
    this.scopeInput.value = this.scope;
    this.composer.hidden = false;
    this.setStatus(`Composing from row ${origin.row}, column ${origin.column}.`);
    this.composerForm.querySelector('input[name="title"]')?.focus();
  }

  fillOriginInputs(origin) {
    this.root.querySelector('[data-rb-origin-x]').value = String(origin.x);
    this.root.querySelector('[data-rb-origin-y]').value = String(origin.y);
    this.root.querySelector('[data-rb-origin-column]').value = String(origin.column);
    this.root.querySelector('[data-rb-origin-row]').value = String(origin.row);
    this.root.querySelector('[data-rb-origin-context]').value = origin.context;
    this.root.querySelector('[data-rb-origin-created-from]').value = origin.createdFrom;
  }

  resolveOrigin(event, overrides = {}) {
    const rect = this.viewport.getBoundingClientRect();
    const localX = event ? Math.max(0, event.clientX - rect.left) : rect.width / 2;
    const localY = event ? Math.max(0, event.clientY - rect.top) : rect.height / 2;
    const x = Math.round(this.viewport.scrollLeft + localX);
    const y = Math.round(this.viewport.scrollTop + localY);
    const metrics = this.gridMetrics();
    const column = Math.max(1, Math.floor(x / metrics.stepX) + 1);
    const row = Math.max(1, Math.floor(y / metrics.stepY) + 1);

    return {
      x,
      y,
      column,
      row,
      context: overrides.context || 'viewport-center',
      createdFrom: overrides.createdFrom || 'floating-add-button'
    };
  }

  gridMetrics() {
    const styles = getComputedStyle(this.region);
    const trackSize = this.pixelValue(styles.getPropertyValue('--rb-square-track')) || 144;
    const gap = this.pixelValue(styles.getPropertyValue('--rb-grid-gap')) || 12;

    return {
      stepX: trackSize + gap,
      stepY: trackSize + gap
    };
  }

  pixelValue(value) {
    return Number.parseFloat(String(value).replace('px', '')) || 0;
  }

  syncComposerFields() {
    const mode = this.composerForm.querySelector('input[name="content_type"]:checked')?.value || 'text';
    const imageMode = mode === 'image';
    this.bodyField.hidden = imageMode;
    this.imageField.hidden = !imageMode;
    this.altField.hidden = !imageMode;
  }

  resetComposer() {
    this.composerForm.reset();
    this.syncComposerFields();
  }

  captureViewport() {
    this.state.scope = this.scope;
    this.state.scrollTop = this.viewport.scrollTop;
    this.state.scrollLeft = this.viewport.scrollLeft;
    this.state.loadedPageX = this.sortedNumbers(this.loadedPageX.size ? [...this.loadedPageX] : [0]);
    this.state.loadedPageY = this.sortedNumbers(this.loadedPageY.size ? [...this.loadedPageY] : [0]);
    this.state.loadedPages = this.sortedPageKeys(this.loadedPages.size ? [...this.loadedPages] : ['0:0']);
  }

  restoreViewport() {
    this.viewport.scrollTop = this.state.scrollTop || 0;
    this.viewport.scrollLeft = this.state.scrollLeft || 0;
  }

  startPolling() {
    if (this.pollTimer !== null) {
      window.clearInterval(this.pollTimer);
    }

    this.pollTimer = window.setInterval(() => this.poll(), this.pollInterval);
  }

  async poll() {
    if (document.hidden || this.isRefreshing || this.isMutating) {
      return;
    }

    const url = new URL(this.pollEndpoint, window.location.origin);
    url.searchParams.set('scope', this.scope);
    if (this.state.cursor) {
      url.searchParams.set('cursor', this.state.cursor);
    }

    try {
      const response = await fetch(url);
      const data = await response.json();
      if (!response.ok) {
        return;
      }

      if (data.cursor) {
        this.state.cursor = data.cursor;
      }

      if (data.has_updates) {
        this.setStatus('New server state detected. Refreshing the loaded window...');
        await this.refreshLoadedWindow({ reopenExpanded: true });
      }
    } catch (_error) {
      // Polling is best-effort for the demo surface.
    }
  }

  updateScopeTabs() {
    this.scopeTabs.forEach(tab => {
      tab.classList.toggle('is-active', tab.dataset.rbScopeTab === this.scope);
    });
  }

  updateGridColumns() {
    const maxPageX = this.sortedNumbers(this.loadedPageX.size ? [...this.loadedPageX] : [0]).pop() ?? 0;
    const columns = Math.max(4, (maxPageX + 1) * this.columns);
    this.region.style.setProperty('--rb-grid-cols', String(columns));
  }

  applySquareEnhancements() {
    this.fitTextSquares();
  }

  fitTextSquares() {
    this.region.querySelectorAll('.rb-grid-square.is-text').forEach(square => {
      const open = square.querySelector('.rb-square-open');
      const copy = square.querySelector('.rb-square-copy');
      const title = square.querySelector('.rb-square-title');
      const preview = square.querySelector('.rb-square-preview');

      if (!open || !copy || !title || !preview) {
        return;
      }

      const squareWidth = square.clientWidth || this.pixelValue(getComputedStyle(square).width);
      if (!squareWidth) {
        return;
      }

      const titleLength = (title.textContent || '').trim().length;
      const previewLength = (preview.textContent || '').trim().length;
      const hasTitle = titleLength > 0;
      const hasPreview = previewLength > 0;
      let titleSize = hasTitle ? Math.max(15, Math.min(squareWidth * 0.145, 28)) : 0;
      let previewSize = hasPreview ? Math.max(12, Math.min(squareWidth * 0.104, 18)) : 0;
      const minTitleSize = hasTitle ? Math.max(12, Math.min(squareWidth * 0.076, 17)) : 0;
      const minPreviewSize = hasPreview ? Math.max(10, Math.min(squareWidth * 0.068, 14)) : 0;
      const minGap = Math.max(4, Math.round(squareWidth * 0.028));
      let titleLines = hasTitle ? (titleLength < 26 ? 2 : 3) : 0;
      let previewLines = hasPreview ? (previewLength < 70 ? 4 : 6) : 0;

      square.style.setProperty('--rb-title-size', `${titleSize}px`);
      square.style.setProperty('--rb-preview-size', `${previewSize}px`);
      square.style.setProperty('--rb-title-lines', String(titleLines));
      square.style.setProperty('--rb-preview-lines', String(previewLines));
      copy.style.gap = `${minGap}px`;

      for (let guard = 0; guard < 24; guard += 1) {
        const overflow = copy.scrollHeight - open.clientHeight;
        if (overflow <= 3) {
          break;
        }

        if (previewSize > minPreviewSize && (previewLength >= titleLength || titleSize <= minTitleSize + 1.5)) {
          previewSize = Math.max(minPreviewSize, previewSize - 1);
          if (overflow > 24 && previewLines > 4) {
            previewLines -= 1;
          }
        } else if (titleSize > minTitleSize) {
          titleSize = Math.max(minTitleSize, titleSize - 1);
          if (overflow > 24 && titleLines > 2) {
            titleLines -= 1;
          }
        } else if (previewLines > 3) {
          previewLines -= 1;
        } else if (titleLines > 2) {
          titleLines -= 1;
        } else {
          break;
        }

        square.style.setProperty('--rb-title-size', `${titleSize}px`);
        square.style.setProperty('--rb-preview-size', `${previewSize}px`);
        square.style.setProperty('--rb-title-lines', String(titleLines));
        square.style.setProperty('--rb-preview-lines', String(previewLines));
      }
    });
  }

  toggleExplainer(button) {
    const expanded = this.explainer.hidden;
    this.explainer.hidden = !expanded;
    button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
  }

  applyResponsiveLayout() {
    const settings = this.responsiveSettings();
    const nextSignature = `${settings.columns}:${settings.rows}:${settings.gap}:${settings.visibleColumns}:${settings.visibleRows}`;
    const viewportStyles = getComputedStyle(this.viewport);
    const padX = this.pixelValue(viewportStyles.paddingLeft) + this.pixelValue(viewportStyles.paddingRight);
    const availableWidth = Math.max(320, this.viewport.clientWidth - padX);
    const visibleColumns = Math.max(1, settings.visibleColumns);
    const visibleRows = Math.max(1, settings.visibleRows);
    const trackByWidth = Math.floor((availableWidth - (settings.gap * Math.max(0, visibleColumns - 1))) / visibleColumns);
    const track = Math.max(settings.minTrack, Math.min(trackByWidth, settings.maxTrack));

    this.rows = settings.rows;
    this.columns = settings.columns;
    this.root.style.setProperty('--rb-grid-gap', `${settings.gap}px`);
    this.root.style.setProperty('--rb-target-cols', String(visibleColumns));
    this.root.style.setProperty('--rb-target-rows', String(visibleRows));
    this.root.style.setProperty('--rb-square-track', `${track}px`);

    const changed = this.layoutSignature !== '' && this.layoutSignature !== nextSignature;
    this.layoutSignature = nextSignature;

    return changed;
  }

  responsiveSettings() {
    const width = window.innerWidth;

    if (width >= 1400) {
      return { columns: 5, rows: 8, visibleColumns: 5, visibleRows: 8, gap: 12, minTrack: 148, maxTrack: 244 };
    }

    if (width >= 1100) {
      return { columns: 5, rows: 8, visibleColumns: 5, visibleRows: 8, gap: 12, minTrack: 132, maxTrack: 228 };
    }

    if (width >= 860) {
      return { columns: 4, rows: 8, visibleColumns: 4, visibleRows: 8, gap: 11, minTrack: 112, maxTrack: 186 };
    }

    if (width >= 640) {
      return { columns: 4, rows: 8, visibleColumns: 3.5, visibleRows: 8, gap: 10, minTrack: 88, maxTrack: 132 };
    }

    return { columns: 4, rows: 8, visibleColumns: 2.5, visibleRows: 8, gap: 9, minTrack: 104, maxTrack: 152 };
  }

  seedViewportPageState() {
    const metrics = this.gridMetrics();
    const maxVisibleColumn = Math.max(0, Math.floor((this.state.scrollLeft + this.viewport.clientWidth) / metrics.stepX));
    const maxVisibleRow = Math.max(0, Math.floor((this.state.scrollTop + this.viewport.clientHeight) / metrics.stepY));
    const maxPageX = Math.max(0, Math.floor(maxVisibleColumn / this.columns));
    const maxPageY = Math.max(0, Math.floor(maxVisibleRow / this.rows));

    this.state.loadedPageX = Array.from({ length: maxPageX + 1 }, (_value, index) => index);
    this.state.loadedPageY = Array.from({ length: maxPageY + 1 }, (_value, index) => index);
    this.state.loadedPages = [];

    for (const pageY of this.state.loadedPageY) {
      for (const pageX of this.state.loadedPageX) {
        this.state.loadedPages.push(this.pageKey(pageX, pageY));
      }
    }
  }

  loadStoredState(scope) {
    try {
      const raw = window.sessionStorage.getItem(this.scopeStorageKey(scope));
      if (!raw) {
        return this.defaultState(scope);
      }

      const parsed = JSON.parse(raw);
      return {
        ...this.defaultState(scope),
        ...parsed,
        scope,
        loadedPageX: this.sortedNumbers(parsed.loadedPageX || [0]),
        loadedPageY: this.sortedNumbers(parsed.loadedPageY || [0]),
        loadedPages: this.sortedPageKeys(parsed.loadedPages || ['0:0'])
      };
    } catch (_error) {
      return this.defaultState(scope);
    }
  }

  persistState() {
    this.captureViewport();
    window.sessionStorage.setItem(this.scopeStorageKey(this.scope), JSON.stringify(this.state));
  }

  scopeStorageKey(scope) {
    return scope === 'mine'
      ? 'restbinder.demo.grid.viewState.mine'
      : 'restbinder.demo.grid.viewState.all';
  }

  defaultState(scope) {
    return {
      scope,
      scrollTop: 0,
      scrollLeft: 0,
      loadedPageX: [0],
      loadedPageY: [0],
      loadedPages: ['0:0'],
      expandedItem: null,
      cursor: null
    };
  }

  removePage(pageX, pageY) {
    this.region.querySelectorAll(`[data-page-key="${pageX}:${pageY}"]`).forEach(node => node.remove());
  }

  pageKey(pageX, pageY) {
    return `${pageX}:${pageY}`;
  }

  sortedNumbers(values) {
    return [...new Set(values.map(value => Number.parseInt(value, 10) || 0))].sort((left, right) => left - right);
  }

  sortedPageKeys(keys) {
    return [...new Set(keys)].sort((left, right) => {
      const [leftX, leftY] = left.split(':').map(value => Number.parseInt(value, 10) || 0);
      const [rightX, rightY] = right.split(':').map(value => Number.parseInt(value, 10) || 0);
      if (leftY !== rightY) {
        return leftY - rightY;
      }
      return leftX - rightX;
    });
  }

  setStatus(message, isError = false) {
    this.status.textContent = message;
    this.status.classList.toggle('is-error', isError);
  }

  debounce(callback, wait) {
    let timer = null;
    return (...args) => {
      window.clearTimeout(timer);
      timer = window.setTimeout(() => callback(...args), wait);
    };
  }
}

window.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-rb-surface-demo]').forEach(root => {
    new RestBinderSurfaceDemo(root).mount();
  });
});
