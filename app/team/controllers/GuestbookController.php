<?php
/** FOURWARD coursework | Author: 严苑毓 | Controller: public guestbook API. */
declare(strict_types=1);
namespace Fourward\team\controllers;
use Fourward\common\HttpError;
use Fourward\common\RateLimiter;
use Fourward\team\models\GuestbookMessage;
use Fourward\team\models\MemberMotto;

final class GuestbookController {
  public function __construct(private readonly \PDO $db) {}

  public function dispatch(string $action): array {
    $method = $_SERVER['REQUEST_METHOD'];
    if ($action === 'bootstrap' && $method === 'GET') {
      return ['csrfToken' => \csrfToken(), 'mottos' => (new MemberMotto($this->db))->all()];
    }
    if ($action === 'messages' && $method === 'GET') {
      $recipient = $_GET['recipient'] ?? 'team';
      $targets = require \PROJECT_ROOT . '/config/team.php';
      if (!is_string($recipient) || !isset($targets[$recipient])) throw new HttpError(422, '请选择有效的留言对象。');
      $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10000]]);
      if ($page === false) throw new HttpError(422, '无效的页码。');
      return (new GuestbookMessage($this->db))->listing('approved', $recipient, $page);
    }
    if ($action === 'submit' && $method === 'POST') {
      if (!str_starts_with(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) throw new HttpError(415, '请使用 JSON 提交留言。');
      $raw = file_get_contents('php://input', false, null, 0, 8193);
      if (strlen($raw) > 8192) throw new HttpError(413, '提交内容过大。');
      $body = json_decode($raw, true);
      if (!is_array($body)) throw new HttpError(422, '无效的留言内容。');
      foreach (['memberId', 'author', 'message'] as $field) {
        if (!is_string($body[$field] ?? null)) throw new HttpError(422, '留言字段格式不正确。');
      }
      \checkCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
      (new RateLimiter($this->db))->consume('guestbook', 5, 60);
      $id = (new GuestbookMessage($this->db))->create($body['memberId'], $body['author'], $body['message']);
      http_response_code(201);
      return ['id' => $id, 'status' => 'pending', 'notice' => '留言已提交，审核通过后会公开展示。'];
    }
    if (in_array($action, ['bootstrap', 'messages', 'submit'], true)) throw new HttpError(405, '请求方式不正确。');
    throw new HttpError(404, '接口不存在。');
  }
}
