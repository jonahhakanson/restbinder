class RestBinderClient {
  constructor(api = '/api.php') {
    this.api = api;
  }

  async load(id) {
    const response = await fetch(`${this.api}?resource=${encodeURIComponent(id)}`);
    if (!response.ok) throw new Error('Unable to load resource');
    return response.json();
  }

  async command(id, command, input = {}, expectHash = null) {
    const response = await fetch(this.api, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ resource: id, command, input, expect_hash: expectHash })
    });
    const data = await response.json();
    if (!response.ok) throw new Error(data.error || 'Command failed');
    return data;
  }

  async reset(id) {
    const response = await fetch(this.api, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ resource: id, action: 'reset' })
    });
    const data = await response.json();
    if (!response.ok) throw new Error(data.error || 'Reset failed');
    return data;
  }
}

class RestBinderView {
  constructor(root, client) {
    this.root = root;
    this.client = client;
    this.id = root.dataset.rbResource;
    this.envelope = null;
    this.circleSyncTimer = null;
    this.pendingCircleInput = null;
    this.syncingCircle = false;
    this.plotSyncTimer = null;
    this.pendingPlotStages = null;
    this.syncingPlot = false;
    this.plotRunVersion = 0;
    this.plotRunTimeout = null;
  }

  async mount() {
    this.envelope = await this.client.load(this.id);
    this.render();
  }

  async run(command) {
    const input = this.defaultInput(command);
    try {
      this.envelope = await this.client.command(this.id, command, input, this.envelope.resource.hash);
      this.render();
    } catch (error) {
      this.showError(error.message);
    }
  }

  async reset() {
    try {
      this.pendingCircleInput = null;
      this.syncingCircle = false;
      clearTimeout(this.circleSyncTimer);
      this.pendingPlotStages = null;
      this.syncingPlot = false;
      clearTimeout(this.plotSyncTimer);
      this.clearPlotRunTimers();
      this.envelope = await this.client.reset(this.id);
      this.render();
    } catch (error) {
      this.showError(error.message);
    }
  }

  defaultInput(command) {
    const inputs = {
      germinate: { moisture: true, temperature_above_minimum: true },
      sprout: {}
    };
    return inputs[command] || {};
  }

  render() {
    const resource = this.envelope.resource;

    if (resource.type === 'Dandelion') {
      this.renderDandelion(resource);
      return;
    }

    if (resource.type === 'FeedbackCircle') {
      this.renderFeedbackCircle(resource);
      return;
    }

    if (resource.type === 'StagePlot') {
      this.renderStagePlot(resource);
      return;
    }

    this.root.innerHTML = `<p class="rb-error">Unsupported resource type: ${this.escape(resource.type)}</p>`;
  }

  renderDandelion(resource) {
    const state = resource.state;
    const commands = this.availableCommands(resource);

    this.root.innerHTML = `
      <article class="rb-card rb-demo">
        <section class="rb-stage-panel">
          <header>
            <div>
              <span class="rb-badge">${this.escape(resource.type)}</span>
              <h2>${this.escape(this.dandelionTitle(state))}</h2>
              <div class="rb-id">${this.escape(resource.id)}</div>
            </div>
          </header>
          <div class="rb-shape-stage">${this.dandelionVisual(state)}</div>
          <dl class="rb-state">${this.stateRows(state)}</dl>
          <div class="rb-actions">
            ${commands.map(command => `<button data-rb-command="${this.escape(command)}">${this.escape(command)}</button>`).join('')}
            <button data-rb-reset="true" class="secondary">reset</button>
          </div>
          ${commands.length === 0 ? '<p class="rb-note">Sprout reached. The record shows the updated state and mutation history now stored for this resource.</p>' : ''}
        </section>
        <aside class="rb-record-panel">
          <div class="rb-record-label">Object record</div>
          <pre class="rb-record">${this.escape(this.recordJson(resource))}</pre>
        </aside>
      </article>
    `;

    this.root.querySelectorAll('[data-rb-command]').forEach(button => {
      button.addEventListener('click', () => this.run(button.dataset.rbCommand));
    });
    this.root.querySelector('[data-rb-reset]')?.addEventListener('click', () => this.reset());
  }

  renderFeedbackCircle(resource) {
    const state = resource.state;

    this.root.innerHTML = `
      <article class="rb-card rb-demo">
        <section class="rb-stage-panel">
          <header>
            <div>
              <span class="rb-badge">${this.escape(resource.type)}</span>
              <h2>${this.escape(this.circleTitle(state))}</h2>
              <div class="rb-id">${this.escape(resource.id)}</div>
            </div>
          </header>
          <div class="rb-shape-stage rb-circle-stage">
            <div class="rb-circle-shell">
              <div
                class="rb-circle"
                data-rb-circle
                style="width:${this.escape(state.diameter_px)}px;height:${this.escape(state.diameter_px)}px;background:${this.escape(state.fill_hsl)};"
              ></div>
            </div>
          </div>
          <div class="rb-slider-grid">
            <label class="rb-slider">
              <span class="rb-slider-copy">
                <strong>Size</strong>
                <span data-rb-slider-value="diameter_px">${this.escape(state.diameter_px)} px</span>
              </span>
              <input type="range" min="48" max="180" step="1" value="${this.escape(state.diameter_px)}" data-rb-slider="diameter_px">
            </label>
            <label class="rb-slider">
              <span class="rb-slider-copy">
                <strong>Color</strong>
                <span data-rb-slider-value="hue_degrees">${this.escape(state.hue_degrees)} deg</span>
              </span>
              <input type="range" min="0" max="360" step="1" value="${this.escape(state.hue_degrees)}" data-rb-slider="hue_degrees">
            </label>
          </div>
          <dl class="rb-state">${this.stateRows(state, ['loop_mode', 'diameter_px', 'radius_px', 'hue_degrees', 'stream_revision', 'last_input_at'])}</dl>
          <div class="rb-actions">
            <button data-rb-reset="true" class="secondary">reset</button>
          </div>
          <p class="rb-note" data-rb-circle-status>${this.circleStatusText()}</p>
        </section>
        <aside class="rb-record-panel">
          <div class="rb-record-label">Object record</div>
          <pre class="rb-record">${this.escape(this.recordJson(resource))}</pre>
        </aside>
      </article>
    `;

    this.bindFeedbackCircleControls();
  }

  renderStagePlot(resource) {
    const state = resource.state;
    const draftStages = this.pendingPlotStages || state.stages;
    const record = this.recordJson(resource);
    const running = state.run_status === 'running';
    const startDisabled = running || !this.hasTimedPlotStages(draftStages);

    this.root.innerHTML = `
      <article class="rb-card rb-demo rb-plot-demo">
        <section class="rb-stage-panel">
          <header>
            <div>
              <span class="rb-badge">${this.escape(resource.type)}</span>
              <h2>${this.escape(this.stagePlotTitle(state))}</h2>
              <div class="rb-id">${this.escape(resource.id)}</div>
            </div>
          </header>
          <div class="rb-actions rb-plot-actions">
            <button data-rb-start="true" ${startDisabled ? 'disabled' : ''}>start</button>
            <button data-rb-reset="true" class="secondary">reset</button>
          </div>
          <div class="rb-shape-stage rb-plot-stage">
            ${this.stagePlotSvg(state.stages, state.plotted_stages, state.active_stage)}
          </div>
          <div class="rb-plot-table-wrap">
            <table class="rb-stage-table">
              <thead>
                <tr>
                  <th>Stage</th>
                  <th>Timer</th>
                  <th>X</th>
                  <th>Y</th>
                </tr>
              </thead>
              <tbody>
                ${draftStages.map((stage, index) => `
                  <tr>
                    <td>${index + 1}</td>
                    <td><input type="text" value="${this.escape(stage.timer ?? '')}" data-rb-stage-timer="${index}" inputmode="numeric" pattern="[0-9]{2}:[0-9]{2}" maxlength="5" placeholder="00:00" ${running ? 'disabled' : ''}></td>
                    <td><input type="number" value="${this.escape(stage.x ?? '')}" data-rb-stage-x="${index}" min="-100" max="100" step="1" placeholder="0" ${running ? 'disabled' : ''}></td>
                    <td><input type="number" value="${this.escape(stage.y ?? '')}" data-rb-stage-y="${index}" min="-100" max="100" step="1" placeholder="0" ${running ? 'disabled' : ''}></td>
                  </tr>
                `).join('')}
              </tbody>
            </table>
          </div>
          <dl class="rb-state">${this.stateRows(state, ['sequence_mode', 'stage_count', 'run_status', 'active_stage', 'elapsed_display', 'stream_revision', 'last_input_at'])}</dl>
          <p class="rb-note" data-rb-plot-status>${this.plotStatusText(state)}</p>
        </section>
        <aside class="rb-record-panel">
          <div class="rb-record-label">Object record</div>
          <pre class="rb-record" data-rb-plot-record>${this.escape(record)}</pre>
        </aside>
      </article>
    `;

    this.bindStagePlotControls();
  }

  bindFeedbackCircleControls() {
    this.root.querySelectorAll('[data-rb-slider]').forEach(input => {
      input.addEventListener('input', () => {
        const values = this.circleInputValues();
        if (!values) return;
        this.previewCircle(values);
        this.pendingCircleInput = values;
        clearTimeout(this.circleSyncTimer);
        this.circleSyncTimer = setTimeout(() => this.flushCircleTune(), 70);
      });
    });

    this.root.querySelector('[data-rb-reset]')?.addEventListener('click', () => this.reset());

    if (this.pendingCircleInput) {
      this.applyPendingCircleInputs(this.pendingCircleInput);
      this.previewCircle(this.pendingCircleInput);
    }
  }

  bindStagePlotControls() {
    this.root.querySelectorAll('[data-rb-stage-timer], [data-rb-stage-x], [data-rb-stage-y]').forEach(input => {
      input.addEventListener('input', () => {
        const stages = this.stagePlotInputValues();
        if (!stages) return;
        this.pendingPlotStages = stages;
        this.updatePlotDraftState(stages);
      });
    });

    this.root.querySelector('[data-rb-start]')?.addEventListener('click', () => this.startStagePlot());
    this.root.querySelector('[data-rb-reset]')?.addEventListener('click', () => this.reset());

    if (this.pendingPlotStages) {
      this.applyPendingStageInputs(this.pendingPlotStages);
      this.updatePlotDraftState(this.pendingPlotStages);
    }
  }

  async flushCircleTune() {
    if (!this.pendingCircleInput || this.syncingCircle) {
      return;
    }

    const input = this.pendingCircleInput;
    this.pendingCircleInput = null;
    this.syncingCircle = true;
    this.setCircleStatus('Syncing object state...');

    try {
      this.envelope = await this.client.command(this.id, 'tune', input, this.envelope.resource.hash);
      this.render();
    } catch (error) {
      if (error.message.includes('Resource hash conflict')) {
        this.envelope = await this.client.load(this.id);
        this.pendingCircleInput = input;
        this.render();
      } else {
        this.showError(error.message);
      }
    } finally {
      this.syncingCircle = false;
      if (this.pendingCircleInput) {
        this.flushCircleTune();
      } else {
        this.setCircleStatus('Object state synchronized.');
      }
    }
  }

  async startStagePlot() {
    if (this.syncingPlot || this.syncingCircle) {
      return;
    }

    const currentStages = this.stagePlotInputValues() || this.envelope.resource.state.stages;
    this.pendingPlotStages = currentStages;
    this.syncingPlot = true;
    this.setPlotStatus('Preparing staged sequence...');

    try {
      this.envelope = await this.client.command(this.id, 'configure', { stages: currentStages }, this.envelope.resource.hash);
      this.pendingPlotStages = null;
    } catch (error) {
      this.syncingPlot = false;
      this.showError(error.message);
      return;
    }

    const timedStages = this.validTimedStages(this.envelope.resource.state.stages);
    if (timedStages.length === 0) {
      this.syncingPlot = false;
      this.showError('Enter at least one stage with a timer plus x and y values before starting.');
      return;
    }

    this.clearPlotRunTimers();

    try {
      this.envelope = await this.client.command(this.id, 'playback', {
        plotted_stages: [],
        run_status: 'running',
        active_stage: 0,
        elapsed_display: '00:00'
      }, this.envelope.resource.hash);
      this.render();
    } catch (error) {
      this.syncingPlot = false;
      this.showError(error.message);
      return;
    }

    this.syncingPlot = false;

    const runVersion = this.plotRunVersion;
    this.runStagePlotSequence(timedStages, runVersion);
  }

  async runStagePlotSequence(timedStages, runVersion) {
    let previousSeconds = 0;

    for (let index = 0; index < timedStages.length; index += 1) {
      if (runVersion !== this.plotRunVersion) {
        return;
      }

      const entry = timedStages[index];
      const delayMs = Math.max((entry.seconds - previousSeconds) * 1000, 0);
      previousSeconds = entry.seconds;

      const shouldContinue = await this.waitForStageDelay(delayMs, runVersion);
      if (!shouldContinue) {
        return;
      }

      await this.advanceStagePlot(timedStages, index, runVersion);
    }
  }

  async advanceStagePlot(timedStages, index, runVersion = this.plotRunVersion) {
    if (runVersion !== this.plotRunVersion) {
      return;
    }

    const entry = timedStages[index];
    const plottedStages = timedStages.slice(0, index + 1).map(item => item.stage);
    const runStatus = index === timedStages.length - 1 ? 'completed' : 'running';

    try {
      this.envelope = await this.client.command(this.id, 'playback', {
        plotted_stages: plottedStages,
        run_status: runStatus,
        active_stage: entry.stage.stage,
        elapsed_display: entry.stage.timer || this.secondsToDisplay(entry.seconds)
      }, this.envelope.resource.hash);
      this.render();
    } catch (error) {
      this.showError(error.message);
    }

    if (runStatus === 'completed' && runVersion === this.plotRunVersion) {
      this.clearPlotRunTimers();
    }
  }

  availableCommands(resource) {
    const stage = resource.state.life_stage;
    if (stage === 'seed') return ['germinate'];
    if (stage === 'germinating') return ['sprout'];
    return [];
  }

  dandelionTitle(state) {
    return `Dandelion: ${state.life_stage}`;
  }

  circleTitle(state) {
    return `Feedback circle: ${state.stream_revision} updates`;
  }

  stagePlotTitle(state) {
    return `Stage plot: ${state.stage_count} stages`;
  }

  dandelionVisual(state) {
    const emoji = {
      seed: '•',
      germinating: '🌱',
      sprout: '🌱'
    }[state.life_stage] || '•';

    return `<div class="rb-dandelion" title="${this.escape(state.display_shape)}">${emoji}</div>`;
  }

  stagePlotSvg(stages, plottedStages = [], activeStage = 0) {
    const width = 420;
    const height = 260;
    const padding = { top: 20, right: 24, bottom: 32, left: 42 };
    const configuredStages = stages.filter(stage => this.hasPoint(stage));
    const executedStages = plottedStages.filter(stage => this.hasPoint(stage));
    const pointsForBounds = configuredStages.length > 0 ? configuredStages : executedStages;

    const xs = pointsForBounds.map(stage => stage.x);
    const ys = pointsForBounds.map(stage => stage.y);
    const bounds = this.plotBounds(xs, ys);
    const plotWidth = width - padding.left - padding.right;
    const plotHeight = height - padding.top - padding.bottom;

    const toSvgX = value => padding.left + ((value - bounds.minX) / (bounds.maxX - bounds.minX || 1)) * plotWidth;
    const toSvgY = value => height - padding.bottom - ((value - bounds.minY) / (bounds.maxY - bounds.minY || 1)) * plotHeight;
    const zeroX = toSvgX(Math.min(bounds.maxX, Math.max(bounds.minX, 0)));
    const zeroY = toSvgY(Math.min(bounds.maxY, Math.max(bounds.minY, 0)));

    const executedPolyline = executedStages
      .map(stage => `${toSvgX(stage.x).toFixed(2)},${toSvgY(stage.y).toFixed(2)}`)
      .join(' ');

    const points = executedStages.map(stage => {
      const x = toSvgX(stage.x);
      const y = toSvgY(stage.y);
      const active = stage.stage === activeStage;
      return `
        <g class="rb-plot-point is-executed${active ? ' is-active' : ''}">
          <circle cx="${x.toFixed(2)}" cy="${y.toFixed(2)}" r="${active ? '6' : '4.5'}"></circle>
          <text x="${x.toFixed(2)}" y="${(y - 10).toFixed(2)}">${stage.stage}</text>
        </g>
      `;
    }).join('');

    return `
      <svg viewBox="0 0 ${width} ${height}" class="rb-plot-svg" aria-label="Cartesian stage plot">
        <line x1="${padding.left}" y1="${zeroY.toFixed(2)}" x2="${width - padding.right}" y2="${zeroY.toFixed(2)}"></line>
        <line x1="${zeroX.toFixed(2)}" y1="${padding.top}" x2="${zeroX.toFixed(2)}" y2="${height - padding.bottom}"></line>
        ${executedStages.length > 0 ? `<polyline points="${executedPolyline}" class="rb-plot-executed"></polyline>` : ''}
        ${points}
        <text x="${width - padding.right}" y="${height - 8}" class="rb-plot-axis">x</text>
        <text x="${padding.left - 14}" y="${padding.top + 10}" class="rb-plot-axis">y</text>
      </svg>
    `;
  }

  plotBounds(xs, ys) {
    if (xs.length === 0 || ys.length === 0) {
      return {
        minX: -10,
        maxX: 10,
        minY: -10,
        maxY: 10
      };
    }

    const minX = Math.min(...xs);
    const maxX = Math.max(...xs);
    const minY = Math.min(...ys);
    const maxY = Math.max(...ys);
    const xPad = Math.max(6, Math.ceil((maxX - minX || 12) * 0.12));
    const yPad = Math.max(6, Math.ceil((maxY - minY || 12) * 0.12));

    return {
      minX: Math.min(minX - xPad, 0),
      maxX: Math.max(maxX + xPad, 0),
      minY: Math.min(minY - yPad, 0),
      maxY: Math.max(maxY + yPad, 0)
    };
  }

  stateRows(state, order = null) {
    const entries = order
      ? order
          .filter(key => Object.prototype.hasOwnProperty.call(state, key))
          .map(key => [key, state[key]])
      : Object.entries(state);

    return entries
      .map(([key, value]) => `<div><dt>${this.escape(key)}</dt><dd>${this.escape(String(value))}</dd></div>`)
      .join('');
  }

  recordJson(resource, overrideState = null) {
    return JSON.stringify({
      id: resource.id,
      type: resource.type,
      created_at: resource.created_at,
      schema_version: resource.schema_version,
      protocol_version: resource.protocol_version,
      state: overrideState || resource.state,
      history: resource.history,
      hash: resource.hash
    }, null, 2);
  }

  circleInputValues() {
    const diameterInput = this.root.querySelector('[data-rb-slider="diameter_px"]');
    const hueInput = this.root.querySelector('[data-rb-slider="hue_degrees"]');
    if (!diameterInput || !hueInput) {
      return null;
    }

    return {
      diameter_px: Number.parseInt(diameterInput.value, 10),
      hue_degrees: Number.parseInt(hueInput.value, 10)
    };
  }

  stagePlotInputValues() {
    const stages = [];

    for (let index = 0; index < 10; index += 1) {
      const timerInput = this.root.querySelector(`[data-rb-stage-timer="${index}"]`);
      const xInput = this.root.querySelector(`[data-rb-stage-x="${index}"]`);
      const yInput = this.root.querySelector(`[data-rb-stage-y="${index}"]`);
      if (!timerInput || !xInput || !yInput) {
        return null;
      }

      const timer = this.normalizeTimer(timerInput.value);
      const x = this.normalizeCoordinate(xInput.value);
      const y = this.normalizeCoordinate(yInput.value);
      stages.push({ stage: index + 1, timer, x, y });
    }

    return stages;
  }

  applyPendingCircleInputs(values) {
    const diameterInput = this.root.querySelector('[data-rb-slider="diameter_px"]');
    const hueInput = this.root.querySelector('[data-rb-slider="hue_degrees"]');
    if (diameterInput) diameterInput.value = String(values.diameter_px);
    if (hueInput) hueInput.value = String(values.hue_degrees);
  }

  applyPendingStageInputs(stages) {
    stages.forEach((stage, index) => {
      const timerInput = this.root.querySelector(`[data-rb-stage-timer="${index}"]`);
      const xInput = this.root.querySelector(`[data-rb-stage-x="${index}"]`);
      const yInput = this.root.querySelector(`[data-rb-stage-y="${index}"]`);
      if (timerInput) timerInput.value = stage.timer;
      if (xInput) xInput.value = stage.x ?? '';
      if (yInput) yInput.value = stage.y ?? '';
    });
  }

  previewCircle(values) {
    const circle = this.root.querySelector('[data-rb-circle]');
    if (circle) {
      circle.style.width = `${values.diameter_px}px`;
      circle.style.height = `${values.diameter_px}px`;
      circle.style.background = this.previewHsl(values.hue_degrees);
    }

    const diameterValue = this.root.querySelector('[data-rb-slider-value="diameter_px"]');
    if (diameterValue) diameterValue.textContent = `${values.diameter_px} px`;

    const hueValue = this.root.querySelector('[data-rb-slider-value="hue_degrees"]');
    if (hueValue) hueValue.textContent = `${values.hue_degrees} deg`;

    this.setCircleStatus('Streaming slider input...');
  }

  previewStagePlot(stages) {
    this.updatePlotDraftState(stages);
  }

  previewHsl(hue) {
    return `hsl(${hue}, 78%, 52%)`;
  }

  normalizeTimer(value) {
    if (String(value).trim() === '') {
      return '';
    }
    const match = String(value).match(/^(\d{0,2})(?::?(\d{0,2}))?$/);
    const minutes = Math.max(0, Math.min(59, Number.parseInt(match?.[1] || '0', 10) || 0));
    const seconds = Math.max(0, Math.min(59, Number.parseInt(match?.[2] || '0', 10) || 0));
    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
  }

  normalizeCoordinate(value) {
    if (String(value).trim() === '') {
      return null;
    }
    const parsed = Number.parseInt(value, 10);
    if (Number.isNaN(parsed)) {
      return null;
    }
    return Math.max(-100, Math.min(100, parsed));
  }

  circleStatusText() {
    if (this.syncingCircle) {
      return 'Syncing object state...';
    }
    if (this.pendingCircleInput) {
      return 'Streaming slider input...';
    }
    return 'Move the sliders to push live input back into the stored object state.';
  }

  plotStatusText(state = this.envelope?.resource?.state) {
    if (state?.run_status === 'running') {
      return `Running sequence${state.active_stage ? ` through stage ${state.active_stage}` : ''}...`;
    }
    if (state?.run_status === 'completed') {
      return `Sequence completed at ${state.elapsed_display}. Press start to run again or reset to clear the table and graph.`;
    }
    if (this.syncingPlot) {
      return 'Syncing staged sequence...';
    }
    if (this.pendingPlotStages) {
      return 'Sequence updated. The graph stays empty until you press start.';
    }
    return 'Enter timers and coordinates, then press start to plot each point and segment over the timed run.';
  }

  setCircleStatus(message) {
    this.root.querySelector('[data-rb-circle-status]')?.replaceChildren(document.createTextNode(message));
  }

  setPlotStatus(message) {
    this.root.querySelector('[data-rb-plot-status]')?.replaceChildren(document.createTextNode(message));
  }

  updatePlotDraftState(stages) {
    const startButton = this.root.querySelector('[data-rb-start]');
    if (startButton) {
      startButton.disabled = !this.hasTimedPlotStages(stages);
    }
    this.setPlotStatus('Sequence updated. The graph stays empty until you press start.');
    this.root.querySelector('.rb-error')?.remove();
  }

  hasPoint(stage) {
    return Number.isFinite(stage?.x) && Number.isFinite(stage?.y);
  }

  hasTimedPlotStages(stages) {
    return this.validTimedStages(stages).length > 0;
  }

  validTimedStages(stages) {
    let previousSeconds = 0;

    return stages
      .filter(stage => this.hasPoint(stage) && this.parseTimerToSeconds(stage.timer) !== null)
      .map(stage => {
        const parsedSeconds = this.parseTimerToSeconds(stage.timer) ?? previousSeconds;
        const seconds = Math.max(parsedSeconds, previousSeconds);
        previousSeconds = seconds;
        return { stage, seconds };
      });
  }

  parseTimerToSeconds(timer) {
    const match = String(timer).match(/^(\d{2}):(\d{2})$/);
    if (!match) {
      return null;
    }

    const minutes = Number.parseInt(match[1], 10);
    const seconds = Number.parseInt(match[2], 10);
    if (minutes > 59 || seconds > 59) {
      return null;
    }

    return (minutes * 60) + seconds;
  }

  secondsToDisplay(totalSeconds) {
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;
    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
  }

  clearPlotRunTimers() {
    this.plotRunVersion += 1;
    if (this.plotRunTimeout !== null) {
      clearTimeout(this.plotRunTimeout);
      this.plotRunTimeout = null;
    }
  }

  waitForStageDelay(delayMs, runVersion) {
    if (delayMs <= 0) {
      return Promise.resolve(runVersion === this.plotRunVersion);
    }

    return new Promise(resolve => {
      this.plotRunTimeout = setTimeout(() => {
        this.plotRunTimeout = null;
        resolve(runVersion === this.plotRunVersion);
      }, delayMs);
    });
  }

  showError(message) {
    this.root.querySelector('.rb-error')?.remove();
    this.root.insertAdjacentHTML('beforeend', `<p class="rb-error">${this.escape(message)}</p>`);
  }

  escape(value) {
    return String(value).replace(/[&<>'"]/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[char]));
  }
}

window.addEventListener('DOMContentLoaded', () => {
  const client = new RestBinderClient();
  document.querySelectorAll('[data-rb-resource]').forEach(root => new RestBinderView(root, client).mount());
});
