<?php
/** 作者：林子媛 | 学号：2410936 */
declare(strict_types=1);
namespace Fourward\team\controllers;
use Fourward\common\{Auth, HttpError, Portal, RateLimiter};
use Fourward\team\models\{CourseworkFile, TeamProfile, UserAccount};

final class WorkspaceController {
  public function __construct(private readonly \PDO $db) {}

  public function handle(): void {
    $user = (new Auth($this->db))->user();
    if (!$user) {
      $destination = 'workspace.php' . (isset($_GET['section']) && is_string($_GET['section']) && in_array($_GET['section'], ['profile', 'files', 'stats'], true) ? '?section=' . $_GET['section'] : '');
      header('Location: account.php?return=' . rawurlencode($destination), true, 302);
      return;
    }
    Portal::editor($user);
    $section = Portal::text($_GET, 'section', 'profile');
    if (!in_array($section, ['profile', 'files', 'stats'], true)) throw new HttpError(404, '工作台页面不存在。');
    $profiles = (new TeamProfile($this->db))->all();
    $slug = Portal::text($_GET, 'member', $user['member_slug'] ?: 'team');
    if (!TeamProfile::canEdit($user, $slug) || !isset($profiles[$slug])) throw new HttpError(403, '不能访问这位成员的编辑页面。');
    $errorMessage = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      try {
        if ($_POST === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) throw new HttpError(413, '上传请求超过 PHP 的 post_max_size 限制。');
        \checkCsrf(Portal::text($_POST, 'csrf'));
        $action = Portal::text($_POST, 'action');
        if ($action === 'profile') {
          $slug = Portal::text($_POST, 'member_slug');
          (new TeamProfile($this->db))->save($slug, $_POST, $user);
          $section = 'profile';
        } elseif ($action === 'upload') {
          (new RateLimiter($this->db))->consume('coursework-upload', 10, 60);
          $source = Portal::text($_POST, 'source_type', 'file');
          if ($source === 'link') (new CourseworkFile($this->db))->createLink($_POST, $user);
          elseif ($source === 'file') (new CourseworkFile($this->db))->upload($_POST, $_FILES['coursework'] ?? [], $user);
          else throw new HttpError(422, '提交方式不正确。');
          $section = 'files';
        } elseif ($action === 'publish') {
          $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
          if ($id === false) throw new HttpError(422, '文件编号不正确。');
          (new CourseworkFile($this->db))->publish($id, Portal::text($_POST, 'status'), $user);
          $section = 'files';
        } else throw new HttpError(422, '工作台操作不正确。');
        $_SESSION['portal_flash'] = $action === 'upload' ? '已保存为草稿。' : ($action === 'publish' ? '发布状态已更新。' : '资料已保存。');
        header('Location: workspace.php?' . http_build_query(['section' => $section, 'member' => $slug]), true, 303);
        return;
      } catch (HttpError $error) {
        http_response_code($error->status);
        $errorMessage = $error->getMessage();
        if (!TeamProfile::canEdit($user, $slug)) $slug = $user['member_slug'] ?: 'team';
      }
    } elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') throw new HttpError(405, '该页面仅接受 GET 或 POST。');
    $files = (new CourseworkFile($this->db))->listing($user);
    $labels = require \PROJECT_ROOT . '/config/coursework.php';
    $stats = ['files' => array_values(array_filter((new CourseworkFile($this->db))->statistics(), fn($row) => $user['role'] === 'admin' || $row['owner_slug'] === $user['member_slug'])), 'roles' => $user['role'] === 'admin' ? (new UserAccount($this->db))->statistics()['roles'] : null];
    if ($section === 'stats' && Portal::text($_GET, 'format') === 'json') {
      header('Content-Type: application/json; charset=utf-8');
      header('Cache-Control: private, no-store');
      echo json_encode($stats, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
      return;
    }
    $flash = $_SESSION['portal_flash'] ?? '';
    unset($_SESSION['portal_flash']);
    $profile = $profiles[$slug];
    if ($errorMessage && ($_POST['action'] ?? '') === 'profile' && TeamProfile::canEdit($user, $slug)) {
      foreach (['display_name', 'school', 'role_title', 'biography', 'github_url', 'headline'] as $field) if (is_string($_POST[$field] ?? null)) $profile[$field] = $_POST[$field];
    }
    Portal::render('workspace', compact('user', 'section', 'profiles', 'slug', 'profile', 'files', 'labels', 'stats', 'errorMessage', 'flash') + ['title' => '内容工作台']);
  }
}
