<?php
/**
 * FOURWARD coursework | Author: 严苑毓 | Model: visitor submission and moderation.
 * Public queries expose approved messages only; all visitor values use PDO parameters.
 */
declare(strict_types=1);
namespace Fourward\team\models;
use Fourward\common\HttpError;

final class GuestbookMessage {
  public function __construct(private readonly \PDO $db) {}

  public function create(string $recipient, string $author, string $message): int {
    $targets = require \PROJECT_ROOT . '/config/team.php';
    if (!isset($targets[$recipient])) throw new HttpError(422, '请选择有效的留言对象。');
    $author = trim($author) ?: '一位访客';
    $message = trim($message);
    if (mb_strlen($author) > 24) throw new HttpError(422, '称呼最多 24 字。');
    if ($message === '' || mb_strlen($message) > 280) throw new HttpError(422, '请填写 1 至 280 字的留言。');
    $query = $this->db->prepare('INSERT INTO team_guestbook_messages (recipient_slug, author, message, status, created_at) VALUES (?, ?, ?, \'pending\', UTC_TIMESTAMP())');
    $query->execute([$recipient, $author, $message]);
    return (int) $this->db->lastInsertId();
  }

  public function listing(string $status, ?string $recipient, int $page): array {
    if (!in_array($status, ['pending', 'approved', 'rejected'], true)) throw new HttpError(422, '无效的审核状态。');
    $where = 'status = :status';
    $params = ['status' => $status];
    if ($recipient !== null) {
      $where .= ' AND recipient_slug = :recipient';
      $params['recipient'] = $recipient;
    }
    $query = $this->db->prepare('SELECT COUNT(*) FROM team_guestbook_messages WHERE ' . $where);
    $query->execute($params);
    $total = (int) $query->fetchColumn();
    $query = $this->db->prepare('SELECT id, recipient_slug, author, message, status, created_at FROM team_guestbook_messages WHERE ' . $where . ' ORDER BY id DESC LIMIT :limit OFFSET :offset');
    foreach ($params as $name => $value) $query->bindValue(':' . $name, $value);
    $query->bindValue(':limit', 20, \PDO::PARAM_INT);
    $query->bindValue(':offset', ($page - 1) * 20, \PDO::PARAM_INT);
    $query->execute();
    $items = array_map(static fn(array $row): array => [
      'id' => (int) $row['id'], 'memberId' => $row['recipient_slug'],
      'author' => $row['author'], 'message' => $row['message'], 'status' => $row['status'],
      'createdAt' => str_replace(' ', 'T', $row['created_at']) . 'Z',
    ], $query->fetchAll());
    return ['items' => $items, 'total' => $total, 'page' => $page, 'pageSize' => 20];
  }

  public function review(int $id, string $status, int $reviewer): void {
    if ($id < 1 || !in_array($status, ['approved', 'rejected', 'pending'], true)) throw new HttpError(422, '无效的审核操作。');
    $query = $this->db->prepare('UPDATE team_guestbook_messages SET status = ?, reviewer_id = ?, reviewed_at = UTC_TIMESTAMP() WHERE id = ?');
    $query->execute([$status, $reviewer, $id]);
    if (!$query->rowCount()) {
      $exists = $this->db->prepare('SELECT id FROM team_guestbook_messages WHERE id = ?');
      $exists->execute([$id]);
      if (!$exists->fetch()) throw new HttpError(404, '这条留言不存在。');
    }
  }
}
