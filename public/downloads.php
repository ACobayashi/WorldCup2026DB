<?php
/** FOURWARD coursework | Author: 严苑毓 | 学号: 2413682 | Course download entry. */
/** 统一账号入口：林子媛 | 学号：2410936 */
declare(strict_types=1);
require __DIR__ . '/../app/common/bootstrap.php';

use Fourward\common\Auth;
use Fourward\team\models\CourseMaterials;

header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

try {
  $user = (new Auth(database()))->user();
  $member = $_GET['member'] ?? null;
  $assignment = $_GET['assignment'] ?? null;
  if ($member !== null || $assignment !== null) {
    if (!$user) {
      header('Location: account.php?return=' . rawurlencode('downloads.php?' . http_build_query(['member' => $member, 'assignment' => $assignment])), true, 303);
      exit;
    }
    $file = is_string($member) && is_string($assignment) ? CourseMaterials::path($member, $assignment, $user) : null;
    if ($file === null || ($stream = fopen($file, 'rb')) === false) {
      http_response_code(404);
      header('Content-Type: text/plain; charset=utf-8');
      echo '该作业未提供或当前账号无权下载。';
      exit;
    }
    $size = fstat($stream)['size'];
    session_write_close();
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="assignment-' . $assignment . '.zip"');
    header('Content-Length: ' . $size);
    fpassthru($stream);
    fclose($stream);
    exit;
  }
  $targets = require \PROJECT_ROOT . '/config/team.php';
  ob_start();
  require \PROJECT_ROOT . '/app/team/views/downloads.php';
  $personal = ob_get_clean();
  $page = file_get_contents(__DIR__ . '/downloads.html');
  $page = str_replace('<link rel="stylesheet" href="assets/css/course-materials.css?v=1">', '<link rel="stylesheet" href="assets/css/course-materials.css?v=1"><link rel="stylesheet" href="assets/css/site-navigation.css?v=1">', $page);
  $page = str_replace('<body class="materials-page">', '<body class="materials-page">' . Fourward\common\SiteNavigation::html($user, '', 'account'), $page);
  header('Content-Type: text/html; charset=utf-8');
  echo preg_replace_callback('/<!-- personal-start -->.*?<!-- personal-end -->/s', static fn(): string => $personal, $page, 1);
} catch (Throwable $error) {
  error_log((string) $error);
  http_response_code(503);
  header('Content-Type: text/plain; charset=utf-8');
  echo '资料暂时无法读取，请稍后重试。';
}
