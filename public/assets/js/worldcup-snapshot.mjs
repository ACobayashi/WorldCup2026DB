import {
  createSnapshotModel, positions, metrics, rankings, eventLabels, statusLabels, abilityLabels,
  displayNumber as number, matchScore, kickoffLabel, searchMatches,
} from './worldcup-snapshot-data.mjs';
import { chartMount, mountWorldCupCharts } from './worldcup-charts.mjs?v=2';

const input = new URLSearchParams(location.search);
const content = document.getElementById('snapshot-content');
const titles = { matches: '赛程与赛果', teams: '参赛球队', leaders: '球员榜', compare: '球员对比', team_compare: '球队对比', groups: '积分与晋级', player: '球员数据' };
const view = input.get('view') || 'matches';
let model;

function node(tag, attributes = {}, ...children) {
  const element = document.createElement(tag);
  for (const [key, value] of Object.entries(attributes)) {
    if (value == null || value === false) continue;
    if (key === 'class') element.className = value;
    else if (key.startsWith('on')) element.addEventListener(key.slice(2), value);
    else element.setAttribute(key, value === true ? '' : String(value));
  }
  element.append(...children.flat(Infinity).filter((child) => child != null && child !== false));
  return element;
}

function svgNode(tag, attributes = {}, ...children) {
  const element = document.createElementNS('http://www.w3.org/2000/svg', tag);
  for (const [key, value] of Object.entries(attributes)) element.setAttribute(key, value);
  element.append(...children.flat());
  return element;
}

const text = (value, className = 'wc-muted') => node('p', { class: className }, value);
const url = (target, params = {}) => `./index.html?${new URLSearchParams({ view: target, ...params })}`;
const link = (target, label, params = {}, className = '') => node('a', { href: url(target, params), class: className }, label);
const panel = (title, ...children) => node('section', { class: 'wc-panel' }, node('div', { class: 'wc-section-heading' }, node('h2', {}, title)), children);
const hidden = (name, value) => node('input', { type: 'hidden', name, value });
const submit = (label) => node('button', { class: 'glass-button', type: 'submit' }, label);

function flag(team) {
  return node('img', { class: 'wc-flag', src: `../assets/images/flags/${team.code}.svg`, alt: `${team.name_zh}国旗`, width: 36, height: 27 });
}

function teamLabel(team, className = '') {
  return node('span', { class: `wc-standing-team ${className}` }, flag(team), node('span', {}, team.name_zh));
}

function portrait(player, size = 'medium') {
  const fallback = node('span', { class: 'wc-portrait-fallback' }, player.shirt_number ?? player.name.slice(0, 1));
  const wrapper = node('span', { class: `wc-player-portrait wc-player-portrait--${size} wc-player-portrait--headshot`, 'aria-hidden': 'true' }, fallback);
  try {
    const source = new URL(player.photo);
    if (source.protocol !== 'https:' || !['sd.qunliao.info', 'img1.qunliao.info'].includes(source.hostname)) return wrapper;
    const image = node('img', { src: source.href, alt: '', loading: 'eager', decoding: 'async', referrerpolicy: 'no-referrer', hidden: true,
      onload: () => { image.hidden = false; fallback.hidden = true; },
      onerror: () => { image.hidden = true; fallback.hidden = false; },
    });
    wrapper.append(image);
  } catch { /* Keep the local shirt-number fallback for unavailable portraits. */ }
  return wrapper;
}

function playerLink(player, params = {}) {
  return link('player', node('span', { class: 'wc-ranking-player' }, portrait(player, 'small'), player.name), { id: player.id, ...params });
}

function field(label, name, value = '', type = 'search', placeholder = '') {
  return node('label', {}, label, node('input', { type, name, value, placeholder, maxlength: 80, 'aria-label': label }));
}

function select(label, name, options, value) {
  return node('label', {}, label, node('select', { name, 'aria-label': label }, Object.entries(options).map(([key, title]) => node('option', { value: key, selected: key === value }, title))));
}

function filters(target, ...fields) {
  return node('form', { class: 'wc-filters', action: './index.html', method: 'get' }, hidden('view', target), fields, submit('筛选'), link(target, '重置', {}, 'wc-back'));
}

function table(headers, rows, caption) {
  return node('div', { class: 'wc-table-wrap', tabindex: '0', role: 'region', 'aria-label': caption }, node('table', {},
    node('caption', { class: 'visually-hidden' }, caption),
    node('thead', {}, node('tr', {}, headers.map((header) => node('th', { scope: 'col' }, header)))),
    node('tbody', {}, rows.map((row) => node('tr', {}, row.map((value) => node('td', {}, value == null ? '—' : value))))),
  ));
}

function paginate(items, render, size = 24) {
  const pages = Math.max(1, Math.ceil(items.length / size));
  const page = Math.min(pages, Math.max(1, Number.parseInt(input.get('page'), 10) || 1));
  const params = Object.fromEntries(input);
  delete params.view;
  return [render(items.slice((page - 1) * size, page * size)), node('nav', { class: 'wc-pagination', 'aria-label': '分页' },
    node('span', {}, `共 ${items.length} 条 · 第 ${page} / ${pages} 页`),
    page > 1 && link(view, '上一页', { ...params, page: page - 1 }, 'glass-button'),
    page < pages && link(view, '下一页', { ...params, page: page + 1 }, 'glass-button'),
  )];
}

function empty(message = '没有符合条件的记录，试试其他筛选。') {
  return node('section', { class: 'wc-empty' }, node('h2', {}, '暂无结果'), text(message), link(Object.hasOwn(titles, view) ? view : 'matches', '重置筛选', {}, 'glass-button'));
}

function fixtureCard(match) {
  return node('article', { class: 'wc-card' },
    text(`MATCH ${match.match_no} · ${statusLabels[match.status]}`, 'wc-eyebrow'),
    node('time', { datetime: match.kickoff_at }, kickoffLabel(match.kickoff_at)),
    node('div', { class: 'wc-fixture-teams' }, ['home', 'away'].map((side) => teamLabel(model.teamById.get(match[`${side}_team_id`])))),
    node('strong', { class: 'wc-card-score' }, matchScore(match)),
    match.home_penalty_score != null && text(`点球 ${match.home_penalty_score} : ${match.away_penalty_score}`),
    text(`${model.stageByCode.get(match.stage_code).name} · ${model.venueById.get(match.venue_id)?.name ?? '场馆待确认'}`),
    link('matches', '比赛详情 ↗', { id: match.id }, 'glass-button'),
  );
}

function renderMatches() {
  if (input.has('id')) return renderMatch(requireEntity(model.matchById, input.get('id')));
  const fields = [field('搜索球队', 'q', input.get('q') || '', 'search', '中文名、英文名或球队代码'),
    select('赛事阶段', 'phase', { all: '全部比赛', group: '小组赛', knockout: '淘汰赛' }, input.get('phase') || 'all'),
    select('比赛状态', 'status', { all: '全部状态', ...statusLabels }, input.get('status') || 'all'),
    field('比赛日期 / 北京时间', 'date', input.get('date') || '', 'date')];
  if (input.has('team_id')) fields.push(hidden('team_id', input.get('team_id')));
  const matches = model.filterFixtures(Object.fromEntries(input));
  content.append(filters('matches', fields), ...paginate(matches, (rows) => rows.length ? node('div', { class: 'wc-grid' }, rows.map(fixtureCard)) : empty()));
}

function requireEntity(map, id) {
  const entity = map.get(id);
  if (!entity) throw new Error('这条记录不存在，请从列表重新选择。');
  return entity;
}

function renderMatch(match) {
  const home = model.teamById.get(match.home_team_id);
  const away = model.teamById.get(match.away_team_id);
  const heroTeam = (team) => link('teams', node('div', { class: 'wc-hero-team' }, flag(team), node('h2', {}, team.name_zh), node('span', {}, team.code)), { id: team.id });
  content.append(link('matches', '← 全部赛程', {}, 'wc-back'), node('section', { class: 'wc-match-hero' },
    node('div', { class: 'wc-match-top' }, node('span', {}, `MATCH ${match.match_no} / ${model.stageByCode.get(match.stage_code).name}`), node('span', { class: 'wc-badge' }, statusLabels[match.status])),
    node('div', { class: 'wc-big-score' }, heroTeam(home), node('div', { class: 'wc-score-center' }, node('strong', {}, matchScore(match)),
      match.home_penalty_score != null && node('small', {}, `点球 ${match.home_penalty_score} : ${match.away_penalty_score}`),
      match.home_score_et != null && node('small', {}, `含加时 · 常规 ${match.home_score_90} : ${match.away_score_90}`)), heroTeam(away)),
    node('div', { class: 'wc-match-meta' }, `${kickoffLabel(match.kickoff_at)} / 北京时间`, model.venueById.get(match.venue_id)?.name ?? '场馆待确认'),
  ));
  renderFormation(match, home, away);
  const lineups = panel('首发与替补');
  lineups.append(node('div', { class: 'wc-lineups' }, [home, away].map((team) => {
    const section = node('section', {}, node('h3', { class: 'wc-team-heading' }, teamLabel(team)));
    for (const [starting, label] of [[true, '首发'], [false, '替补']]) {
      const rows = (model.lineups.get(match.id) ?? []).filter((row) => row.starting === starting && model.playerById.get(row.player_id)?.team_id === team.id);
      section.append(node('h4', { class: 'wc-squad-label' }, label, node('span', {}, `${rows.length} 人`)), node('ul', { class: 'wc-player-list' }, rows.map((row) => {
        const player = model.playerById.get(row.player_id);
        return node('li', {}, node('span', { class: 'wc-shirt' }, player.shirt_number), node('div', {}, playerLink(player, { match_id: match.id }), node('small', {}, `${positions[player.position]} · ${row.appeared ? '已出场' : '未出场'}`)));
      })));
    }
    return section;
  })));
  const stats = panel('比赛技术统计');
  const left = model.teamStats.get(`${match.id}/${home.id}`) ?? {};
  const right = model.teamStats.get(`${match.id}/${away.id}`) ?? {};
  stats.append(table([home.name_zh, '统计项', away.name_zh], Object.entries({ goals: '进球', shots: '射门', shots_on_target: '射正', possession_pct: '控球率 %', passes_attempted: '传球', passes_completed: '成功传球', pass_completion_pct: '传球成功率 %' }).map(([key, label]) => [number(left[key]), label, number(right[key])]), '双方技术统计'), text('数据缺失保留为 —，控球率按原始来源展示。'));
  const events = panel('比赛事件');
  events.id = 'timeline';
  const rows = [...(model.events.get(match.id) ?? [])].sort((a, b) => a.minute - b.minute || a.added_minute - b.added_minute);
  if (rows.length) {
    events.append(node('ol', { class: 'wc-timeline', 'aria-label': '比赛事件记录' }, rows.map((event) => {
        const player = model.playerById.get(event.player_id);
        const team = model.teamById.get(player?.team_id);
        return node('li', { class: `wc-event wc-event-${event.type}` }, node('time', {}, event.label), node('span', { class: 'wc-event-icon', 'aria-hidden': 'true' }, event.type.includes('card') ? '▰' : '·'), node('div', {}, node('b', {}, eventLabels[event.type] ?? event.type), player ? playerLink(player, { match_id: match.id }) : '未指定球员', team && node('small', {}, team.name_zh)));
      })), text('保留来源的分钟和补时标记。乌龙球记于实施球员名下，进球计入对手。'));
  } else {
    events.append(text('暂无事件记录。'));
  }
  content.append(node('div', { class: 'wc-detail-layout' }, lineups, node('aside', { class: 'wc-detail-aside' }, stats, events)));
}

function renderFormation(match, home, away) {
  const formation = model.snapshot.formations[match.id];
  if (!formation) return;
  const section = panel('首发阵容图');
  section.classList.add('wc-formation-panel');
  section.append(node('div', { class: 'wc-formation-teams' }, ['home', 'away'].map((side) => link('teams', [teamLabel(side === 'home' ? home : away), node('b', {}, formation.sides[side].formation)], { id: formation.sides[side].team_id }))));
  const pitch = node('div', { class: 'wc-pitch', 'aria-label': `${home.name_zh}对${away.name_zh}首发阵型` });
  const lines = () => [svgNode('rect', { x: 22, y: 20, width: 956, height: 600 }), svgNode('path', { d: 'M500 20V620 M22 188H174V452H22 M978 188H826V452H978' }), svgNode('circle', { cx: 500, cy: 320, r: 85 })];
  pitch.append(svgNode('svg', { class: 'wc-pitch-lines', viewBox: '0 0 1000 640', preserveAspectRatio: 'none', 'aria-hidden': 'true' }, lines()), svgNode('svg', { class: 'wc-pitch-lines wc-pitch-lines--vertical', viewBox: '0 0 640 1000', preserveAspectRatio: 'none', 'aria-hidden': 'true' }, svgNode('g', { transform: 'translate(640 0) rotate(90)' }, lines())));
  for (const side of ['home', 'away']) for (const starter of formation.sides[side].starters) {
    const player = model.playerById.get(starter.player_id);
    if (!player) continue;
    const forward = 4 + 0.46 * starter.y;
    const cross = 5 + 0.9 * starter.x;
    const left = side === 'home' ? forward : 100 - forward;
    const top = side === 'home' ? cross : 100 - cross;
    const paint = `url('${new URL(`../assets/images/flags/${model.teamById.get(player.team_id).code}.svg`, location.href).href}')`;
    const item = link('player', [node('span', { class: 'wc-pitch-shirt' }, node('b', {}, starter.shirt_number), starter.captain && node('i', { class: 'wc-pitch-captain', title: '队长' }, 'C')), node('span', { class: 'wc-pitch-player-name' }, player.name)], { id: player.id, match_id: match.id }, `wc-pitch-player${player.position === 'GK' ? ' wc-pitch-goalkeeper' : ''}`);
    const markers = (model.events.get(match.id) ?? []).filter((event) => event.player_id === player.id && event.type !== 'subbed_on');
    if (markers.length) item.append(node('span', { class: 'wc-pitch-events' }, markers.map((event) => node('span', { class: `wc-pitch-event wc-pitch-event--${event.type}`, title: `${event.label} ${eventLabels[event.type]}` }, `${{ goal: 'G', own_goal: 'OG', yellow_card: 'YC', red_card: 'RC', subbed_off: '↙' }[event.type]} ${event.label}`))));
    item.setAttribute('style', `--pitch-x:${left}%;--pitch-y:${top}%;--pitch-mobile-x:${top}%;--pitch-mobile-y:${left}%;--nation-paint:${paint}`);
    item.setAttribute('aria-label', `${starter.shirt_number}号 ${player.name}，查看单场表现`);
    pitch.append(item);
  }
  section.append(pitch, text('点击球员查看该场表现。C 队长 / G 进球 / OG 乌龙 / YC 黄牌 / RC 红牌 / ↙ 换下。圆标为国旗配色，非当场球衣；站位为首发示意，不是实时位置。'));
  content.append(section);
}

function renderTeams() {
  if (input.has('id')) return renderTeam(requireEntity(model.teamById, input.get('id')));
  const teams = model.snapshot.teams.filter((team) => searchMatches(team, input.get('q') || ''));
  content.append(filters('teams', field('搜索国家队', 'q', input.get('q') || '', 'search', '中文名、英文名或球队代码')),
    ...paginate(teams, (rows) => rows.length ? node('div', { class: 'wc-grid wc-national-grid' }, rows.map((team) => {
      const group = model.snapshot.group_teams.find((member) => member.team_id === team.id)?.group_code;
      return link('teams', [node('div', { class: 'wc-national-card-top' }, flag(team), node('span', {}, `${group}组`)), node('h2', {}, team.name_zh), text(team.name), node('div', { class: 'wc-national-card-bottom' }, node('span', {}, team.code), node('span', {}, '球队名单与赛程 ↗'))], { id: team.id }, 'wc-card wc-national-card');
    })) : empty()));
}

function summaryTiles(values) {
  return node('div', { class: 'wc-team-summary' }, values.map(([label, value]) => node('div', {}, node('strong', {}, number(value)), node('span', {}, label))));
}

function renderTeam(team) {
  const summary = model.teamSummary(team.id);
  const players = model.snapshot.players.filter((player) => player.team_id === team.id).sort((a, b) => a.shirt_number - b.shirt_number);
  content.append(link('teams', '← 参赛球队', {}, 'wc-back'), node('section', { class: 'wc-team-hero' }, node('div', { class: 'wc-team-identity' }, flag(team), node('div', {}, text(`${team.code} / NATIONAL TEAM`, 'wc-eyebrow'), node('h2', {}, team.name_zh), text(team.name))), link('matches', '全部球队赛程 ↗', { team_id: team.id }, 'glass-button')),
    summaryTiles([['名单球员', players.length], ['已赛场次', summary.played], ['进球', summary.goals_for], ['失球', summary.goals_against]]));
  const squad = panel('本届球队名单');
  for (const [position, label] of Object.entries(positions)) {
    const group = players.filter((player) => player.position === position);
    squad.append(node('div', { class: 'wc-position-heading' }, node('h3', {}, label), node('span', {}, `${group.length} 人`)), node('div', { class: 'wc-roster-grid' }, group.map((player) => {
      const summary = model.summaries.get(player.id);
      return link('player', [node('div', { class: 'wc-roster-name' }, portrait(player), node('h4', {}, player.name, node('small', { class: 'wc-roster-shirt' }, `#${player.shirt_number}`))), node('div', { class: 'wc-roster-numbers' }, [['出场', summary.appearances], ['进球', summary.totals.goals], ['助攻', summary.totals.assists], ['射门', summary.totals.shots]].map(([label, value]) => node('span', {}, node('b', {}, number(value)), label))), node('span', { class: 'wc-player-card-link' }, '个人数据与雷达 ↗')], { id: player.id }, 'wc-roster-card');
    })));
  }
  const games = panel('比赛记录');
  games.append(node('div', { class: 'wc-team-fixtures' }, model.filterFixtures({ team_id: team.id }).map((match) => {
    const opponent = model.teamById.get(match.home_team_id === team.id ? match.away_team_id : match.home_team_id);
    return link('matches', [node('div', {}, model.stageByCode.get(match.stage_code).name, node('time', { datetime: match.kickoff_at }, kickoffLabel(match.kickoff_at))), node('div', {}, teamLabel(opponent), node('strong', {}, matchScore(match))), node('small', {}, `${match.home_team_id === team.id ? '本队主场' : '本队客场'} · 比分始终主队在前 ↗`)], { id: match.id }, 'wc-team-fixture');
  })));
  content.append(node('div', { class: 'wc-team-layout' }, squad, games));
}

function radar(players) {
  const axes = Object.keys(abilityLabels);
  const point = (index, radius) => [180 + Math.sin(index * Math.PI / 3) * radius, 175 - Math.cos(index * Math.PI / 3) * radius];
  const points = (values) => axes.map((key, index) => point(index, values(key) * 1.05).join(',')).join(' ');
  const svg = svgNode('svg', { class: 'wc-snapshot-radar', 'data-wc-chart-fallback': '', viewBox: '0 0 360 350', role: 'img', 'aria-label': players.map((player) => `${player.name}综合能力六维图`).join('、') });
  for (const radius of [25, 50, 75, 100]) svg.append(svgNode('polygon', { points: points(() => radius), fill: 'none', stroke: '#a5b6dd33' }));
  for (const [index, label] of Object.values(abilityLabels).entries()) {
    const [x, y] = point(index, 140);
    const [endX, endY] = point(index, 105);
    svg.append(svgNode('path', { d: `M180 175L${endX} ${endY}`, stroke: '#a5b6dd33' }), svgNode('text', { x, y, fill: '#bfcee7', 'text-anchor': 'middle', 'dominant-baseline': 'middle', 'font-size': '13' }, document.createTextNode(label)));
  }
  players.forEach((player, index) => {
    if (!player.ability || axes.some((key) => !Number.isFinite(player.ability[key]))) return;
    const color = index === 0 ? '#80b9ff' : '#ffbd85';
    svg.append(svgNode('polygon', { points: points((key) => player.ability[key]), fill: `${color}30`, stroke: color, 'stroke-width': '2' }));
  });
  const active = players.some((player) => axes.every((key) => Number.isFinite(player.ability?.[key])));
  const chart = active && chartMount('radar', {
    axes: Object.entries(abilityLabels),
    players: players.map((player, side) => ({ name: player.name, side, values: Object.fromEntries(axes.map((key) => [key, player.ability?.[key]])) })),
  });
  const unavailable = players.filter((player) => !player.ability).map((player) => player.name);
  return node('div', {}, chart, active ? svg : text('暂无懂球帝能力数据。'),
    active && unavailable.length > 0 && text(`${unavailable.join('、')}暂无能力数据。`),
    node('details', { open: true, 'data-wc-raw-table': true }, node('summary', {}, '查看六维原始评分'),
      table(['能力项', ...players.map((player) => player.name)], Object.entries(abilityLabels).map(([key, label]) => [label, ...players.map((player) => number(player.ability?.[key]))]), '综合能力原始值')));
}

function metricGrid(values, coverage, appearances) {
  return node('div', { class: 'wc-snapshot-metrics' }, Object.entries(metrics).map(([key, label]) => node('div', {}, node('span', {}, label), node('strong', {}, number(values[key])), coverage && node('small', {}, key === 'assists' ? '赛事助攻榜汇总' : `覆盖 ${coverage[key]} / ${appearances} 场`))));
}

function renderPlayer() {
  const player = requireEntity(model.playerById, input.get('id'));
  const team = model.teamById.get(player.team_id);
  const summary = model.summaries.get(player.id);
  content.append(link('teams', `← ${team.name_zh}完整名单`, { id: team.id }, 'wc-back'), node('section', { class: 'wc-player-hero' }, node('div', { class: 'wc-player-identity' }, portrait(player, 'large'), node('div', {}, text(`#${player.shirt_number} / ${positions[player.position]}`, 'wc-eyebrow'), node('h2', {}, player.name), text(player.name_en), teamLabel(team))), link('compare', '球员对比 ↗', { left: player.id }, 'glass-button')),
    summaryTiles([['出场', summary.appearances], ['首发', summary.starts], ['进球', summary.totals.goals], ['助攻', summary.totals.assists]]),
    node('div', { class: 'wc-player-board' }, panel('综合能力六维图', radar([player])), panel('赛事技术统计', metricGrid(summary.totals, summary.coverage, summary.appearances), text('— 表示缺少来源值；部分覆盖的总和不冒充完整统计。进球不含乌龙球与点球大战。'))));
  const games = model.filterFixtures({ team_id: team.id });
  const requested = input.get('match_id');
  const match = requested ? games.find((match) => match.id === requested) : games[0];
  if (!match) throw new Error('这场比赛不属于该球员所在球队。');
  const single = panel('单场表现');
  single.id = 'single-performance';
  const appearances = new Map((model.appearances.get(player.id) ?? []).map((row) => [row.match_id, row]));
  const chartGames = games.map((game) => {
    const appearance = appearances.get(game.id);
    const opponent = model.teamById.get(game.home_team_id === team.id ? game.away_team_id : game.home_team_id);
    return { id: game.id, opponent: opponent.name_zh, stage: model.stageByCode.get(game.stage_code).name,
      appearance: appearance ? appearance.appeared ? appearance.starting ? '首发出场' : '替补出场' : '未上场' : '名单未记录',
      metrics: appearance?.appeared ? model.playerStats.get(`${game.id}/${player.id}`) ?? {} : {},
    };
  });
  const metricKeys = player.position === 'GK'
    ? ['saves', 'goals_prevented', 'saves_inside_box', 'sweeper_actions_won', 'passes_completed']
    : ['shots', 'goals', 'passes_completed', 'interceptions', 'tackles_won', 'ball_progressions'];
  const matchBody = (game, row) => {
    const events = (model.events.get(game.id) ?? []).filter((event) => event.player_id === player.id);
    return [link('matches', `MATCH ${game.match_no} · ${matchScore(game)} · 比赛详情 ↗`, { id: game.id }, 'wc-back'),
      text(`${row.opponent} · ${row.stage} · ${row.appearance}`), metricGrid(row.metrics),
      text('单场助攻和出场分钟暂无数据。'),
      text(events.length ? events.map((event) => `${event.label} ${eventLabels[event.type]}`).join(' / ') : '本场没有该球员的已记录事件。')];
  };
  single.append(chartMount('player-matches', { games: chartGames, metrics: metricKeys.map((key) => [key, metrics[key], ['goals', 'goals_prevented'].includes(key) ? ' 球' : ' 次']) }),
    node('form', { class: 'wc-filters', method: 'get', action: './index.html#single-performance' }, hidden('view', 'player'), hidden('id', player.id), select('选择比赛', 'match_id', Object.fromEntries(games.map((game) => [game.id, `M${game.match_no} ${kickoffLabel(game.kickoff_at)} ${model.teamById.get(game.home_team_id).name_zh} ${matchScore(game)} ${model.teamById.get(game.away_team_id).name_zh}`])), match.id), submit('查看单场')));
  for (const [index, game] of games.entries()) {
    if (game.id === match.id) {
      single.append(node('div', { 'data-wc-match-body': true }, matchBody(game, chartGames[index])));
    } else {
      const template = node('template', { 'data-wc-match-id': game.id });
      template.content.append(...matchBody(game, chartGames[index]));
      single.append(template);
    }
  }
  content.append(single, panel('逐场记录', table(['场次', '对阵', '出场', '进球', '射门'], games.map((game) => {
    const row = (model.appearances.get(player.id) ?? []).find((row) => row.match_id === game.id);
    const stats = row?.appeared ? model.playerStats.get(`${game.id}/${player.id}`) : null;
    return [link('player', `M${game.match_no}`, { id: player.id, match_id: game.id }), `${model.teamById.get(game.home_team_id).name_zh} ${matchScore(game)} ${model.teamById.get(game.away_team_id).name_zh}`, row?.appeared ? row.starting ? '首发' : '替补' : '未出场', number(stats?.goals), number(stats?.shots)];
  }), '球员逐场记录')));
  content.append(panel('球员赛事事件', node('div', { class: 'wc-player-event-summary' }, Object.entries(eventLabels).map(([type, label]) => node('span', {}, label, node('b', {}, summary.event_counts[type] ?? 0)))), text('仅统计来源已记录的事件；不能据此推断完整公平竞赛分。'), node('ul', { class: 'wc-player-events' }, (model.playerEvents.get(player.id) ?? []).map((event) => node('li', {}, link('matches', `M${model.matchById.get(event.match_id).match_no}`, { id: event.match_id }), node('time', {}, event.label), node('span', {}, eventLabels[event.type]))))));
}

function renderLeaders() {
  const metric = input.get('metric') || 'goals';
  const position = input.get('position') || '';
  if (!Object.hasOwn(rankings, metric) || (position && !Object.hasOwn(positions, position))) throw new Error('球员榜筛选条件不正确。');
  content.append(filters('leaders', select('统计项', 'metric', rankings, metric), select('位置', 'position', { '': '全部位置', ...positions }, position)), text('技术指标只对已出场且覆盖完整的球员排名，同值并列。助攻使用赛事助攻榜；红黄牌榜仅统计已记录事件（含替补席），不代表完整公平竞赛分。'),
    ...paginate(model.leaders(metric, position), (rows) => rows.length ? panel(`${rankings[metric]}榜`, table(['名次', '球员', '国家队', '位置', rankings[metric], '出场'], rows.map((player) => [player.rank, playerLink(player), teamLabel(model.teamById.get(player.team_id)), positions[player.position], number(player.value), model.summaries.get(player.id).appearances]), `${rankings[metric]}榜`)) : empty(), 30));
}

function searchableSelect(label, name, rows, current, playerMode) {
  const options = () => rows.map((row) => node('option', { value: row.id, selected: row.id === current }, playerMode ? `${row.name} / ${model.teamById.get(row.team_id).name_zh} / #${row.shirt_number}` : `${row.name_zh} / ${row.code}`));
  const dropdown = node('select', { name, 'aria-label': label }, options());
  const search = node('input', { type: 'search', placeholder: playerMode ? '搜索球员中文名、英文名或国家队' : '搜索中文名、英文名或代码', 'aria-label': `${label}搜索`, maxlength: 80 });
  search.addEventListener('input', () => {
    const selected = dropdown.value;
    const query = search.value.trim().toLocaleLowerCase();
    for (const option of dropdown.options) {
      const row = rows.find((row) => row.id === option.value);
      option.hidden = query !== '' && !(searchMatches(row, query) || (playerMode && searchMatches(model.teamById.get(row.team_id), query)));
    }
    const first = [...dropdown.options].find((option) => !option.hidden);
    if (!first) {
      search.setCustomValidity('没有匹配的记录，请修改搜索词。');
    } else {
      search.setCustomValidity('');
      if (dropdown.selectedOptions[0]?.hidden) dropdown.value = first.value;
      else dropdown.value = selected;
    }
  });
  return node('label', {}, label, search, dropdown);
}

function comparisonCharts(left, right, fields, games, perMatch = []) {
  const charts = node('div', { class: 'wc-snapshot-comparison', 'data-wc-chart-fallback': true });
  for (const [key, label] of Object.entries(fields)) {
    const a = left[key];
    const b = right[key];
    if (a < 0 || b < 0) continue;
    const maximum = Math.max(a ?? 0, b ?? 0, 1);
    charts.append(node('div', { class: 'wc-snapshot-bar-row' }, node('span', {}, label), node('div', {}, [a, b].map((value, index) => {
      const bar = node('span', { class: index === 0 ? 'wc-snapshot-bar wc-snapshot-bar--left' : 'wc-snapshot-bar wc-snapshot-bar--right' });
      bar.style.width = `${Math.max(0, value ?? 0) / maximum * 100}%`;
      return node('div', { class: 'wc-snapshot-bar-track' }, bar, node('b', {}, number(value)));
    }))));
  }
  const chartMetrics = Object.entries(fields).filter(([key]) => key !== 'market_value').map(([key, label]) => ({
    key, label: label.replace(/ %$/, ''), values: [left[key], right[key]], unit: key.endsWith('_pct') ? '%' : '',
    perMatch: perMatch.includes(key),
    coverage: left.coverage && right.coverage && Object.hasOwn(left.coverage, key) ? [left.coverage[key], right.coverage[key]] : null,
  }));
  return [chartMount('comparison', { names: [left.label, right.label], games, metrics: chartMetrics }), charts,
    node('details', { open: true, 'data-wc-raw-table': true }, node('summary', {}, '查看赛事原始数据'), table(['指标', left.label, right.label], Object.entries(fields).map(([key, label]) => [label, number(left[key]), number(right[key])]), '对比原始统计'))];
}

function renderCompare(teamMode = false) {
  const rows = teamMode ? model.snapshot.teams : model.snapshot.players;
  const target = teamMode ? 'team_compare' : 'compare';
  const defaultLeft = rows.find((row) => teamMode ? row.code === 'ARG' : row.id === 'fifa:player:229397') ?? rows[0];
  const left = requireEntity(teamMode ? model.teamById : model.playerById, input.get('left') || defaultLeft.id);
  const defaultRight = rows.find((row) => (teamMode ? row.code === 'FRA' : row.id === 'fifa:player:201200') && row.id !== left.id) ?? rows.find((row) => row.id !== left.id);
  const right = requireEntity(teamMode ? model.teamById : model.playerById, input.get('right') || defaultRight.id);
  content.append(node('form', { class: 'wc-filters wc-snapshot-compare-form', action: './index.html', method: 'get' }, hidden('view', target), searchableSelect('左侧 / 蓝色', 'left', rows, left.id, !teamMode), searchableSelect('右侧 / 橙色', 'right', rows, right.id, !teamMode), submit('开始对比')));
  if (left.id === right.id) { content.append(empty('请选择两个不同的对象进行对比。')); return; }
  const hero = (item) => teamMode ? link('teams', node('div', { class: 'wc-team-identity' }, flag(item), node('h2', {}, item.name_zh)), { id: item.id }, 'wc-card') : link('player', node('div', { class: 'wc-player-identity' }, portrait(item, 'large'), node('div', {}, node('h2', {}, item.name), text(model.teamById.get(item.team_id).name_zh))), { id: item.id }, 'wc-card');
  content.append(node('div', { class: 'wc-snapshot-compare-heroes' }, hero(left), hero(right)));
  if (teamMode) {
    const fields = { played: '已赛', won: '胜', drawn: '平', lost: '负', goals_for: '进球', goals_against: '失球', goal_difference: '净胜球', expected_goals: 'xG', expected_goals_against: 'xGA', shots: '射门', shots_on_target: '射正', average_possession_pct: '平均控球率 %', passes_completed: '成功传球', pass_completion_pct: '传球成功率 %', clean_sheets: '零封', market_value: '最高首发身价 / 欧元' };
    const a = model.teamSummary(left.id);
    const b = model.teamSummary(right.id);
    content.append(panel('球队赛事对比', text(`蓝色 ${left.name_zh} · ${a.played} 场　/　橙色 ${right.name_zh} · ${b.played} 场`), comparisonCharts({ ...a, label: left.name_zh }, { ...b, label: right.name_zh }, fields, [a.played, b.played], ['goals_for', 'goals_against', 'shots', 'shots_on_target', 'passes_completed', 'clean_sheets']), text('胜平负按含加时的比分统计。xG/xGA 汇总自逐场报告。')));
  } else {
    const fields = { goals: '进球', assists: '助攻', shots: '射门', passes_completed: '成功传球', interceptions: '拦截', tackles_won: '成功抢断', clearances: '解围' };
    if (left.position === 'GK' || right.position === 'GK') Object.assign(fields, { saves: '扑救', goals_prevented: '阻止进球', saves_inside_box: '禁区内扑救' });
    const a = model.summaries.get(left.id);
    const b = model.summaries.get(right.id);
    content.append(node('div', { class: 'wc-player-board' }, panel('双球员综合能力', radar([left, right])), panel('赛事累计统计', comparisonCharts({ ...a.totals, coverage: a.coverage, label: left.name }, { ...b.totals, coverage: b.coverage, label: right.name }, fields, [a.appearances, b.appearances]), text('部分覆盖的统计仅汇总已有记录。'))));
  }
}

function standingsTable(rows, caption) {
  return table(['名次', '球队', '赛', '胜', '平', '负', '进', '失', '净', '分', '晋级'], rows.map((team) => [team.rank_tied ? `${team.rank}–${team.rank_end}` : team.rank, link('teams', teamLabel(team), { id: team.id }), team.played, team.won, team.drawn, team.lost, team.goals_for, team.goals_against, team.goal_difference, team.points, team.qualified ? '已晋级' : team.qualified === null || team.rank_tied ? '待确认' : '—']), caption);
}

function renderGroups() {
  const panelName = input.get('panel') || 'standings';
  if (!['standings', 'thirds', 'bracket'].includes(panelName)) throw new Error('积分页面分类不正确。');
  content.append(node('nav', { class: 'wc-data-tabs', 'aria-label': '积分与晋级分类' }, Object.entries({ standings: '小组积分', thirds: '第三名比较', bracket: '淘汰赛图' }).map(([key, label]) => {
    const item = link('groups', label, { panel: key });
    if (key === panelName) item.setAttribute('aria-current', 'page');
    return item;
  })));
  if (panelName === 'bracket') return renderBracket();
  const standings = model.standings();
  content.append(text('同分先比较相互战绩，必要时对剩余同分队重新比较，再看总净胜球和总进球。公平竞赛与 FIFA 排名资料不足时保留并列；不以代码排序冒充体育排名。'));
  if (panelName === 'thirds') content.append(panel('最佳第三名横向比较', standingsTable(standings.thirds, '十二组第三名横向比较'), text('前八名晋级；横向比较不使用相互战绩。并列跨越第八名边界时保留待确认。')));
  else content.append(node('div', { class: 'wc-standings-grid' }, standings.groups.map((group) => panel(`${group.name}${group.complete ? '' : ' · 暂定'}`, standingsTable(group.teams, `${group.name}积分榜`)))));
}

function renderBracket() {
  const matches = new Map(model.snapshot.matches.map((match) => [match.match_no, match]));
  const nodes = new Map();
  const edges = [];
  let leaf = 0;
  function walk(no) {
    const parents = model.snapshot.winner_feeds[no];
    let point;
    if (!parents) point = { column: 0, y: 80 + leaf++ * 108 };
    else {
      const a = walk(parents[0]);
      const b = walk(parents[1]);
      point = { column: a.column + 1, y: (a.y + b.y) / 2 };
      edges.push(...parents.map((from) => ({ from, to: no })));
    }
    nodes.set(no, point);
    return point;
  }
  walk(104);
  const warnings = edges.some(({ from, to }) => {
    const source = matches.get(from);
    const target = matches.get(to);
    return source?.winner_team_id && ![target?.home_team_id, target?.away_team_id].includes(source.winner_team_id);
  });
  const canvas = node('div', { class: 'wc-bracket-canvas', style: 'height:1790px' });
  ['三十二强', '十六强', '四分之一决赛', '半决赛', '决赛'].forEach((label, column) => canvas.append(node('h3', { class: 'wc-bracket-round', style: `left:${column * 20}%` }, label)));
  if (!warnings) canvas.append(svgNode('svg', { class: 'wc-bracket-lines', viewBox: '0 0 1000 1790', preserveAspectRatio: 'none', 'aria-hidden': 'true' }, edges.map(({ from, to }) => {
    const a = nodes.get(from);
    const b = nodes.get(to);
    return svgNode('path', { d: `M${a.column * 200 + 168} ${a.y}H${a.column * 200 + 184}V${b.y}H${b.column * 200}` });
  })));
  function bracketMatch(match) {
    if (!match) return text('比赛资料缺失');
    return link('matches', [node('small', {}, `M${match.match_no}`, node('span', {}, match.home_penalty_score != null ? `点球 ${match.home_penalty_score}:${match.away_penalty_score}` : kickoffLabel(match.kickoff_at))), ['home', 'away'].map((side) => {
      const team = model.teamById.get(match[`${side}_team_id`]);
      return node('span', { class: `wc-bracket-team ${team.id === match.winner_team_id ? 'is-winner' : 'is-loser'}` }, flag(team), node('span', {}, team.name_zh), node('b', {}, number(match[`${side}_score_et`] ?? match[`${side}_score_90`])));
    })], { id: match.id }, 'wc-bracket-match');
  }
  for (const [no, point] of nodes) canvas.append(node('div', { class: `wc-bracket-node${no === 104 ? ' is-final' : ''}`, style: `left:${point.column * 20}%;top:${point.y - 43}px` }, bracketMatch(matches.get(no))));
  content.append(panel('淘汰赛晋级图', text('32 强 → 决赛。绿色为该场胜者，点球比分单列。点击比赛查看详情；窄屏可横向滚动，滚动区域支持键盘。'), warnings && text('晋级资料存在冲突，连接线暂不显示。', 'wc-notice'), node('div', { class: 'wc-bracket-scroll', tabindex: '0', role: 'region', 'aria-label': '可横向滚动的世界杯淘汰赛晋级图' }, canvas), node('div', { class: 'wc-bronze' }, node('h3', {}, '三四名决赛'), bracketMatch(matches.get(103)))));
}

async function initialize() {
  try {
    const response = await fetch('../assets/data/worldcup-snapshot.json');
    if (!response.ok) throw new Error('快照文件暂时不可用，请稍后刷新。');
    model = createSnapshotModel(await response.json());
    content.replaceChildren();
    if (!Object.hasOwn(titles, view)) throw new Error('页面不存在，请从世界杯导航重新选择。');
    document.title = `${titles[view]} · FOURWARD 2026`;
    document.getElementById('snapshot-title').textContent = titles[view];
    document.querySelectorAll('[data-view]').forEach((item) => {
      if (item.dataset.view === (view === 'player' ? 'teams' : view)) item.setAttribute('aria-current', 'page');
    });
    document.getElementById('snapshot-summary').replaceChildren(...[
      ['支球队', model.snapshot.teams.length], ['名球员', model.snapshot.players.length], ['场比赛', model.snapshot.matches.length], ['个小组', model.snapshot.groups.length],
    ].map(([label, count]) => node('div', {}, node('strong', {}, count), node('span', {}, label))));
    ({ matches: renderMatches, teams: renderTeams, player: renderPlayer, leaders: renderLeaders, compare: renderCompare, team_compare: () => renderCompare(true), groups: renderGroups })[view]();
    await mountWorldCupCharts(content);
    document.getElementById('snapshot-status').textContent = `${titles[view]}已加载，只读快照。`;
    if (['#single-performance', '#timeline'].includes(location.hash)) document.getElementById(location.hash.slice(1))?.scrollIntoView();
  } catch (error) {
    content.replaceChildren(node('section', { class: 'wc-empty' }, node('h2', {}, '无法显示赛事页面'), text(error.message), link('matches', '返回赛程列表', {}, 'glass-button')));
    document.getElementById('snapshot-status').textContent = '赛事页面加载失败。';
  } finally {
    content.setAttribute('aria-busy', 'false');
  }
}

initialize();
