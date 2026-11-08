"""FOURWARD coursework | Author: 韩羽宸 | Capture official DQD six-axis player abilities."""
from __future__ import annotations
import argparse, concurrent.futures, hashlib, json, subprocess, time
from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]
HEADSHOTS=ROOT/'data/worldcup/player-photos/headshots.json'
SUPPLEMENTAL=ROOT/'data/worldcup/player-supplemental-dqd.json'
OUTPUT=ROOT/'data/worldcup/player-abilities.json'
LABELS={'速度':'pace','射门':'shooting','传球':'passing','盘带':'dribbling','防守':'defending','力量':'physical'}
DEFAULT_NAMES={
  'CRISTIANO RONALDO','Lionel MESSI','Kylian MBAPPE','Erling HAALAND','Jude BELLINGHAM',
  'Lamine YAMAL','Harry KANE','Kevin DE BRUYNE','MOHAMED SALAH','PEDRI','RODRI',
  'Romelu LUKAKU','Thibaut COURTOIS','VINICIUS JUNIOR','Virgil VAN DIJK','Bukayo SAKA',
  'BRUNO FERNANDES','Lautaro MARTINEZ','Rodrigo DE PAUL',
}

def fetch(item:tuple[str,dict])->tuple[str,dict|None,str|None]:
  fifa_id,player=item;dqd_id=str(player['dqd_id']);page_url=f'https://pc.dongqiudi.com/player/{dqd_id}'
  raw_id=str(int(dqd_id)-50_000_000);url=f'https://api.dongqiudi.com/soccer/data/sofifa/v1/player_ability/{raw_id}?player_type='
  try:
    result=subprocess.run(['curl.exe','--fail','--silent','--show-error','--location','--compressed','--max-time','15',
      '--header','Accept-Language: zh-CN,zh;q=0.9','--user-agent','Mozilla/5.0 FOURWARD-coursework/1.0',url],
      check=True,capture_output=True)
    payload=json.loads(result.stdout);data=payload.get('data') or {};radar=data.get('redar') or []
    score=(data.get('average') or {}).get('val');values={LABELS[r['name']]:int(r['val']) for r in radar if r.get('name') in LABELS}
    if score is None or set(values)!=set(LABELS.values()):return fifa_id,None,'ability unavailable'
    all_values=[int(score),*values.values()]
    if any(value<0 or value>100 for value in all_values):return fifa_id,None,'ability outside 0..100'
    return fifa_id,{'name':player['name'],'dqd_name':player['dqd_name'],'dqd_id':dqd_id,'overall':int(score),
      **values,'source_url':page_url,'data_url':url,'source_sha256':hashlib.sha256(result.stdout).hexdigest()},None
  except Exception as exc:return fifa_id,None,type(exc).__name__

def main()->None:
  parser=argparse.ArgumentParser();parser.add_argument('--all',action='store_true');parser.add_argument('--workers',type=int,default=6);args=parser.parse_args()
  source=json.loads(HEADSHOTS.read_text(encoding='utf-8'))['players']|json.loads(SUPPLEMENTAL.read_text(encoding='utf-8'))['players']
  if len(source)!=1248:raise ValueError('DQD identity coverage must include all 1248 players')
  selected=list(source.items()) if args.all else [(key,value) for key,value in source.items() if value['name'] in DEFAULT_NAMES]
  players={};failures={}
  with concurrent.futures.ThreadPoolExecutor(max_workers=max(1,min(args.workers,12))) as pool:
    futures=[pool.submit(fetch,item) for item in selected]
    for done,future in enumerate(concurrent.futures.as_completed(futures),1):
      fifa_id,entry,error=future.result()
      if entry:players[fifa_id]=entry
      else:failures[fifa_id]=error
      if done%50==0 or done==len(futures):print(f'Ability profiles: {done}/{len(futures)}; available {len(players)}; unavailable {len(failures)}',flush=True)
  payload={'edition':2026,'provider':'懂球帝','captured_at':time.strftime('%Y-%m-%dT%H:%M:%SZ',time.gmtime()),
    'axes':['pace','shooting','passing','dribbling','defending','physical'],
    'method':'Values copied from the official JSON endpoint used by each DQD player ability page; no values derived or imputed.',
    'requested':len(selected),'available':len(players),'unavailable':len(failures),'players':dict(sorted(players.items())),'failures':dict(sorted(failures.items()))}
  OUTPUT.write_text(json.dumps(payload,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
  print(f'Saved {len(players)}/{len(selected)} verified DQD ability profiles to {OUTPUT}')

if __name__=='__main__':main()
