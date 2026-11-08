"""Supplement full Chinese names with FIFA's official zh-CN tournament squads."""
import concurrent.futures, json, re, subprocess
from worldcup_data import ROOT, require

OUTPUT=ROOT/'data/worldcup/player-full-names.json'
CORE=ROOT/'data/worldcup/full/core.json'

def fetch(team):
    team_id=team['id'].split(':')[-1]
    url=f'https://api.fifa.com/api/v3/teams/{team_id}/squad?idCompetition=17&idSeason=285023&language=zh-CN'
    result=subprocess.run(['curl.exe','--fail','--silent','--show-error','--location','--compressed','--max-time','25','--retry','3',url],check=True,capture_output=True)
    payload=json.loads(result.stdout);require(str(payload['IdTeam'])==team_id,'FIFA team identity changed')
    return url,payload['Players']

def main():
    core=json.loads(CORE.read_text(encoding='utf-8'));snapshot=json.loads(OUTPUT.read_text(encoding='utf-8'))
    players=snapshot.get('players',{});added=0
    with concurrent.futures.ThreadPoolExecutor(max_workers=8) as pool:
        for url,rows in pool.map(fetch,core['teams']):
            for row in rows:
                fifa_id=str(row['IdPlayer']);name=(row.get('PlayerName') or [{}])[0]
                value=name.get('Description','').strip()
                if name.get('Locale')!='zh-CN' or not re.search(r'[\u3400-\u9fff]',value) or len(value)>60:continue
                if fifa_id not in players:
                    players[fifa_id]={'full_name_zh':value,'source':'FIFA official zh-CN tournament squad','source_url':url};added+=1
    require('430669' in players,'Wirtz Wikidata override must be retained')
    snapshot.update({'provider':'Wikidata/Wikipedia + FIFA','method':'Prefer exact English-Wikipedia-to-Chinese identity links; supplement missing entries with exact FIFA player IDs from official zh-CN tournament squads. No transliteration generated.',
      'available':len(players),'unavailable':1248-len(players),'players':dict(sorted(players.items()))})
    snapshot.pop('partial',None);OUTPUT.write_text(json.dumps(snapshot,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
    print(f'Added {added} FIFA zh-CN full names; total {len(players)}/1248.')

if __name__=='__main__':main()
