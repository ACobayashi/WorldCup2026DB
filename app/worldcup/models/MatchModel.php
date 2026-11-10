<?php
// 2410933_马禹翔
/** FOURWARD coursework | Author: 韩羽宸 | 学号: 2410673 | Fixtures, results and actual support counts. */
declare(strict_types=1);
namespace Fourward\worldcup\models;

final class MatchModel extends ReadModel {
  private const SELECT = 'SELECT m.*, h.code AS home_code, h.name_zh AS home_name, a.code AS away_code, a.name_zh AS away_name, st.name AS stage_name, g.name AS group_name, v.name AS venue_name, v.city AS venue_city, COALESCE(m.home_score_et,m.home_score_90) AS home_score, COALESCE(m.away_score_et,m.away_score_90) AS away_score, (SELECT COUNT(*) FROM wc_supports s WHERE s.match_id=m.id AND s.team_id=m.home_team_id) AS home_support, (SELECT COUNT(*) FROM wc_supports s WHERE s.match_id=m.id AND s.team_id=m.away_team_id) AS away_support FROM wc_matches m LEFT JOIN wc_teams h ON h.id=m.home_team_id LEFT JOIN wc_teams a ON a.id=m.away_team_id JOIN wc_stages st ON st.id=m.stage_id LEFT JOIN wc_groups g ON g.id=m.group_id LEFT JOIN wc_venues v ON v.id=m.venue_id';

  public function listing(array $filters, int $page, int $pageSize): array {
    $conditions = [];
    $params = [];
    if ($filters['status'] !== 'all') {
      $conditions[] = 'm.status=:status';
      $params['status'] = $filters['status'];
    }
    $phase=$filters['phase']??'all';
    if ($phase !== 'all') {
      $conditions[] = 'st.is_group_stage=:is_group_stage';
      $params['is_group_stage'] = $phase === 'group' ? 1 : 0;
    }
    $teamQuery=$filters['q']??'';
    if ($teamQuery !== '') {
      $conditions[] = '(h.name_zh LIKE :home_zh OR h.name_en LIKE :home_en OR h.code LIKE :home_code OR a.name_zh LIKE :away_zh OR a.name_en LIKE :away_en OR a.code LIKE :away_code)';
      foreach(['home_zh','home_en','home_code','away_zh','away_en','away_code'] as $key)$params[$key]='%'.$teamQuery.'%';
    }
    foreach (['stage_id', 'group_id'] as $field) {
      if ($filters[$field] !== null) {
        $conditions[] = 'm.' . $field . '=:' . $field;
        $params[$field] = $filters[$field];
      }
    }
    if ($filters['team_id'] !== null) {
      $conditions[] = '(m.home_team_id=:home OR m.away_team_id=:away)';
      $params['home'] = $params['away'] = $filters['team_id'];
    }
    if ($filters['date'] !== '') {
      $start = new \DateTimeImmutable($filters['date'] . ' 00:00:00', new \DateTimeZone('Asia/Shanghai'));
      $conditions[] = 'm.kickoff_at>=:start AND m.kickoff_at<:end';
      $params['start'] = $start->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
      $params['end'] = $start->modify('+1 day')->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
    $where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
    $countFrom = 'SELECT COUNT(*) AS total FROM wc_matches m LEFT JOIN wc_teams h ON h.id=m.home_team_id LEFT JOIN wc_teams a ON a.id=m.away_team_id JOIN wc_stages st ON st.id=m.stage_id';
    $result = $this->paginate(self::SELECT . $where . ' ORDER BY m.kickoff_at, m.id', $countFrom . $where, $params, $page, $pageSize);
    $result['items'] = array_map([$this, 'serialize'], $result['items']);
    return $result;
  }

  public function find(int $id): ?array {
    $row = $this->rows(self::SELECT . ' WHERE m.id=:id', ['id' => $id])[0] ?? null;
    return $row ? $this->serialize($row) : null;
  }

  private function serialize(array $row): array {
    foreach (['kickoff_at', 'support_closed_at', 'source_retrieved_at', 'created_at', 'updated_at'] as $field) {
      if ($row[$field] !== null) $row[$field] = str_replace(' ', 'T', $row[$field]) . 'Z';
    }
    return $row;
  }
}
