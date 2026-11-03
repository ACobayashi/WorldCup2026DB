"""FOURWARD coursework | Author: 韩羽宸 | Verify actual starting formations and coordinates against the full FIFA dataset."""
import argparse
import json
import re
from concurrent.futures import ThreadPoolExecutor, as_completed
from datetime import datetime, timezone
from worldcup_data import ROOT, require, digest, encode_json
from worldcup_headshots import SOURCE, fetch_json

OUTPUT = ROOT/'data/worldcup/formations.json'


def coordinate(value):
    require(isinstance(value, (str,int,float)) and not isinstance(value,bool), 'Invalid position type')
    require(re.fullmatch(r'\d+(?:\.\d+)?',str(value)) is not None, 'Non-numeric starting position')
    number=float(value);require(0 < number < 100, 'Position outside pitch')
    return number


def verify_side(team, team_id, fixture_id, core, headshots):
    starters=[a for a in core['appearances'] if a['match_id']==fixture_id and a['starting']]
    roster={p['shirt_number']:p for p in core['players'] if p['team_id']==team_id}
    expected={a['player_id'] for a in starters if a['player_id'] in {p['id'] for p in roster.values()}}
    require(len(expected)==11, 'Incomplete FIFA starters')
    formation=team.get('formation','')
    require(re.fullmatch(r'[1-5](?:-[1-5]){1,4}',formation) is not None and sum(map(int,formation.split('-')))==10, 'Invalid formation')
    rows=team.get('lineups') or [];require(len(rows)==11,'Expected eleven actual starters')
    result=[];ids=set();spots=set()
    for row in rows:
        shirt=int(row['shirtnumber']);p=roster.get(shirt)
        require(p is not None and p['id'] in expected and p['id'] not in ids,'Starter identity disagrees with FIFA')
        pid=p['id'].split(':')[-1];identity=headshots.get(pid)
        if identity: require(str(row['person_id'])==identity['dqd_id'],'DQD player ID changed')
        x,y=coordinate(row['position_x']),coordinate(row['position_y'])
        require((x,y) not in spots,'Overlapping source starter positions');spots.add((x,y));ids.add(p['id'])
        result.append({'player_id':p['id'],'shirt_number':shirt,'name':p['name'],'x':x,'y':y,
                       'dqd_id':str(row['person_id']),'source_name':row['person'],'captain':row.get('captain')==1})
    require(ids==expected,'Source starter set disagrees with FIFA')
    return {'team_id':team_id,'formation':formation,'starters':result}


def capture():
    core=json.loads((ROOT/'data/worldcup/full/complete.json').read_text(encoding='utf-8'))
    teams={t['name_zh']:t['id'] for t in core['teams']}
    aliases={'波黑':'BIH','民主刚果':'COD','刚果民主共和国':'COD','佛得角':'CPV','科特迪瓦':'CIV','韩国':'KOR','捷克':'CZE'}
    for name,code in aliases.items():teams[name]=next(t['id'] for t in core['teams'] if t['code']==code)
    schedule=fetch_json('https://sport-data.dongqiudi.com/soccer/biz/data/schedule?season_id=26123&app=dqd&platform=&version=0&lang=zh-cn',SOURCE/'schedule.json')
    fixtures={};round_sources=[]
    for i,r in enumerate(schedule['content']['rounds']):
        payload=fetch_json(r['url'],SOURCE/f'formation-round-{i}.json')
        round_sources.append({'url':r['url'],'sha256':digest((SOURCE/f'formation-round-{i}.json').read_bytes())})
        for m in payload['content'].get('matches',[]):
            require(str(m['competition_id'])=='61','Wrong competition')
            h,a=teams.get(m['team_A_name'].strip()),teams.get(m['team_B_name'].strip())
            kickoff=m['date_utc']+'T'+m['time_utc']+'Z'
            found=[f for f in core['matches'] if f['home_team_id']==h and f['away_team_id']==a and f['kickoff_at']==kickoff]
            require(len(found)==1,'Fixture identity mismatch');f=found[0]
            for label,prefix in [('A','home'),('B','away')]:
                final=f[prefix+'_score_et'] if f[prefix+'_score_et'] is not None else f[prefix+'_score_90']
                require(int(m['fs_'+label])==final,'Fixture result mismatch')
                penalties=m.get('ps_'+label)
                require((int(penalties) if penalties not in ['',None] else None)==f[prefix+'_penalty_score'],'Penalty result mismatch')
            fixtures[str(m['match_id'])]=(m,f)
    require(len(fixtures)==104,'Incomplete tournament schedule')
    headshots=json.loads((ROOT/'data/worldcup/player-photos/headshots.json').read_text(encoding='utf-8'))['players']
    results={}
    def task(mid):
        return mid,fetch_json(f'https://api.dongqiudi.com/soccer/biz/dqd/v1/match/lineup/{mid}',SOURCE/f'lineup-{mid}.json')
    with ThreadPoolExecutor(max_workers=3) as pool:
        futures=[pool.submit(task,mid) for mid in fixtures]
        for done,future in enumerate(as_completed(futures),1):
            mid,payload=future.result();m,f=fixtures[mid];sides={}
            for side,field in [('team_A','home_team_id'),('team_B','away_team_id')]:
                team=payload['persons'][side];require(str(team['team_id'])==str(m[side+'_id']),'Lineup team mismatch')
                sides['home' if side=='team_A' else 'away']=verify_side(team,f[field],f['id'],core,headshots)
            results[f['id']]={'match_no':f['match_no'],'kickoff_at':f['kickoff_at'],'source_match_id':mid,
                'source_url':f'https://api.dongqiudi.com/soccer/biz/dqd/v1/match/lineup/{mid}',
                'source_sha256':digest((SOURCE/f'lineup-{mid}.json').read_bytes()),'sides':sides}
            if done%12==0 or done==104:print(f'Actual formations verified: {done}/104',flush=True)
    value={'edition':2026,'fifa_season':'285023','dqd_season':'26123','captured_at':datetime.now(timezone.utc).isoformat(),
           'round_sources':round_sources,'matches':results,'coordinates':'Source schematic positions: x across team own half, y from own goal to attack. Not player tracking or average positions.'}
    temporary=OUTPUT.with_suffix('.json.tmp');temporary.write_bytes(encode_json(value));temporary.replace(OUTPUT)
    print('Saved 208 formations and 2,288 verified starters.',flush=True)


if __name__=='__main__':capture()
