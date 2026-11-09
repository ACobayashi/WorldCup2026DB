<?php
/** FOURWARD coursework | Author: 韩羽宸 | 学号: 2410673 | Verified source positions joined to actual database starters. */
declare(strict_types=1);
namespace Fourward\worldcup\models;

final class Formation extends ReadModel {
  public function forMatch(array $match):?array {
    $mapping=$this->rows("SELECT source_id FROM wc_source_mappings WHERE provider='fifa' AND entity_type='match' AND match_id=:id",['id'=>$match['id']])[0]??null;
    if(!$mapping)return null;
    static $snapshot=null;
    if($snapshot===null){$path=\PROJECT_ROOT.'/data/worldcup/formations.json';$snapshot=is_file($path)?json_decode(file_get_contents($path),true):[];}
    $entry=$snapshot['matches']['fifa:match:'.$mapping['source_id']]??null;
    if(!is_array($entry)||($snapshot['edition']??null)!==2026||($entry['kickoff_at']??null)!==(new \DateTimeImmutable($match['kickoff_at'],new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z'))return null;
    $people=$this->rows("SELECT a.player_id,a.team_id,a.shirt_number,a.is_starter,p.name,p.position,m.source_id,tm.source_id AS source_team FROM wc_match_appearances a JOIN wc_players p ON p.id=a.player_id JOIN wc_source_mappings m ON m.player_id=p.id AND m.provider='fifa' AND m.entity_type='player' JOIN wc_source_mappings tm ON tm.team_id=a.team_id AND tm.provider='fifa' AND tm.entity_type='team' WHERE a.match_id=:id AND a.is_starter=1",['id'=>$match['id']]);
    $people=(new PlayerIdentity($this->db))->attach($people,'player_id');
    $bySource=[];foreach($people as $p)$bySource['fifa:player:'.$p['source_id']]=$p;
    if(count($bySource)!==22)return null;
    $result=['source_url'=>$entry['source_url']??'','sides'=>[]];$seen=[];
    foreach(['home','away'] as $side){
      $team=$entry['sides'][$side]??null;
      if(!is_array($team)||!preg_match('/^[1-5](?:-[1-5]){1,4}$/D',$team['formation']??'')||array_sum(array_map('intval',explode('-',$team['formation'])))!==10||count($team['starters']??[])!==11)return null;
      $players=[];
      foreach($team['starters'] as $spot){
        $p=$bySource[$spot['player_id']??'']??null;
        if(!$p||isset($seen[$p['player_id']])||$p['team_id']!==$match[$side.'_team_id']||'fifa:team:'.$p['source_team']!==$team['team_id']||$p['shirt_number']!==($spot['shirt_number']??null))return null;
        foreach(['x','y'] as $axis)if(!isset($spot[$axis])||!is_numeric($spot[$axis])||$spot[$axis]<=0||$spot[$axis]>=100)return null;
        $seen[$p['player_id']]=true;$p['x']=(float)$spot['x'];$p['y']=(float)$spot['y'];$p['captain']=($spot['captain']??false)===true;
        $p['display_name']=is_string($spot['source_name']??null)&&mb_strlen($spot['source_name'])<=60&&trim($spot['source_name'])!==''?$spot['source_name']:$p['name'];
        $p['markers']=array_values(array_filter($match['events'],fn($e)=>$e['player_id']===$p['player_id']&&in_array($e['event_type'],['goal','own_goal','yellow_card','red_card','subbed_off'],true)));
        $players[]=$p;
      }
      $result['sides'][$side]=['formation'=>$team['formation'],'starters'=>$players];
    }
    return $result;
  }
}
