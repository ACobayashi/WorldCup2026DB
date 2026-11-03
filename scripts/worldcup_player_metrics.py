"""FOURWARD coursework | Author: 韩羽宸 | Audited player-level extension, separate from season aggregates."""
import argparse
import csv
import json
import re
from datetime import datetime,timezone
from pathlib import Path
from worldcup_data import ROOT,encode_json,digest,require,integer
from worldcup_full import DIRECTORY,load,build_details

TARGET=ROOT/'data/worldcup/player-metrics'
IDENTITY=['appearance_id','match_id','player_id','team_id','shirt_number','player_name']
ATTACK=['ball_progressions','crosses_attempted','crosses_completed','line_breaks_attempted','line_breaks_completed','take_ons','step_ins','switches_of_play']
DEFENCE=['blocks','clearances','duels_won_aerial','duels_won_physical','interceptions','possession_regains','pressing_direct','tackles_made_won']
FIELDS=ATTACK+[x for x in DEFENCE if x!='tackles_made_won']+['tackles_attempted','tackles_won']

def capture(cache):
    (TARGET/'raw').mkdir(parents=True,exist_ok=True);manifest={'edition':2026,'files':{}}
    for name,parent,fields,upstream in [('attack','advanced_player_stats.csv',ATTACK,'player_in_possession_distributions'),('defence','advanced_defending.csv',DEFENCE,'player_out_of_possession')]:
        source=cache/parent
        with source.open(encoding='utf-8') as f:rows=[{k:r[k] for k in IDENTITY+fields} for r in csv.DictReader(f)]
        payload=encode_json(rows);(TARGET/'raw'/f'{name}.json').write_bytes(payload)
        manifest['files'][name]={'sha256':digest(payload),'original_payload_sha256':digest(source.read_bytes()),
            'url':'https://github.com/Alamyy/Worldcup26/blob/main/data/csv/'+upstream+'.csv',
            'license':'CC-BY-4.0','author':'Hesham El Alamy','retrieved_at':None,
            'cache_saved_at':datetime.fromtimestamp(source.stat().st_mtime,timezone.utc).isoformat()}
    (TARGET/'manifest.json').write_bytes(encode_json(manifest))

def build(raw,manifest,bundle,core_manifest,corrections):
    canonical=build_details(bundle,core_manifest,corrections)
    source_players={m['source_key']:m['player_id'] for m in canonical['player_mappings']}
    source_matches={m['source_key']:m['target_id'] for m in canonical['entity_mappings'] if m['entity_type']=='match'}
    source_teams={m['source_key']:m['target_id'] for m in canonical['entity_mappings'] if m['provider']=='worldcup26-matchteam'}
    players={p['id']:p for p in canonical['players']}
    valid_appearances={r['appearance_id']:r for r in bundle['report-appearances.json']}
    valid_stats={(r['match_id'],r['player_id']) for r in canonical['player_stats']}
    merged={}
    for section in ['attack','defence']:
        seen=set()
        for r in raw[section]:
            original=valid_appearances.get(r['appearance_id']);require(original is not None,'Unknown appearance')
            require(all(r[k]==original[k] for k in IDENTITY),'Unreviewed extension identity change')
            player=source_players[r['player_id']];match=source_matches[r['match_id']];key=(match,player)
            require(key in valid_stats and key not in seen,'Duplicate/unused player metrics');seen.add(key)
            require(source_teams[r['match_id']+'/'+r['team_id']]==players[player]['team_id'],'Extension team mismatch')
            record=merged.setdefault(key,{'player_id':player,'match_id':match,'source_appearance_id':r['appearance_id'],'metrics':{}})
            for field in ATTACK if section=='attack' else DEFENCE:
                if field=='tackles_made_won':
                    if not r[field].strip():record['metrics'].update(tackles_attempted=None,tackles_won=None);continue
                    pair=re.fullmatch(r'(\d+)\s*/\s*(\d+)',r[field].strip());require(pair is not None,'Invalid tackles made/won')
                    made,won=map(int,pair.groups());require(won<=made,'Won tackles exceed attempts')
                    record['metrics'].update(tackles_attempted=made,tackles_won=won)
                else:record['metrics'][field]=integer(r[field])
        require(seen==valid_stats,'Incomplete extension participant coverage')
    for r in merged.values():
        for total,part in [('crosses_attempted','crosses_completed'),('line_breaks_attempted','line_breaks_completed')]:
            a,b=r['metrics'][total],r['metrics'][part];require(a is None or b is None or b<=a,'Completed metric exceeds attempts')
    return {'schema':1,'dataset_name':'2026-player-match-extensions','manifest':manifest,'canonical_dataset_sha256':digest(encode_json(canonical)),
            'fields':FIELDS,'rows':list(merged.values())}

def main():
    parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('--capture-from',type=Path);args=parser.parse_args()
    if args.capture_from:capture(args.capture_from)
    manifest=json.loads((TARGET/'manifest.json').read_text(encoding='utf-8'));raw={}
    for key in ['attack','defence']:
        payload=(TARGET/'raw'/f'{key}.json').read_bytes();require(digest(payload)==manifest['files'][key]['sha256'],'Checksum mismatch');raw[key]=json.loads(payload)
    bundle,core_manifest=load();corrections=json.loads((DIRECTORY/'corrections.json').read_text(encoding='utf-8'))
    result=build(raw,manifest,bundle,core_manifest,corrections);(TARGET/'metrics.json').write_bytes(encode_json(result))
    print('Verified player extensions:',len(result['rows']),'rows;',len(FIELDS),'metrics; no season aggregates copied into matches.')

if __name__=='__main__':main()
