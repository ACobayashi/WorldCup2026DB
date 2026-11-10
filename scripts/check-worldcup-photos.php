<?php
// 2410933_马禹翔
/** FOURWARD coursework | Author: 韩羽宸 | 学号: 2410673 | Check local portrait lookup, rendering and missing-image behavior. */
declare(strict_types=1);
require __DIR__.'/../app/common/bootstrap.php';
use Fourward\worldcup\models\PlayerPortrait;
use Fourward\worldcup\controllers\PageController;
$db=database();$checks=0;
function photoCheck(bool $ok,string $label):void {global $checks;if(!$ok)throw new RuntimeException($label);$checks++;}
$players=$db->query('SELECT id,name,shirt_number FROM wc_players')->fetchAll();
$linked=(new PlayerPortrait($db))->attach($players);
$report=json_decode(file_get_contents(PROJECT_ROOT.'/data/worldcup/player-photos/availability.json'),true);
$count=0;$heads=0;
foreach($linked as $p){if($p['portrait_url']!==null){$count++;if($p['portrait_kind']==='headshot')$heads++;}}
photoCheck($count===$report['available'],'All locally available files linked to DB');
photoCheck($heads===1248,'Complete DQD headshot coverage');
photoCheck((new PlayerPortrait($db))->attach([['id'=>PHP_INT_MAX]])[0]['portrait_url']===null,'Unknown source ID has no portrait');
require PROJECT_ROOT.'/app/worldcup/views/helpers.php';
photoCheck(!str_contains($portrait(['name'=>'<script>x</script>','portrait_url'=>'https://evil/photo.jpg']),'src='),'Reject remote and untrusted photo paths');
photoCheck(!str_contains($portrait(['name'=>'<script>x</script>']),'<script>'),'Escaped fallback label');
$native=array_values(array_filter($linked,fn($p)=>$p['portrait_kind']==='headshot'))[0];
photoCheck(str_contains($portrait($native),'wc-player-portrait--headshot'),'Native headshot style');
startSession();$_SERVER['REQUEST_METHOD']='GET';
foreach([['view'=>'player','id'=>$native['id']],['view'=>'leaders']] as $input){
  ob_start();(new PageController($db))->handle($input);$html=ob_get_clean();
  photoCheck(str_contains($html,'data-player-portrait'),'Photo rendered');
  photoCheck(str_contains($html,'player-portraits.mjs'),'Broken-photo fallback loaded');
}
echo "Player photos verified: $checks checks passed; $count photos, $heads native headshots. No database writes.\n";
