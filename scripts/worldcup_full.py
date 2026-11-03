"""FOURWARD coursework | Author: 韩羽宸 | Reproducible full-edition core-data normalization."""
import argparse
import csv
import json
import re
import copy
from collections import Counter
from datetime import datetime, timezone, timedelta
from pathlib import Path
from worldcup_data import ROOT, require, digest, encode_json, localized, normalized_name, integer, percentage, pair, event_minute, TEAM_METRICS

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


def build_details(bundle, manifest, corrections):
    require(corrections['edition']==2026,'Wrong correction edition')
    result, references=build_core(bundle,manifest)
    result['dataset_name']='2026-full-core-and-details'
    result['manifest']=copy.deepcopy(manifest)
    result['manifest']['corrections']={'canonical_json_sha256':digest(encode_json(corrections)),'rules':corrections}
    teams={t['code']:t for t in result['teams']}; codes={t['id']:t['code'] for t in result['teams']}
    players={(codes[p['team_id']],p['shirt_number']):p for p in result['players']}
    by_id={p['id']:p for p in result['players']}
    by_name={(codes[p['team_id']],normalized_name(p['name'])):p for p in result['players']}
    matches={m['match_no']:m for m in result['matches']}; source_matches={}; corrected_rows=Counter()
    result['entity_mappings']=[]
    require(len(bundle['report-matches.json'])==104,'Missing report fixtures')
    for r in bundle['report-matches.json']:
        number=integer(r['match_number']);require(number in matches and r['match_id'] not in source_matches,'Duplicate/foreign report fixture')
        match=matches[number]; expected=[codes[match['home_team_id']],codes[match['away_team_id']]]
        observed=[r['home_team_id'],r['away_team_id']]
        rules=[c for c in corrections['match_team_corrections'] if c['match_no']==number and c['source_match_id']==r['match_id']]
        if observed!=expected:
            require(len(rules)==1,'Unreviewed report team-code mismatch')
            rule=rules[0]
            require(expected==[rule['expected_home'],rule['expected_away']] and observed==[rule['expected_home'],rule['source_team']] and rule['canonical_team']==expected[1], 'Invalid scoped team correction')
        else: require(not rules,'Unexpected correction on an already consistent match')
        final=[match['home_score_et'] if match['home_score_et'] is not None else match['home_score_90'],match['away_score_et'] if match['away_score_et'] is not None else match['away_score_90']]
        require([integer(r['home_score']),integer(r['away_score'])]==final,'Report final score disagreement')
        source_matches[r['match_id']]=(match,rules[0] if rules else None)
        result['entity_mappings'].append({'provider':'worldcup26','entity_type':'match','source_key':r['match_id'],'target_id':match['id']})
        for source_code in observed:
            canonical_code=rules[0]['canonical_team'] if rules and source_code==rules[0]['source_team'] else source_code
            result['entity_mappings'].append({'provider':'worldcup26-matchteam','entity_type':'team','source_key':r['match_id']+'/'+source_code,'target_id':teams[canonical_code]['id']})
    require({m['match_no'] for m,_ in source_matches.values()}==set(matches),'Missing report match mapping')

    def connect(row,section):
        require(row['match_id'] in source_matches,'Unknown report match ID')
        match,rule=source_matches[row['match_id']];code=row['team_id']
        if rule and code==rule['source_team']:
            code=rule['canonical_team'];corrected_rows[section]+=1
        require(code in [codes[match['home_team_id']],codes[match['away_team_id']]],'Report team outside canonical fixture')
        return match,code

    def connect_player(row,section):
        match,code=connect(row,section);p=players.get((code,integer(row['shirt_number'])))
        require(p is not None,'Unknown player number')
        if normalized_name(row['player_name'])!=normalized_name(p['name']):
            aliases=[c for c in corrections['player_name_corrections'] if c['match_no']==match['match_no'] and c['team_code']==code and c['shirt_number']==p['shirt_number'] and c['source_name']==row['player_name'] and c['player_id']==p['id']]
            require(len(aliases)==1,'Unreviewed player name mismatch')
            side=0 if code==codes[match['home_team_id']] else 1
            require(any(normalized_name(p['name'])==normalized_name(x['name']) for x in references[match['match_no']]['lineup'][side]['starter']),'Name correction lacks independent starting-XI evidence')
            corrected_rows['short_name_'+section]+=1
        return match,code,p

    appearances={}; source_players={}; source_appearances={}; lineup_sizes={};position_differences=[]
    for r in bundle['report-appearances.json']:
        match,code,p=connect_player(r,'appearances');key=(match['id'],p['id'])
        require(key not in appearances,'Duplicate match/player appearance')
        require(r['is_starter'] in ['True','False'] and r['appeared'] in ['True','False'],'Invalid appearance flags')
        require(r['position'] in ['GK','DF','MF','FW'],'Unknown match position')
        if r['position']!=p['position']:
            position_differences.append({'match_no':match['match_no'],'player_id':p['id'],'roster_position':p['position'],'report_match_position':r['position'],'resolution':'Preserve the source match designation separately; do not overwrite the FIFA roster position.'})
        require(r['team_role']==('home' if code==codes[match['home_team_id']] else 'away'),'Wrong report team role')
        require(r['player_id'] not in source_players or source_players[r['player_id']]==p['id'],'Report player ID collision')
        require(r['appearance_id'] not in source_appearances,'Duplicate appearance source ID')
        source_players[r['player_id']]=p['id'];source_appearances[r['appearance_id']]=key
        appearances[key]={'match_id':match['id'],'player_id':p['id'],'position':r['position'],'starting':r['is_starter']=='True','appeared':r['appeared']=='True'}
        require(not appearances[key]['starting'] or appearances[key]['appeared'],'Starter cannot be marked unused')
    for match in result['matches']:
        for side,team in enumerate([match['home_team_id'],match['away_team_id']]):
            code=codes[team];rows=[r for r in appearances.values() if r['match_id']==match['id'] and by_id[r['player_id']]['team_id']==team]
            require(11<=len(rows)<=26,'Invalid match squad size')
            lineup_sizes[str(match['match_no'])+'/'+code]=len(rows)
            actual={r['player_id'] for r in rows if r['starting']}
            reference=references[match['match_no']]['lineup'][side]
            expected={by_name[(code,normalized_name(p['name']))]['id'] for p in reference['starter']}
            require(len(actual)==11 and actual==expected,'Starting XI disagrees with detailed source')
            participants={by_name[(code,normalized_name(p['name']))]['id'] for p in reference['starter']+reference['bench']}
            require({r['player_id'] for r in rows if r['appeared']}==participants,'Participant set disagrees with detailed source')
    result['appearances']=list(appearances.values())
    result['player_mappings']=[{'provider':'worldcup26','source_key':key,'player_id':value} for key,value in sorted(source_players.items())]

    def linked(row,section):
        match,code,p=connect_player(row,section);key=(match['id'],p['id'])
        require(source_players.get(row['player_id'])==p['id'] and source_appearances.get(row['appearance_id'])==key,'Unlinked report source player/appearance')
        return match,code,p,key

    stat_keys=set()
    for r in bundle['report-player_stats.json']:
        match,code,p,key=linked(r,'player_stats');require(key not in stat_keys,'Duplicate player statistics');stat_keys.add(key)
        record={'match_id':match['id'],'player_id':p['id'],'goals':integer(r['goals']),'assists':None,'minutes_played':None,
                'shots':integer(r['attempts_at_goal']),'passes_attempted':integer(r['passes_attempted']),
                'passes_completed':integer(r['passes_completed']),'pass_completion_pct':percentage(r['pass_completion_pct'])}
        require(record['passes_completed'] is None or record['passes_attempted'] is None or record['passes_completed']<=record['passes_attempted'],'Completed passes exceed attempts')
        result['player_stats'].append(record)
    require(stat_keys=={key for key,r in appearances.items() if r['appeared']},'Missing/unexpected played-player statistics')

    team_stats={};metric_keys=set();ignored=Counter()
    for r in bundle['report-team_stats.json']:
        if r['team_id']=='CONTEST' or r['metric'] not in TEAM_METRICS:
            ignored[r['metric']]+=1;continue
        match,code=connect(r,'team_stats');metric=r['metric'];key=(match['id'],teams[code]['id'])
        require((key,metric) not in metric_keys,'Duplicate team metric');metric_keys.add((key,metric))
        record=team_stats.setdefault(key,{'match_id':match['id'],'team_id':teams[code]['id']})
        if metric=='Possession':record['possession_pct']=percentage(r['value'])
        elif metric=='Goals':record['goals']=integer(r['value'])
        elif metric=='Pass Completion %':record['pass_completion_pct']=percentage(r['value'])
        elif metric=='Attempts at Goal (On Target)':record['shots'],record['shots_on_target']=pair(r['value'])
        elif metric=='Total Passes (Complete)':record['passes_attempted'],record['passes_completed']=pair(r['value'])
    require(len(team_stats)==208 and len(metric_keys)==208*len(TEAM_METRICS),'Missing full-edition team metrics')
    result['team_stats']=list(team_stats.values())
    event_ids=set();event_counts=Counter();goals=Counter();own_goals=Counter();bench_cards=[]
    for r in bundle['report-events.json']:
        match,code,p,key=linked(r,'events')
        require(r['event_id'] not in event_ids,'Duplicate event source ID');event_ids.add(r['event_id'])
        require(r['event_type'] in ['goal','own_goal','yellow_card','red_card','subbed_on','subbed_off'],'Unknown event type')
        require(appearances[key]['appeared'] or r['event_type'] in ['yellow_card','red_card'],'On-field event attributed to unused player')
        if not appearances[key]['appeared']:
            bench_cards.append({'match_no':match['match_no'],'player_id':p['id'],'event_type':r['event_type'],'minute_raw':r['minute_raw']})
        result['events'].append({'source_id':r['event_id'],'match_id':match['id'],'player_id':p['id'],'type':r['event_type'],**event_minute(r['minute_raw'])})
        event_counts[r['event_type']]+=1
        if r['event_type']=='goal':goals[key]+=1
        if r['event_type']=='own_goal':own_goals[(match['id'],p['team_id'])]+=1
    for r in result['player_stats']:
        require(r['goals']==goals[(r['match_id'],r['player_id'])],'Player goal statistic/event disagreement')
    for match in result['matches']:
        for side,team in [('home',match['home_team_id']),('away',match['away_team_id'])]:
            opponent=match['away_team_id'] if side=='home' else match['home_team_id']
            expected=match[side+'_score_et'] if match[side+'_score_et'] is not None else match[side+'_score_90']
            actual=sum(r['goals'] for r in result['player_stats'] if r['match_id']==match['id'] and by_id[r['player_id']]['team_id']==team)+own_goals[(match['id'],opponent)]
            require(actual==expected and team_stats[(match['id'],team)]['goals']==expected,'Goal totals (including own goals) disagree with fixture')
    result['report'].update(corrected_rows=dict(corrected_rows),applied_corrections=corrections,position_differences=position_differences,
                            source_match_squad_size_distribution=dict(Counter(lineup_sizes.values())),
                            shorter_match_squads={k:v for k,v in lineup_sizes.items() if v<26},
                            event_counts=dict(event_counts),bench_cards=bench_cards,excluded_noncore_metric_rows=dict(ignored),
                            missing_fields=['Player match assists and minutes_played are unavailable in the selected source and remain null.'],
                            external_roster_policy='FIFA season roster is canonical; openfootball roster name/selection differences are not silently merged.',
                            coverage={key:len({r['match_id'] for r in result[key]}) for key in ['appearances','player_stats','team_stats','events']})
    return result


def main():
    parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('--capture-from',type=Path);parser.add_argument('--details',action='store_true')
    args=parser.parse_args()
    if args.capture_from:capture(args.capture_from)
    bundle,manifest=load(); result,_=build_core(bundle,manifest)
    (DIRECTORY/'core.json').write_bytes(encode_json(result))
    if args.details:
        corrections=json.loads((DIRECTORY/'corrections.json').read_text(encoding='utf-8'))
        result=build_details(bundle,manifest,corrections)
        (DIRECTORY/'complete.json').write_bytes(encode_json(result))
        (DIRECTORY/'quality-report.json').write_bytes(encode_json(result['report']))
    print(json.dumps({k:len(v) for k,v in result.items() if isinstance(v,list)}))


if __name__=='__main__':main()
