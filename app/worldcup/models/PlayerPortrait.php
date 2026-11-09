<?php
/** FOURWARD coursework | Author: 韩羽宸 | 学号: 2410673 | FIFA source-ID portrait lookup; local allowlisted files only. */
declare(strict_types=1);
namespace Fourward\worldcup\models;

final class PlayerPortrait extends ReadModel {
  public function attach(array $players,string $idField='id'):array {
    if(!$players)return [];
    $ids=array_values(array_unique(array_map(fn($p)=>(int)$p[$idField],$players)));$params=[];$holders=[];
    foreach($ids as $i=>$id){$params['id'.$i]=$id;$holders[]=':id'.$i;}
    $mappings=$this->rows("SELECT player_id,source_id FROM wc_source_mappings WHERE provider='fifa' AND entity_type='player' AND player_id IN (".implode(',',$holders).')',$params);
    $external=[];foreach($mappings as $m)if(ctype_digit($m['source_id']))$external[$m['player_id']]=$m['source_id'];
    static $report=null;
    if($report===null){
      $path=\PROJECT_ROOT.'/data/worldcup/player-photos/availability.json';
      $decoded=is_file($path)?json_decode(file_get_contents($path),true):null;
      $report=is_array($decoded)?($decoded['players']??[]):[];
    }
    foreach($players as &$p){
      $pid=$external[$p[$idField]]??null;$photo=$pid!==null?($report[$pid]??null):null;$p['portrait_url']=null;$p['portrait_kind']=null;
      if(is_array($photo)&&($photo['kind']??null)==='headshot'&&($photo['status']??null)==='available'&&preg_match('/^[a-f0-9]{64}$/D',$photo['sha256']??'')&&preg_match('/^'.preg_quote($pid,'/').'\.(?:webp|png|jpg)$/D',$photo['file']??'')){
        $file=\PROJECT_ROOT.'/public/assets/images/player-photos/'.$photo['file'];
        if(is_file($file)){$p['portrait_url']='../assets/images/player-photos/'.$photo['file'].'?v='.substr($photo['sha256'],0,12);$p['portrait_kind']=($photo['kind']??'full-body')==='headshot'?'headshot':'full-body';}
      }
    }unset($p);
    return $players;
  }
}
