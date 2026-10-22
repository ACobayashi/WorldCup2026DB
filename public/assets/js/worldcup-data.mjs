export const SUPPORT_STORAGE_KEY = 'nkdev.worldcup.support.v1';
export const DEMO_SNAPSHOT_AT = '2026-06-28T20:08:00Z';

const team = (code, name) => Object.freeze({ code, name });
const brazil = team('BRA', '巴西');
const france = team('FRA', '法国');
const argentina = team('ARG', '阿根廷');
const japan = team('JPN', '日本');
const germany = team('GER', '德国');
const spain = team('ESP', '西班牙');
const portugal = team('POR', '葡萄牙');
const england = team('ENG', '英格兰');

// Synthetic fixtures: these are UI examples, not the official 2026 schedule.
export const demoMatches = Object.freeze([
  { id: 'demo-01', status: 'live', home_team: brazil, away_team: france, home_score: 2, away_score: 1, minute: 68, kickoff_at: '2026-06-28T19:00:00Z', venue: '纽约 / 新泽西', stage: '淘汰赛 · 示例对阵', home_support: 1482, away_support: 1026 },
  { id: 'demo-02', status: 'scheduled', home_team: argentina, away_team: japan, home_score: null, away_score: null, minute: null, kickoff_at: '2026-06-28T22:00:00Z', venue: '洛杉矶', stage: '淘汰赛 · 示例对阵', home_support: 812, away_support: 588 },
  { id: 'demo-03', status: 'scheduled', home_team: germany, away_team: spain, home_score: null, away_score: null, minute: null, kickoff_at: '2026-06-29T01:00:00Z', venue: '达拉斯', stage: '淘汰赛 · 示例对阵', home_support: 634, away_support: 766 },
  { id: 'demo-04', status: 'scheduled', home_team: portugal, away_team: england, home_score: null, away_score: null, minute: null, kickoff_at: '2026-06-29T19:00:00Z', venue: '迈阿密', stage: '淘汰赛 · 示例对阵', home_support: 692, away_support: 708 },
  { id: 'demo-05', status: 'scheduled', home_team: brazil, away_team: japan, home_score: null, away_score: null, minute: null, kickoff_at: '2026-06-29T22:00:00Z', venue: '西雅图', stage: '下一比赛日 · 示例对阵', home_support: 0, away_support: 0 },
  { id: 'demo-06', status: 'scheduled', home_team: france, away_team: germany, home_score: null, away_score: null, minute: null, kickoff_at: '2026-06-30T01:00:00Z', venue: '休斯敦', stage: '下一比赛日 · 示例对阵', home_support: 486, away_support: 514 },
  { id: 'demo-07', status: 'scheduled', home_team: argentina, away_team: spain, home_score: null, away_score: null, minute: null, kickoff_at: '2026-06-30T19:00:00Z', venue: '温哥华', stage: '下一比赛日 · 示例对阵', home_support: 420, away_support: 580 },
  { id: 'demo-08', status: 'finished', home_team: england, away_team: japan, home_score: 1, away_score: 1, minute: 90, kickoff_at: '2026-06-28T16:00:00Z', venue: '多伦多', stage: '小组赛 · 示例对阵', home_support: 627, away_support: 373 },
].map(Object.freeze));

export function beijingDate(value) {
  return new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Shanghai', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date(value));
}

export function formatKickoff(value, options = {}) {
  return new Intl.DateTimeFormat('zh-CN', { timeZone: 'Asia/Shanghai', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23', ...options }).format(new Date(value));
}

export function selectFeaturedMatch(matches, now = DEMO_SNAPSHOT_AT) {
  const ordered = matches.slice().sort((a, b) => Date.parse(a.kickoff_at) - Date.parse(b.kickoff_at));
  return ordered.find((match) => match.status === 'live')
    || ordered.find((match) => match.status === 'scheduled' && Date.parse(match.kickoff_at) >= Date.parse(now))
    || ordered.filter((match) => match.status === 'finished').at(-1)
    || ordered[0]
    || null;
}

export function filterMatches(matches, { status = 'scheduled', date = 'all' } = {}) {
  return matches.filter((match) => (status === 'all' || match.status === status)
    && (date === 'all' || beijingDate(match.kickoff_at) === date))
    .sort((a, b) => Date.parse(a.kickoff_at) - Date.parse(b.kickoff_at));
}

export function normalizeSupportChoices(value, ids) {
  if (!value || typeof value !== 'object' || Array.isArray(value)) return {};
  return Object.fromEntries(Object.entries(value).filter(([id, side]) => ids.includes(id) && ['home', 'away'].includes(side)));
}

export function supportStats(match, choice) {
  const home = match.home_support + (choice === 'home' ? 1 : 0);
  const away = match.away_support + (choice === 'away' ? 1 : 0);
  const total = home + away;
  const homePercent = total ? Math.round(home / total * 100) : 0;
  return { home, away, total, homePercent, awayPercent: total ? 100 - homePercent : 0 };
}

export function scoreLabel(match) {
  return match.status === 'scheduled' ? '— : —' : `${match.home_score} : ${match.away_score}`;
}

export async function loadMatchFeed() {
  return { code: 'OK', message: '', data: { matches: demoMatches, source: 'demo', snapshot_at: DEMO_SNAPSHOT_AT }, errors: null };
}
