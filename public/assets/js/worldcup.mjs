import { notify } from './common.mjs';
import {
  SUPPORT_STORAGE_KEY, beijingDate, formatKickoff, selectFeaturedMatch,
  filterMatches, normalizeSupportChoices, supportStats, scoreLabel, loadMatchFeed,
} from './worldcup-data.mjs';

const $ = (id) => document.getElementById(id);
const statusLabels = { live: '进行中', scheduled: '即将开赛', finished: '已结束' };
const filterLabels = { live: '进行中的比赛', scheduled: '接下来的比赛', finished: '已结束的比赛', all: '全部比赛' };
const dialog = $('match-dialog');
let matches = [];
let activeMatch;
let supportChoices = {};
let selectedStatus = 'scheduled';
let selectedDate = 'all';

function element(tag, className, text) {
  const node = document.createElement(tag);
  if (className) node.className = className;
  if (text !== undefined) node.textContent = text;
  return node;
}

function flag(team) {
  const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
  svg.classList.add('team-flag');
  svg.setAttribute('viewBox', '0 0 60 40');
  svg.setAttribute('aria-hidden', 'true');
  const use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
  use.setAttribute('href', `../assets/images/flags.svg#${team.code}`);
  svg.append(use);
  return svg;
}

function readSupport() {
  try {
    return normalizeSupportChoices(JSON.parse(localStorage.getItem(SUPPORT_STORAGE_KEY) || '{}'), matches.map((match) => match.id));
  } catch {
    return {};
  }
}

function renderSupport() {
  const choice = supportChoices[activeMatch.id];
  const stats = supportStats(activeMatch, choice);
  $('home-percent').textContent = `${stats.homePercent}%`;
  $('away-percent').textContent = `${stats.awayPercent}%`;
  $('support-home-name').textContent = activeMatch.home_team.name;
  $('support-away-name').textContent = activeMatch.away_team.name;
  $('support-total').textContent = stats.total ? `${stats.total.toLocaleString('zh-CN')} 份示例支持` : '暂无示例支持';
  $('home-support-fill').style.width = `${stats.homePercent}%`;
  $('away-support-fill').style.width = `${stats.awayPercent}%`;
  $('support-bar').setAttribute('aria-label', stats.total
    ? `${activeMatch.home_team.name} ${stats.homePercent}%，${activeMatch.away_team.name} ${stats.awayPercent}%，仅示例统计`
    : '暂无支持数据');
  for (const side of ['home', 'away']) {
    const button = $(`support-${side}`);
    button.setAttribute('aria-pressed', String(choice === side));
    button.disabled = activeMatch.status === 'finished';
    button.textContent = activeMatch.status === 'finished' ? `${activeMatch[`${side}_team`].name} · 已结束`
      : `${choice === side ? '已支持' : '支持'}${activeMatch[`${side}_team`].name}`;
  }
}

function focusMatch(match, { moveFocus = false, animate = true } = {}) {
  activeMatch = match;
  for (const side of ['home', 'away']) {
    const team = match[`${side}_team`];
    $(`${side}-name`).textContent = team.name;
    $(`${side}-code`).textContent = team.code;
    $(`${side}-flag`).setAttribute('href', `../assets/images/flags.svg#${team.code}`);
    $(`${side}-flag`).parentElement.setAttribute('aria-label', `${team.name}国旗`);
  }
  $('focus-title').textContent = `${match.home_team.name} VS ${match.away_team.name}`;
  $('focus-title').tabIndex = -1;
  $('focus-status').textContent = `${statusLabels[match.status]} · 演示`;
  $('focus-status').classList.toggle('is-live', match.status === 'live');
  $('focus-stage').textContent = match.stage;
  $('focus-score').textContent = scoreLabel(match);
  $('focus-clock').textContent = match.status === 'live' ? `${match.minute}′ / ${match.minute > 45 ? '下半场' : '上半场'} · 快照`
    : match.status === 'finished' ? '全场结束 · 示例' : '尚未开赛';
  $('focus-venue').textContent = match.venue;
  $('focus-kickoff').textContent = `${formatKickoff(match.kickoff_at)} 开球 / 北京时间`;
  $('focus-counter').textContent = `${String(matches.indexOf(match) + 1).padStart(2, '0')} / ${String(matches.length).padStart(2, '0')}`;
  renderSupport();
  document.querySelectorAll('.fixture-card').forEach((card) => {
    const selected = card.dataset.match === match.id;
    card.classList.toggle('is-selected', selected);
    card.querySelector('button').setAttribute('aria-pressed', String(selected));
  });
  if (animate && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    $('match-hero').classList.remove('is-changing');
    void $('match-hero').offsetWidth;
    $('match-hero').classList.add('is-changing');
  }
  $('match-status').textContent = `${match.home_team.name}对阵${match.away_team.name}，${statusLabels[match.status]}，${scoreLabel(match)}。演示赛况。`;
  if (moveFocus) {
    $('match-hero').scrollIntoView({ behavior: 'auto', block: 'start' });
    $('focus-title').focus({ preventScroll: true });
  }
}

function renderFixtures() {
  const list = filterMatches(matches, { status: selectedStatus, date: selectedDate });
  const grid = $('fixture-grid');
  grid.replaceChildren();
  $('fixture-empty').hidden = list.length > 0;
  $('fixtures-title').firstChild.textContent = `${filterLabels[selectedStatus]} `;
  $('fixture-count').textContent = `${String(list.length).padStart(2, '0')} 场`;
  $('fixture-result').textContent = `${filterLabels[selectedStatus]}，共 ${list.length} 场。`;
  document.querySelectorAll('[data-status]').forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.status === selectedStatus)));
  document.querySelectorAll('[data-date]').forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.date === selectedDate)));

  for (const match of list) {
    const card = element('article', 'fixture-card');
    card.dataset.match = match.id;
    card.classList.toggle('is-selected', match.id === activeMatch.id);
    const top = element('div', 'fixture-card-top');
    const time = element('time', '', formatKickoff(match.kickoff_at));
    time.dateTime = match.kickoff_at;
    top.append(time, element('span', `status-chip${match.status === 'live' ? ' is-live' : ''}`, `${statusLabels[match.status]} · 示例`));
    const teams = element('div', 'fixture-teams');
    const home = element('div', 'fixture-team');
    const away = element('div', 'fixture-team');
    home.append(flag(match.home_team), element('span', '', match.home_team.name));
    away.append(element('span', '', match.away_team.name), flag(match.away_team));
    teams.append(home, element('strong', '', match.status === 'scheduled' ? 'VS' : scoreLabel(match)), away);
    const bottom = element('div', 'fixture-card-bottom');
    const venue = element('p', '', match.venue);
    venue.append(element('span', '', match.stage));
    const button = element('button', 'glass-button', '聚焦此场 ↗');
    button.type = 'button';
    button.setAttribute('aria-label', `查看${match.home_team.name}对阵${match.away_team.name}`);
    button.setAttribute('aria-pressed', String(match.id === activeMatch.id));
    button.addEventListener('click', () => focusMatch(match, { moveFocus: true }));
    bottom.append(venue, button);
    card.append(top, teams, bottom);
    grid.append(card);
  }
}

function makeDateFilters() {
  const dates = [...new Set(matches.map((match) => beijingDate(match.kickoff_at)))].sort();
  for (const date of ['all', ...dates]) {
    const label = date === 'all' ? '全部日期' : new Intl.DateTimeFormat('zh-CN', { timeZone: 'Asia/Shanghai', month: '2-digit', day: '2-digit', weekday: 'short' }).format(new Date(`${date}T00:00:00+08:00`));
    const button = element('button', 'glass-button', label);
    button.type = 'button';
    button.dataset.date = date;
    button.setAttribute('aria-pressed', String(date === selectedDate));
    button.addEventListener('click', () => { selectedDate = date; renderFixtures(); });
    $('date-filter').append(button);
  }
}

function chooseSupport(side) {
  if (!activeMatch || activeMatch.status === 'finished') return;
  if (supportChoices[activeMatch.id] === side) delete supportChoices[activeMatch.id];
  else supportChoices[activeMatch.id] = side;
  try {
    localStorage.setItem(SUPPORT_STORAGE_KEY, JSON.stringify(supportChoices));
    notify('本地演示选择已保存，未发送到服务器。');
  } catch {
    notify('浏览器无法保存；选择仅在当前页面有效。');
  }
  renderSupport();
}

function openDetails() {
  const match = activeMatch;
  $('match-dialog-title').textContent = `${match.home_team.name} VS ${match.away_team.name}`;
  const facts = $('match-facts');
  facts.replaceChildren();
  for (const [label, value] of [
    ['比赛阶段', match.stage], ['开球时间', `${formatKickoff(match.kickoff_at)} / 北京时间`],
    ['举办城市', match.venue], ['比赛状态', `${statusLabels[match.status]} · 示例`],
    ['比分', scoreLabel(match)], ['数据来源', '合成示例 / 非官方数据'],
  ]) {
    const row = element('div');
    row.append(element('dt', '', label), element('dd', '', value));
    facts.append(row);
  }
  dialog.showModal();
}

async function initialize() {
  try {
    const feed = await loadMatchFeed();
    matches = feed.data.matches;
    activeMatch = selectFeaturedMatch(matches, feed.data.snapshot_at);
    if (!activeMatch) throw new Error('Empty match feed');
    supportChoices = readSupport();
    $('snapshot-time').textContent = `演示快照：${formatKickoff(feed.data.snapshot_at)}`;
    makeDateFilters();
    focusMatch(activeMatch, { animate: false });
    renderFixtures();
    $('previous-match').addEventListener('click', () => focusMatch(matches[(matches.indexOf(activeMatch) - 1 + matches.length) % matches.length]));
    $('next-match').addEventListener('click', () => focusMatch(matches[(matches.indexOf(activeMatch) + 1) % matches.length]));
    $('support-home').addEventListener('click', () => chooseSupport('home'));
    $('support-away').addEventListener('click', () => chooseSupport('away'));
    $('match-details').addEventListener('click', openDetails);
    document.querySelectorAll('[data-status]').forEach((button) => button.addEventListener('click', () => { selectedStatus = button.dataset.status; renderFixtures(); }));
    $('reset-filters').addEventListener('click', () => { selectedStatus = 'all'; selectedDate = 'all'; renderFixtures(); });
  } catch {
    $('focus-status').textContent = '加载失败';
    $('focus-clock').textContent = '无法读取演示赛况，请刷新重试。';
    $('support-total').textContent = '暂无支持数据';
    document.querySelectorAll('.match-hero button, [data-status]').forEach((button) => { button.disabled = true; });
    $('fixture-result').textContent = '赛程加载失败，请刷新页面重试。';
  }
}

$('close-match-dialog').addEventListener('click', () => dialog.close());
dialog.addEventListener('click', (event) => {
  if (event.target !== dialog) return;
  const bounds = dialog.getBoundingClientRect();
  if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) dialog.close();
});
initialize();
