<?php
// 2410933_马禹翔
/** FOURWARD coursework | Author: 韩羽宸 | 学号: 2410673 | Verified tournament-wide team comparison aggregates. */
declare(strict_types=1);
namespace Fourward\worldcup\models;

final class TeamComparison extends ReadModel {
  public function options(): array {
    return $this->rows('SELECT t.id,t.code,t.name_zh,t.name_en,g.name AS group_name FROM wc_teams t LEFT JOIN wc_group_teams gt ON gt.team_id=t.id LEFT JOIN wc_groups g ON g.id=gt.group_id ORDER BY t.name_zh,t.code');
  }

  public function find(int $id): ?array {
    $rows=$this->rows("SELECT t.id,t.code,t.name_zh,t.name_en,t.crest_path,t.source_url,g.name AS group_name,
      COUNT(m.id) AS played,
      COALESCE(SUM(CASE WHEN (CASE WHEN m.home_team_id=t.id THEN COALESCE(m.home_score_et,m.home_score_90) ELSE COALESCE(m.away_score_et,m.away_score_90) END) > (CASE WHEN m.home_team_id=t.id THEN COALESCE(m.away_score_et,m.away_score_90) ELSE COALESCE(m.home_score_et,m.home_score_90) END) THEN 1 ELSE 0 END),0) AS wins,
      COALESCE(SUM(CASE WHEN COALESCE(m.home_score_et,m.home_score_90)=COALESCE(m.away_score_et,m.away_score_90) THEN 1 ELSE 0 END),0) AS draws,
      COALESCE(SUM(CASE WHEN (CASE WHEN m.home_team_id=t.id THEN COALESCE(m.home_score_et,m.home_score_90) ELSE COALESCE(m.away_score_et,m.away_score_90) END) < (CASE WHEN m.home_team_id=t.id THEN COALESCE(m.away_score_et,m.away_score_90) ELSE COALESCE(m.home_score_et,m.home_score_90) END) THEN 1 ELSE 0 END),0) AS losses,
      COALESCE(SUM(CASE WHEN m.home_team_id=t.id THEN COALESCE(m.home_score_et,m.home_score_90) ELSE COALESCE(m.away_score_et,m.away_score_90) END),0) AS goals_for,
      COALESCE(SUM(CASE WHEN m.home_team_id=t.id THEN COALESCE(m.away_score_et,m.away_score_90) ELSE COALESCE(m.home_score_et,m.home_score_90) END),0) AS goals_against,
      COALESCE(SUM(s.shots),0) AS shots,COALESCE(SUM(s.shots_on_target),0) AS shots_on_target,
      COUNT(s.shots) AS shots_games,COUNT(s.shots_on_target) AS shots_on_target_games,COUNT(s.passes_completed) AS passes_completed_games,
      ROUND(AVG(s.possession_pct),2) AS average_possession_pct,
      COALESCE(SUM(s.passes_attempted),0) AS passes_attempted,COALESCE(SUM(s.passes_completed),0) AS passes_completed,
      ROUND(100*SUM(s.passes_completed)/NULLIF(SUM(s.passes_attempted),0),2) AS pass_completion_pct,
      COALESCE(SUM(CASE WHEN (CASE WHEN m.home_team_id=t.id THEN COALESCE(m.away_score_et,m.away_score_90) ELSE COALESCE(m.home_score_et,m.home_score_90) END)=0 THEN 1 ELSE 0 END),0) AS clean_sheets
      FROM wc_teams t
      LEFT JOIN wc_group_teams gt ON gt.team_id=t.id LEFT JOIN wc_groups g ON g.id=gt.group_id
      LEFT JOIN wc_matches m ON m.status='finished' AND (m.home_team_id=t.id OR m.away_team_id=t.id)
      LEFT JOIN wc_team_match_stats s ON s.match_id=m.id AND s.team_id=t.id
      WHERE t.id=:id GROUP BY t.id,t.code,t.name_zh,t.name_en,t.crest_path,t.source_url,g.name",['id'=>$id]);
    if(!$rows)return null;
    $team=$rows[0];
    foreach(['id','played','wins','draws','losses','goals_for','goals_against','shots','shots_on_target','passes_attempted','passes_completed','clean_sheets'] as $key)$team[$key]=(int)$team[$key];
    $team['goal_difference']=$team['goals_for']-$team['goals_against'];
    $team['stat_match_counts'] = [];
    foreach (['shots', 'shots_on_target', 'passes_completed'] as $key) {
      $team['stat_match_counts'][$key] = (int)$team[$key.'_games'];
      unset($team[$key.'_games']);
    }
    foreach(['average_possession_pct','pass_completion_pct'] as $key)$team[$key]=$team[$key]===null?null:(float)$team[$key];
    $xg=self::expectedGoals();
    $team['expected_goals']=$xg[$team['code']]['for']??null;
    $team['expected_goals_against']=$xg[$team['code']]['against']??null;
    $values=self::marketValues();$value=$values[$team['code']]??null;
    $team['highest_starting_xi_value_eur']=$value['highest_starting_xi_value_eur']??null;
    $team['highest_starting_xi_value_raw']=$value['highest_starting_xi_value_raw']??null;
    $team['market_value_source_url']=$value['source_url']??null;
    return $team;
  }

  private static function expectedGoals(): array {
    $path=\PROJECT_ROOT.'/data/worldcup/full/raw/report-team_stats.json';
    $rows=json_decode((string)file_get_contents($path),true,512,JSON_THROW_ON_ERROR);$matches=[];
    foreach($rows as $row)if(($row['metric']??null)==='xG (Expected Goals)')$matches[$row['match_id']][$row['team_id']]=(float)$row['value'];
    $result=[];
    foreach($matches as $teams)foreach($teams as $code=>$value){$result[$code]['for']=($result[$code]['for']??0)+$value;foreach($teams as $opponent=>$other)if($opponent!==$code)$result[$code]['against']=($result[$code]['against']??0)+$other;}
    foreach($result as &$team){$team['for']=round($team['for'],2);$team['against']=round($team['against'],2);}unset($team);
    return $result;
  }

  private static function marketValues(): array {
    $path=\PROJECT_ROOT.'/data/worldcup/team-market-values.json';
    if(!is_file($path))return [];
    return json_decode((string)file_get_contents($path),true,512,JSON_THROW_ON_ERROR)['teams']??[];
  }
}
