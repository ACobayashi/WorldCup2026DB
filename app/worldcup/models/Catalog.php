<?php
/** FOURWARD coursework | Author: 韩羽宸 | 学号: 2410673 | Fixed-table venue, stage, group and overview queries. */
declare(strict_types=1);
namespace Fourward\worldcup\models;

final class Catalog extends ReadModel {
  private const TABLES = ['teams' => 'wc_teams', 'players' => 'wc_players', 'venues' => 'wc_venues', 'stages' => 'wc_stages', 'groups' => 'wc_groups', 'group_teams' => 'wc_group_teams', 'matches' => 'wc_matches', 'supports' => 'wc_supports'];

  public function counts(): array {
    $counts = [];
    foreach (self::TABLES as $key => $table) $counts[$key] = (int) $this->rows('SELECT COUNT(*) AS total FROM ' . $table)[0]['total'];
    return $counts;
  }

  public function listing(string $resource, int $page, int $pageSize): array {
    if (!in_array($resource, ['venues', 'stages', 'groups'], true)) throw new \LogicException('Unsupported catalog.');
    $table = self::TABLES[$resource];
    $order = $resource === 'venues' ? 'country, city, id' : 'sort_order, id';
    $result = $this->paginate('SELECT * FROM ' . $table . ' ORDER BY ' . $order, 'SELECT COUNT(*) AS total FROM ' . $table, [], $page, $pageSize);
    if ($resource === 'groups' && $result['items']) {
      $members = $this->rows('SELECT gt.group_id, t.id, t.code, t.name_zh FROM wc_group_teams gt JOIN wc_teams t ON t.id=gt.team_id ORDER BY t.code');
      foreach ($result['items'] as &$group) $group['teams'] = array_values(array_filter($members, fn(array $team): bool => $team['group_id'] === $group['id']));
      unset($group);
    }
    return $result;
  }
}
