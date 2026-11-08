"""Supplement unresolved Chinese names via exact Wikidata English-label search."""
import concurrent.futures, json, re, subprocess, time, unicodedata
from worldcup_data import ROOT

BASE=ROOT/'data/worldcup/player-names.json';OUTPUT=ROOT/'data/worldcup/player-full-names.json';API='https://www.wikidata.org/w/api.php'

def norm(value):return ''.join(c for c in unicodedata.normalize('NFKD',value).casefold() if c.isalnum())
def title_case(name):return ' '.join(part if any(c.islower() for c in part) else part.title() for part in name.split())
def clean(value):
    if not isinstance(value,str):return None
    value=re.sub(r'\s*[（(][^()（）]*(?:足球|球员|運動員|运动员)[^()（）]*[)）]\s*$','',value).strip()
    return value if re.search(r'[\u3400-\u9fff]',value) and 1<len(value)<=60 else None
def curl(params):
    args=['curl.exe','--fail','--silent','--show-error','--location','--compressed','--max-time','25','--retry','3','--retry-all-errors','--get','--user-agent','WorldCup2026DB-coursework/1.0']
    for key,value in params.items():args+=['--data-urlencode',f'{key}={value}']
    args.append(API);return json.loads(subprocess.run(args,check=True,capture_output=True).stdout)
def search(item):
    pid,name=item;query=title_case(name)
    try:results=curl({'action':'wbsearchentities','search':query,'language':'en','type':'item','limit':'8','format':'json'}).get('search',[])
    except Exception:return pid,None
    exact=[row for row in results if norm(row.get('label',''))==norm(query) and 'football' in row.get('description','').casefold()]
    return pid,exact[0]['id'] if len(exact)==1 else None

def main():
    base=json.loads(BASE.read_text(encoding='utf-8'))['players'];snapshot=json.loads(OUTPUT.read_text(encoding='utf-8'));players=snapshot.get('players',{})
    unresolved=[(pid,p['name_en']) for pid,p in base.items() if pid not in players];found={}
    with concurrent.futures.ThreadPoolExecutor(max_workers=6) as pool:
        for done,(pid,qid) in enumerate(pool.map(search,unresolved),1):
            if qid:found[pid]=qid
            if done%50==0 or done==len(unresolved):print(f'Exact Wikidata search: {done}/{len(unresolved)}; identities {len(found)}',flush=True)
    qids=list(dict.fromkeys(found.values()));entities={}
    for i in range(0,len(qids),50):
        payload=curl({'action':'wbgetentities','ids':'|'.join(qids[i:i+50]),'props':'labels|sitelinks','languages':'zh-cn|zh-hans|zh','sitefilter':'zhwiki','format':'json'})
        entities.update(payload.get('entities',{}));time.sleep(1)
    added=0
    for pid,qid in found.items():
        entity=entities.get(qid,{ });labels=entity.get('labels') or {}
        zh=(labels.get('zh-cn') or labels.get('zh-hans') or labels.get('zh') or {}).get('value') or ((entity.get('sitelinks') or {}).get('zhwiki') or {}).get('title')
        if clean(zh):players[pid]={'full_name_zh':clean(zh),'wikidata_id':qid,'source':'Exact Wikidata English-label footballer search'};added+=1
    snapshot.update({'provider':'Wikidata/Wikipedia + FIFA','available':len(players),'unavailable':1248-len(players),'players':dict(sorted(players.items()))})
    OUTPUT.write_text(json.dumps(snapshot,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
    print(f'Added {added} exact-search Chinese names; total {len(players)}/1248.')

if __name__=='__main__':main()
