<?php
/** FOURWARD coursework | Author: 韩羽宸 | 学号: 2410673 | Player summary, positional radar and honest source coverage. */
declare(strict_types=1);
namespace Fourward\worldcup\models;

final class PlayerBoard extends ReadModel {
  public const BASE_METRICS=[
    'goals'=>'进球','assists'=>'助攻','shots'=>'射门','passes_attempted'=>'传球','passes_completed'=>'成功传球',
    'ball_progressions'=>'推进','crosses_attempted'=>'传中','crosses_completed'=>'成功传中',
    'line_breaks_attempted'=>'尝试破线','line_breaks_completed'=>'完成破线','take_ons'=>'过人次数',
    'step_ins'=>'带球切入','switches_of_play'=>'转移球','blocks'=>'封堵','clearances'=>'解围',
    'duels_won_aerial'=>'赢得空中对抗','duels_won_physical'=>'赢得身体对抗','interceptions'=>'拦截',
    'possession_regains'=>'夺回球权','pressing_direct'=>'直接施压','tackles_attempted'=>'抢断尝试','tackles_won'=>'成功抢断',
  ];
  public const GOALKEEPER_METRICS=[
    'saves'=>'扑救','goals_prevented'=>'阻止进球','saves_inside_box'=>'禁区内扑救','penalties_saved'=>'扑出点球','shootout_saves'=>'点球大战扑救',
    'sweeper_actions'=>'出击','sweeper_actions_won'=>'成功出击','high_claims'=>'摘高球','punches'=>'拳击球','goal_line_clearances'=>'门线解围',
  ];
  public const METRICS=self::BASE_METRICS+self::GOALKEEPER_METRICS;
  public const RANKINGS=self::BASE_METRICS+['saves'=>'扑救']+['yellow_cards'=>'已记录黄牌','red_cards'=>'已记录红牌'];
  private function records(?string $position=null):array {
    $where=$position===null?'':' WHERE p.position=:position';
    $rows=$this->rows('SELECT p.id,p.team_id,p.name,p.shirt_number,p.position,t.code,t.name_zh,a.match_id,a.is_starter,a.appeared,s.goals,s.shots,s.passes_attempted,s.passes_completed,s.advanced_stats FROM wc_players p JOIN wc_teams t ON t.id=p.team_id LEFT JOIN wc_match_appearances a ON a.player_id=p.id LEFT JOIN wc_player_match_stats s ON s.player_id=a.player_id AND s.match_id=a.match_id'.$where.' ORDER BY p.id,a.match_id',$position===null?[]:['position'=>$position]);
    $rows=(new GoalkeeperStats($this->db))->attach($rows);
    return (new PlayerIdentity($this->db))->attach($rows);
  }
  public static function aggregate(array $records):array {
    $players=[];
    foreach($records as $r){
      if(!isset($players[$r['id']]))$players[$r['id']]=array_intersect_key($r,array_flip(['id','team_id','name','name_en','player_name_zh','shirt_number','position','code','name_zh']))+['appearances'=>0,'starts'=>0,'listed'=>0,'totals'=>array_fill_keys(array_keys(self::METRICS),null),'coverage'=>array_fill_keys(array_keys(self::METRICS),0)];
      $p=&$players[$r['id']];if($r['match_id']!==null)$p['listed']++;
      if(!$r['appeared']){unset($p);continue;}
      $p['appearances']++;$p['starts']+=(int)$r['is_starter'];
      $values=array_intersect_key($r,array_flip(array_merge(['goals','shots','passes_attempted','passes_completed'],array_keys(self::GOALKEEPER_METRICS))));
      if($r['advanced_stats']!==null){$advanced=json_decode($r['advanced_stats'],true,512,JSON_THROW_ON_ERROR);$values+=($advanced['metrics']??[]);}
      foreach(self::METRICS as $key=>$label)if(isset($values[$key])){$value=$key==='goals_prevented'?(float)$values[$key]:(int)$values[$key];$p['totals'][$key]=($p['totals'][$key]??0)+$value;$p['coverage'][$key]++;}
      unset($p);
    }
    foreach($players as &$p){
      $attempted=$p['totals']['passes_attempted'];$completed=$p['totals']['passes_completed'];
      $p['pass_completion_pct']=$attempted!==null&&$attempted>0&&$completed!==null&&$p['coverage']['passes_attempted']===$p['coverage']['passes_completed']&&$p['coverage']['passes_attempted']===$p['appearances'] ? $completed/$attempted*100 : null;
    }unset($p);
    return $players;
  }
  private static function rate(array $p,string $key):?float {
    if($key==='pass_completion_pct')return $p['pass_completion_pct'];
    if($key==='starting_pct')return $p['appearances']>0?$p['starts']/$p['appearances']*100:null;
    return $p['appearances']>0&&$p['coverage'][$key]===$p['appearances'] ? $p['totals'][$key]/$p['appearances'] : null;
  }
  public static function radar(array $p,array $peers):array {
    $axes=match($p['position']){
      'GK'=>['saves','goals_prevented','saves_inside_box','sweeper_actions_won','high_claims','pass_completion_pct'],
      'DF'=>['interceptions','tackles_won','clearances','blocks','possession_regains','duels_won_aerial'],
      'MF'=>['passes_completed','pass_completion_pct','line_breaks_completed','ball_progressions','crosses_completed','take_ons'],
      default=>['goals','shots','take_ons','ball_progressions','crosses_completed','passes_completed'],
    };
    $labels=self::METRICS+['pass_completion_pct'=>'传球成功率','starting_pct'=>'首发占比'];$result=[];
    foreach($axes as $key){
      $samples=[];foreach($peers as $peer)if($peer['position']===$p['position']&&$peer['appearances']>0){$v=self::rate($peer,$key);if($v!==null)$samples[]=$v;}
      $value=self::rate($p,$key);$max=$samples?max($samples):null;$average=$samples?array_sum($samples)/count($samples):null;
      $normalize=static fn(?float $v):?float=>$v===null||$max===null?null:($max>0?min(100,max(0,$v/$max*100)):0.0);
      $result[]=['key'=>$key,'label'=>($key==='pass_completion_pct'||$key==='starting_pct'?'':'场均').$labels[$key],
        'value'=>$value,'average'=>$average,'maximum'=>$max,'score'=>$normalize($value),'average_score'=>$normalize($average),'peers'=>count($samples),
        'unit'=>str_ends_with($key,'pct')?'%':'次/场'];
    }
    return $result;
  }
  public function find(int $id):?array {
    $player=$this->rows('SELECT p.id,p.team_id,p.name,p.shirt_number,p.position,p.source_url,t.code,t.name_zh,t.name_en FROM wc_players p JOIN wc_teams t ON t.id=p.team_id WHERE p.id=:id',['id'=>$id])[0]??null;
    if(!$player)return null;
    $player=(new PlayerIdentity($this->db))->attach([$player])[0];
    $player=(new PlayerTournamentStats($this->db))->attach([$player])[0];
    $player=(new PlayerPortrait($this->db))->attach([$player])[0];
    $player=(new PlayerAbility($this->db))->attach([$player])[0];
    $peers=self::aggregate($this->records($player['position']));$summary=$peers[$id];
    $summary['totals']['assists']=$player['tournament_assists'];
    $summary['coverage']['assists']=$player['tournament_assists']===null?0:$summary['appearances'];
    $player['summary']=$summary;$player['radar']=self::radar($summary,array_values($peers));
    $player['matches']=$this->rows('SELECT m.id,m.match_no,m.kickoff_at,m.status,m.home_team_id,m.home_score_90,m.away_score_90,m.home_score_et,m.away_score_et,m.home_penalty_score,m.away_penalty_score,st.name AS stage_name,a.is_starter,a.appeared,s.goals,s.shots,s.passes_attempted,s.passes_completed,s.advanced_stats,opp.code AS opponent_code,opp.name_zh AS opponent_name FROM wc_matches m JOIN wc_stages st ON st.id=m.stage_id JOIN wc_teams opp ON opp.id=IF(m.home_team_id=:home,m.away_team_id,m.home_team_id) LEFT JOIN wc_match_appearances a ON a.match_id=m.id AND a.player_id=:player LEFT JOIN wc_player_match_stats s ON s.match_id=m.id AND s.player_id=:stats WHERE m.home_team_id=:t1 OR m.away_team_id=:t2 ORDER BY m.kickoff_at,m.id',['home'=>$player['team_id'],'player'=>$id,'stats'=>$id,'t1'=>$player['team_id'],'t2'=>$player['team_id']]);
    foreach($player['matches'] as &$m)$m['id']=(int)$m['id'];unset($m);
    $goalkeeperRows=[];foreach($player['matches'] as $m)$goalkeeperRows[]=['id'=>$id,'match_id'=>$m['id']];$goalkeeperRows=(new GoalkeeperStats($this->db))->attach($goalkeeperRows);
    foreach($player['matches'] as $i=>&$m)$m+=array_intersect_key($goalkeeperRows[$i],self::GOALKEEPER_METRICS);unset($m);
    foreach($player['matches'] as &$m){$m['metrics']=array_intersect_key($m,array_flip(array_merge(['goals','shots','passes_attempted','passes_completed'],array_keys(self::GOALKEEPER_METRICS))));if($m['advanced_stats']!==null)$m['metrics']+=json_decode($m['advanced_stats'],true,512,JSON_THROW_ON_ERROR)['metrics'];unset($m['advanced_stats']);}unset($m);
    $player['events']=$this->rows('SELECT e.event_type,e.minute_label,e.match_id,m.match_no FROM wc_match_events e JOIN wc_matches m ON m.id=e.match_id WHERE e.player_id=:id ORDER BY m.kickoff_at,(e.minute+e.added_minute),e.id',['id'=>$id]);
    $player['event_counts']=array_count_values(array_column($player['events'],'event_type'));
    return $player;
  }
  public function comparisonOptions():array {
    $players=$this->rows('SELECT p.id,p.name,p.position,p.shirt_number,t.code,t.name_zh FROM wc_players p JOIN wc_teams t ON t.id=p.team_id ORDER BY t.name_zh,p.shirt_number,p.name');
    $players=(new PlayerIdentity($this->db))->attach($players);
    $players=(new PlayerAbility($this->db))->attach($players);
    foreach($players as &$player){$player['has_ability']=$player['ability']!==null;unset($player['ability']);}unset($player);
    return $players;
  }
  public function leaders(string $metric,string $position,int $page,int $size):array {
    if(in_array($metric,['yellow_cards','red_cards'],true)){
      $params=['event'=>$metric==='yellow_cards'?'yellow_card':'red_card'];$where=$position===''?'':' AND p.position=:position';if($position!=='')$params['position']=$position;
      $rows=$this->rows('SELECT p.id,p.team_id,p.name,p.shirt_number,p.position,t.code,t.name_zh,COUNT(e.id) AS recorded_count,(SELECT COALESCE(SUM(a.appeared),0) FROM wc_match_appearances a WHERE a.player_id=p.id) AS appearances FROM wc_match_events e JOIN wc_players p ON p.id=e.player_id JOIN wc_teams t ON t.id=p.team_id WHERE e.event_type=:event'.$where.' GROUP BY p.id,p.team_id,p.name,p.shirt_number,p.position,t.code,t.name_zh',$params);
      $rows=(new PlayerIdentity($this->db))->attach($rows);
      foreach($rows as &$r)$r['totals']=[$metric=>(int)$r['recorded_count']];unset($r);
    }else{
      $rows=array_values(self::aggregate($this->records($position===''?null:$position)));
      if($metric==='assists'){
        $rows=(new PlayerTournamentStats($this->db))->attach($rows);
        foreach($rows as &$r){$r['totals']['assists']=$r['tournament_assists'];$r['coverage']['assists']=$r['tournament_assists']===null?0:$r['appearances'];}unset($r);
        $rows=array_values(array_filter($rows,fn($p)=>$p['appearances']>0&&$p['totals']['assists']!==null&&$p['totals']['assists']>0));
      }else $rows=array_values(array_filter($rows,fn($p)=>$p['appearances']>0&&$p['totals'][$metric]!==null&&$p['coverage'][$metric]===$p['appearances']));
    }
    usort($rows,fn($a,$b)=>($b['totals'][$metric]<=>$a['totals'][$metric]) ?: strcmp($a['name'],$b['name']));
    $rank=0;$previous=null;foreach($rows as $i=>&$r){if($previous!==$r['totals'][$metric])$rank=$i+1;$r['rank']=$rank;$previous=$r['totals'][$metric];}unset($r);
    return ['items'=>(new PlayerPortrait($this->db))->attach(array_slice($rows,($page-1)*$size,$size)),'metric'=>$metric,'position'=>$position,
      'meta'=>['total'=>count($rows),'page'=>$page,'page_size'=>$size,'total_pages'=>(int)ceil(count($rows)/$size)]];
  }
}
