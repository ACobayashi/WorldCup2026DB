<?php
/** FOURWARD coursework | Author: 韩羽宸 | 学号: 2410673 | Additive player metrics migration and transactional extension import. */
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require __DIR__.'/../app/common/bootstrap.php';
$db=database();$db->exec("SET time_zone = '+00:00'");
try {
  $path=$argv[1]??PROJECT_ROOT.'/data/worldcup/player-metrics/metrics.json';
  $content=file_get_contents($path);$data=json_decode($content,true,512,JSON_THROW_ON_ERROR);
  if(($data['schema']??null)!==1||count($data['rows']??[])!==3288)throw new RuntimeException('Unexpected metrics contract/coverage');
  // Assists are imported separately as tournament totals, not as match extensions.
  $allowed=array_values(array_diff(array_keys(Fourward\worldcup\models\PlayerBoard::BASE_METRICS),['goals','assists','shots','passes_attempted','passes_completed']));$provided=$data['fields'];sort($allowed);sort($provided);
  if($allowed!==$provided)throw new RuntimeException('Unknown extended metrics');
  $canonical=$db->prepare('SELECT id FROM wc_import_batches WHERE content_sha256=?');$canonical->execute([$data['canonical_dataset_sha256']]);
  if(!$canonical->fetchColumn())throw new RuntimeException('Import the matching verified complete dataset first');
  $field=$db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='wc_player_match_stats' AND COLUMN_NAME='advanced_stats'")->fetchColumn();
  if(!$field)$db->exec(file_get_contents(PROJECT_ROOT.'/data/migrations/003-player-advanced-stats.sql'));
  $lock=$db->query("SELECT GET_LOCK('fourward_worldcup_import',10)")->fetchColumn();if((int)$lock!==1)throw new RuntimeException('Another import is running');
  try {
    $db->beginTransaction();$hash=hash('sha256',$content);
    $q=$db->prepare('SELECT id FROM wc_import_batches WHERE content_sha256=?');$q->execute([$hash]);
    if($q->fetchColumn()){$db->rollBack();echo "Player metrics already imported.\n";exit(0);}
    $find=$db->prepare("SELECT s.match_id,s.player_id FROM wc_player_match_stats s JOIN wc_source_mappings m ON m.match_id=s.match_id AND m.provider='fifa' AND m.entity_type='match' JOIN wc_source_mappings p ON p.player_id=s.player_id AND p.provider='fifa' AND p.entity_type='player' WHERE m.source_id=? AND p.source_id=?");
    $write=$db->prepare('UPDATE wc_player_match_stats SET advanced_stats=? WHERE match_id=? AND player_id=?');$seen=[];
    foreach($data['rows'] as $r){
      if(!preg_match('/^fifa:match:(\d+)$/D',$r['match_id'],$m)||!preg_match('/^fifa:player:(\d+)$/D',$r['player_id'],$p))throw new RuntimeException('Invalid canonical identity');
      $find->execute([$m[1],$p[1]]);$row=$find->fetch();if(!$row)throw new RuntimeException('Missing verified player/match statistics');
      $key=$row['match_id'].'/'.$row['player_id'];if(isset($seen[$key]))throw new RuntimeException('Duplicate metrics row');$seen[$key]=true;
      $keys=array_keys($r['metrics']);$expected=$data['fields'];sort($keys);sort($expected);if($keys!==$expected)throw new RuntimeException('Unexpected metrics fields');
      foreach($r['metrics'] as $v)if($v!==null&&(!is_int($v)||$v<0))throw new RuntimeException('Invalid source count');
      foreach([['crosses_attempted','crosses_completed'],['line_breaks_attempted','line_breaks_completed'],['tackles_attempted','tackles_won']] as [$a,$b])if($r['metrics'][$a]!==null&&$r['metrics'][$b]!==null&&$r['metrics'][$b]>$r['metrics'][$a])throw new RuntimeException('Completed count exceeds attempts');
      $write->execute([json_encode(['metrics'=>$r['metrics'],'manifest_sha256'=>hash('sha256',json_encode($data['manifest'],JSON_THROW_ON_ERROR)),'source_appearance_id'=>$r['source_appearance_id']],JSON_THROW_ON_ERROR),$row['match_id'],$row['player_id']]);
    }
    $q=$db->prepare('INSERT INTO wc_import_batches(dataset_name,content_sha256,source_manifest,counts_json) VALUES(?,?,?,?)');
    $q->execute([$data['dataset_name'],$hash,json_encode($data['manifest'],JSON_THROW_ON_ERROR),json_encode(['extended_player_match_rows'=>count($seen)],JSON_THROW_ON_ERROR)]);
    if(in_array('--dry-run',$argv,true)){$db->rollBack();echo 'Player metrics validated and rolled back: '.count($seen)." rows.\n";}
    else{$db->commit();echo 'Player metrics imported: '.count($seen)." rows; table count unchanged.\n";}
  } finally {$db->query("SELECT RELEASE_LOCK('fourward_worldcup_import')");}
}catch(Throwable $error){if($db->inTransaction())$db->rollBack();fwrite(STDERR,'Metrics import failed: '.$error->getMessage()."\n");exit(1);}
