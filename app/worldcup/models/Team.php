<?php
/** FOURWARD coursework | Author: 韩羽宸 | Team and group membership queries. */
declare(strict_types=1);
namespace Fourward\worldcup\models;

final class Team extends ReadModel {
  private const SELECT = 'SELECT t.*, g.code AS group_code, g.name AS group_name FROM wc_teams t LEFT JOIN wc_group_teams gt ON gt.team_id=t.id LEFT JOIN wc_groups g ON g.id=gt.group_id';

  public function listing(string $search, int $page, int $pageSize): array {
    $where = '';
    $params = [];
    if ($search !== '') {
      $where = " WHERE (t.name_zh LIKE :zh ESCAPE '!' OR t.name_en LIKE :en ESCAPE '!' OR t.code LIKE :code ESCAPE '!')";
      $pattern = '%' . strtr($search, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
      $params = ['zh' => $pattern, 'en' => $pattern, 'code' => $pattern];
    }
    return $this->paginate(self::SELECT . $where . ' ORDER BY t.code, t.id', 'SELECT COUNT(*) AS total FROM wc_teams t' . $where, $params, $page, $pageSize);
  }

  public function find(int $id): ?array {
    return $this->rows(self::SELECT . ' WHERE t.id=:id', ['id' => $id])[0] ?? null;
  }

  public function squad(int $id): array {
    $players=$this->rows('SELECT p.id,p.name,p.shirt_number,p.position,COALESCE(SUM(a.appeared),0) AS appearances,SUM(s.goals) AS goals,SUM(s.shots) AS shots FROM wc_players p LEFT JOIN wc_match_appearances a ON a.player_id=p.id LEFT JOIN wc_player_match_stats s ON s.match_id=a.match_id AND s.player_id=a.player_id WHERE p.team_id=:id GROUP BY p.id,p.name,p.shirt_number,p.position ORDER BY FIELD(p.position,\'GK\',\'DF\',\'MF\',\'FW\'),p.shirt_number,p.id', ['id'=>$id]);
    return (new PlayerPortrait($this->db))->attach($players);
  }
}
