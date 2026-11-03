"""FOURWARD coursework | Author: 韩羽宸 | Reproducible full-edition core-data normalization."""
import argparse
import csv
import json
import re
from datetime import datetime, timezone, timedelta
from pathlib import Path
from worldcup_data import ROOT, require, digest, encode_json, localized, normalized_name, integer

DIRECTORY = ROOT/'data/worldcup/full'
CHINESE = dict(item.split(':',1) for item in '''ALG:阿尔及利亚 ARG:阿根廷 AUS:澳大利亚 AUT:奥地利 BEL:比利时 BIH:波黑 BRA:巴西 CPV:佛得角 CAN:加拿大 COL:哥伦比亚 COD:刚果民主共和国 CIV:科特迪瓦 CRO:克罗地亚 CUW:库拉索 CZE:捷克 ECU:厄瓜多尔 EGY:埃及 ENG:英格兰 FRA:法国 GER:德国 GHA:加纳 HAI:海地 IRN:伊朗 IRQ:伊拉克 JPN:日本 JOR:约旦 KOR:韩国 MEX:墨西哥 MAR:摩洛哥 NED:荷兰 NZL:新西兰 NOR:挪威 PAN:巴拿马 PAR:巴拉圭 POR:葡萄牙 QAT:卡塔尔 KSA:沙特阿拉伯 SCO:苏格兰 SEN:塞内加尔 RSA:南非 ESP:西班牙 SWE:瑞典 SUI:瑞士 TUN:突尼斯 TUR:土耳其 URU:乌拉圭 USA:美国 UZB:乌兹别克斯坦'''.split())
STAGES = {'289273': ('group','小组赛',1), '289287': ('round32','三十二强',2),
          '289288': ('round16','十六强',3), '289289': ('quarter','四分之一决赛',4),
          '289290': ('semi','半决赛',5), '289291': ('third','季军赛',6), '289292': ('final','决赛',7)}
ZONES = {'Mexico City':'America/Mexico_City','Guadalajara':'America/Mexico_City',
         'Monterrey':'America/Monterrey','Toronto':'America/Toronto','Vancouver':'America/Vancouver',
         'Los Angeles':'America/Los_Angeles','San Francisco Bay Area':'America/Los_Angeles',
         'Seattle':'America/Los_Angeles','New Jersey':'America/New_York','Boston':'America/New_York',
         'Philadelphia':'America/New_York','Atlanta':'America/New_York','Miami':'America/New_York',
         'Houston':'America/Chicago','Dallas':'America/Chicago','Kansas City':'America/Chicago'}
ALIASES = {'Bosnia & Herzegovina':'BIH','Cape Verde':'CPV','Czech Republic':'CZE','DR Congo':'COD',
           'Iran':'IRN','Ivory Coast':'CIV','South Korea':'KOR','Turkey':'TUR'}


def capture(cache, directory=DIRECTORY):
    """Select core fields; record projections separately from original payload hashes."""
    raw = directory/'raw'; raw.mkdir(parents=True,exist_ok=True)
    manifest={'edition':2026,'fifa_season':'285023','files':{}}
    def save(name, content, parent, url, license_name, retrieval=None):
        payload=encode_json(content); (raw/name).write_bytes(payload)
        manifest['files'][name]={'sha256':digest(payload),'audit_payload_sha256':digest(parent.read_bytes()),
                                 'source_url':url,'license':license_name,'retrieved_at':retrieval,
                                 'cache_saved_at':datetime.fromtimestamp(parent.stat().st_mtime,timezone.utc).isoformat(),
                                 'projection':'Core football fields only; no birth dates, body measurements, photos or clubs.'}
    def read(name): return json.loads((cache/name).read_text(encoding='utf-8'))
    fifa=[]
    for row in read('fifa_matches.json')['Results']:
        record={k:row[k] for k in ['IdMatch','IdSeason','MatchNumber','IdStage','IdGroup','StageName','GroupName',
                                  'Date','MatchStatus','ResultType','HomeTeamScore','AwayTeamScore',
                                  'HomeTeamPenaltyScore','AwayTeamPenaltyScore','Winner']}
        for side in ['Home','Away']: record[side]={k:row[side][k] for k in ['IdTeam','IdCountry','TeamName']}
        record['Stadium']={k:row['Stadium'][k] for k in ['IdStadium','Name','CityName','IdCountry']}
        fifa.append(record)
    save('matches.json',fifa,cache/'fifa_matches.json','https://api.fifa.com/api/v3/calendar/matches?idSeason=285023&idCompetition=17&count=200&language=en-US','FIFA first-party response; no media')
    for file in sorted((cache/'squads').glob('*.json')):
        if '.meta.' in file.name: continue
        row=json.loads(file.read_text(encoding='utf-8')); meta=json.loads(file.with_suffix('.meta.json').read_text(encoding='utf-8'))
        squad={k:row[k] for k in ['IdTeam','IdSeason','IdCountry']}
        squad['Players']=[{k:p[k] for k in ['IdPlayer','IdTeam','IdSeason','PlayerName','JerseyNum','PositionLocalized']} for p in row['Players']]
        save('squad-'+row['IdCountry']+'.json',squad,file,meta['url'],'FIFA first-party response; no media',meta['retrieved_at'])
    for name,parent in [('openfootball-matches.json','openfootball_matches_api.json'),('openfootball-full.json','openfootball_full_api.json')]:
        save(name,read(parent),cache/parent,'https://github.com/openfootball/worldcup.json/blob/master/2026/'+('worldcup-full.json' if 'full' in name else 'worldcup.json'),'CC0-1.0')
    # Detail source rows remain verbatim except intentionally omitted fields.
    fields={'matches':None,
            'appearances':['appearance_id','appeared','is_starter','match_id','player_id','player_name','position','shirt_number','team_id','team_role'],
            'player_stats':['appearance_id','attempts_at_goal','goals','match_id','pass_completion_pct','passes_attempted','passes_completed','player_id','player_name','shirt_number','team_id'],
            'team_stats':['match_id','metric','raw','team_id','unit','value'],
            'events':['appearance_id','event_id','event_type','match_id','minute','minute_raw','player_id','player_name','shirt_number','team_id']}
    upstream={'matches':'matches','appearances':'match_appearances','player_stats':'player_in_possession_distributions','team_stats':'team_key_stats','events':'player_events'}
    for key,selection in fields.items():
        file=cache/('advanced_'+key+'.csv')
        with file.open(encoding='utf-8') as stream: rows=[{k:v for k,v in r.items() if selection is None or k in selection} for r in csv.DictReader(stream)]
        save('report-'+key+'.json',rows,file,'https://github.com/Alamyy/Worldcup26/blob/main/data/csv/'+upstream[key]+'.csv','CC-BY-4.0')
    (directory/'manifest.json').write_bytes(encode_json(manifest))


def load(directory=DIRECTORY):
    manifest=json.loads((directory/'manifest.json').read_text(encoding='utf-8'))
    require(manifest['edition']==2026 and manifest['fifa_season']=='285023','Wrong edition manifest')
    expected={'matches.json','openfootball-matches.json','openfootball-full.json'} | {'squad-'+c+'.json' for c in CHINESE} | {'report-'+key+'.json' for key in ['matches','appearances','player_stats','team_stats','events']}
    require(set(manifest['files'])==expected,'Missing full snapshot file')
    bundle={}
    for name,metadata in manifest['files'].items():
        payload=(directory/'raw'/name).read_bytes(); require(digest(payload)==metadata['sha256'],'Checksum mismatch: '+name)
        bundle[name]=json.loads(payload)
    return bundle,manifest


def kickoff(row):
    match=re.fullmatch(r'(\d{2}:\d{2}) UTC([+-]\d+)',row['time'])
    require(match is not None,'Invalid openfootball timezone')
    dt=datetime.fromisoformat(row['date']+'T'+match[1]).replace(tzinfo=timezone(timedelta(hours=int(match[2]))))
    return dt.astimezone(timezone.utc).isoformat().replace('+00:00','Z')


def build_core(bundle,manifest):
    official=bundle['matches.json']; require(len(official)==104,'Expected 104 official matches')
    require({r['MatchNumber'] for r in official}==set(range(1,105)),'Duplicate/missing match number')
    teams={}; groups={}; memberships={}; venues={}; names={normalized_name(n):c for n,c in ALIASES.items()}
    for r in official:
        require(r['IdSeason']=='285023','Wrong match edition')
        for side in ['Home','Away']:
            t=r[side];code=t['IdCountry'];name=localized(t['TeamName']);names[normalized_name(name)]=code
            candidate={'id':'fifa:team:'+t['IdTeam'],'code':code,'name':name,'name_zh':CHINESE[code]}
            require(code not in teams or teams[code]==candidate,'Inconsistent team identity'); teams[code]=candidate
            if r['IdGroup']:
                group=localized(r['GroupName']).removeprefix('Group ')
                require(code not in memberships or memberships[code]==group,'Inconsistent group')
                memberships[code]=group; groups[group]={'code':group,'name':group+'组','sort_order':ord(group)-64}
        v=r['Stadium'];city=localized(v['CityName']);venues[v['IdStadium']]={'id':'fifa:venue:'+v['IdStadium'],'name':localized(v['Name']),'city':city,'country':v['IdCountry'],'timezone':ZONES[city]}
    require(set(teams)==set(CHINESE) and len(groups)==12 and len(venues)==16 and len(memberships)==48,'Incomplete edition catalogs')
    require(all(sum(g==value for value in memberships.values())==4 for g in groups),'Each group needs four teams')
    def index(rows):
        result={}
        for r in rows:
            key=(kickoff(r),names[normalized_name(r['team1'])],names[normalized_name(r['team2'])])
            require(key not in result,'Duplicate openfootball fixture');result[key]=r
        require(len(result)==104,'Incomplete independent fixture reference')
        return result
    primary=index(bundle['openfootball-matches.json']['matches']); detailed=index(bundle['openfootball-full.json']['matches'])
    matches=[]; full_matches={}; time_conflicts=[]; halftime_conflicts=[]
    for r in sorted(official,key=lambda r:r['MatchNumber']):
        home=r['Home']['IdCountry'];away=r['Away']['IdCountry'];key=(r['Date'],home,away)
        require(key in detailed,'Cannot align detailed fixture '+str(r['MatchNumber']))
        independent=primary.get(key)
        if independent is None:
            alternatives=[v for k,v in primary.items() if v.get('num')==r['MatchNumber'] and k[1:]==key[1:]]
            require(len(alternatives)==1,'Cannot align numbered fixture '+str(r['MatchNumber']))
            independent=alternatives[0]
            difference=abs((datetime.fromisoformat(kickoff(independent))-datetime.fromisoformat(r['Date'])).total_seconds())
            require(difference<=3600,'Unexpected independent kickoff difference')
            time_conflicts.append({'match_no':r['MatchNumber'],'official_utc':r['Date'],'openfootball_basic_utc':kickoff(independent),
                                   'resolution':'Use FIFA UTC, independently agreed by openfootball detailed snapshot; retain basic-time discrepancy.'})
        score=independent['score']
        require({k:score.get(k) for k in ['ft','et','p']}=={k:detailed[key]['score'].get(k) for k in ['ft','et','p']},'Independent result breakdown disagreement')
        if score.get('ht')!=detailed[key]['score'].get('ht'):
            halftime_conflicts.append({'match_no':r['MatchNumber'],'basic':score.get('ht'),'detailed':detailed[key]['score'].get('ht'),'resolution':'Half-time scores are outside the import contract; neither conflicting value is imported.'})
        final=score.get('et',score['ft']); penalties=score.get('p',[None,None]); et=score.get('et',[None,None])
        require(final==[r['HomeTeamScore'],r['AwayTeamScore']],'Official/final score disagreement')
        require(penalties==[r['HomeTeamPenaltyScore'],r['AwayTeamPenaltyScore']],'Penalty disagreement')
        require(r['MatchStatus']==0 and r['ResultType']==(2 if 'p' in score else 3 if 'et' in score else 1),'Unverified source result/status combination')
        deciding=penalties if 'p' in score else final
        winner=teams[home]['id'] if deciding[0]>deciding[1] else teams[away]['id'] if deciding[1]>deciding[0] else None
        require((winner.split(':')[-1] if winner else None)==r['Winner'],'Winner disagreement')
        stage=STAGES[r['IdStage']][0];group=memberships[home] if stage=='group' else None
        match={'id':'fifa:match:'+r['IdMatch'],'match_no':r['MatchNumber'],'stage_code':stage,'group_code':group,
               'home_team_id':teams[home]['id'],'away_team_id':teams[away]['id'],'kickoff_at':r['Date'],
               'venue_id':'fifa:venue:'+r['Stadium']['IdStadium'],'status':'finished',
               'home_score_90':score['ft'][0],'away_score_90':score['ft'][1],
               'home_score_et':et[0],'away_score_et':et[1],'home_penalty_score':penalties[0],'away_penalty_score':penalties[1],
               'winner_team_id':winner}
        matches.append(match);full_matches[r['MatchNumber']]=detailed[key]
    positions={'Goalkeeper':'GK','Defender':'DF','Midfielder':'MF','Forward':'FW'}
    players=[]; seen=set()
    for code in sorted(teams):
        squad=bundle['squad-'+code+'.json'];require(squad['IdSeason']=='285023' and squad['IdCountry']==code and 'fifa:team:'+squad['IdTeam']==teams[code]['id'],'Wrong squad identity')
        shirts=set();require(len(squad['Players'])==26,'Unexpected official squad size')
        for p in squad['Players']:
            shirt=integer(p['JerseyNum']);require(shirt not in shirts and shirt is not None and 1<=shirt<=99,'Invalid roster number');shirts.add(shirt)
            require(p['IdSeason']=='285023' and p['IdTeam']==squad['IdTeam'] and p['IdPlayer'] not in seen,'Duplicate/foreign official player');seen.add(p['IdPlayer'])
            require(localized(p['PositionLocalized']) in positions,'Unknown position')
            source=manifest['files']['squad-'+code+'.json']
            players.append({'id':'fifa:player:'+p['IdPlayer'],'team_id':teams[code]['id'],'name':localized(p['PlayerName']),
                            'shirt_number':shirt,'position':positions[localized(p['PositionLocalized'])],
                            'source_url':source['source_url'],'source_retrieved_at':datetime.fromisoformat(source['retrieved_at']).astimezone(timezone.utc).strftime('%Y-%m-%d %H:%M:%S')})
    result={'import_schema':1,'dataset_name':'2026-full-core','manifest':manifest,
            'teams':[teams[c] for c in sorted(teams)],'players':players,'venues':list(venues.values()),
            'stages':[{'code':v[0],'name':v[1],'sort_order':v[2],'is_group_stage':int(v[0]=='group')} for v in STAGES.values()],
            'groups':[groups[g] for g in sorted(groups)],'group_teams':[{'group_code':memberships[c],'team_id':teams[c]['id']} for c in sorted(teams)],
            'matches':matches,'appearances':[],'player_stats':[],'team_stats':[],'events':[],'player_mappings':[],
            'report':{'kickoff_discrepancies':time_conflicts,'halftime_discrepancies':halftime_conflicts,'status_basis':'FIFA status 0 with ResultType 1/2/3 plus independently completed score breakdown and winner agreement.'}}
    return result,full_matches


def main():
    parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('--capture-from',type=Path)
    args=parser.parse_args()
    if args.capture_from:capture(args.capture_from)
    bundle,manifest=load(); result,_=build_core(bundle,manifest)
    (DIRECTORY/'core.json').write_bytes(encode_json(result))
    print(json.dumps({k:len(v) for k,v in result.items() if isinstance(v,list)}))


if __name__=='__main__':main()
