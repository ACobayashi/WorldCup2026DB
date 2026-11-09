import assert from 'node:assert/strict';
import { readFile, access } from 'node:fs/promises';
import { buildSnapshot } from './build-worldcup-snapshot.mjs';
import { createSnapshotModel, calculateGroup, rankThirds, beijingDate, matchScore, displayNumber, metrics, rankings } from '../public/assets/js/worldcup-snapshot-data.mjs';

const root = new URL('../', import.meta.url);
const snapshot = await buildSnapshot();
assert.deepEqual(snapshot, await buildSnapshot(), 'Snapshot builds must be deterministic');
try {
  const exported = JSON.parse(await readFile(new URL('public/assets/data/worldcup-snapshot.json', root)));
  assert.deepEqual(exported, snapshot, 'The served snapshot must match its source files; rebuild it after data changes');
} catch (error) {
  if (error.code !== 'ENOENT') throw error;
}
const model = createSnapshotModel(snapshot);
assert.throws(() => createSnapshotModel({ schema: 0 }));
assert.equal(snapshot.teams.length, 48);
assert.equal(snapshot.players.length, 1248);
assert.equal(snapshot.matches.length, 104);
assert.equal(snapshot.appearances.length, 5392);
assert.equal(snapshot.player_stats.length, 3288);
assert.equal(snapshot.events.length, 2587);
assert.equal(model.filterFixtures({ phase: 'group' }).length, 72);
assert.equal(model.filterFixtures({ phase: 'knockout' }).length, 32);
assert.equal(model.filterFixtures({ q: 'MEX' }).length, model.filterFixtures({ q: '墨西哥' }).length);
assert.equal(model.filterFixtures({ q: 'Mexico' }).length, model.filterFixtures({ q: '墨西哥' }).length);
assert.equal(model.filterFixtures({ q: '<img src=x onerror=alert(1)>' }).length, 0);
assert.equal(model.filterFixtures({ status: 'scheduled' }).length, 0);
assert.equal(model.filterFixtures({ date: '2099-01-01' }).length, 0);
assert.equal(beijingDate('2026-06-28T22:00:00Z'), '2026-06-29');
assert.equal(displayNumber(null), '—');
assert.equal(displayNumber(0), '0');
assert.equal(matchScore({ home_score_90: null }), '— : —');
assert.equal(matchScore({ home_score_90: 1, away_score_90: 1, home_score_et: 2, away_score_et: 1 }), '2 : 1');

for (const team of snapshot.teams) {
  await access(new URL(`public/assets/images/flags/${team.code}.svg`, root));
  assert.equal(snapshot.players.filter((player) => player.team_id === team.id).length, 26);
  const summary = model.teamSummary(team.id);
  assert.ok(summary.played >= 3);
  assert.equal(summary.won + summary.drawn + summary.lost, summary.played);
  assert.equal(summary.goals_for - summary.goals_against, summary.goal_difference);
}
for (const player of snapshot.players) {
  assert.ok(player.name && player.name_en);
  assert.ok(model.teamById.has(player.team_id));
  if (player.photo) assert.ok(['sd.qunliao.info', 'img1.qunliao.info'].includes(new URL(player.photo).hostname));
  const summary = model.summaries.get(player.id);
  assert.equal(summary.totals.assists, player.assists);
  assert.ok(summary.starts <= summary.appearances);
  for (const key of Object.keys(metrics)) assert.ok(summary.coverage[key] <= summary.appearances);
}
assert.ok(snapshot.player_stats.every((row) => row.assists === null && row.minutes_played === null));
assert.ok(snapshot.player_stats.some((row) => row.saves > 0));
assert.ok(snapshot.player_stats.some((row) => row.goals_prevented < 0));
for (const match of snapshot.matches) {
  const formation = snapshot.formations[match.id];
  assert.ok(formation);
  for (const side of ['home', 'away']) {
    assert.equal(formation.sides[side].team_id, match[`${side}_team_id`]);
    assert.equal(formation.sides[side].starters.length, 11);
    for (const player of formation.sides[side].starters) {
      assert.equal(model.playerById.get(player.player_id).team_id, match[`${side}_team_id`]);
      assert.ok(player.x >= 0 && player.x <= 100 && player.y >= 0 && player.y <= 100);
      assert.ok(model.lineups.get(match.id).some((row) => row.player_id === player.player_id && row.starting));
    }
  }
}

const standings = model.standings();
assert.equal(standings.complete, true);
const reference = JSON.parse(await readFile(new URL('data/worldcup/standings-reference.json', root)));
for (const group of standings.groups) {
  const expected = reference.groups.find((row) => row.name === group.name);
  for (const team of group.teams) {
    const row = expected.teams.find((row) => row.team_name.trim() === team.name_zh);
    assert.ok(row);
    for (const [actual, source] of Object.entries({ played: 'matches_total', won: 'matches_won', drawn: 'matches_draw', lost: 'matches_lost', goals_for: 'goals_pro', goals_against: 'goals_against', points: 'points' })) assert.equal(team[actual], Number(row[source]), `${group.name}/${team.code}/${actual}`);
    assert.ok(Number(row.rank) >= team.rank && Number(row.rank) <= team.rank_end);
  }
}
assert.equal(standings.groups.flatMap((group) => group.teams).filter((team) => team.qualified).length, 32);
assert.equal(standings.thirds.filter((team) => team.qualified).length, 8);
const teams = ['a', 'b', 'c', 'd'].map((id) => ({ id, code: id }));
assert.ok(calculateGroup(teams, []).every((team) => team.rank_tied && team.rank === 1 && team.rank_end === 4));
const equalThirds = Array.from({ length: 12 }, (_, i) => ({ id: String(i), code: String(i), points: 1, goal_difference: 0, goals_for: 1 }));
assert.ok(rankThirds(equalThirds, true).every((team) => team.qualified === null));
assert.ok(rankThirds(equalThirds.slice(0, 11), false).every((team) => team.qualified === null));
const byNumber = new Map(snapshot.matches.map((match) => [match.match_no, match]));
assert.equal(Object.values(snapshot.winner_feeds).flat().length, 30);
for (const [target, parents] of Object.entries(snapshot.winner_feeds)) for (const parent of parents) {
  const match = byNumber.get(Number(target));
  assert.ok([match.home_team_id, match.away_team_id].includes(byNumber.get(parent).winner_team_id));
}
for (const metric of Object.keys(rankings)) {
  const leaders = model.leaders(metric);
  for (let i = 1; i < leaders.length; i += 1) {
    assert.ok(leaders[i - 1].value >= leaders[i].value);
    if (leaders[i - 1].value === leaders[i].value) assert.equal(leaders[i - 1].rank, leaders[i].rank);
  }
}
assert.ok(model.leaders('saves', 'GK').length > 0);
assert.ok(model.leaders('saves', 'GK').every((player) => player.position === 'GK'));
for (const [metric, type] of [['yellow_cards', 'yellow_card'], ['red_cards', 'red_card']]) assert.equal(model.leaders(metric).reduce((total, player) => total + player.value, 0), snapshot.events.filter((event) => event.type === type).length);
assert.throws(() => model.leaders('__proto__'));
assert.throws(() => model.leaders('constructor'));
console.log('World Cup snapshot checks passed: deterministic export, 48 squads, 104 formations, source-matched standings, knockout paths, filters, missing values, goalkeeper metrics and tied rankings.');
