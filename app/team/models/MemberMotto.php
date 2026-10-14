<?php
/** FOURWARD coursework | Author: 严苑毓 | Model: editable team and member mottos. */
declare(strict_types=1);
namespace Fourward\team\models;
use Fourward\common\HttpError;

final class MemberMotto {
  public function __construct(private readonly \PDO $db) {}

  public function all(): array {
    $query = $this->db->query('SELECT member_slug, motto, is_placeholder FROM team_mottos');
    $mottos = [];
    foreach ($query as $row) {
      $mottos[$row['member_slug']] = ['text' => $row['motto'], 'isPlaceholder' => (bool) $row['is_placeholder']];
    }
    return $mottos;
  }

  public function save(string $member, string $text, array $user): void {
    $targets = require \PROJECT_ROOT . '/config/team.php';
    if (!isset($targets[$member])) throw new HttpError(422, '无效的成员。');
    if ($user['role'] !== 'admin' && $user['member_slug'] !== $member) throw new HttpError(403, '只能修改自己的座右铭。');
    $text = trim($text);
    if ($text === '' || mb_strlen($text) > 120) throw new HttpError(422, '座右铭需要填写 1 至 120 字。');
    $query = $this->db->prepare('UPDATE team_mottos SET motto = ?, is_placeholder = 0, updated_by = ?, updated_at = UTC_TIMESTAMP() WHERE member_slug = ?');
    $query->execute([$text, $user['id'], $member]);
  }
}
