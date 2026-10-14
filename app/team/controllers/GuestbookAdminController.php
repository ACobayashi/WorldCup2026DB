<?php
/** FOURWARD coursework | Author: 严苑毓 | Controller: login, review and motto editing. */
declare(strict_types=1);
namespace Fourward\team\controllers;
use Fourward\common\Auth;
use Fourward\common\HttpError;
use Fourward\common\RateLimiter;
use Fourward\team\models\GuestbookMessage;
use Fourward\team\models\MemberMotto;

final class GuestbookAdminController {
  public function __construct(private readonly \PDO $db) {}

  public function handle(): void {
    $auth = new Auth($this->db);
    \startSession();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $action = $_POST['action'] ?? '';
      try {
        \checkCsrf(is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : null);
        if ($action === 'login') {
          (new RateLimiter($this->db))->consume('login', 10, 900);
          $username = $_POST['username'] ?? '';
          $password = $_POST['password'] ?? '';
          if (!is_string($username) || !is_string($password) || strlen($username) > 48 || strlen($password) > 256) throw new HttpError(422, '账号格式不正确。');
          $auth->login(trim($username), $password);
          $_SESSION['flash'] = '登录成功。';
        } elseif ($action === 'logout') {
          $auth->logout();
        } elseif ($action === 'review') {
          $user = $auth->requireAdmin();
          $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
          $statusValue = $_POST['status'] ?? '';
          if ($id === false || !is_string($statusValue)) throw new HttpError(422, '无效的审核操作。');
          (new GuestbookMessage($this->db))->review($id, $statusValue, (int) $user['id']);
          $_SESSION['flash'] = '审核状态已更新。';
        } elseif ($action === 'motto') {
          $user = $auth->requireUser();
          if (!is_string($_POST['member'] ?? null) || !is_string($_POST['motto'] ?? null)) throw new HttpError(422, '座右铭格式不正确。');
          (new MemberMotto($this->db))->save($_POST['member'], $_POST['motto'], $user);
          $_SESSION['flash'] = '座右铭已更新，前台刷新后可见。';
        } else {
          throw new HttpError(422, '无效的操作。');
        }
        $filter = is_string($_POST['filter'] ?? null) && in_array($_POST['filter'], ['pending', 'approved', 'rejected'], true) ? $_POST['filter'] : 'pending';
        header('Location: admin.php?status=' . $filter, true, 303);
        return;
      } catch (HttpError $error) {
        http_response_code($error->status);
        $errorMessage = $error->getMessage();
      }
    }
    $user = $auth->user();
    $targets = require \PROJECT_ROOT . '/config/team.php';
    $mottos = (new MemberMotto($this->db))->all();
    $status = is_string($_GET['status'] ?? null) && in_array($_GET['status'], ['pending', 'approved', 'rejected'], true) ? $_GET['status'] : 'pending';
    $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10000]]) ?: 1;
    $listing = $user && $user['role'] === 'admin' ? (new GuestbookMessage($this->db))->listing($status, null, $page) : null;
    $flash = $_SESSION['flash'] ?? '';
    unset($_SESSION['flash']);
    require \PROJECT_ROOT . '/app/team/views/guestbook/admin.php';
  }
}
