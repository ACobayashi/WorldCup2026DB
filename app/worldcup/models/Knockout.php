<?php
/** FOURWARD coursework | Author: 韩羽宸 | Actual WC26 winner-feed graph, not chronological index pairing. */
declare(strict_types=1);
namespace Fourward\worldcup\models;

final class Knockout extends ReadModel {
  public function bracket():array {
    $config=json_decode(file_get_contents(\PROJECT_ROOT.'/data/worldcup/knockout-paths.json'),true,512,JSON_THROW_ON_ERROR);
    $rows=$this->rows('SELECT m.id,m.match_no,m.home_team_id,m.away_team_id,m.home_score_90,m.away_score_90,m.home_score_et,m.away_score_et,m.home_penalty_score,m.away_penalty_score,m.winner_team_id,m.status,m.kickoff_at,h.name_zh AS home_name,h.code AS home_code,a.name_zh AS away_name,a.code AS away_code,COALESCE(m.home_score_et,m.home_score_90) AS home_score,COALESCE(m.away_score_et,m.away_score_90) AS away_score FROM wc_matches m LEFT JOIN wc_teams h ON h.id=m.home_team_id LEFT JOIN wc_teams a ON a.id=m.away_team_id WHERE m.match_no BETWEEN 73 AND 104 ORDER BY m.match_no');
    $matches=[];foreach($rows as $m)$matches[$m['match_no']]=$m;
    $nodes=[];$edges=[];$leaf=0;$warnings=[];
    $walk=function(int $no)use(&$walk,&$nodes,&$edges,&$leaf,&$warnings,$config,$matches):array {
      $parents=$config['winner_feeds'][(string)$no]??[];
      if(!$parents){$point=['column'=>0,'y'=>80+($leaf++)*108];}
      else{
        $a=$walk($parents[0]);$b=$walk($parents[1]);$point=['column'=>$a['column']+1,'y'=>($a['y']+$b['y'])/2];
        foreach($parents as $parent){$edges[]=['from'=>$parent,'to'=>$no];$source=$matches[$parent]??null;$target=$matches[$no]??null;
          if($source&&$target&&$source['status']==='finished'&&$source['winner_team_id']!==null&&!in_array($source['winner_team_id'],[$target['home_team_id'],$target['away_team_id']],true))$warnings[]='第 '.$parent.' 场胜者与第 '.$no.' 场对阵不一致。';
        }
      }
      $nodes[$no]=$point+['match'=>$matches[$no]??null,'match_no'=>$no];return $point;
    };
    $walk(104);
    return ['nodes'=>array_values($nodes),'edges'=>$edges,'bronze'=>$matches[103]??null,'warnings'=>$warnings,'height'=>1790];
  }
}
