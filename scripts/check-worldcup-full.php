<?php
/** FOURWARD coursework | Author: 韩羽宸 | Verify the actual full import, including repeatability. */
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit(1);
require __DIR__.'/../app/common/bootstrap.php';
$db=database();$checks=0;
function fullCheck(bool $ok,string $message):void { global $checks; if (!$ok) throw new RuntimeException('FAILED: '.$message); $checks++; }
foreach (['teams'=>48,'players'=>1248,'venues'=>16,'stages'=>7,'groups'=>12,'group_teams'=>48,'matches'=>104,
          'match_appearances'=>5392,'player_match_stats'=>3288,'team_match_stats'=>208,'match_events'=>2587] as $suffix=>$expected) {
  fullCheck((int)$db->query('SELECT COUNT(*) FROM wc_'.$suffix)->fetchColumn()===$expected,'Full row count: '.$suffix);
}
foreach (['wc_match_appearances','wc_player_match_stats','wc_team_match_stats','wc_match_events'] as $table) fullCheck((int)$db->query('SELECT COUNT(DISTINCT match_id) FROM '.$table)->fetchColumn()===104,'104-match coverage: '.$table);
fullCheck((int)$db->query("SELECT COUNT(*) FROM wc_match_appearances a JOIN wc_matches m ON m.id=a.match_id JOIN wc_players p ON p.id=a.player_id WHERE a.team_id<>p.team_id OR a.team_id NOT IN (m.home_team_id,m.away_team_id)")->fetchColumn()===0,'No cross-team player corruption');
fullCheck((int)$db->query('SELECT COUNT(*) FROM (SELECT match_id,team_id FROM wc_match_appearances GROUP BY match_id,team_id HAVING SUM(is_starter)<>11) s')->fetchColumn()===0,'Every starting XI has 11 players');
fullCheck((int)$db->query('SELECT COUNT(*) FROM wc_player_match_stats s JOIN wc_match_appearances a USING(match_id,player_id) WHERE a.appeared=0')->fetchColumn()===0,'Statistics only for players who appeared');
fullCheck((int)$db->query('SELECT COUNT(*) FROM wc_player_match_stats WHERE assists IS NOT NULL OR minutes_played IS NOT NULL')->fetchColumn()===0,'Missing values were not invented');
fullCheck((int)$db->query('SELECT COUNT(*) FROM wc_players WHERE birth_date IS NOT NULL')->fetchColumn()===0,'No birth dates imported');
fullCheck((int)$db->query('SELECT COUNT(*) FROM wc_matches WHERE home_penalty_score IS NOT NULL')->fetchColumn()===4,'Four separately scored shootouts');
$final=$db->query('SELECT * FROM wc_matches WHERE match_no=104')->fetch();
fullCheck((int)$final['home_score_90']===0 && (int)$final['away_score_90']===0 && (int)$final['home_score_et']===1 && (int)$final['away_score_et']===0,'Final regulation/extra-time score separation');
fullCheck((int)$db->query('SELECT SUM(goals) FROM wc_player_match_stats')->fetchColumn()===294,'294 player goals');
fullCheck((int)$db->query("SELECT COUNT(*) FROM wc_match_events WHERE event_type='own_goal'")->fetchColumn()===14,'14 own goals');
fullCheck((int)$db->query('SELECT SUM(goals) FROM wc_team_match_stats')->fetchColumn()===308,'308 team goals including own goals');
fullCheck($db->query("SELECT GROUP_CONCAT(DISTINCT t.code ORDER BY t.code) FROM wc_match_appearances a JOIN wc_matches m ON m.id=a.match_id JOIN wc_teams t ON t.id=a.team_id WHERE m.match_no=47")->fetchColumn()==='POR,UZB','Match 47 scoped correction');
fullCheck($db->query("SELECT t.code FROM wc_source_mappings s JOIN wc_teams t ON t.id=s.team_id WHERE s.provider='worldcup26-matchteam' AND s.source_id='2026-M047-POR-GHA/GHA'")->fetchColumn()==='UZB','Scoped source-team identity preserved');
$content=file_get_contents(PROJECT_ROOT.'/data/worldcup/full/complete.json');$data=json_decode($content,true,512,JSON_THROW_ON_ERROR);
$result=(new Fourward\worldcup\services\DataImporter($db))->import($data,hash('sha256',$content));
fullCheck($result['status']==='already_imported','Identical full import is idempotent');
echo "Full World Cup import verified: $checks checks passed.\n";
