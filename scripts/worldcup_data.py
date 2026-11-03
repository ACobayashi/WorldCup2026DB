"""FOURWARD coursework | Author: 韩羽宸 | Offline, minimal World Cup data pilot.

Packages previously audited responses, then builds staging JSON. Never opens a DB.
"""
import argparse
import csv
import hashlib
import json
import re
import sys
import unicodedata
from datetime import datetime, timedelta, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
DEFAULT_SAMPLE = ROOT / 'data/samples/worldcup2026'
SCOPE = {'edition': 2026, 'fifa_season': '285023', 'team_code': 'ALG',
         'match_numbers': [19, 44], 'control_match_numbers': [47]}
TEAM_METRICS = {'Possession', 'Goals', 'Attempts at Goal (On Target)',
                'Total Passes (Complete)', 'Pass Completion %'}
CSV_FIELDS = {
    'appearances': ['appearance_id', 'match_id', 'player_id', 'player_name', 'shirt_number',
                    'position', 'is_starter', 'appeared', 'team_id', 'team_role'],
    'player_stats': ['appearance_id', 'match_id', 'player_id', 'player_name', 'shirt_number',
                     'team_id', 'goals', 'attempts_at_goal', 'passes_attempted',
                     'passes_completed', 'pass_completion_pct'],
    'team_stats': ['match_id', 'team_id', 'metric', 'raw', 'unit', 'value'],
    'events': ['event_id', 'appearance_id', 'match_id', 'player_id', 'player_name',
               'shirt_number', 'team_id', 'event_type', 'minute', 'minute_raw'],
}


class DataError(ValueError):
    pass


def require(condition, message):
    if not condition:
        raise DataError(message)


def digest(data):
    return hashlib.sha256(data).hexdigest()


def encode_json(value):
    return (json.dumps(value, ensure_ascii=False, indent=2, sort_keys=True) + '\n').encode('utf-8')


def normalized_name(value):
    value = unicodedata.normalize('NFKD', value).casefold()
    return ''.join(c for c in value if c.isalnum() and not unicodedata.combining(c))


def integer(value):
    text = str(value).strip() if value is not None else ''
    if not text:
        return None
    require(re.fullmatch(r'\d+', text) is not None, 'Expected a non-negative integer: ' + text)
    return int(text)


def percentage(value):
    text = str(value).strip().removesuffix('%') if value is not None else ''
    if not text:
        return None
    require(re.fullmatch(r'\d+(?:\.\d+)?', text) is not None, 'Invalid percentage: ' + text)
    number = float(text)
    require(0 <= number <= 100, 'Percentage outside 0..100')
    return number


def pair(value):
    match = re.fullmatch(r'(\d+)\s*\((\d+)\)', value.strip())
    require(match is not None, 'Invalid attempted/completed pair')
    total, part = map(int, match.groups())
    require(part <= total, 'Completed/on-target value exceeds total')
    return total, part


def event_minute(value):
    match = re.fullmatch(r'(\d{1,3})(?:\+(\d{1,2}))?', value.strip().rstrip("'′"))
    require(match is not None, 'Invalid event minute')
    base, added = int(match[1]), int(match[2] or 0)
    require(base <= 150, 'Invalid event minute range')
    return {'minute': base, 'added_minute': added, 'label': value}


def localized(values):
    require(bool(values) and bool(values[0].get('Description')), 'Missing source name')
    return values[0]['Description']


def capture(audit_dir, sample_dir):
    """Project audit responses into a small, redistributable reproducibility bundle."""
    raw_dir = sample_dir / 'raw'
    raw_dir.mkdir(parents=True, exist_ok=True)
    manifest = {'schema_version': 1, 'scope': SCOPE, 'files': {}}

    def save(name, value, parent, url, selector, license_name):
        content = encode_json(value)
        (raw_dir / name).write_bytes(content)
        source = audit_dir / parent
        manifest['files'][name] = {
            'sha256': digest(content), 'source_url': url, 'selection': selector,
            'license': license_name, 'audit_payload_sha256': digest(source.read_bytes()),
            'retrieved_at': None,
            'audit_cache_saved_at': datetime.fromtimestamp(source.stat().st_mtime, timezone.utc).isoformat(),
        }

    def read(name):
        return json.loads((audit_dir / name).read_text(encoding='utf-8'))

    fifa = read('fifa_matches.json')['Results']
    selected = []
    match_numbers = SCOPE['match_numbers'] + SCOPE['control_match_numbers']
    for record in fifa:
        if record['MatchNumber'] not in match_numbers:
            continue
        row = {k: record[k] for k in ['IdMatch', 'IdSeason', 'MatchNumber', 'Date', 'IdGroup',
                                      'StageName', 'GroupName', 'MatchStatus', 'ResultType',
                                      'HomeTeamScore', 'AwayTeamScore']}
        for side in ['Home', 'Away']:
            row[side] = {k: record[side][k] for k in ['IdTeam', 'IdCountry', 'TeamName']}
        selected.append(row)
    save('fifa-matches.json', selected, 'fifa_matches.json',
         'https://api.fifa.com/api/v3/calendar/matches?idSeason=285023&idCompetition=17&count=200&language=en-US',
         'Matches 19, 44; match 47 is an excluded negative control. Core fields only.', 'FIFA first-party response; media not included')

    squad = read('fifa_squad_2026.json')
    squad = {k: squad[k] for k in ['IdTeam', 'IdSeason', 'IdCountry']} | {
        'Players': [{k: player[k] for k in ['IdPlayer', 'IdTeam', 'IdSeason', 'PlayerName',
                                           'JerseyNum', 'PositionLocalized']} for player in squad['Players']]}
    save('fifa-squad.json', squad, 'fifa_squad_2026.json',
         'https://api.fifa.com/api/v3/teams/43843/squad?idCompetition=17&idSeason=285023&language=en-US',
         'Algeria core squad fields; no birthday, physical attributes or photos.', 'FIFA first-party response; media not included')
    roster = next(team for team in read('openfootball_squads_api.json') if team['fifa_code'] == 'ALG')
    roster = {'fifa_code': roster['fifa_code'], 'players': [
        {k: player[k] for k in ['number', 'pos', 'name']} for player in roster['players']]}
    save('openfootball-squad.json', roster, 'openfootball_squads_api.json',
         'https://github.com/openfootball/worldcup.json/blob/master/2026/worldcup.squads.json',
         'ALG; core fields; Git blob 3c01473715a2533fc28f1b3f2a68e6fa848bea84.', 'CC0-1.0')

    with (audit_dir / 'advanced_matches.csv').open(encoding='utf-8') as stream:
        matches = [row for row in csv.DictReader(stream) if int(row['match_number']) in match_numbers]
    save('report-matches.json', matches, 'advanced_matches.csv',
         'https://github.com/Alamyy/Worldcup26/blob/main/data/csv/matches.csv',
         'Matches 19, 44 and negative control 47; original fields preserved.', 'CC-BY-4.0')
    source_ids = {row['match_id'] for row in matches if int(row['match_number']) in SCOPE['match_numbers']}
    upstream_files = {'appearances': 'match_appearances', 'player_stats': 'player_in_possession_distributions',
                      'team_stats': 'team_key_stats', 'events': 'player_events'}
    for key, fields in CSV_FIELDS.items():
        parent = 'advanced_' + key + '.csv'
        with (audit_dir / parent).open(encoding='utf-8') as stream:
            rows = [{field: row[field] for field in fields} for row in csv.DictReader(stream)
                    if row['team_id'] == 'ALG' and row['match_id'] in source_ids
                    and (key != 'team_stats' or row['metric'] in TEAM_METRICS)]
        save(key.replace('_', '-') + '.json', rows, parent,
             'https://github.com/Alamyy/Worldcup26/blob/main/data/csv/' + upstream_files[key] + '.csv',
             'ALG in matches 19 and 44; selected core fields only.', 'CC-BY-4.0')
    (sample_dir / 'manifest.json').write_bytes(encode_json(manifest))


def load_bundle(sample_dir):
    manifest = json.loads((sample_dir / 'manifest.json').read_text(encoding='utf-8'))
    require(manifest['scope'] == SCOPE, 'Unexpected sample scope')
    required = {'fifa-matches.json', 'fifa-squad.json', 'openfootball-squad.json',
                'report-matches.json', 'appearances.json', 'player-stats.json', 'team-stats.json', 'events.json'}
    require(set(manifest['files']) == required, 'Incomplete raw snapshot manifest')
    data = {}
    for name, metadata in manifest['files'].items():
        content = (sample_dir / 'raw' / name).read_bytes()
        require(digest(content) == metadata['sha256'], 'Snapshot checksum mismatch: ' + name)
        data[name] = json.loads(content)
    return data


def clean(bundle):
    squad = bundle['fifa-squad.json']
    require(squad['IdSeason'] == SCOPE['fifa_season'] and squad['IdCountry'] == 'ALG', 'Wrong squad edition/team')
    team_id = 'fifa:team:' + squad['IdTeam']
    players, by_shirt, mappings, reviews = [], {}, [], []
    positions = {'Goalkeeper': 'GK', 'Defender': 'DF', 'Midfielder': 'MF', 'Forward': 'FW'}
    for row in squad['Players']:
        require(row['IdSeason'] == SCOPE['fifa_season'] and row['IdTeam'] == squad['IdTeam'], 'Foreign player in squad')
        shirt = integer(row['JerseyNum'])
        require(shirt is not None and 1 <= shirt <= 99 and shirt not in by_shirt, 'Duplicate/invalid squad number')
        position = positions.get(localized(row['PositionLocalized']))
        require(position is not None, 'Unknown player position')
        player = {'id': 'fifa:player:' + row['IdPlayer'], 'team_id': team_id,
                  'name': localized(row['PlayerName']), 'shirt_number': shirt, 'position': position}
        by_shirt[shirt] = player
        players.append(player)
    require(len({p['id'] for p in players}) == len(players) >= 11, 'Duplicate IDs or incomplete squad')
    require(bundle['openfootball-squad.json']['fifa_code'] == 'ALG', 'Wrong comparison roster')
    seen = set()
    for row in bundle['openfootball-squad.json']['players']:
        shirt = integer(row['number'])
        require(shirt not in seen, 'Duplicate comparison roster number')
        seen.add(shirt)
        candidate = by_shirt.get(shirt)
        if candidate and normalized_name(row['name']) == normalized_name(candidate['name']) and row['pos'] == candidate['position']:
            mappings.append({'provider': 'openfootball', 'source_key': 'ALG/' + str(shirt), 'player_id': candidate['id']})
        else:
            reviews.append({'type': 'roster_identity_conflict', 'source_key': 'ALG/' + str(shirt),
                            'source_name': row['name'], 'candidate_name': candidate['name'] if candidate else None,
                            'action': 'Do not map this source row automatically.'})

    references = {}
    for row in bundle['fifa-matches.json']:
        require(row['IdSeason'] == SCOPE['fifa_season'], 'Wrong match edition')
        require(row['MatchNumber'] not in references, 'Duplicate official match number')
        references[row['MatchNumber']] = row
    require(set(references) == set(SCOPE['match_numbers'] + SCOPE['control_match_numbers']), 'Missing official reference match')
    matches, source_matches, controls, teams = [], {}, [], {}
    seen = set()
    for row in bundle['report-matches.json']:
        number = integer(row['match_number'])
        require(number in references and number not in seen, 'Duplicate/unexpected report match')
        seen.add(number)
        official = references[number]
        expected = [official['Home']['IdCountry'], official['Away']['IdCountry']]
        observed = [row['home_team_id'], row['away_team_id']]
        if number in SCOPE['control_match_numbers']:
            controls.append({'match_no': number, 'accepted': False, 'team_codes_match': observed == expected,
                             'expected': expected, 'observed': observed, 'source_match_id': row['match_id']})
            continue
        require(observed == expected, 'Report team-code mismatch at match ' + str(number))
        scores = [integer(row['home_score']), integer(row['away_score'])]
        require(scores == [official['HomeTeamScore'], official['AwayTeamScore']], 'Report/official score mismatch')
        require('ALG' in expected and official['IdGroup'], 'Pilot expects ALG group-stage matches')
        kickoff = datetime.fromisoformat(official['Date'].replace('Z', '+00:00'))
        require(kickoff.tzinfo is not None, 'Kickoff needs an explicit timezone')
        match = {'id': 'fifa:match:' + official['IdMatch'], 'match_no': number,
                 'kickoff_at': kickoff.astimezone(timezone.utc).isoformat().replace('+00:00', 'Z'),
                 'kickoff_beijing': kickoff.astimezone(timezone(timedelta(hours=8))).isoformat(),
                 'home_team_id': 'fifa:team:' + official['Home']['IdTeam'],
                 'away_team_id': 'fifa:team:' + official['Away']['IdTeam'],
                 'home_score': scores[0], 'away_score': scores[1],
                 'score_scope': 'group_stage_full_time', 'group': localized(official['GroupName']),
                 'source_status': {'match_status': official['MatchStatus'], 'result_type': official['ResultType']},
                 'report_source_id': row['match_id']}
        require(row['match_id'] not in source_matches, 'Duplicate report match ID')
        source_matches[row['match_id']] = match
        matches.append(match)
        for side in ['Home', 'Away']:
            entity = official[side]
            teams[entity['IdCountry']] = {'id': 'fifa:team:' + entity['IdTeam'],
                                         'code': entity['IdCountry'], 'name': localized(entity['TeamName'])}
    require(len(matches) == 2 and len(controls) == 1, 'Incomplete pilot match selection')
    require(teams['ALG']['id'] == team_id, 'Squad/match team ID disagreement')

    appearances, by_source_player, appearance_keys = [], {}, set()

    def connect(row):
        require(row['team_id'] == 'ALG' and row['match_id'] in source_matches, 'Row outside pilot team/matches')
        player = by_shirt.get(integer(row['shirt_number']))
        require(player is not None and normalized_name(row['player_name']) == normalized_name(player['name']), 'Unverified report player identity')
        return source_matches[row['match_id']], player

    for row in bundle['appearances.json']:
        match, player = connect(row)
        require(row['is_starter'] in ['True', 'False'] and row['appeared'] in ['True', 'False'], 'Invalid appearance flags')
        require(row['position'] == player['position'], 'Report/squad position mismatch')
        key = (match['id'], player['id'])
        require(key not in appearance_keys, 'Duplicate match/player appearance')
        appearance_keys.add(key)
        require(row['player_id'] not in by_source_player or by_source_player[row['player_id']] == player['id'], 'Source player ID collision')
        by_source_player[row['player_id']] = player['id']
        appearances.append({'match_id': match['id'], 'player_id': player['id'],
                            'starting': row['is_starter'] == 'True', 'appeared': row['appeared'] == 'True'})
    for match in matches:
        rows = [r for r in appearances if r['match_id'] == match['id']]
        require(len(rows) == len(players) and sum(r['starting'] for r in rows) == 11, 'Incomplete match squad or starting XI')
    mappings.extend({'provider': 'worldcup26', 'source_key': key, 'player_id': value} for key, value in sorted(by_source_player.items()))

    def linked(row):
        match, player = connect(row)
        require(by_source_player.get(row['player_id']) == player['id'] and (match['id'], player['id']) in appearance_keys, 'Unlinked player row')
        return match, player

    stats, stat_keys = [], set()
    for row in bundle['player-stats.json']:
        match, player = linked(row)
        key = (match['id'], player['id'])
        require(key not in stat_keys, 'Duplicate player statistics')
        stat_keys.add(key)
        record = {'match_id': match['id'], 'player_id': player['id'],
                  'goals': integer(row['goals']), 'shots': integer(row['attempts_at_goal']),
                  'passes_attempted': integer(row['passes_attempted']), 'passes_completed': integer(row['passes_completed']),
                  'pass_completion_pct': percentage(row['pass_completion_pct']), 'minutes_played': None, 'assists': None}
        if record['passes_completed'] is not None and record['passes_attempted'] is not None:
            require(record['passes_completed'] <= record['passes_attempted'], 'Player completed passes exceed attempted')
        stats.append(record)
    require(stat_keys == {(r['match_id'], r['player_id']) for r in appearances if r['appeared']}, 'Missing/unexpected participant statistics')

    team_stats, metric_keys = {}, set()
    for row in bundle['team-stats.json']:
        require(row['team_id'] == 'ALG' and row['match_id'] in source_matches, 'Foreign team statistics')
        match = source_matches[row['match_id']]
        metric = row['metric']
        require(metric in TEAM_METRICS and (match['id'], metric) not in metric_keys, 'Unexpected/duplicate team metric')
        metric_keys.add((match['id'], metric))
        record = team_stats.setdefault(match['id'], {'match_id': match['id'], 'team_id': team_id})
        if metric == 'Possession': record['possession_pct'] = percentage(row['value'])
        elif metric == 'Goals': record['goals'] = integer(row['value'])
        elif metric == 'Pass Completion %': record['pass_completion_pct'] = percentage(row['value'])
        elif metric == 'Attempts at Goal (On Target)': record['shots'], record['shots_on_target'] = pair(row['value'])
        elif metric == 'Total Passes (Complete)': record['passes_attempted'], record['passes_completed'] = pair(row['value'])
    require(len(metric_keys) == len(matches) * len(TEAM_METRICS), 'Missing team metrics')
    for match in matches:
        expected_goals = match['home_score'] if match['home_team_id'] == team_id else match['away_score']
        require(team_stats[match['id']]['goals'] == expected_goals, 'Team-stat goals disagree with fixture')
        values = [r['goals'] for r in stats if r['match_id'] == match['id']]
        require(all(v is not None for v in values) and sum(values) == expected_goals, 'Pilot player-goal total disagrees with fixture')

    events, event_ids = [], set()
    for row in bundle['events.json']:
        match, player = linked(row)
        require(row['event_id'] not in event_ids, 'Duplicate event ID')
        event_ids.add(row['event_id'])
        require(row['event_type'] in ['goal', 'own_goal', 'yellow_card', 'red_card', 'subbed_on', 'subbed_off'], 'Unknown event type')
        events.append({'source_id': row['event_id'], 'match_id': match['id'], 'player_id': player['id'],
                       'type': row['event_type'], **event_minute(row['minute_raw'])})

    output = {'schema_version': 1, 'scope': SCOPE, 'teams': list(teams.values()), 'players': players,
              'matches': matches, 'appearances': appearances, 'player_stats': stats,
              'team_stats': list(team_stats.values()), 'events': events, 'player_mappings': mappings}
    report = {'status': 'passed_with_review' if reviews else 'passed', 'ready_for_database_import': False,
              'counts': {key: len(output[key]) for key in output if isinstance(output[key], list)},
              'review_queue': reviews, 'excluded_control_matches': controls,
              'notes': ['Only ALG player/appearance/stat/event records are in scope; opponents have team references only.',
                        'Missing minutes/assists stay null. Status enum mapping is not yet an import contract.',
                        'Goal-total check is limited to these group-stage samples without own goals.',
                        'No database connection, schema change or import was performed.']}
    return output, report


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--sample-dir', type=Path, default=DEFAULT_SAMPLE)
    parser.add_argument('--capture-from', type=Path, help='Package the earlier audit cache before building')
    args = parser.parse_args()
    try:
        if args.capture_from:
            capture(args.capture_from, args.sample_dir)
        output, report = clean(load_bundle(args.sample_dir))
        (args.sample_dir / 'sample.json').write_bytes(encode_json(output))
        (args.sample_dir / 'validation-report.json').write_bytes(encode_json(report))
        print(json.dumps({'status': report['status'], 'counts': report['counts'],
                          'review_items': len(report['review_queue']), 'database_import': False}, ensure_ascii=False))
    except (DataError, OSError, KeyError, StopIteration, ValueError, TypeError) as error:
        print('Sample build failed: ' + str(error), file=sys.stderr)
        return 1
    return 0


if __name__ == '__main__':
    sys.exit(main())
