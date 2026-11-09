import { readFile, mkdir, writeFile } from 'node:fs/promises';
import { createHash } from 'node:crypto';
import { pathToFileURL } from 'node:url';

const root = new URL('../', import.meta.url);
const pick = (row, keys) => Object.fromEntries(keys.filter((key) => key in row).map((key) => [key, row[key]]));

export async function buildSnapshot() {
  const hashes = {};
  async function source(name) {
    const bytes = await readFile(new URL(`data/worldcup/${name}.json`, root));
    hashes[name] = createHash('sha256').update(bytes).digest('hex');
    return JSON.parse(bytes);
  }
  const core = await source('full/complete');
  const names = (await source('player-names')).players;
  const fullNames = (await source('player-full-names')).players;
  const abilities = (await source('player-abilities')).players;
  const assists = await source('player-assists');
  const photos = (await source('player-photos/catalog')).players;
  const advanced = await source('player-metrics/metrics');
  const goalkeepers = await source('goalkeeper-metrics');
  const formations = await source('formations');
  const paths = await source('knockout-paths');
  const values = (await source('team-market-values')).teams;
  const rawTeamStats = await source('full/raw/report-team_stats');
  const extraStats = new Map();
  for (const row of [...advanced.rows, ...goalkeepers.rows]) {
    const key = `${row.match_id}/${row.player_id}`;
    extraStats.set(key, { ...extraStats.get(key), ...row.metrics });
  }
  const xgGames = new Map();
  for (const row of rawTeamStats.filter((row) => row.metric === 'xG (Expected Goals)')) {
    const code = core.manifest.corrections.rules.match_team_corrections.find((rule) => rule.source_match_id === row.match_id && rule.source_team === row.team_id)?.canonical_team ?? row.team_id;
    if (!xgGames.has(row.match_id)) xgGames.set(row.match_id, {});
    xgGames.get(row.match_id)[code] = Number(row.value);
  }
  const xg = {};
  for (const game of xgGames.values()) {
    for (const [code, value] of Object.entries(game)) {
      xg[code] ??= { for: 0, against: 0 };
      xg[code].for += value;
      xg[code].against += Object.entries(game).filter(([other]) => other !== code).reduce((sum, [, amount]) => sum + amount, 0);
    }
  }
  return {
    schema: 1,
    edition: 2026,
    source: 'repository-snapshot',
    hashes,
    teams: core.teams.map((team) => ({ ...team, expected_goals: xg[team.code]?.for ?? null, expected_goals_against: xg[team.code]?.against ?? null, market_value: values[team.code]?.highest_starting_xi_value_eur ?? null })),
    players: core.players.map((player) => {
      const id = player.id.split(':').at(-1);
      return {
        ...pick(player, ['id', 'team_id', 'position', 'shirt_number']),
        name: fullNames[id]?.full_name_zh ?? names[id]?.name_zh ?? player.name,
        name_en: names[id]?.name_en ?? player.name,
        assists: assists.players[id]?.assists ?? (assists.roster_total === 1248 ? 0 : null),
        ability: abilities[id] ? pick(abilities[id], ['overall', 'pace', 'shooting', 'passing', 'dribbling', 'defending', 'physical']) : null,
        photo: photos[id]?.download_url ?? null,
      };
    }),
    matches: core.matches,
    stages: core.stages,
    groups: core.groups,
    group_teams: core.group_teams,
    venues: core.venues,
    appearances: core.appearances,
    player_stats: core.player_stats.map((row) => ({ ...row, ...extraStats.get(`${row.match_id}/${row.player_id}`) })),
    team_stats: core.team_stats,
    events: core.events.map((event) => pick(event, ['match_id', 'player_id', 'type', 'minute', 'added_minute', 'label'])),
    formations: Object.fromEntries(Object.entries(formations.matches).map(([id, value]) => [id, {
      sides: Object.fromEntries(Object.entries(value.sides).map(([side, team]) => [side, {
        ...pick(team, ['formation', 'team_id']),
        starters: team.starters.map((player) => pick(player, ['player_id', 'shirt_number', 'x', 'y', 'captain'])),
      }])),
    }])),
    winner_feeds: paths.winner_feeds,
  };
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  const snapshot = await buildSnapshot();
  const target = new URL('public/assets/data/', root);
  await mkdir(target, { recursive: true });
  const json = JSON.stringify(snapshot);
  await writeFile(new URL('worldcup-snapshot.json', target), `${json}\n`);
  console.log(`World Cup snapshot built: ${snapshot.teams.length} teams, ${snapshot.players.length} players, ${snapshot.matches.length} matches, ${(Buffer.byteLength(json) / 1024 / 1024).toFixed(2)} MiB. No network or database required.`);
}
