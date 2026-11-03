"""FOURWARD coursework | Author: 韩羽宸 | Adapt the verified pilot to import v1."""
import sys
from pathlib import Path
from worldcup_data import DEFAULT_SAMPLE, ROOT, clean, load_bundle, encode_json

sample, report = clean(load_bundle(DEFAULT_SAMPLE))
groups = sorted({m['group'].removeprefix('Group ') for m in sample['matches']})
assert len(groups) == 1
for team in sample['teams']:
    team['name_zh'] = {'ALG': '阿尔及利亚', 'ARG': '阿根廷', 'JOR': '约旦'}[team['code']]
for match in sample['matches']:
    match.update(stage_code='group', group_code=groups[0], venue_id=None,
                 status='finished', home_score_90=match['home_score'], away_score_90=match['away_score'],
                 home_score_et=None, away_score_et=None, home_penalty_score=None, away_penalty_score=None)
    match['winner_team_id'] = (match['home_team_id'] if match['home_score'] > match['away_score'] else
                              match['away_team_id'] if match['away_score'] > match['home_score'] else None)
sample.update(import_schema=1, dataset_name='2026-core-pilot',
              manifest=__import__('json').loads((DEFAULT_SAMPLE/'manifest.json').read_text(encoding='utf-8')),
              stages=[{'code': 'group', 'name': '小组赛', 'sort_order': 1, 'is_group_stage': 1}],
              groups=[{'code': g, 'name': g+'组', 'sort_order': ord(g)-64} for g in groups],
              group_teams=[{'group_code': groups[0], 'team_id': t['id']} for t in sample['teams']], venues=[],
              report=report)
target = ROOT/'data/worldcup/pilot.json'
target.parent.mkdir(parents=True, exist_ok=True)
target.write_bytes(encode_json(sample))
print('Prepared verified pilot: 26 players / 2 group-stage fixtures; no DB writes.')
