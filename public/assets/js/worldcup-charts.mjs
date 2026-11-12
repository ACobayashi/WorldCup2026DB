/** FOURWARD coursework | 韩羽宸 2410673 | Interactive charts shared by PHP and the World Cup snapshot. */
const blue = '#72b7ff';
const orange = '#ffb57b';
const muted = '#b4c4df';
const gridLine = '#a7bdeb29';
const libraryUrl = new URL('../vendor/echarts/echarts.min.js', import.meta.url);
const mountSelector = '[data-wc-chart]';
const eventTypes = {
  goal: ['进球', 'goal', '#83e7ae', 'circle'],
  own_goal: ['乌龙球', 'goal', '#83e7ae', 'circle'],
  yellow_card: ['黄牌', 'card', '#ffda74', 'rect'],
  red_card: ['红牌', 'card', '#ff8f9a', 'rect'],
  subbed_on: ['换上', 'sub', '#b8a5ff', 'diamond'],
  subbed_off: ['换下', 'sub', '#b8a5ff', 'diamond'],
};
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
    tooltip: { ...tooltipStyle, trigger: 'item', formatter: () => readout(active) },
    radar: {
      center: ['50%', '54%'], radius: '66%', startAngle: 90, splitNumber: 5, triggerEvent: true,
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
  chart.on('mouseover', 'radar', ({ name }) => {
    const key = axisKey(name);
    if (key) status.textContent = readout(key);
  });
  chart.on('mouseout', 'radar', () => { status.textContent = readout(active); });
  chart.on('click', 'radar', ({ name }) => {
    const key = axisKey(name);
    if (key) selectAxis(key);
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
  const { canvas, toolbar, status } = prepare(root, '双方指标对比', '切换统计项后，双方使用同一刻度。悬停或轻触柱形可查看数值。');
  const chart = setup(echarts, canvas);
  const { select, wrapper } = picker('对比指标', metrics.map(({ key, label }) => [key, label]));
  select.value = (metrics.find(({ key }) => key === 'goals_for' || key === 'goals') ?? metrics[0]).key;
  toolbar.append(wrapper);
  let mode = 'total';
  const fullCoverage = (metric) => !metric.coverage || metric.coverage.every((count, index) => count === data.games[index]);
  const canAverage = (metric) => metric.perMatch === true && fullCoverage(metric) && data.games?.every((games) => games > 0);
  const render = () => {
    const metric = metrics.find(({ key }) => key === select.value);
    const average = canAverage(metric) && mode === 'average';
    const unit = average ? `${metric.unit ?? ''}/场` : metric.unit;
    const values = metric.values.map((value, index) => {
      const count = number(value);
      return count == null || !average ? count : count / data.games[index];
    });
    modes.group.hidden = !canAverage(metric);
    const results = data.names.map((name, index) => {
      const coverage = metric.coverage && metric.coverage[index] !== data.games[index] ? `（${metric.coverage[index]}/${data.games[index]} 场有数据）` : '';
      return `${name} ${format(values[index], unit)}${coverage}`;
    });
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
  const modes = chips([['total', '赛事总量'], ['average', '场均']], '统计口径', (value) => {
    mode = value;
    modes.update(value);
    render();
  });
  toolbar.append(modes.group);
  select.addEventListener('change', render);
  render();
  root.parentElement.querySelector('[data-wc-chart-fallback]')?.setAttribute('hidden', '');
  root.parentElement.querySelector('[data-wc-raw-table]')?.removeAttribute('open');
}

function eventTimeline(root, data, echarts) {
  if (!data.events.length) return;
  const list = root.parentElement.querySelector('[data-wc-event-list]');
  const rows = [...list.querySelectorAll('[data-wc-event-index]')];
  const { canvas, toolbar, status } = prepare(root, '双方比赛事件时间轴', '上方为主队，下方为客队。点事件标记定位到记录，拖动下方滑块查看某一时段。');
  const chart = setup(echarts, canvas);
  let filter = 'all';
  let selected = [];
  const limit = data.extraTime ? 120 : 90;
  const max = Math.max(limit + 1, ...data.events.map((event) => event.minute + 1));
  let timeRange = [0, max];
  // Place stoppage-time labels after their boundary and before the following phase.
  const position = (event) => event.minute + (event.added ? 0.85 * event.added / (event.added + 1) : 0);
  const activeGroups = () => {
    const groups = new Map();
    data.events.forEach((event, index) => {
      const type = eventTypes[event.type];
      if (!type || (filter !== 'all' && type[1] !== filter)) return;
      const key = `${event.side}/${event.minute}/${event.added}`;
      if (!groups.has(key)) groups.set(key, []);
      groups.get(key).push({ ...event, index });
    });
    return [...groups.values()];
  };
  const highlight = (events, scroll) => {
    selected = events.map((event) => event.index);
    rows.forEach((row, index) => row.classList.toggle('is-chart-selected', selected.includes(index)));
    status.textContent = events.length ? `${events[0].label} · ${data.names[events[0].side]} · ${events.map((event) => `${eventTypes[event.type][0]} ${event.name}`).join(' / ')}` : '点击图上的事件查看详情';
    if (scroll && selected.length) {
      const row = rows[selected[0]];
      list.scrollTop += row.getBoundingClientRect().top - list.getBoundingClientRect().top - 10;
    }
  };
  const updateList = (groups) => {
    const visible = new Set(groups.flat().filter((event) => position(event) >= timeRange[0] && position(event) <= timeRange[1]).map((event) => event.index));
    rows.forEach((row, index) => { row.hidden = !visible.has(index); });
    if (selected.some((index) => !visible.has(index))) highlight([], false);
    if (!selected.length) status.textContent = visible.size ? `当前显示 ${visible.size} 条事件` : '所选时段和类别暂无已记录事件。';
  };
  const render = () => {
    const groups = activeGroups();
    highlight([], false);
    updateList(groups);
    chart.setOption({
      series: [{ type: 'scatter', cursor: 'pointer', clip: true, data: groups.map((events) => {
        const first = events[0];
        const type = eventTypes[first.type];
        return { value: [position(first), first.side === 0 ? 1 : -1], events, symbol: type[3], symbolSize: events.length > 1 ? 15 : 11, itemStyle: { color: type[2] } };
      }) }],
    });
  };
  const controls = chips([['all', '全部事件'], ['goal', '进球'], ['card', '领牌'], ['sub', '换人']], '事件筛选', (value) => {
    filter = value;
    controls.update(value);
    render();
  });
  controls.group.classList.add('wc-chart-event-filters');
  const reset = element('button', '', '查看全场');
  reset.type = 'button';
  reset.addEventListener('click', () => chart.dispatchAction({ type: 'dataZoom', start: 0, end: 100 }));
  controls.group.append(reset);
  toolbar.append(controls.group);
  const legend = element('div', 'wc-chart-team-key');
  data.names.forEach((name, index) => legend.append(element('span', index === 0 ? 'is-home' : 'is-away', name)));
  toolbar.append(legend);
  chart.setOption({
    ...baseOptions('以两条轨道显示双方带分钟的比赛事件，包含加时和补时标记。'),
    grid: { top: 12, bottom: 65, left: 25, right: 18 },
    xAxis: { ...valueAxis, min: 0, max, minInterval: 1, axisLabel: { color: muted, formatter: (value) => `${value}′` }, axisPointer: { show: true, label: { show: false }, lineStyle: { color: '#d4e3ff70' } } },
    yAxis: { type: 'value', min: -1.7, max: 1.7, axisLabel: { show: false }, axisLine: { show: false }, axisTick: { show: false }, splitLine: { show: false } },
    tooltip: { ...tooltipStyle, trigger: 'item', formatter: ({ data }) => `${data.events[0].label}\n${data.events.map((event) => `${eventTypes[event.type][0]} · ${event.name}`).join('\n')}` },
    dataZoom: [{ type: 'slider', height: 22, bottom: 12, filterMode: 'none', minValueSpan: 8, showDetail: false, showDataShadow: false, borderColor: gridLine, backgroundColor: '#162439', fillerColor: '#75a8ec4d' }],
    series: [{ type: 'scatter', data: [], markLine: { silent: true, symbol: 'none', label: { show: false }, lineStyle: { color: gridLine, type: 'dashed' }, data: [{ yAxis: 0 }, { xAxis: 45 }, { xAxis: 90 }, ...(data.extraTime ? [{ xAxis: 105 }] : [])] } }],
  });
  chart.on('click', 'series.scatter', ({ data }) => highlight(data.events, true));
  chart.on('datazoom', (event) => {
    const { start, end } = event.batch?.[0] ?? event;
    if (!Number.isFinite(start) || !Number.isFinite(end)) return;
    timeRange = [start * max / 100, end * max / 100];
    updateList(activeGroups());
  });
  list.classList.add('wc-chart-event-list');
  render();
}

const charts = { radar, 'player-matches': playerMatches, comparison, 'event-timeline': eventTimeline };

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
