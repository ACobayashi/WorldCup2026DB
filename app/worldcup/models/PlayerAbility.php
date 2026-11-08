<?php
/** FOURWARD coursework | Author: 韩羽宸 | Verified DQD ability snapshot lookup. */
declare(strict_types=1);
namespace Fourward\worldcup\models;

final class PlayerAbility extends ReadModel {
  public const AXES=[
    'pace'=>'速度','shooting'=>'射门','passing'=>'传球',
    'dribbling'=>'盘带','defending'=>'防守','physical'=>'力量',
  ];
  private static ?array $snapshot=null;

  private static function snapshot():array {
    if(self::$snapshot!==null)return self::$snapshot;
    $path=\PROJECT_ROOT.'/data/worldcup/player-abilities.json';
    if(!is_file($path))return self::$snapshot=['players'=>[]];
    $decoded=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
    return self::$snapshot=is_array($decoded)?$decoded:['players'=>[]];
  }

  public function attach(array $players,string $idField='id'):array {
    if(!$players)return [];
    $ids=array_values(array_unique(array_map(fn($p)=>(int)$p[$idField],$players)));$params=[];$holders=[];
    foreach($ids as $i=>$id){$params['id'.$i]=$id;$holders[]=':id'.$i;}
    $rows=$this->rows("SELECT player_id,source_id FROM wc_source_mappings WHERE provider='fifa' AND entity_type='player' AND player_id IN (".implode(',',$holders).')',$params);
    $fifa=[];foreach($rows as $row)$fifa[(int)$row['player_id']]=$row['source_id'];
    $snapshot=self::snapshot();$entries=$snapshot['players']??[];
    foreach($players as &$player){
      $sourceId=$fifa[(int)$player[$idField]]??null;$entry=$sourceId!==null?($entries[$sourceId]??null):null;
      $player['ability']=is_array($entry)?$entry:null;
    }unset($player);
    return $players;
  }

  public static function coverage():array {
    $snapshot=self::snapshot();
    return ['captured_at'=>$snapshot['captured_at']??null,'provider'=>$snapshot['provider']??'懂球帝','players'=>count($snapshot['players']??[])];
  }
}
