/** FOURWARD coursework | 韩羽宸 2410673 | Interactive charts shared by PHP and the World Cup snapshot. */
const blue = '#72b7ff';
const orange = '#ffb57b';
const muted = '#b4c4df';
const gridLine = '#a7bdeb29';
const libraryUrl = new URL('../vendor/echarts/echarts.min.js', import.meta.url);
const mountSelector = '[data-wc-chart]';
let library;

function loadLibrary() {
  if (globalThis.echarts) return Promise.resolve(globalThis.echarts);
  library ??= new Promise((resolve, reject) => {
    const script = document.createElement('script');
    script.src = libraryUrl.href;
    script.onload = () => globalThis.echarts ? resolve(globalThis.echarts) : reject(new Error('ECharts unavailable'));
    script.onerror = () => reject(new Error('ECharts failed to load'));
    document.head.append(script);
  });
  return library;
}

function element(tag, className, text) {
  const node = document.createElement(tag);
  if (className) node.className = className;
  if (text != null) node.textContent = text;
  return node;
}

function number(value) {
  return value != null && value !== '' && Number.isFinite(Number(value)) ? Number(value) : null;
}

function format(value, unit = '') {
  return value == null ? '暂无' : `${Number(value.toFixed(2))}${unit}`;
}

function picker(label, options) {
  const select = element('select');
  select.setAttribute('aria-label', label);
  for (const [value, name] of options) {
    const option = element('option', '', name);
    option.value = value;
    select.append(option);
  }
  const wrapper = element('label', 'wc-chart-picker', label);
  wrapper.append(select);
  return { select, wrapper };
}

function chips(options, label, onSelect) {
  const group = element('div', 'wc-chart-chips');
  group.setAttribute('role', 'group');
  group.setAttribute('aria-label', label);
  const buttons = options.map(([key, text]) => {
    const button = element('button', '', text);
    button.type = 'button';
    button.addEventListener('click', () => onSelect(key));
    group.append(button);
    return [key, button];
  });
  const update = (active) => buttons.forEach(([key, button]) => button.setAttribute('aria-pressed', String(key === active)));
  update(options[0][0]);
  return { group, update };
}

function prepare(root, title, hint) {
  const toolbar = element('div', 'wc-chart-toolbar');
  const status = element('p', 'wc-chart-readout');
  status.setAttribute('role', 'status');
  const canvas = element('div', 'wc-chart-canvas');
  const detail = element('p', 'wc-chart-hint', hint);
  root.append(toolbar, canvas, status, detail);
  root.hidden = false;
  canvas.setAttribute('aria-label', title);
  return { canvas, toolbar, status, detail };
}

function baseOptions(description) {
  return {
    animation: !matchMedia('(prefers-reduced-motion: reduce)').matches,
    animationDuration: 360,
    animationDurationUpdate: 280,
    aria: { enabled: true, label: { description } },
    textStyle: { color: muted, fontFamily: 'system-ui, sans-serif' },
  };
}

const categoryAxis = { type: 'category', axisLabel: { color: muted, hideOverlap: true }, axisLine: { lineStyle: { color: gridLine } }, axisTick: { show: false } };
const valueAxis = { type: 'value', axisLabel: { color: muted }, splitLine: { lineStyle: { color: gridLine } } };
const tooltipStyle = {
  renderMode: 'richText', confine: true, transitionDuration: 0.12,
  backgroundColor: '#132139', borderColor: '#6087b5', textStyle: { color: '#f1f6ff', fontSize: 12 },
};

function setup(echarts, canvas) {
  const chart = echarts.init(canvas, null, { renderer: 'svg' });
  const observer = new ResizeObserver(() => {
    if (canvas.clientWidth && canvas.clientHeight) chart.resize();
  });
  observer.observe(canvas);
  return chart;
}

function radar(root, data, echarts) {
  const players = data.players.filter((player) => data.axes.every(([key]) => number(player.values?.[key]) != null));
  if (!players.length) return;
  const { canvas, toolbar, status } = prepare(root, '球员综合能力雷达图', '悬停或点击图上的能力名称查看评分。点击图例可切换显示的球员。');
  const chart = setup(echarts, canvas);
  let active = data.axes[0][0];
  const centerYRatio = 0.54;
  const radiusRatio = 0.66;
  const hoverTip = element('div', 'wc-radar-hover-tip');
  hoverTip.hidden = true;
  hoverTip.setAttribute('aria-hidden', 'true');
  canvas.append(hoverTip);
  const seriesColors = players.map((player) => player.side === 1 ? orange : blue);
  const names = players.map((player) => player.name);
  // Keep identities separate when two different players have the same display name.
  const seriesNames = names.map((name, index) => names.filter((n) => n === name).length > 1 ? `${name}（${index === 0 ? 'A' : 'B'}）` : name);
  const readout = (key) => {
    const label = data.axes.find(([axis]) => axis === key)[1];
    const scores = players.map((player, index) => `${seriesNames[index]} ${format(number(player.values[key]), ' 分')}`);
    const difference = players.length === 2 ? ` · 相差 ${format(Math.abs(players[0].values[key] - players[1].values[key]), ' 分')}` : '';
    return `${label} · ${scores.join(' / ')}${difference}`;
  };
  const selectAxis = (key) => {
    active = key;
    controls.update(key);
    status.textContent = readout(key);
    chart.setOption({ radar: { axisName: { formatter: (name) => name === data.axes.find(([axis]) => axis === active)[1] ? `{selected|${name}}` : name } } });
  };
  const controls = chips(data.axes, '能力项', selectAxis);
  toolbar.append(controls.group);
  chart.setOption({
    ...baseOptions('六项懂球帝能力评分，统一使用零到一百分。'),
    color: seriesColors,
    legend: { top: 0, textStyle: { color: muted }, data: seriesNames },
    radar: {
      center: ['50%', `${centerYRatio * 100}%`], radius: `${radiusRatio * 100}%`, startAngle: 90, splitNumber: 5, triggerEvent: true,
      indicator: data.axes.map(([, name]) => ({ name, max: 100 })),
      axisName: { color: muted, rich: { selected: { color: '#fff', fontWeight: 700 } } },
      axisLine: { lineStyle: { color: gridLine } }, splitLine: { lineStyle: { color: gridLine } },
      splitArea: { areaStyle: { color: ['#1a2b40', '#1e3147'] } },
    },
    series: [{
      type: 'radar', symbol: 'circle', symbolSize: 5,
      data: players.map((player, index) => ({
        name: seriesNames[index], value: data.axes.map(([key]) => number(player.values[key])),
        lineStyle: { color: seriesColors[index], width: 2 }, itemStyle: { color: seriesColors[index] },
        areaStyle: { color: seriesColors[index], opacity: 0.16 },
      })),
    }],
  });
  const axisKey = (name) => data.axes.find(([, label]) => label === name?.replace(/^\{selected\|(.+)\}$/, '$1'))?.[0];
  chart.on('click', 'radar', ({ name }) => {
    const key = axisKey(name);
    if (key) selectAxis(key);
  });
  const hideTip = () => { hoverTip.hidden = true; };
  canvas.addEventListener('pointerleave', hideTip);
  canvas.addEventListener('pointermove', (event) => {
    if (event.pointerType === 'touch') return;
    const bounds = canvas.getBoundingClientRect();
    const x = event.clientX - bounds.left;
    const y = event.clientY - bounds.top;
    const dx = x - bounds.width / 2;
    const dy = bounds.height * centerYRatio - y;
    const distance = Math.hypot(dx, dy);
    const radius = Math.min(bounds.width, bounds.height) / 2 * radiusRatio;
    if (distance < 8 || distance > radius + 28) {
      hideTip();
      return;
    }
    // ECharts starts at the top and places subsequent indicators counterclockwise.
    const angle = (Math.atan2(dy, dx) - Math.PI / 2 + 2 * Math.PI) % (2 * Math.PI);
    const index = Math.round(angle / (2 * Math.PI) * data.axes.length) % data.axes.length;
    const message = readout(data.axes[index][0]);
    if (hoverTip.textContent !== message) hoverTip.textContent = message;
    hoverTip.hidden = false;
    const left = Math.max(0, Math.min(x + 12, bounds.width - hoverTip.offsetWidth));
    const top = y - hoverTip.offsetHeight - 12;
    hoverTip.style.left = `${left}px`;
    hoverTip.style.top = `${top >= 0 ? top : y + 12}px`;
  });
  selectAxis(active);
  root.parentElement.querySelector('[data-wc-chart-fallback]')?.setAttribute('hidden', '');
  root.parentElement.querySelector('[data-wc-raw-table]')?.removeAttribute('open');
}

function playerMatches(root, data, echarts) {
  const panel = root.closest('#single-performance');
  const form = panel.querySelector('form');
  const matchSelect = form.elements.match_id;
  const body = panel.querySelector('[data-wc-match-body]');
  const activeId = () => matchSelect.value;
  const fallback = new Map([...panel.querySelectorAll('template[data-wc-match-id]')].map((item) => [item.dataset.wcMatchId, item.content]));
  fallback.set(activeId(), document.createDocumentFragment());
  fallback.get(activeId()).append(...[...body.childNodes].map((node) => node.cloneNode(true)));
  const metrics = data.metrics.filter(([key]) => data.games.some((game) => number(game.metrics[key]) != null));
  if (!metrics.length) return;
  const { canvas, toolbar, status } = prepare(root, '逐场表现柱状图', '点柱形或零值圆点切换单场明细。暂无数据的比赛可用下方比赛选择框查看。');
  const chart = setup(echarts, canvas);
  const { select, wrapper } = picker('逐场统计项', metrics.map(([key, label]) => [key, label]));
  toolbar.append(wrapper);
  const render = () => {
    const [key, label, unit = ''] = metrics.find(([key]) => key === select.value);
    const values = data.games.map((game) => number(game.metrics[key]));
    const selectedIndex = data.games.findIndex((game) => String(game.id) === activeId());
    const summary = (index) => `${data.games[index].opponent} · ${data.games[index].stage} · ${data.games[index].appearance}\n${label} ${format(values[index], unit)}`;
    status.textContent = selectedIndex < 0 ? '' : `已选 ${summary(selectedIndex).replace('\n', ' · ')}`;
    chart.setOption({
      ...baseOptions('横轴为该球员球队的比赛，纵轴为选中的单场指标。'),
      grid: { top: 32, bottom: 54, left: 48, right: 16 },
      xAxis: { ...categoryAxis, data: data.games.map((game) => game.opponent), axisLabel: { ...categoryAxis.axisLabel, width: 62, overflow: 'truncate', interval: 'auto' } },
      yAxis: { ...valueAxis, minInterval: key === 'goals_prevented' ? 0.1 : 1 },
      tooltip: { ...tooltipStyle, trigger: 'axis', axisPointer: { type: 'shadow' }, formatter: (items) => items.length ? summary(items[0].dataIndex) : '' },
      series: [
        { type: 'bar', name: label, barMaxWidth: 42, cursor: 'pointer', data: values.map((value, index) => ({ value, itemStyle: { color: index === selectedIndex ? orange : blue } })), itemStyle: { borderRadius: [5, 5, 0, 0] }, emphasis: { focus: 'self' } },
        { type: 'scatter', name: '零值', symbolSize: 9, cursor: 'pointer', data: values.map((value, index) => value === 0 ? { value: [index, 0], itemStyle: { color: index === selectedIndex ? orange : blue } } : null) },
      ],
    });
  };
  const switchMatch = (id, updateUrl = true) => {
    const source = fallback.get(String(id));
    if (!source) return false;
    matchSelect.value = String(id);
    body.replaceChildren(source.cloneNode(true));
    if (updateUrl) {
      const url = new URL(location.href);
      url.searchParams.set('match_id', id);
      history.replaceState(null, '', url);
    }
    render();
    return true;
  };
  chart.on('click', 'series', ({ dataIndex }) => {
    if (data.games[dataIndex]) switchMatch(data.games[dataIndex].id);
  });
  select.addEventListener('change', render);
  matchSelect.addEventListener('change', () => switchMatch(activeId()));
  form.addEventListener('submit', (event) => {
    if (switchMatch(activeId())) event.preventDefault();
  });
  render();
}

function comparison(root, data, echarts) {
  const metrics = data.metrics.filter(({ values }) => values.some((value) => number(value) != null));
  if (!metrics.length) return;
  const { canvas, toolbar, status, detail } = prepare(root, '双方指标对比');
  const chart = setup(echarts, canvas);
  const { select, wrapper } = picker('对比指标', [['', '全部指标'], ...metrics.map(({ key, label }) => [key, label])]);
  toolbar.append(wrapper);
  let mode = 'total';
  const fullCoverage = (metric) => !metric.coverage || metric.coverage.every((count, index) => count === data.games[index]);
  const canAverage = (metric) => metric.perMatch === true && fullCoverage(metric) && data.games?.every((games) => games > 0);
  const resultsFor = (metric, values, unit) => data.names.map((name, index) => {
    const coverage = metric.coverage && metric.coverage[index] !== data.games[index] ? `（${metric.coverage[index]}/${data.games[index]} 场有数据）` : '';
    return `${name} ${format(values[index], unit)}${coverage}`;
  });
  const overview = metrics.map((metric) => {
    const values = metric.values.map(number);
    const maximum = Math.max(metric.unit === '%' ? 100 : 1, ...values.map(Math.abs));
    return { ...metric, values, lengths: values.map((value) => value == null ? 0 : 100 * value / maximum) };
  });
  const overviewOptions = {
    ...baseOptions('每行比较双方的同一项统计，柱长按该行单独缩放，标签显示原始数值。'),
    grid: { top: 0, bottom: 0, left: 96, right: 52 },
    xAxis: { type: 'value', max: 100, show: false },
    yAxis: {
      ...categoryAxis, inverse: true, data: overview.map(({ label }) => label), triggerEvent: true,
      axisLabel: { ...categoryAxis.axisLabel, interval: 0, width: 86, overflow: 'truncate' },
      splitArea: { show: true, areaStyle: { color: ['#ffffff05', 'transparent'] } },
    },
    tooltip: {
      ...tooltipStyle, trigger: 'axis', axisPointer: { type: 'shadow' },
      formatter: (items) => {
        const metric = overview[items[0]?.dataIndex];
        return metric ? [metric.label, ...resultsFor(metric, metric.values, metric.unit)].join('\n') : '';
      },
    },
    series: [blue, orange].map((color, side) => ({
      type: 'bar', name: data.names[side], barMaxWidth: 12, cursor: 'pointer',
      data: overview.map(({ lengths }) => lengths[side]),
      itemStyle: { color, borderRadius: 4 },
      label: {
        show: true, position: 'right', color: '#eff6ff', fontSize: 11,
        formatter: ({ dataIndex }) => format(overview[dataIndex].values[side], overview[dataIndex].unit),
      },
    })),
  };
  const renderSingle = (metric) => {
    const average = canAverage(metric) && mode === 'average';
    const unit = average ? `${metric.unit ?? ''}/场` : metric.unit;
    const values = metric.values.map((value, index) => {
      const count = number(value);
      return count == null || !average ? count : count / data.games[index];
    });
    const results = resultsFor(metric, values, unit);
    const difference = fullCoverage(metric) && values.every((value) => value != null) ? ` · 相差 ${format(Math.abs(values[0] - values[1]), metric.unit === '%' ? ' 个百分点' : unit)}` : '';
    status.textContent = `${metric.label}${average ? '（场均）' : ''} · ${results.join(' / ')}${difference}`;
    chart.setOption({
      ...baseOptions('蓝色和橙色代表两个对比对象，柱形高度使用同一指标的原始单位。'),
      grid: { top: 36, bottom: 46, left: 55, right: 20 },
      xAxis: { ...categoryAxis, data: data.names, axisLabel: { ...categoryAxis.axisLabel, width: 120, overflow: 'truncate' } },
      yAxis: { ...valueAxis, max: unit === '%' ? 100 : undefined, axisLabel: { ...valueAxis.axisLabel, formatter: (value) => format(value, unit === '%' ? '%' : '') } },
      tooltip: { ...tooltipStyle, trigger: 'item', formatter: ({ dataIndex }) => `${metric.label}\n${results[dataIndex]}` },
      series: [{ type: 'bar', barMaxWidth: 92, data: values.map((value, index) => ({ value, itemStyle: { color: index === 0 ? blue : orange } })),
        itemStyle: { borderRadius: [6, 6, 0, 0] }, label: { show: true, position: 'top', color: '#eff6ff', formatter: ({ dataIndex }) => format(values[dataIndex], unit) } }],
    }, { notMerge: true });
  };
  const render = () => {
    const metric = metrics.find(({ key }) => key === select.value);
    modes.group.hidden = !metric || !canAverage(metric);
    if (metric) canvas.style.removeProperty('--wc-chart-height');
    else canvas.style.setProperty('--wc-chart-height', `${overview.length * 38}px`);
    chart.resize();
    if (metric) {
      detail.textContent = '双方共用原始单位和刻度。选择「全部指标」可返回总览。';
      renderSingle(metric);
    } else {
      status.textContent = '';
      detail.textContent = '每行使用自己的刻度，数值标在柱旁。点击指标名或柱形可查看单项。';
      chart.setOption(overviewOptions, { notMerge: true });
    }
  };
  const modes = chips([['total', '赛事总量'], ['average', '场均']], '统计口径', (value) => {
    mode = value;
    modes.update(value);
    render();
  });
  toolbar.append(modes.group);
  select.addEventListener('change', render);
  chart.on('click', ({ componentType, dataIndex, value }) => {
    if (select.value) return;
    const metric = componentType === 'series' ? overview[dataIndex] : overview.find(({ label }) => componentType === 'yAxis' && label === value);
    if (!metric) return;
    select.value = metric.key;
    render();
  });
  render();
  root.parentElement.querySelector('[data-wc-chart-fallback]')?.setAttribute('hidden', '');
  root.parentElement.querySelector('[data-wc-raw-table]')?.removeAttribute('open');
}

const charts = { radar, 'player-matches': playerMatches, comparison };

export async function mountWorldCupCharts(scope = document) {
  const mounts = [...scope.querySelectorAll(mountSelector)];
  if (!mounts.length) return;
  try {
    const echarts = await loadLibrary();
    for (const root of mounts) {
      try {
        const mount = charts[root.dataset.wcChart];
        if (mount && root.hidden) mount(root, JSON.parse(root.dataset.wcChartData), echarts);
      } catch (error) {
        root.hidden = true;
        console.warn('World Cup chart could not be displayed', error);
      }
    }
  } catch (error) {
    console.warn('World Cup chart library could not be loaded', error);
  }
}

export function chartMount(kind, data) {
  const root = element('div', 'wc-chart');
  root.dataset.wcChart = kind;
  root.dataset.wcChartData = JSON.stringify(data);
  root.hidden = true;
  return root;
}

if (document.querySelector(mountSelector)) mountWorldCupCharts();
