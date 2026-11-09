export const positions = { GK: '门将', DF: '后卫', MF: '中场', FW: '前锋' };
export const metrics = {
  goals: '进球', assists: '助攻', shots: '射门', passes_attempted: '传球', passes_completed: '成功传球',
  ball_progressions: '推进', crosses_attempted: '传中', crosses_completed: '成功传中',
  line_breaks_attempted: '尝试破线', line_breaks_completed: '完成破线', take_ons: '过人',
  step_ins: '带球切入', switches_of_play: '转移球', blocks: '封堵', clearances: '解围',
  duels_won_aerial: '赢得空中对抗', duels_won_physical: '赢得身体对抗', interceptions: '拦截',
  possession_regains: '夺回球权', pressing_direct: '直接施压', tackles_attempted: '抢断尝试', tackles_won: '成功抢断',
  saves: '扑救', goals_prevented: '阻止进球', saves_inside_box: '禁区内扑救', penalties_saved: '扑出点球',
  shootout_saves: '点球大战扑救', sweeper_actions: '出击', sweeper_actions_won: '成功出击',
  high_claims: '摘高球', punches: '拳击球', goal_line_clearances: '门线解围',
};
export const eventLabels = { goal: '进球', own_goal: '乌龙球', yellow_card: '黄牌', red_card: '红牌', subbed_on: '替补上场', subbed_off: '被换下' };
export const statusLabels = { scheduled: '未开赛', live: '进行中', finished: '已结束', postponed: '已延期', cancelled: '已取消' };
export const abilityLabels = { pace: '速度', shooting: '射门', passing: '传球', dribbling: '盘带', defending: '防守', physical: '力量' };
export const rankings = { ...metrics, yellow_cards: '已记录黄牌', red_cards: '已记录红牌' };
export const displayNumber = (value) => value == null ? '—' : Number.isInteger(value) ? String(value) : Number(value.toFixed(2)).toString();
export const matchScore = (match) => match.home_score_90 == null ? '— : —' : `${match.home_score_et ?? match.home_score_90} : ${match.away_score_et ?? match.away_score_90}`;
export const beijingDate = (value) => new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Shanghai', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date(value));
export const kickoffLabel = (value) => new Intl.DateTimeFormat('zh-CN', { timeZone: 'Asia/Shanghai', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }).format(new Date(value));
export const searchMatches = (item, query) => [item.name, item.name_zh, item.name_en, item.code].filter(Boolean).join(' ').toLocaleLowerCase().includes(query.trim().toLocaleLowerCase());

function indexRows(rows, key) {
  const map = new Map();
  for (const row of rows) {
    if (!map.has(row[key])) map.set(row[key], []);
    map.get(row[key]).push(row);
  }
  return map;
}

function compareKeys(a, b) {
  for (let i = 0; i < a.length; i += 1) if (a[i] !== b[i]) return b[i] - a[i];
  return 0;
}

function buckets(rows, key) {
  const sorted = [...rows].sort((a, b) => compareKeys(key(a), key(b)) || a.code.localeCompare(b.code));
  const result = [];
  for (const row of sorted) {
    if (!result.length || compareKeys(key(row), key(result.at(-1)[0])) !== 0) result.push([]);
    result.at(-1).push(row);
  }
  return result;
}

function ranked(parts) {
  let rank = 1;
  return parts.flatMap((part) => {
    const rows = part.map((row) => ({ ...row, rank, rank_end: rank + part.length - 1, rank_tied: part.length > 1 }));
    rank += part.length;
    return rows;
  });
}

export function calculateGroup(teams, matches) {
  const rows = new Map(teams.map((team) => [team.id, { ...team, played: 0, won: 0, drawn: 0, lost: 0, goals_for: 0, goals_against: 0, goal_difference: 0, points: 0 }]));
  const games = matches.filter((match) => match.status === 'finished' && match.home_score_90 != null && match.away_score_90 != null && rows.has(match.home_team_id) && rows.has(match.away_team_id));
  for (const game of games) {
    for (const [side, other] of [['home', 'away'], ['away', 'home']]) {
      const row = rows.get(game[`${side}_team_id`]);
      const scored = game[`${side}_score_90`];
      const conceded = game[`${other}_score_90`];
      row.played += 1;
      row.goals_for += scored;
      row.goals_against += conceded;
      row.goal_difference += scored - conceded;
      row[scored > conceded ? 'won' : scored === conceded ? 'drawn' : 'lost'] += 1;
      row.points += scored > conceded ? 3 : scored === conceded ? 1 : 0;
    }
  }
  function headToHead(tied) {
    if (tied.length < 2) return [tied];
    const mini = new Map(tied.map((team) => [team.id, [0, 0, 0]]));
    for (const game of games) {
      if (!mini.has(game.home_team_id) || !mini.has(game.away_team_id)) continue;
      for (const [side, other] of [['home', 'away'], ['away', 'home']]) {
        const score = game[`${side}_score_90`];
        const opponent = game[`${other}_score_90`];
        const row = mini.get(game[`${side}_team_id`]);
        row[0] += score > opponent ? 3 : score === opponent ? 1 : 0;
        row[1] += score - opponent;
        row[2] += score;
      }
    }
    const parts = buckets(tied, (team) => mini.get(team.id));
    return parts.length > 1 ? parts.flatMap(headToHead) : buckets(tied, (team) => [team.goal_difference, team.goals_for]);
  }
  return ranked(buckets([...rows.values()], (team) => [team.points]).flatMap(headToHead));
}

export function rankThirds(rows, complete) {
  const result = ranked(buckets(rows, (team) => [team.points, team.goal_difference, team.goals_for]));
  return result.map((team) => ({ ...team, qualified: !complete || rows.length !== 12 || (team.rank <= 8 && team.rank_end > 8) ? null : team.rank_end <= 8 }));
}

export function createSnapshotModel(snapshot) {
  if (snapshot.schema !== 1 || snapshot.source !== 'repository-snapshot') throw new Error('Unsupported World Cup snapshot');
  const teamById = new Map(snapshot.teams.map((team) => [team.id, team]));
  const playerById = new Map(snapshot.players.map((player) => [player.id, player]));
  const matchById = new Map(snapshot.matches.map((match) => [match.id, match]));
  const stageByCode = new Map(snapshot.stages.map((stage) => [stage.code, stage]));
  const venueById = new Map(snapshot.venues.map((venue) => [venue.id, venue]));
  const appearances = indexRows(snapshot.appearances, 'player_id');
  const lineups = indexRows(snapshot.appearances, 'match_id');
  const events = indexRows(snapshot.events, 'match_id');
  const playerEvents = indexRows(snapshot.events, 'player_id');
  const playerStats = new Map(snapshot.player_stats.map((row) => [`${row.match_id}/${row.player_id}`, row]));
  const teamStats = new Map(snapshot.team_stats.map((row) => [`${row.match_id}/${row.team_id}`, row]));
  const summaries = new Map();
  for (const player of snapshot.players) {
    const records = (appearances.get(player.id) ?? []).filter((row) => row.appeared);
    const totals = Object.fromEntries(Object.keys(metrics).map((key) => [key, null]));
    const coverage = Object.fromEntries(Object.keys(metrics).map((key) => [key, 0]));
    for (const record of records) {
      const values = playerStats.get(`${record.match_id}/${player.id}`) ?? {};
      for (const key of Object.keys(metrics)) if (key !== 'assists' && values[key] != null) {
        totals[key] = (totals[key] ?? 0) + values[key];
        coverage[key] += 1;
      }
    }
    totals.assists = player.assists;
    coverage.assists = player.assists == null ? 0 : records.length;
    const eventCounts = {};
    for (const event of playerEvents.get(player.id) ?? []) eventCounts[event.type] = (eventCounts[event.type] ?? 0) + 1;
    totals.yellow_cards = eventCounts.yellow_card ?? 0;
    totals.red_cards = eventCounts.red_card ?? 0;
    summaries.set(player.id, { appearances: records.length, starts: records.filter((row) => row.starting).length, totals, coverage, event_counts: eventCounts });
  }

  function filterFixtures(input = {}) {
    return snapshot.matches.filter((match) => {
      const home = teamById.get(match.home_team_id);
      const away = teamById.get(match.away_team_id);
      return (!input.q || searchMatches(home, input.q) || searchMatches(away, input.q))
        && (!input.status || input.status === 'all' || match.status === input.status)
        && (!input.phase || input.phase === 'all' || (input.phase === 'group' ? match.stage_code === 'group' : match.stage_code !== 'group'))
        && (!input.date || beijingDate(match.kickoff_at) === input.date)
        && (!input.team_id || [match.home_team_id, match.away_team_id].includes(input.team_id));
    }).sort((a, b) => a.match_no - b.match_no);
  }

  function teamSummary(id) {
    const games = filterFixtures({ team_id: id }).filter((match) => match.status === 'finished');
    const result = { played: games.length, won: 0, drawn: 0, lost: 0, goals_for: 0, goals_against: 0, clean_sheets: 0 };
    const totals = {};
    const possession = [];
    for (const game of games) {
      const home = game.home_team_id === id;
      const scored = game[home ? 'home_score_et' : 'away_score_et'] ?? game[home ? 'home_score_90' : 'away_score_90'];
      const conceded = game[home ? 'away_score_et' : 'home_score_et'] ?? game[home ? 'away_score_90' : 'home_score_90'];
      result[scored > conceded ? 'won' : scored === conceded ? 'drawn' : 'lost'] += 1;
      result.goals_for += scored;
      result.goals_against += conceded;
      result.clean_sheets += Number(conceded === 0);
      const stats = teamStats.get(`${game.id}/${id}`) ?? {};
      for (const key of ['shots', 'shots_on_target', 'passes_attempted', 'passes_completed']) if (stats[key] != null) totals[key] = (totals[key] ?? 0) + stats[key];
      if (stats.possession_pct != null) possession.push(stats.possession_pct);
    }
    return { ...result, ...totals, goal_difference: result.goals_for - result.goals_against, average_possession_pct: possession.length ? possession.reduce((a, b) => a + b) / possession.length : null, pass_completion_pct: totals.passes_attempted ? totals.passes_completed / totals.passes_attempted * 100 : null, ...teamById.get(id) };
  }

  function standings() {
    const groups = snapshot.groups.map((group) => {
      const teams = snapshot.group_teams.filter((member) => member.group_code === group.code).map((member) => teamById.get(member.team_id));
      const games = snapshot.matches.filter((match) => match.group_code === group.code && match.stage_code === 'group');
      return { ...group, teams: calculateGroup(teams, games), complete: teams.length === 4 && games.length === 6 && games.every((game) => game.status === 'finished' && game.home_score_90 != null && game.away_score_90 != null) };
    });
    const complete = groups.length === 12 && groups.every((group) => group.complete);
    const thirds = rankThirds(groups.flatMap((group) => group.teams.filter((team) => team.rank === 3 && !team.rank_tied)), complete);
    const thirdMap = new Map(thirds.map((team) => [team.id, team.qualified]));
    for (const group of groups) for (const team of group.teams) team.qualified = !complete || (team.rank_tied && team.rank <= 2 && team.rank_end > 2) || thirdMap.get(team.id) === null ? null : team.rank_end <= 2 || thirdMap.get(team.id) === true;
    return { groups, thirds, complete };
  }

  function leaders(metric, position = '') {
    if (!Object.hasOwn(rankings, metric)) throw new Error('Unknown ranking metric');
    const rows = snapshot.players.filter((player) => {
      const summary = summaries.get(player.id);
      if (['yellow_cards', 'red_cards'].includes(metric)) return (!position || player.position === position) && summary.totals[metric] > 0;
      return (!position || player.position === position) && summary.appearances > 0 && summary.totals[metric] != null && summary.coverage[metric] === summary.appearances && (metric !== 'assists' || summary.totals.assists > 0);
    }).sort((a, b) => summaries.get(b.id).totals[metric] - summaries.get(a.id).totals[metric] || a.name.localeCompare(b.name));
    let rank = 0;
    let previous;
    return rows.map((player, index) => {
      const value = summaries.get(player.id).totals[metric];
      if (value !== previous) rank = index + 1;
      previous = value;
      return { ...player, value, rank };
    });
  }

  return { snapshot, teamById, playerById, matchById, stageByCode, venueById, appearances, lineups, events, playerEvents, playerStats, teamStats, summaries, filterFixtures, teamSummary, standings, leaders };
}
