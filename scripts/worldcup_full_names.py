"""Capture full Chinese player names from Wikidata's English-to-Chinese identity links."""
import json, re, subprocess, time
from pathlib import Path
from worldcup_data import ROOT, require

SOURCE=ROOT/'data/worldcup/player-names.json'
OUTPUT=ROOT/'data/worldcup/player-full-names.json'
API='https://www.wikidata.org/w/api.php'

def title_case(name):
    return ' '.join(part if any(c.islower() for c in part) else part.title() for part in name.split())

def clean(value):
    if not isinstance(value,str):return None
    value=re.sub(r'\s*[（(][^()（）]*(?:足球|球员|運動員|运动员)[^()（）]*[)）]\s*$','',value).strip()
    return value if re.search(r'[\u3400-\u9fff]',value) and 1<len(value)<=60 else None

def fetch(titles):
    args=['curl.exe','--fail','--silent','--show-error','--location','--compressed','--max-time','30','--retry','4','--retry-all-errors','--retry-delay','2',
      '--user-agent','WorldCup2026DB-coursework/1.0','--get',
      '--data-urlencode','action=wbgetentities','--data-urlencode','sites=enwiki','--data-urlencode','titles='+'|'.join(titles),
      '--data-urlencode','props=labels|sitelinks','--data-urlencode','languages=zh-cn|zh-hans|zh','--data-urlencode','sitefilter=enwiki|zhwiki',
      '--data-urlencode','redirects=yes','--data-urlencode','format=json',API]
    for attempt in range(3):
        try:return json.loads(subprocess.run(args,check=True,capture_output=True).stdout)
        except Exception:
            if attempt==2:raise
            time.sleep(1+attempt)

def main():
    source=json.loads(SOURCE.read_text(encoding='utf-8'))['players']
    requested={pid:title_case(p['name_en']) for pid,p in source.items()}
    resolved={};batches=[list(requested.items())[i:i+50] for i in range(0,len(requested),50)]
    for number,batch in enumerate(batches,1):
        titles=[title for _,title in batch];payload=fetch(titles)
        aliases={x['from']:x['to'] for key in ['normalized','redirects'] for x in payload.get(key,[])}
        by_title={}
        for entity in payload.get('entities',{}).values():
            en=((entity.get('sitelinks') or {}).get('enwiki') or {}).get('title')
            labels=entity.get('labels') or {}
            zh=(labels.get('zh-cn') or labels.get('zh-hans') or labels.get('zh') or {}).get('value') or ((entity.get('sitelinks') or {}).get('zhwiki') or {}).get('title')
            if en and clean(zh):by_title[en]=clean(zh)
        for pid,title in batch:
            seen=set()
            while title in aliases and title not in seen:seen.add(title);title=aliases[title]
            if title in by_title:resolved[pid]={'full_name_zh':by_title[title],'enwiki_title':title,'source':'Wikidata zhwiki sitelink or zh label'}
        partial={'edition':2026,'provider':'Wikidata/Wikipedia','partial':number<len(batches),'requested':len(source),'available':len(resolved),'players':dict(sorted(resolved.items()))}
        OUTPUT.write_text(json.dumps(partial,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
        print(f'Full Chinese names: batch {number}/{len(batches)}; mapped {len(resolved)}/{len(source)}',flush=True);time.sleep(1)
    require(resolved.get('430669',{}).get('full_name_zh')=='弗洛里安·维尔茨','Wirtz full-name identity failed')
    haaland=next((pid for pid,p in source.items() if p['name_en']=='Erling HAALAND'),None)
    require(haaland and resolved.get(haaland,{}).get('full_name_zh')=='埃尔林·哈兰德','Haaland full-name identity failed')
    output={'edition':2026,'provider':'Wikidata/Wikipedia','method':'Exact English Wikipedia title identity resolved to its Chinese sitelink or Chinese Wikidata label; no transliteration generated.',
      'requested':len(source),'available':len(resolved),'unavailable':len(source)-len(resolved),'players':dict(sorted(resolved.items()))}
    OUTPUT.write_text(json.dumps(output,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
    print(f'Saved {len(resolved)}/{len(source)} verified full Chinese names.')

if __name__=='__main__':main()
