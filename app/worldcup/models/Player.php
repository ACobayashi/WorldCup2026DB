<?php
/** FOURWARD coursework | Author: 韩羽宸 | 学号: 2410673 | This edition's squad queries. */
declare(strict_types=1);
namespace Fourward\worldcup\models;

final class Player extends ReadModel {
  public function listing(int $teamId, int $page, int $pageSize): array {
    $result=$this->paginate('SELECT id, team_id, name, shirt_number, position, birth_date, source_url, source_retrieved_at FROM wc_players WHERE team_id=:team ORDER BY shirt_number IS NULL, shirt_number, id', 'SELECT COUNT(*) AS total FROM wc_players WHERE team_id=:team', ['team' => $teamId], $page, $pageSize);
    $result['items']=(new PlayerIdentity($this->db))->attach($result['items']);
    $result['items']=(new PlayerPortrait($this->db))->attach($result['items']);
    return $result;
  }
}
