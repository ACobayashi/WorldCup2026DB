<?php
/** 作者：林子媛 | 学号：2410936 */
declare(strict_types=1);
namespace Fourward\team\controllers;
use Fourward\common\{Auth, HttpError, Portal, RateLimiter};
use Fourward\team\models\UserAccount;

final class AccountController {
  public function __construct(private readonly \PDO $db) {}

  public function handle(): void {
    $auth = new Auth($this->db);
    $user = $auth->user();
    $mode = Portal::text($_GET, 'mode', 'login');
    if (!in_array($mode, ['login', 'register'], true)) throw new HttpError(404, '账号页面不存在。');
    $returnPath = Portal::returnPath($_POST['return'] ?? $_GET['return'] ?? 'account.php');
    $username = '';
    $errorMessage = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      try {
        \checkCsrf(Portal::text($_POST, 'csrf'));
        $action = Portal::text($_POST, 'action');
        if ($action === 'logout') {
          $auth->logout();
          header('Location: ./', true, 303);
          return;
        }
        if (!in_array($action, ['login', 'register'], true)) throw new HttpError(422, '账号操作不正确。');
        $mode = $action;
        $username = trim(Portal::text($_POST, 'username'));
        $password = Portal::text($_POST, 'password');
        if (strlen($username) > 48 || strlen($password) > 256) throw new HttpError(422, '账号或密码格式不正确。');
        (new RateLimiter($this->db))->consume($action === 'register' ? 'registration' : 'login', $action === 'register' ? 5 : 10, 900);
        if ($action === 'register') {
          if ($user) throw new HttpError(409, '请先退出当前账号再注册。');
          (new UserAccount($this->db))->register($username, $password, Portal::text($_POST, 'password_confirmation'));
        }
        $auth->login($username, $password);
        header('Location: ' . $returnPath, true, 303);
        return;
      } catch (HttpError $error) {
        http_response_code($error->status);
        $errorMessage = $error->getMessage();
      }
    } elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') {
      throw new HttpError(405, '该页面仅接受 GET 或 POST。');
    }
    Portal::render('account', compact('user', 'mode', 'returnPath', 'username', 'errorMessage') + ['title' => $user ? '个人中心' : ($mode === 'register' ? '注册账号' : '账号登录')]);
  }
}
