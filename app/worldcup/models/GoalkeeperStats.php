<?php
/** FOURWARD coursework | Author: 韩羽宸 | Source-backed DQD goalkeeper match statistics. */
declare(strict_types=1);
namespace Fourward\worldcup\models;

final class GoalkeeperStats extends ReadModel {
  private static ?array $snapshot=null;

  private static function snapshot():array {
    if(self::$snapshot!==null)return self::$snapshot;
    $path=\PROJECT_ROOT.'/data/worldcup/goalkeeper-metrics.json';
    $decoded=is_file($path)?json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR):[];
    $indexed=[];
    foreach($decoded['rows']??[] as $row)$indexed[$row['match_id'].'/'.$row['player_id']]=$row['metrics'];
    return self::$snapshot=$indexed;
  }

  private function mappings(string $entity,array $ids):array {
    $ids=array_values(array_unique(array_filter(array_map('intval',$ids))));if(!$ids)return [];
    $params=[];$holders=[];foreach($ids as $i=>$id){$params['id'.$i]=$id;$holders[]=':id'.$i;}
    $field=$entity.'_id';$rows=$this->rows("SELECT $field,source_id FROM wc_source_mappings WHERE provider='fifa' AND entity_type=:entity AND $field IN (".implode(',',$holders).')',['entity'=>$entity]+$params);
    $result=[];foreach($rows as $row)$result[(int)$row[$field]]=$row['source_id'];return $result;
  }

  public function attach(array $records,string $playerIdField='id',string $matchIdField='match_id'):array {
    if(!$records)return [];$players=$this->mappings('player',array_column($records,$playerIdField));$matches=$this->mappings('match',array_column($records,$matchIdField));$snapshot=self::snapshot();
    foreach($records as &$record){
      $player=$players[(int)$record[$playerIdField]]??null;$match=$matches[(int)($record[$matchIdField]??0)]??null;
      $metrics=$player!==null&&$match!==null?($snapshot['fifa:match:'.$match.'/fifa:player:'.$player]??[]):[];
      foreach(PlayerBoard::GOALKEEPER_METRICS as $key=>$label)$record[$key]=$metrics[$key]??null;
    }unset($record);return $records;
  }
}
