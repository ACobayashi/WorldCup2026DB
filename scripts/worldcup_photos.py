"""FOURWARD coursework | Author: 韩羽宸 | Resume and validate FIFA-ID-linked player portrait thumbnails."""
import argparse
import hashlib
import io
import json
import re
import time
import urllib.error
import urllib.parse
import urllib.request
from concurrent.futures import ThreadPoolExecutor,as_completed
from datetime import datetime,timezone
from pathlib import Path
from PIL import Image
from worldcup_data import ROOT,encode_json,require,digest
from worldcup_data import normalized_name

DATA=ROOT/'data/worldcup/player-photos'
CACHE=ROOT/'public/assets/images/player-photos'
TRANSFORM='io=transform:fit,width:320,height:480&format=webp&quality=82'
MAX_BYTES=2_000_000

def validated_url(url,player_id,name=None):
    require(isinstance(url,str),'Missing photo URL')
    parsed=urllib.parse.urlsplit(url)
    require(parsed.scheme=='https' and parsed.netloc=='digitalhub.fifa.com' and not parsed.query and not parsed.fragment,'Unapproved portrait host or URL')
    require(re.fullmatch(r'/transform/[0-9a-fA-F-]{36}/[^\r\n]+',parsed.path) is not None,'Unexpected portrait asset path')
    slug=urllib.parse.unquote(parsed.path.rsplit('/',1)[-1]);match=re.fullmatch(r'(.+?)_(\d+)(?:_\d*)?',slug)
    require(match is not None,'Portrait filename has no identity')
    if match[2]!=player_id:
        tokens=lambda s:sorted(normalized_name(t) for t in re.split(r'[\s-]+',s) if t)
        require(name is not None and tokens(match[1])==tokens(name),'Portrait filename identifies another player')
    return urllib.parse.urlunsplit(('https',parsed.netloc,urllib.parse.quote(parsed.path,safe='/%-_.'),TRANSFORM,''))

def capture(directory):
    core=json.loads((ROOT/'data/worldcup/full/core.json').read_text(encoding='utf-8'))
    expected={p['id'].split(':')[-1]:p for p in core['players']};players={};sources={}
    for path in sorted(directory.glob('*.json')):
        if '.meta.' in path.name:continue
        content=path.read_bytes();squad=json.loads(content)
        require(str(squad['IdSeason'])=='285023','Wrong source edition')
        sources[squad['IdCountry']]={'payload_sha256':digest(content),'url':f"https://api.fifa.com/api/v3/teams/{squad['IdTeam']}/squad?idCompetition=17&idSeason=285023&language=en-US"}
        for p in squad['Players']:
            pid=str(p['IdPlayer']);require(pid in expected and pid not in players,'Duplicate/unexpected player ID')
            require(str(p['IdTeam'])==expected[pid]['team_id'].split(':')[-1],'Player team changed')
            require(p['PlayerName'][0]['Description']==expected[pid]['name'],'Player name changed')
            photo=p.get('PlayerPicture') or {};url=photo.get('PictureUrl')
            entry={'name':expected[pid]['name'],'team_code':squad['IdCountry'],'source_url':url,'asset_id':photo.get('Id')}
            try:
                entry['download_url']=validated_url(url,pid,entry['name']) if url else None
                entry['identity_check']='FIFA squad player association; matching asset player ID or exact normalized full-name tokens.'
            except ValueError as error:
                entry['download_url']=None;entry['identity_error']=str(error)
            players[pid]=entry
    require(set(players)==set(expected) and len(sources)==48,'Incomplete portrait catalog')
    DATA.mkdir(parents=True,exist_ok=True)
    (DATA/'catalog.json').write_bytes(encode_json({'edition':2026,'fifa_season':'285023','squad_sources':sources,'players':players,
        'transform_documentation':'https://support.bynder.com/hc/en-us/articles/18953104053266-Create-a-DAT-Derivative-Using-URL-Parameters',
        'rights':'FIFA/individual rights holders; image redistribution rights not established; local cache excluded from Git.'}))

def inspect_image(payload):
    require(0<len(payload)<=MAX_BYTES,'Unexpected image size')
    with Image.open(io.BytesIO(payload)) as im:
        require(im.format in ['WEBP','PNG','JPEG'],'Unsupported image content')
        require(32<=im.width<=1024 and 32<=im.height<=1024,'Unexpected thumbnail dimensions')
        im.verify()
    with Image.open(io.BytesIO(payload)) as im:
        im.load()
        return {'width':im.width,'height':im.height,'format':im.format,'extension':{'WEBP':'webp','PNG':'png','JPEG':'jpg'}[im.format]}

def safe_file(filename):
    require(isinstance(filename,str) and re.fullmatch(r'\d+\.(webp|png|jpg)',filename) is not None,'Unsafe portrait filename')
    result=CACHE/filename;require(result.resolve().parent==CACHE.resolve(),'Portrait escaped cache')
    return result

def atomic_json(path,value):
    temp=path.with_suffix('.json.tmp');temp.write_bytes(encode_json(value));temp.replace(path)

def approved_download(entry):
    if entry.get('kind')=='headshot':
        from worldcup_headshots import headshot_url
        return headshot_url(entry['download_url'])
    pid=entry['download_url'].rsplit('/',1)[-1].split('?')[0]
    source=entry['source_url'];parsed=urllib.parse.urlsplit(source)
    require(parsed.netloc=='digitalhub.fifa.com' and parsed.scheme=='https','Unapproved FIFA download')
    require(entry['download_url'].split('?')[0]==source and entry['download_url'].split('?',1)[-1]==TRANSFORM,'Unexpected FIFA transform')
    return entry['download_url']

def collect(catalog,workers=6,attempts=3,only=None):
    CACHE.mkdir(parents=True,exist_ok=True);report_path=DATA/'availability.json'
    previous=json.loads(report_path.read_text(encoding='utf-8')).get('players',{}) if report_path.exists() else {}
    results={k:v for k,v in previous.items() if k in catalog['players']}
    targets=[(k,v) for k,v in catalog['players'].items() if only is None or k in only]
    def fetch(pid,entry):
        old=previous.get(pid)
        if old and old.get('status')=='available' and old.get('source_url')==entry['source_url']:
            try:
                file=safe_file(old['file']);require(file.stem==pid,'Cache ID mismatch')
                payload=file.read_bytes();require(digest(payload)==old['sha256'],'Cached checksum changed');inspect_image(payload)
                return pid,old
            except (ValueError,OSError):pass
        if not entry['download_url']:return pid,{'status':'unavailable','reason':entry.get('identity_error','No FIFA portrait URL'),'source_url':entry['source_url']}
        for attempt in range(attempts):
            try:
                request=urllib.request.Request(approved_download(entry),headers={'User-Agent':'WorldCup2026DB-coursework/1.0'})
                with urllib.request.urlopen(request,timeout=18) as response:
                    expected_host=urllib.parse.urlsplit(entry['download_url']).netloc
                    require(expected_host in ['digitalhub.fifa.com','sd.qunliao.info','img1.qunliao.info','img2.qunliao.info'],'Unapproved image host')
                    require(urllib.parse.urlsplit(response.geturl()).netloc==expected_host,'Unexpected image redirect')
                    require(response.headers.get('Content-Type','').split(';')[0] in ['image/webp','image/png','image/jpeg'],'Non-image response')
                    payload=response.read(MAX_BYTES+1)
                meta=inspect_image(payload);filename=pid+'.'+meta.pop('extension');file=safe_file(filename)
                temporary=file.with_suffix(file.suffix+'.tmp');temporary.write_bytes(payload);temporary.replace(file)
                return pid,{'status':'available','file':filename,'sha256':digest(payload),'bytes':len(payload),**meta,'source_url':entry['source_url'],
                            'kind':entry.get('kind','full-body'),'retrieved_at':datetime.now(timezone.utc).isoformat()}
            except Exception as error:
                reason=type(error).__name__+': '+str(error)
                if isinstance(error,urllib.error.HTTPError) and error.code in [400,401,403,404]:break
                if attempt+1<attempts:time.sleep(min(8,2**attempt))
        return pid,{'status':'unavailable','reason':reason,'source_url':entry['source_url'],'checked_at':datetime.now(timezone.utc).isoformat()}
    def save():
        available=sum(v.get('status')=='available' for v in results.values())
        atomic_json(report_path,{'edition':2026,'catalog_sha256':digest(encode_json(catalog)),'total':len(catalog['players']),
                                'available':available,'unavailable':len(results)-available,'pending':len(catalog['players'])-len(results),'players':results})
    started=time.monotonic();last=started
    with ThreadPoolExecutor(max_workers=workers) as pool:
        tasks={pool.submit(fetch,pid,entry):pid for pid,entry in targets}
        for done,future in enumerate(as_completed(tasks),1):
            pid,result=future.result();results[pid]=result
            if done%20==0 or time.monotonic()-last>=10 or done==len(tasks):
                save();last=time.monotonic();print(json.dumps({'completed':done,'requested':len(tasks),'available':sum(v.get('status')=='available' for v in results.values()),'failed':sum(v.get('status')=='unavailable' for v in results.values()),'seconds':round(last-started)}),flush=True)
    save()
    return results

def main():
    parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('--capture-from',type=Path);parser.add_argument('--workers',type=int,default=6);parser.add_argument('--attempts',type=int,default=3);parser.add_argument('--only',nargs='+');args=parser.parse_args()
    require(1<=args.workers<=8 and 1<=args.attempts<=5,'Invalid download bounds')
    if args.capture_from:capture(args.capture_from)
    catalog=json.loads((DATA/'catalog.json').read_text(encoding='utf-8'));require(catalog['fifa_season']=='285023','Wrong edition')
    if args.only:require(set(args.only)<=set(catalog['players']),'Unknown requested FIFA ID')
    collect(catalog,args.workers,args.attempts,set(args.only) if args.only else None)

if __name__=='__main__':main()
