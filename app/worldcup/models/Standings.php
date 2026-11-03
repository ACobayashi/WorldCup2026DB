<?php
/** FOURWARD coursework | Author: 韩羽宸 | WC26 article 13 score-based standings, explicit unresolved ties. */
declare(strict_types=1);
namespace Fourward\worldcup\models;

final class Standings extends ReadModel {
  private static function buckets(array $rows,callable $key):array {
    usort($rows,fn($a,$b)=>($key($b)<=>$key($a)) ?: strcmp($a['code'],$b['code']));
    $result=[];$previous=null;
    foreach($rows as $row){$value=$key($row);if($previous!==$value)$result[]=[];$result[array_key_last($result)][]=$row;$previous=$value;}
    return $result;
  }
  private static function miniTable(array $rows,array $games):array {
    $ids=array_column($rows,'id');$mini=array_fill_keys($ids,[0,0,0]);
    foreach($games as $m)if(in_array($m['home_team_id'],$ids,true)&&in_array($m['away_team_id'],$ids,true)){
      $a=$m['home_team_id'];$b=$m['away_team_id'];$x=$m['home_score_90'];$y=$m['away_score_90'];
      $mini[$a][0]+=$x>$y?3:($x===$y?1:0);$mini[$b][0]+=$y>$x?3:($x===$y?1:0);
      $mini[$a][1]+=$x-$y;$mini[$b][1]+=$y-$x;$mini[$a][2]+=$x;$mini[$b][2]+=$y;
    }
    return $mini;
  }
  private static function headToHead(array $rows,array $games):array {
    if(count($rows)<2)return [$rows];
    $mini=self::miniTable($rows,$games);$parts=self::buckets($rows,fn($r)=>$mini[$r['id']]);
    if(count($parts)>1){$result=[];foreach($parts as $part)$result=[...$result,...self::headToHead($part,$games)];return $result;}
    // Full player+official conduct scores and historical FIFA ranks are not imported.
    return self::buckets($rows,fn($r)=>[$r['goal_difference'],$r['goals_for']]);
  }
  private static function ranked(array $buckets):array {
    $result=[];$position=1;
    foreach($buckets as $bucket){$end=$position+count($bucket)-1;foreach($bucket as $row)$result[]=$row+['rank'=>$position,'rank_end'=>$end,'rank_tied'=>count($bucket)>1];$position=$end+1;}
    return $result;
  }
  public static function calculateGroup(array $teams,array $matches):array {
    $rows=[];
    foreach($teams as $t)$rows[$t['id']]=$t+['played'=>0,'won'=>0,'drawn'=>0,'lost'=>0,'goals_for'=>0,'goals_against'=>0,'goal_difference'=>0,'points'=>0];
    $games=[];
    foreach($matches as $m){
      if($m['status']!=='finished'||$m['home_score_90']===null||$m['away_score_90']===null)continue;
      if(!isset($rows[$m['home_team_id']],$rows[$m['away_team_id']]))continue;
      $games[]=$m;
      foreach(['home','away'] as $side){$other=$side==='home'?'away':'home';$id=$m[$side.'_team_id'];$x=$m[$side.'_score_90'];$y=$m[$other.'_score_90'];
        $rows[$id]['played']++;$rows[$id]['goals_for']+=$x;$rows[$id]['goals_against']+=$y;$rows[$id]['goal_difference']+=$x-$y;
        $rows[$id][$x>$y?'won':($x===$y?'drawn':'lost')]++;$rows[$id]['points']+=$x>$y?3:($x===$y?1:0);
      }
    }
    $buckets=[];foreach(self::buckets(array_values($rows),fn($r)=>[$r['points']]) as $tied)$buckets=[...$buckets,...self::headToHead($tied,$games)];
    return self::ranked($buckets);
  }
  public static function rankThirds(array $rows,bool $complete):array {
    $rows=array_map(function($r){unset($r['rank'],$r['rank_end'],$r['rank_tied']);return $r;},$rows);
    $ranked=self::ranked(self::buckets($rows,fn($r)=>[$r['points'],$r['goal_difference'],$r['goals_for']]));
    foreach($ranked as &$r)$r['qualified']=!$complete||count($ranked)!==12 ? null : ($r['rank']<=8&&$r['rank_end']>8 ? null : $r['rank_end']<=8);
    return $ranked;
  }
  public function table():array {
    $groups=$this->rows('SELECT id,code,name FROM wc_groups ORDER BY sort_order,id');
    $members=$this->rows('SELECT gt.group_id,t.id,t.code,t.name_zh FROM wc_group_teams gt JOIN wc_teams t ON t.id=gt.team_id ORDER BY t.code');
    $games=$this->rows('SELECT m.* FROM wc_matches m JOIN wc_stages s ON s.id=m.stage_id WHERE s.is_group_stage=1 ORDER BY m.match_no');
    $thirds=[];$complete=count($groups)===12;
    foreach($groups as &$g){
      $teams=array_values(array_filter($members,fn($r)=>$r['group_id']===$g['id']));
      $matches=array_values(array_filter($games,fn($r)=>$r['group_id']===$g['id']));
      $g['teams']=self::calculateGroup($teams,$matches);
      $g['complete']=count($teams)===4 && count($matches)===6 && count(array_filter($matches,fn($m)=>$m['status']==='finished'&&$m['home_score_90']!==null&&$m['away_score_90']!==null))===6;
      $complete=$complete&&$g['complete'];
      foreach($g['teams'] as $r)if($r['rank']===3 && !$r['rank_tied'])$thirds[]=$r+['group_code'=>$g['code']];
    }unset($g);
    $thirds=self::rankThirds($thirds,$complete);
    $thirdMap=[];foreach($thirds as $t)$thirdMap[$t['id']]=$t['qualified'];
    foreach($groups as &$g)foreach($g['teams'] as &$t){
      $t['qualified']=$complete&&($t['rank_end']<=2||($thirdMap[$t['id']]??false));
      $pendingThird=array_key_exists($t['id'],$thirdMap)&&$thirdMap[$t['id']]===null;
      $t['qualification']=$t['qualified']?($t['rank_end']<=2?'小组前二晋级':'最佳第三名晋级'):($complete&&!$t['rank_tied']&&!$pendingThird?'小组出局':'待确认');
    }unset($g,$t);
    return ['groups'=>$groups,'thirds'=>$thirds,'complete'=>$complete,
      'rules_url'=>'https://digitalhub.fifa.com/m/636f5c9c6f29771f/original/FWC2026_regulations_EN.pdf',
      'tie_note'=>'同分先比较相互战绩，必要时对剩余同分球队重新比较，再比较总净胜球、总进球；公平竞赛与 FIFA 排名资料不足时保留并列，不用代码排序冒充体育排名。'];
  }
}
