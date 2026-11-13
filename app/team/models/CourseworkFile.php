<?php
/** 作者：林子媛 | 学号：2410936 */
declare(strict_types=1);
namespace Fourward\team\models;
use Fourward\common\HttpError;

final class CourseworkFile {
  public const MAX_BYTES = 52428800;
  private const MIMES = [
    'pdf' => ['application/pdf'], 'zip' => ['application/zip', 'application/x-zip-compressed'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
    'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
    'mp4' => ['video/mp4'],
  ];
  public function __construct(private readonly \PDO $db) {}

  public function listing(?array $editor = null): array {
    $where = $editor ? ($editor['role'] === 'admin' ? '' : ' WHERE owner_slug=:owner') : " WHERE status='published'";
    $order = $editor ? 'id DESC' : 'published_at DESC, id DESC';
    $query = $this->db->prepare('SELECT id, owner_slug, assignment_no, title, original_name, mime_type, file_size, source_type, status, created_at, published_at FROM coursework_files' . $where . ' ORDER BY ' . $order . ' LIMIT 500');
    $query->execute($editor && $editor['role'] !== 'admin' ? ['owner' => $editor['member_slug']] : []);
    return $query->fetchAll();
  }

  public function catalog(): array {
    $catalog = [];
    foreach ($this->listing() as $file) $catalog[$file['owner_slug']][$file['assignment_no']] ??= $file;
    return $catalog;
  }

  public function upload(array $input, array $file, array $user): int {
    [$owner, $number, $title] = $this->metadata($input, $user);
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) throw new HttpError(413, '文件超过服务器上传限制。');
    if ($error !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) throw new HttpError(422, '请选择有效文件并完整上传。');
    $name = $file['name'] ?? '';
    if (!is_string($name) || mb_strlen($name) > 255 || preg_match('~[\\\\/\x00-\x1f\x7f]~', $name)) throw new HttpError(422, '文件名不合法。');
    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset(self::MIMES[$extension]) || !in_array($mime, self::MIMES[$extension], true)) throw new HttpError(422, '仅支持真实的 PDF、ZIP、DOCX、PPTX 和 MP4 文件。');
    if (in_array($extension, ['docx', 'pptx'], true)) {
      $zip = new \ZipArchive();
      if ($zip->open($file['tmp_name']) !== true) throw new HttpError(422, 'Office 文件损坏。');
      $valid = $zip->locateName('[Content_Types].xml') !== false && $zip->locateName($extension === 'docx' ? 'word/document.xml' : 'ppt/presentation.xml') !== false;
      $zip->close();
      if (!$valid) throw new HttpError(422, '文件内容与 Office 扩展名不匹配。');
    }
    $size = filesize($file['tmp_name']);
    if (!$size || $size > self::MAX_BYTES) throw new HttpError(413, '文件大小需在 1 字节至 50 MiB 之间。');
    $relative = 'uploads/' . bin2hex(random_bytes(20)) . '.' . $extension;
    $folder = \PROJECT_ROOT . '/data/' . ($owner === 'team' ? 'team' : 'personal/' . $owner);
    if (!is_dir($folder . '/uploads') && !mkdir($folder . '/uploads', 0700, true) && !is_dir($folder . '/uploads')) throw new \RuntimeException('Cannot create coursework storage.');
    $path = $folder . '/' . $relative;
    if (!move_uploaded_file($file['tmp_name'], $path)) throw new \RuntimeException('Cannot store uploaded coursework.');
    chmod($path, 0600);
    $stored = ($owner === 'team' ? 'team/' : 'personal/' . $owner . '/') . $relative;
    try {
      $query = $this->db->prepare("INSERT INTO coursework_files (owner_slug, assignment_no, title, original_name, stored_name, mime_type, file_size, sha256, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
      $query->execute([$owner, $number, $title, $name, $stored, $mime, $size, hash_file('sha256', $path), $user['id']]);
    } catch (\Throwable $error) {
      unlink($path);
      throw $error;
    }
    return (int) $this->db->lastInsertId();
  }

  private function metadata(array $input, array $user): array {
    $owner = is_string($input['owner_slug'] ?? null) ? $input['owner_slug'] : '';
    if (!TeamProfile::canEdit($user, $owner)) throw new HttpError(403, '只能上传自己的个人作业，团队作业仅管理员可上传。');
    $labels = require \PROJECT_ROOT . '/config/coursework.php';
    $number = filter_var($input['assignment_no'] ?? null, FILTER_VALIDATE_INT);
    if ($number === false || !isset($labels[$owner === 'team' ? 'team' : 'personal'][$number])) throw new HttpError(422, '请选择有效的作业类别。');
    $title = is_string($input['title'] ?? null) ? trim($input['title']) : '';
    if ($title === '' || mb_strlen($title) > 120) throw new HttpError(422, '作业标题需为 1–120 字。');
    return [$owner, $number, $title];
  }

  public function createLink(array $input, array $user): int {
    [$owner, $number, $title] = $this->metadata($input, $user);
    $url = self::externalUrl($input['external_url'] ?? null);
    $query = $this->db->prepare("INSERT INTO coursework_files (owner_slug, assignment_no, title, original_name, source_type, external_url, uploaded_by) VALUES (?, ?, ?, '外部下载链接', 'link', ?, ?)");
    $query->execute([$owner, $number, $title, $url, $user['id']]);
    return (int) $this->db->lastInsertId();
  }

  public static function externalUrl(mixed $url): string {
    if (!is_string($url) || strlen($url) > 2048 || preg_match('/[\x00-\x20\x7f]/', $url) || !filter_var($url, FILTER_VALIDATE_URL)) throw new HttpError(422, '请输入有效的 HTTPS 下载链接。');
    $parts = parse_url($url);
    if (($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) throw new HttpError(422, '下载链接必须使用 HTTPS，且不能包含账号密码。');
    return $url;
  }

  public function publish(int $id, string $status, array $user): void {
    if (!in_array($status, ['draft', 'published'], true)) throw new HttpError(422, '发布状态不正确。');
    $row = $this->find($id);
    if (!$row) throw new HttpError(404, '作业不存在。');
    if (!TeamProfile::canEdit($user, $row['owner_slug'])) throw new HttpError(403, '不能发布或撤回其他成员的作业。');
    if ($status === 'published') {
      if ($row['source_type'] === 'link') self::externalUrl($row['external_url']);
      else $this->path($row);
    }
    $query = $this->db->prepare("UPDATE coursework_files SET status=?, published_at=CASE WHEN ?='published' THEN COALESCE(published_at, UTC_TIMESTAMP()) ELSE NULL END, updated_at=UTC_TIMESTAMP() WHERE id=?");
    $query->execute([$status, $status, $id]);
  }

  public function find(int $id): ?array {
    $query = $this->db->prepare('SELECT * FROM coursework_files WHERE id=?');
    $query->execute([$id]);
    return $query->fetch() ?: null;
  }

  public function downloadable(int $id, array $user): array {
    $row = $this->find($id);
    if (!$row || ($row['status'] !== 'published' && !TeamProfile::canEdit($user, $row['owner_slug']))) throw new HttpError(404, '作业未发布或不存在。');
    if ($row['source_type'] === 'link') $row['external_url'] = self::externalUrl($row['external_url']);
    else $row['path'] = $this->path($row);
    return $row;
  }

  private function path(array $row): string {
    $root = realpath(\PROJECT_ROOT . '/data');
    $path = realpath(\PROJECT_ROOT . '/data/' . $row['stored_name']);
    if (!$path || !$root || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path) || !is_readable($path) || filesize($path) !== (int) $row['file_size']) throw new HttpError(404, '文件暂不可用，请联系提交者。');
    return $path;
  }

  public function statistics(): array {
    return $this->db->query('SELECT owner_slug, status, COUNT(*) AS total FROM coursework_files GROUP BY owner_slug, status')->fetchAll();
  }
}
