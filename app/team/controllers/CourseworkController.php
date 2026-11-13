<?php
/** 作者：林子媛 | 学号：2410936 */
declare(strict_types=1);
namespace Fourward\team\controllers;
use Fourward\common\{Auth, HttpError, Portal};
use Fourward\team\models\{CourseworkFile, TeamProfile};

final class CourseworkController {
  public function __construct(private readonly \PDO $db) {}

  public function index(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') throw new HttpError(405, '作业目录仅接受 GET。');
    $user = (new Auth($this->db))->user();
    $labels = require \PROJECT_ROOT . '/config/coursework.php';
    $profiles = (new TeamProfile($this->db))->all();
    $catalog = (new CourseworkFile($this->db))->catalog();
    Portal::render('coursework', compact('user', 'labels', 'profiles', 'catalog') + ['title' => '课程作业']);
  }

  public function download(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') throw new HttpError(405, '下载仅接受 GET。');
    $id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) throw new HttpError(422, '文件编号不正确。');
    $user = (new Auth($this->db))->user();
    if (!$user) {
      header('Location: account.php?return=' . rawurlencode('download.php?id=' . $id), true, 302);
      return;
    }
    $file = (new CourseworkFile($this->db))->downloadable($id, $user);
    if ($file['source_type'] === 'link') {
      session_write_close();
      header('Cache-Control: private, no-store');
      header('Referrer-Policy: no-referrer');
      header('Location: ' . $file['external_url'], true, 302);
      return;
    }
    $stream = fopen($file['path'], 'rb');
    if (!$stream) throw new HttpError(404, '文件暂不可用。');
    session_write_close();
    header('Content-Type: ' . $file['mime_type']);
    header('Content-Length: ' . $file['file_size']);
    header('Content-Disposition: attachment; filename="coursework-' . $id . '.' . pathinfo($file['stored_name'], PATHINFO_EXTENSION) . '"; filename*=UTF-8\'\'' . rawurlencode($file['original_name']));
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    fpassthru($stream);
    fclose($stream);
  }
}
