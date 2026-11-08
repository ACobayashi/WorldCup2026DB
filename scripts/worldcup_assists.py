"""Capture the complete non-zero 2026 World Cup assist ranking from DQD and map it to FIFA squad IDs."""
import hashlib, json, subprocess, time
from worldcup_data import ROOT, require

URL='https://sport-data.dongqiudi.com/soccer/biz/data/person_ranking?app=dqd&version=0&platform=&type=assists&season_id=26123'
OUTPUT=ROOT/'data/worldcup/player-assists.json'

def main():
    identities=json.loads((ROOT/'data/worldcup/player-names.json').read_text(encoding='utf-8'))['players']
    reverse={str(v['dqd_id']):pid for pid,v in identities.items()}
    require(len(reverse)==len(identities)==1248,'DQD player IDs must be unique and complete')
    result=subprocess.run(['curl.exe','--fail','--silent','--show-error','--location','--max-time','30',
        '--header','Accept-Language: zh-CN,zh;q=0.9','--user-agent','Mozilla/5.0 FOURWARD-coursework/1.0',URL],check=True,capture_output=True)
    payload=json.loads(result.stdout)
    rows=payload['content']['data'];require(isinstance(rows,list) and rows,'Empty assist ranking')
    players={};unmatched=[]
    for row in rows:
        dqd_id=str(row['person_id']);count=int(row['count'])
        require(count>0,'Assist ranking unexpectedly contains zero')
        fifa_id=reverse.get(dqd_id)
        if fifa_id is None:unmatched.append({'dqd_id':dqd_id,'name':row.get('person_name'),'count':count});continue
        require(fifa_id not in players,'Duplicate assist ranking player')
        players[fifa_id]={'assists':count,'dqd_id':dqd_id,'dqd_name':row['person_name'],'team_name':row.get('team_name')}
    require(not unmatched,'Non-zero assist rows failed roster identity mapping: '+json.dumps(unmatched,ensure_ascii=False))
    output={'edition':2026,'season_id':'26123','provider':'懂球帝','source_url':URL,
        'captured_at':time.strftime('%Y-%m-%dT%H:%M:%SZ',time.gmtime()),'source_sha256':hashlib.sha256(result.stdout).hexdigest(),
        'roster_total':len(identities),'nonzero_players':len(players),'zero_semantics':'Players absent from the complete non-zero ranking have 0 assists.',
        'players':dict(sorted(players.items()))}
    OUTPUT.write_text(json.dumps(output,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
    print(f'Saved {len(players)} non-zero assist totals for the {len(identities)}-player roster.')

if __name__=='__main__':main()
