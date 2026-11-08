<?php
/** FOURWARD coursework | Author: 韩羽宸 | Match squads, statistics and source event timeline. */
declare(strict_types=1);
namespace Fourward\worldcup\models;

final class MatchDetail extends ReadModel {
  public function forMatch(int $id): array {
    $result = [
      'lineups' => $this->rows('SELECT a.match_id,a.team_id,a.player_id,a.shirt_number,a.position,a.is_starter,a.appeared,p.name,s.goals,s.shots,s.passes_completed,s.passes_attempted,s.assists,s.minutes_played FROM wc_match_appearances a JOIN wc_players p ON p.id=a.player_id LEFT JOIN wc_player_match_stats s ON s.match_id=a.match_id AND s.player_id=a.player_id WHERE a.match_id=:id ORDER BY a.is_starter DESC,a.shirt_number,a.player_id', ['id'=>$id]),
      'statistics' => $this->rows('SELECT team_id,goals,possession_pct,shots,shots_on_target,passes_attempted,passes_completed,pass_completion_pct FROM wc_team_match_stats WHERE match_id=:id ORDER BY team_id', ['id'=>$id]),
      'events' => $this->rows('SELECT e.id,e.player_id,e.event_type,e.minute,e.added_minute,e.minute_label,p.name,a.team_id,a.shirt_number,a.appeared FROM wc_match_events e JOIN wc_players p ON p.id=e.player_id JOIN wc_match_appearances a ON a.match_id=e.match_id AND a.player_id=e.player_id WHERE e.match_id=:id ORDER BY (e.minute+e.added_minute),e.minute,e.added_minute,e.id', ['id'=>$id]),
    ];
    $identity=new PlayerIdentity($this->db);
    $result['lineups']=(new GoalkeeperStats($this->db))->attach($result['lineups'],'player_id');
    $result['lineups']=$identity->attach($result['lineups'],'player_id');
    $result['events']=$identity->attach($result['events'],'player_id');
    $result['lineups']=(new PlayerPortrait($this->db))->attach($result['lineups'],'player_id');
    return $result;
  }
}
