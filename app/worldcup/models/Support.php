<?php
/** FOURWARD coursework | Author: 韩羽宸 | Read-only support queries; write workflow is a later module. */
declare(strict_types=1);
namespace Fourward\worldcup\models;

final class Support extends ReadModel {
  public function listing(int $userId, int $page, int $pageSize): array {
    return $this->paginate('SELECT s.id, s.match_id, s.team_id, t.name_zh AS team_name, m.match_no, s.created_at, s.updated_at FROM wc_supports s JOIN wc_teams t ON t.id=s.team_id JOIN wc_matches m ON m.id=s.match_id WHERE s.user_id=:user ORDER BY s.updated_at DESC, s.id DESC', 'SELECT COUNT(*) AS total FROM wc_supports WHERE user_id=:user', ['user' => $userId], $page, $pageSize);
  }
}
