"""Build the complete Chinese-name snapshot and merge verified DQD supplemental identities."""
import json
from pathlib import Path
from worldcup_data import ROOT, encode_json, require

HEADSHOTS=ROOT/'data/worldcup/player-photos/headshots.json'
SUPPLEMENTAL=ROOT/'data/worldcup/player-supplemental-dqd.json'
CATALOG=ROOT/'data/worldcup/player-photos/catalog.json'
OUTPUT=ROOT/'data/worldcup/player-names.json'

def main():
    core=json.loads((ROOT/'data/worldcup/full/core.json').read_text(encoding='utf-8'))
    roster={p['id'].split(':')[-1]:p for p in core['players']}
    headshots=json.loads(HEADSHOTS.read_text(encoding='utf-8'))['players']
    supplemental=json.loads(SUPPLEMENTAL.read_text(encoding='utf-8'))['players']
    require(not(set(headshots)&set(supplemental)),'Supplemental DQD identities overlap verified headshots')
    sources=headshots|supplemental
    require(set(sources)==set(roster) and len(roster)==1248,'Chinese-name coverage must equal the complete roster')
    names={}
    for pid,p in roster.items():
        source=sources[pid]
        require(source['name']==p['name'],'DQD identity does not match FIFA roster name')
        require(isinstance(source['dqd_name'],str) and source['dqd_name'].strip(),'Missing Chinese name')
        names[pid]={'name_zh':source['dqd_name'].strip(),'name_en':p['name'],'dqd_id':str(source['dqd_id'])}
    OUTPUT.write_bytes(encode_json({'edition':2026,'provider':'懂球帝','complete':True,'players':names}))

    catalog=json.loads(CATALOG.read_text(encoding='utf-8'))
    for pid,source in supplemental.items():
        original=catalog['players'][pid]
        catalog['players'][pid]={**source,'team_code':original['team_code'],'fifa_full_body_url':original.get('fifa_full_body_url',original.get('source_url'))}
    CATALOG.write_bytes(encode_json(catalog))
    print(f'Saved {len(names)} Chinese player names and merged {len(supplemental)} supplemental DQD identities.')

if __name__=='__main__':main()
