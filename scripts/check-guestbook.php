<?php
/**
 * FOURWARD coursework | Author: 严苑毓 | Database integration acceptance checks.
 * Uses a transaction and always rolls back fixtures; run against a development DB.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require __DIR__ . '/../app/common/bootstrap.php';
use Fourward\common\Auth;
use Fourward\common\HttpError;
use Fourward\common\RateLimiter;
use Fourward\team\models\GuestbookMessage;
use Fourward\team\models\MemberMotto;

$checks = 0;
function verify(bool $condition, string $label): void {
  global $checks;
  if (!$condition) throw new RuntimeException('FAILED: ' . $label);
  $checks++;
}
function rejects(callable $operation, int $status): void {
  try { $operation(); } catch (HttpError $error) {
    verify($error->status === $status, 'expected HTTP ' . $status);
    return;
  }
  throw new RuntimeException('Operation unexpectedly succeeded.');
}
startSession();
$db = database();
$db->beginTransaction();
try {
  $suffix = bin2hex(random_bytes(5));
  $query = $db->prepare('INSERT INTO users (username, role, member_slug, password_hash) VALUES (?, ?, ?, ?)');
  $password = bin2hex(random_bytes(12));
  $query->execute(['test-admin-' . $suffix, 'admin', 'yan-yuanyu', password_hash($password, PASSWORD_DEFAULT)]);
  $adminId = (int) $db->lastInsertId();
  $query->execute(['test-member-' . $suffix, 'member', 'lin-ziyuan', password_hash($password, PASSWORD_DEFAULT)]);
  $memberId = (int) $db->lastInsertId();
  $admin = ['id' => $adminId, 'role' => 'admin', 'member_slug' => 'yan-yuanyu'];
  $member = ['id' => $memberId, 'role' => 'member', 'member_slug' => 'lin-ziyuan'];
  $messages = new GuestbookMessage($db);
  $mottos = new MemberMotto($db);

  $id = $messages->create('team', '', '   测试留言   ');
  $query = $db->prepare('SELECT author, message, status FROM team_guestbook_messages WHERE id = ?');
  $query->execute([$id]);
  $row = $query->fetch();
  verify($row === ['author' => '一位访客', 'message' => '测试留言', 'status' => 'pending'], 'anonymous submission starts pending');
  verify(!in_array($id, array_column($messages->listing('approved', 'team', 1)['items'], 'id'), true), 'pending message stays private');
  $messages->review($id, 'approved', $adminId);
  verify(in_array($id, array_column($messages->listing('approved', 'team', 1)['items'], 'id'), true), 'approval publishes');
  $messages->review($id, 'rejected', $adminId);
  verify(!in_array($id, array_column($messages->listing('approved', 'team', 1)['items'], 'id'), true), 'rejection hides previously public content');
  $messages->review($id, 'pending', $adminId);
  $query->execute([$id]);
  verify($query->fetch()['status'] === 'pending', 'review can return to pending');
  rejects(fn() => $messages->create('unknown', '', '你好'), 422);
  rejects(fn() => $messages->create('team', '', '  '), 422);
  rejects(fn() => $messages->create('team', str_repeat('名', 25), '你好'), 422);
  rejects(fn() => $messages->create('team', '', str_repeat('字', 281)), 422);
  verify($messages->create('team', str_repeat('名', 24), str_repeat('😀', 280)) > 0, 'Unicode length boundaries');
  rejects(fn() => $messages->review(PHP_INT_MAX, 'approved', $adminId), 404);
  rejects(fn() => $messages->review($id, 'deleted', $adminId), 422);
  $literal = '<img src=x onerror=alert(1)> ' . "'; DROP TABLE users; --";
  $literalId = $messages->create('team', '<svg onload=alert(1)>', $literal);
  $messages->review($literalId, 'approved', $adminId);
  $public = $messages->listing('approved', 'team', 1)['items'];
  verify($public[0]['message'] === $literal, 'SQL and HTML input remain plain text');
  verify(!str_contains(e($literal), '<img'), 'server view escapes visitor text');
  verify(str_ends_with($public[0]['createdAt'], 'Z'), 'API timestamps declare UTC');

  $before = $messages->listing('approved', 'lin-ziyuan', 1)['total'];
  for ($i = 0; $i < 23; $i++) {
    $newId = $messages->create('lin-ziyuan', '分页测试', '分页 ' . $i);
    $messages->review($newId, 'approved', $adminId);
  }
  $first = $messages->listing('approved', 'lin-ziyuan', 1);
  $second = $messages->listing('approved', 'lin-ziyuan', 2);
  verify($first['total'] === $before + 23 && count($first['items']) === 20 && count($second['items']) >= 3, 'pagination and total count');
  verify(count(array_intersect(array_column($first['items'], 'id'), array_column($second['items'], 'id'))) === 0, 'pages contain distinct records');

  $auth = new Auth($db);
  rejects(fn() => $auth->requireUser(), 401);
  $auth->login('test-member-' . $suffix, $password);
  verify($auth->user()['username'] === 'test-member-' . $suffix, 'password login');
  rejects(fn() => $auth->requireAdmin(), 403);
  $mottos->save('lin-ziyuan', '自己的座右铭', $member);
  verify($mottos->all()['lin-ziyuan'] === ['text' => '自己的座右铭', 'isPlaceholder' => false], 'member edits own motto');
  rejects(fn() => $mottos->save('yan-yuanyu', '越权修改', $member), 403);
  rejects(fn() => $mottos->save('team', '越权修改', $member), 403);
  rejects(fn() => $mottos->save('lin-ziyuan', str_repeat('字', 121), $member), 422);
  rejects(fn() => $mottos->save('lin-ziyuan', ' ', $member), 422);
  $mottos->save('team', '团队的新座右铭', $admin);
  verify($mottos->all()['team']['text'] === '团队的新座右铭', 'admin edits team motto');
  rejects(fn() => checkCsrf('invalid'), 403);
  checkCsrf(csrfToken());
  verify(true, 'valid CSRF token accepted');
  $auth->logout();
  rejects(fn() => $auth->requireUser(), 401);
  $limiter = new RateLimiter($db);
  for ($i = 0; $i < 5; $i++) $limiter->consume('test-' . $suffix, 5, 60);
  rejects(fn() => $limiter->consume('test-' . $suffix, 5, 60), 429);
  echo "Guestbook database acceptance passed: $checks checks. All fixtures rolled back.\n";
} finally {
  if ($db->inTransaction()) $db->rollBack();
}
