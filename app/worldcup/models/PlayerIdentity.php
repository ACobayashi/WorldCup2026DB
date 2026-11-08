<?php
/** FOURWARD coursework | Author: 韩羽宸 | Complete DQD Chinese-name identity mapping. */
declare(strict_types=1);
namespace Fourward\worldcup\models;

final class PlayerIdentity extends ReadModel {
  private static ?array $snapshot=null;
  private static ?array $fullNames=null;

  private static function snapshot():array {
    if(self::$snapshot!==null)return self::$snapshot;
    $path=\PROJECT_ROOT.'/data/worldcup/player-names.json';
    $decoded=is_file($path)?json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR):[];
    return self::$snapshot=is_array($decoded)?($decoded['players']??[]):[];
  }

  private static function fullNames():array {
    if(self::$fullNames!==null)return self::$fullNames;
    $path=\PROJECT_ROOT.'/data/worldcup/player-full-names.json';
    $decoded=is_file($path)?json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR):[];
    return self::$fullNames=is_array($decoded)?($decoded['players']??[]):[];
  }

  public function attach(array $players,string $idField='id'):array {
    if(!$players)return [];
    $ids=array_values(array_unique(array_map(fn($p)=>(int)$p[$idField],$players)));$params=[];$holders=[];
    foreach($ids as $i=>$id){$params['id'.$i]=$id;$holders[]=':id'.$i;}
    $rows=$this->rows("SELECT player_id,source_id FROM wc_source_mappings WHERE provider='fifa' AND entity_type='player' AND player_id IN (".implode(',',$holders).')',$params);
    $fifa=[];foreach($rows as $row)$fifa[(int)$row['player_id']]=$row['source_id'];
    $snapshot=self::snapshot();$fullNames=self::fullNames();
    foreach($players as &$player){
      $sourceId=$fifa[(int)$player[$idField]]??null;$entry=$sourceId!==null?($snapshot[$sourceId]??null):null;
      $player['name_en']=is_array($entry)?$entry['name_en']:($player['name_en']??$player['name']);
      $player['player_name_zh']=isset($fullNames[$sourceId]['full_name_zh'])?$fullNames[$sourceId]['full_name_zh']:(is_array($entry)?$entry['name_zh']:$player['name']);
      $player['name']=$player['player_name_zh'];
    }unset($player);
    return $players;
  }
}
