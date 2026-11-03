"""FOURWARD coursework | Author: 韩羽宸 | Fetch native DQD headshots; never crop FIFA full-body photos."""
import argparse
import json
import re
import time
import urllib.parse
import urllib.request
from concurrent.futures import ThreadPoolExecutor, as_completed
from datetime import datetime, timezone
from pathlib import Path
from worldcup_data import ROOT, require, digest, encode_json
from worldcup_photos import DATA, atomic_json, collect

SOURCE = ROOT/'tmp/player-headshot-sources'
HOSTS = {'sd.qunliao.info', 'img1.qunliao.info', 'img2.qunliao.info'}


def headshot_url(url):
    require(isinstance(url, str), 'Missing native headshot URL')
    p = urllib.parse.urlsplit(url)
    require(p.scheme == 'https' and p.netloc in HOSTS and not p.query and not p.fragment,
            'Unapproved headshot URL')
    require(re.fullmatch(r'/fastdfs\d+/M\d+/[A-F0-9]{2}/[A-F0-9]{2}/[A-Za-z0-9_-]+\.(jpg|png|jpeg)', p.path) is not None,
            'Unexpected native headshot path')
    return url


def fetch_json(url, path):
    require(urllib.parse.urlsplit(url).netloc in {'api.dongqiudi.com', 'sport-data.dongqiudi.com'}, 'Unapproved metadata host')
    if path.exists(): return json.loads(path.read_text(encoding='utf-8'))
    for attempt in range(4):
        try:
            with urllib.request.urlopen(urllib.request.Request(url, headers={'User-Agent': 'WorldCup2026DB-coursework/1.0'}), timeout=20) as r:
                require(urllib.parse.urlsplit(r.geturl()).netloc in {'api.dongqiudi.com', 'sport-data.dongqiudi.com'}, 'Unexpected metadata redirect')
                payload = r.read(4_000_001)
            require(len(payload) <= 4_000_000, 'Oversized metadata')
            value = json.loads(payload); path.parent.mkdir(parents=True, exist_ok=True)
            path.write_bytes(payload)
            atomic_json(path.with_suffix('.meta.json'), {'url': url, 'sha256': digest(payload), 'retrieved_at': datetime.now(timezone.utc).isoformat()})
            return value
        except Exception:
            if attempt == 3: raise
            time.sleep(2**attempt)


def capture():
    core = json.loads((ROOT/'data/worldcup/full/core.json').read_text(encoding='utf-8'))
    teams = {t['name_zh']: t['id'] for t in core['teams']}
    # Source display-name differences are explicit; no fuzzy person-name matching.
    aliases = {'波黑':'BIH', '民主刚果':'COD', '刚果民主共和国':'COD', '科特迪瓦':'CIV', '佛得角':'CPV', '韩国':'KOR', '捷克':'CZE'}
    for name, code in aliases.items(): teams[name] = next(t['id'] for t in core['teams'] if t['code'] == code)
    schedule = fetch_json('https://sport-data.dongqiudi.com/soccer/biz/data/schedule?season_id=26123&app=dqd&platform=&version=0&lang=zh-cn', SOURCE/'schedule.json')
    fixtures = {}
    rounds = [r for r in schedule['content']['rounds'] if r['params'].get('gameweek') in [1, 2, 3]]
    require(len(rounds) == 3, 'Missing three group rounds')
    for n, r in enumerate(rounds, 1):
        payload = fetch_json(r['url'], SOURCE/f'round-{n}.json')
        for m in payload['content']['matches']:
            require(str(m['competition_id']) == '61', 'Wrong competition')
            home, away = teams.get(m['team_A_name'].strip()), teams.get(m['team_B_name'].strip())
            require(home and away, 'Unmapped national team: '+m['team_A_name']+'/'+m['team_B_name'])
            found = [f for f in core['matches'] if f['stage_code'] == 'group' and f['home_team_id'] == home and f['away_team_id'] == away]
            require(len(found) == 1, 'Ambiguous fixture')
            f = found[0]
            require(m['date_utc']+'T'+m['time_utc']+'Z' == f['kickoff_at'], 'Fixture date mismatch')
            require(int(m['score_A']) == f['home_score_90'] and int(m['score_B']) == f['away_score_90'], 'Fixture score mismatch')
            fixtures[str(m['match_id'])] = (m, f)
    require(len(fixtures) == 72, 'Incomplete group schedule')
    results = {}
    def task(mid):
        url = f'https://api.dongqiudi.com/soccer/biz/dqd/v1/match/lineup/{mid}'
        return mid, fetch_json(url, SOURCE/f'lineup-{mid}.json')
    with ThreadPoolExecutor(max_workers=3) as pool:
        futures = [pool.submit(task, mid) for mid in fixtures]
        for done, future in enumerate(as_completed(futures), 1):
            mid, value = future.result(); results[mid] = value
            if done % 8 == 0 or done == len(fixtures): print(f'Headshot source lineups: {done}/{len(fixtures)}', flush=True)
    return build(core, fixtures, results)


def build(core, fixtures, results):
    roster = {(p['team_id'], int(p['shirt_number'])): p for p in core['players']}
    require(len(roster) == len(core['players']), 'Ambiguous squad shirt numbers')
    observations = {}; mappings = {}; reverse = {}; sources = {}
    for mid, payload in sorted(results.items()):
        m, f = fixtures[mid]
        sources[mid] = {'url': f'https://api.dongqiudi.com/soccer/biz/dqd/v1/match/lineup/{mid}', 'fifa_match_id': f['id'],
                        'kickoff_at': f['kickoff_at'], 'sha256': digest((SOURCE/f'lineup-{mid}.json').read_bytes())}
        for side, field in [('team_A', 'home_team_id'), ('team_B', 'away_team_id')]:
            team = payload['persons'][side]
            require(str(team['team_id']) == str(m[side+'_id']), 'Lineup team changed')
            people = (team.get('lineups') or [])+(team.get('sub') or [])
            require(11 <= len(people) <= 26, 'Unexpected source match roster size')
            seen = set()
            for person in people:
                shirt = int(person['shirtnumber']); require(shirt not in seen, 'Duplicate lineup shirt'); seen.add(shirt)
                p = roster.get((f[field], shirt)); require(p is not None, 'Unknown shirt in national squad')
                pid = p['id'].split(':')[-1]; external = str(person['person_id'])
                require(external.isdigit(), 'Invalid DQD player ID')
                require(mappings.get(pid, external) == external and reverse.get(external, pid) == pid, 'Conflicting player identity across fixtures')
                mappings[pid] = external; reverse[external] = pid
                try: url = headshot_url(person.get('logo'))
                except ValueError: continue
                entry = observations.setdefault(pid, {'name': p['name'], 'dqd_id': external, 'dqd_name': person['person'], 'team_id': p['team_id'],
                    'shirt_number': shirt, 'source_url': url, 'download_url': url, 'kind': 'headshot', 'evidence_matches': []})
                require(entry['source_url'] == url and entry['dqd_name'] == person['person'], 'Source headshot identity changed')
                entry['evidence_matches'].append(mid)
    verified = {pid: entry for pid, entry in observations.items() if len(set(entry['evidence_matches'])) >= 2}
    require(len(verified) > 1000, 'Unexpectedly low verified headshot coverage')
    result = {'edition': 2026, 'dqd_season': '26123', 'sources': sources, 'players': verified,
              'identity_check': 'Exact national team, unique tournament squad shirt, fixture date and score; same DQD ID and headshot in at least two different matches. No fuzzy names or shirt-only cross-team join.'}
    atomic_json(DATA/'headshots.json', result)
    catalog = json.loads((DATA/'catalog.json').read_text(encoding='utf-8'))
    for entry in catalog['players'].values(): entry.setdefault('kind', 'full-body')
    for pid, entry in verified.items():
        original = catalog['players'][pid]
        entry['fifa_full_body_url'] = original.get('fifa_full_body_url', original['source_url'])
        entry['team_code'] = original['team_code']; catalog['players'][pid] = entry
    catalog['headshot_identity_check'] = result['identity_check']
    atomic_json(DATA/'catalog.json', catalog)
    print(f'Native headshots verified: {len(verified)}/{len(core["players"])}', flush=True)
    return catalog


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__); parser.add_argument('--capture', action='store_true'); args = parser.parse_args()
    catalog = capture() if args.capture else json.loads((DATA/'catalog.json').read_text(encoding='utf-8'))
    collect(catalog, workers=4)
