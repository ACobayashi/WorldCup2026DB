<?php
/** 作者：林子媛 | 学号：2410936 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require __DIR__ . '/../app/common/bootstrap.php';

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8088', '/');
if (!preg_match('~^http://127\.0\.0\.1:[0-9]+$~D', $base)) throw new RuntimeException('Use a local development server on 127.0.0.1.');
$checks = 0;
function verify(bool $condition, string $label): void {
  global $checks;
  if (!$condition) throw new RuntimeException('FAILED: ' . $label);
  $checks++;
}

final class Browser {
  private array $cookies = [];
  public function __construct(private readonly string $base) {}
  public function request(string $path, ?array $fields = null, ?array $file = null): array {
    $headers = ['Connection: close'];
    if ($this->cookies) $headers[] = 'Cookie: ' . implode('; ', $this->cookies);
    $content = '';
    if ($fields !== null) {
      if ($file !== null) {
        $boundary = 'fourward-' . bin2hex(random_bytes(12));
        $headers[] = 'Content-Type: multipart/form-data; boundary=' . $boundary;
        foreach ($fields as $name => $value) $content .= "--$boundary\r\nContent-Disposition: form-data; name=\"$name\"\r\n\r\n$value\r\n";
        $content .= "--$boundary\r\nContent-Disposition: form-data; name=\"coursework\"; filename=\"" . $file['name'] . "\"\r\nContent-Type: application/octet-stream\r\n\r\n" . $file['bytes'] . "\r\n--$boundary--\r\n";
      } else {
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        $content = http_build_query($fields);
      }
    }
    $context = stream_context_create(['http' => ['method' => $fields === null ? 'GET' : 'POST', 'header' => implode("\r\n", $headers), 'content' => $content, 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 30]]);
    $bytes = file_get_contents($this->base . '/' . $path, false, $context);
    if ($bytes === false) throw new RuntimeException('Local HTTP request failed.');
    $responseHeaders = $http_response_header ?? [];
    preg_match('~HTTP/\S+ ([0-9]+)~', $responseHeaders[0] ?? '', $status);
    $map = [];
    foreach ($responseHeaders as $header) {
      if (!str_contains($header, ':')) continue;
      [$key, $value] = explode(':', $header, 2);
      $map[strtolower($key)] = trim($value);
      if (strtolower($key) === 'set-cookie') {
        $cookie = explode(';', trim($value))[0];
        $this->cookies[explode('=', $cookie, 2)[0]] = $cookie;
      }
    }
    return ['status' => (int) ($status[1] ?? 0), 'headers' => $map, 'body' => $bytes];
  }
  public function csrf(string $page = 'account.php'): string {
    $response = $this->request($page);
    if (!preg_match('/name="csrf" value="([a-f0-9]{64})"/', $response['body'], $matches)) throw new RuntimeException('No CSRF token on ' . $page . '; check database migration.');
    return $matches[1];
  }
  public function login(string $username, string $password): void {
    $response = $this->request('account.php', ['action' => 'login', 'csrf' => $this->csrf(), 'username' => $username, 'password' => $password, 'return' => 'workspace.php']);
    verify($response['status'] === 303, 'team account login');
  }
}

$db = database();
$suffix = bin2hex(random_bytes(6));
$password = bin2hex(random_bytes(14));
$memberName = 'http-member-' . $suffix;
$adminName = 'http-admin-' . $suffix;
$userName = 'http-user-' . $suffix;
$userIds = [];
$storedFiles = [];
$quotaBuckets = [];
// Remove only the local quota buckets touched by this run, restoring prior values.
$key = getenv('APP_KEY');
if (!$key) throw new RuntimeException('Configure APP_KEY before HTTP acceptance.');
foreach (['login' => 900, 'registration' => 900, 'coursework-upload' => 60] as $scope => $seconds) {
  $window = intdiv(time(), $seconds);
  for ($offset = 0; $offset <= 2; $offset++) {
    $bucket = hash_hmac('sha256', $scope . ':127.0.0.1:' . ($window + $offset), $key);
    $query = $db->prepare('SELECT * FROM app_rate_limits WHERE bucket=?');
    $query->execute([$bucket]);
    $quotaBuckets[$bucket] = $query->fetch() ?: null;
  }
}
try {
  $insert = $db->prepare('INSERT INTO users (username, password_hash, role, member_slug) VALUES (?, ?, ?, ?)');
  foreach ([[$memberName, 'member', 'lin-ziyuan'], [$adminName, 'admin', 'yan-yuanyu']] as [$username, $role, $slug]) {
    $insert->execute([$username, password_hash($password, PASSWORD_DEFAULT), $role, $slug]);
    $userIds[] = (int) $db->lastInsertId();
  }
  $anonymous = new Browser($base);
  $homepage = $anonymous->request('./');
  verify($homepage['status'] === 200 && str_contains($homepage['body'], 'id="team-profile-data"') && str_contains($homepage['body'], 'href="coursework.php"'), 'merged homepage uses live profiles and unified coursework catalog');
  verify(substr_count($homepage['body'], 'class="team-materials-link') === 1 && !str_contains($homepage['body'], '四个名字，一次共同创作'), 'homepage has one coursework entry and keeps the requested text removal');
  $response = $anonymous->request('account.php');
  verify($response['status'] === 200 && str_contains($response['body'], '注册普通用户'), 'visible login and registration');
  verify($anonymous->request('workspace.php')['status'] === 302, 'anonymous workspace requires login');
  verify($anonymous->request('admin.php')['status'] === 302, 'anonymous moderation workspace requires login');
  $legacy = $anonymous->request('downloads.php?member=lin-ziyuan&assignment=1');
  verify($legacy['status'] === 303 && str_starts_with($legacy['headers']['location'] ?? '', 'account.php?return=downloads.php'), 'legacy archives use unified login');
  $legacy = $anonymous->request('downloads.php');
  verify($legacy['status'] === 200 && substr_count($legacy['body'], '<nav class="main-nav"') === 1, 'legacy archive retains shared four-column navigation');
  $response = $anonymous->request('download.php?id=1');
  verify($response['status'] === 302 && ($response['headers']['location'] ?? '') === 'account.php?return=download.php%3Fid%3D1', 'download login preserves return path');
  verify($anonymous->request('account.php', ['action' => 'register', 'username' => $userName, 'password' => $password, 'password_confirmation' => $password, 'csrf' => 'wrong'])['status'] === 403, 'registration CSRF');
  $response = $anonymous->request('account.php?mode=register', ['action' => 'register', 'username' => $userName, 'password' => $password, 'password_confirmation' => $password, 'csrf' => $anonymous->csrf('account.php?mode=register'), 'return' => 'https://example.test', 'role' => 'admin', 'member_slug' => 'lin-ziyuan']);
  verify($response['status'] === 303 && $response['headers']['location'] === 'coursework.php', 'registration rejects open redirect');
  $query = $db->prepare('SELECT id, role, member_slug FROM users WHERE username=?');
  $query->execute([$userName]);
  $registered = $query->fetch();
  verify($registered && $registered['role'] === 'user' && $registered['member_slug'] === null, 'forged role fields ignored');
  $userIds[] = (int) $registered['id'];
  verify($anonymous->request('workspace.php')['status'] === 403, 'ordinary user cannot edit');
  $center = $anonymous->request('account.php');
  verify(str_contains($center['body'], '个人中心') && !str_contains($center['body'], 'href="workspace.php"'), 'ordinary personal center has no editor entry');
  verify(substr_count($center['body'], '<nav class="main-nav"') === 1 && str_contains($center['body'], '个人介绍'), 'shared four-column navigation');
  verify($anonymous->request('admin.php')['headers']['location'] === 'coursework.php', 'ordinary user leaves old team workspace');

  $member = new Browser($base);
  $member->login($memberName, $password);
  $token = $member->csrf('workspace.php?section=files');
  $fields = ['action' => 'upload', 'csrf' => $token, 'owner_slug' => 'lin-ziyuan', 'assignment_no' => '1', 'title' => 'Acceptance ' . $suffix];
  $pdf = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n";
  $response = $member->request('workspace.php?section=files', $fields, ['name' => 'acceptance.pdf', 'bytes' => $pdf]);
  verify($response['status'] === 303, 'real multipart upload creates draft');
  $query = $db->prepare('SELECT * FROM coursework_files WHERE uploaded_by=? ORDER BY id DESC');
  $query->execute([$userIds[0]]);
  $file = $query->fetch();
  verify($file && $file['status'] === 'draft' && $file['sha256'] === hash('sha256', $pdf), 'upload metadata and hash');
  $storedFiles[] = PROJECT_ROOT . '/data/' . $file['stored_name'];
  $id = (int) $file['id'];
  verify(!str_contains($anonymous->request('coursework.php')['body'], $fields['title']), 'draft stays off public directory');
  verify($anonymous->request('download.php?id=' . $id)['status'] === 404, 'ordinary user cannot fetch draft');
  verify($member->request('download.php?id=' . $id)['body'] === $pdf, 'owner draft bytes round-trip');
  $otherFields = array_replace($fields, ['owner_slug' => 'yan-yuanyu']);
  verify($member->request('workspace.php?section=files', $otherFields, ['name' => 'acceptance.pdf', 'bytes' => $pdf])['status'] === 403, 'upload ownership enforced');
  verify($member->request('workspace.php?section=files', $fields, ['name' => 'fake.pdf', 'bytes' => '<?php echo "not a PDF";'])['status'] === 422, 'extension cannot disguise PHP');
  verify($member->request('workspace.php?section=files', array_replace($fields, ['assignment_no' => '4']), ['name' => 'acceptance.pdf', 'bytes' => $pdf])['status'] === 422, 'personal assignment range');
  verify($member->request('workspace.php', ['action' => 'profile', 'csrf' => $token, 'member_slug' => 'yan-yuanyu'])['status'] === 403, 'profile ownership enforced over HTTP');
  $publish = ['action' => 'publish', 'csrf' => $token, 'id' => $id, 'status' => 'published'];
  verify($member->request('workspace.php?section=files', array_replace($publish, ['csrf' => 'wrong']))['status'] === 403, 'publish CSRF');
  verify($member->request('workspace.php?section=files', $publish)['status'] === 303, 'publish draft');
  verify(str_contains($anonymous->request('coursework.php')['body'], $fields['title']), 'published file visible');
  $download = $anonymous->request('download.php?id=' . $id);
  verify($download['status'] === 200 && $download['body'] === $pdf, 'logged-in user downloads exact bytes');
  verify(str_starts_with($download['headers']['content-disposition'] ?? '', 'attachment;') && str_contains($download['headers']['cache-control'] ?? '', 'no-store'), 'download attachment and no-cache headers');
  $newVisitor = new Browser($base);
  $response = $newVisitor->request('download.php?id=' . $id);
  verify($response['status'] === 302, 'published file still requires login');
  $response = $newVisitor->request('account.php', ['action' => 'login', 'csrf' => $newVisitor->csrf(), 'username' => $userName, 'password' => $password, 'return' => 'download.php?id=' . $id]);
  verify($response['status'] === 303 && $response['headers']['location'] === 'download.php?id=' . $id, 'login returns to requested file');
  $stats = $member->request('workspace.php?section=stats&format=json');
  $data = json_decode($stats['body'], true, 512, JSON_THROW_ON_ERROR);
  verify($stats['status'] === 200 && $data['roles'] === null && count(array_unique(array_column($data['files'], 'owner_slug'))) === 1, 'member statistics are scoped');
  verify($member->request('workspace.php?section=stats')['status'] === 200, 'ECharts view renders');
  $admin = new Browser($base);
  $admin->login($adminName, $password);
  verify(str_contains($admin->request('account.php')['body'], 'admin.php?status=pending#review-heading'), 'admin personal center exposes moderation entry');
  verify(str_contains($admin->request('admin.php?status=pending')['body'], 'id="review-heading"'), 'moderation workspace renders for administrators');
  $response = $admin->request('workspace.php?section=stats&format=json');
  verify(json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR)['roles'] !== null, 'administrator sees role statistics');
  verify($member->request('workspace.php?section=files', array_replace($publish, ['status' => 'draft']))['status'] === 303, 'withdraw publication');
  verify($anonymous->request('download.php?id=' . $id)['status'] === 404, 'withdrawn file becomes private');
  $linkTitle = 'External acceptance ' . $suffix;
  $externalUrl = 'https://example.test/coursework/acceptance.zip';
  $linkFields = array_replace($fields, ['source_type' => 'link', 'assignment_no' => '2', 'title' => $linkTitle, 'external_url' => $externalUrl]);
  verify($member->request('workspace.php?section=files', $linkFields)['status'] === 303, 'link saved as draft');
  $query = $db->prepare('SELECT id FROM coursework_files WHERE uploaded_by=? AND title=?');
  $query->execute([$userIds[0], $linkTitle]);
  $linkId = (int) $query->fetchColumn();
  verify($linkId > 0 && $anonymous->request('download.php?id=' . $linkId)['status'] === 404, 'draft link stays private');
  verify($member->request('workspace.php?section=files', array_replace($publish, ['id' => $linkId]))['status'] === 303, 'publish external link');
  $linkDownload = $anonymous->request('download.php?id=' . $linkId);
  verify($linkDownload['status'] === 302 && $linkDownload['headers']['location'] === $externalUrl, 'authenticated external download redirect');
  $linkVisitor = new Browser($base);
  $beforeLogin = $linkVisitor->request('download.php?id=' . $linkId);
  verify($beforeLogin['status'] === 302 && str_starts_with($beforeLogin['headers']['location'], 'account.php?return='), 'anonymous cannot resolve external download URL');
  $home = $linkVisitor->request('index.php');
  verify(str_contains($home['body'], 'member-coursework-data') && !str_contains($home['body'], $externalUrl), 'member cards include published metadata but not raw external links');
  verify($member->request('workspace.php?section=files', array_replace($linkFields, ['external_url' => 'javascript:alert(1)']))['status'] === 422, 'invalid link rejected over HTTP');
  $response = $anonymous->request('account.php', ['action' => 'logout', 'csrf' => $anonymous->csrf()]);
  verify($response['status'] === 303 && $anonymous->request('download.php?id=' . $id)['status'] === 302, 'logout revokes download access');
} finally {
  // Find even a partially processed upload before deleting only this run's users.
  $find = $db->prepare('SELECT stored_name FROM coursework_files WHERE uploaded_by=?');
  foreach ($userIds as $userId) {
    $find->execute([$userId]);
    foreach ($find->fetchAll() as $row) if ($row['stored_name'] !== null) $storedFiles[] = PROJECT_ROOT . '/data/' . $row['stored_name'];
    $db->prepare('DELETE FROM coursework_files WHERE uploaded_by=?')->execute([$userId]);
    $db->prepare('DELETE FROM users WHERE id=?')->execute([$userId]);
  }
  // A registration may have succeeded before a later assertion failed.
  $db->prepare('DELETE FROM users WHERE username=?')->execute([$userName]);
  foreach (array_unique($storedFiles) as $path) if (is_file($path)) unlink($path);
  foreach ($quotaBuckets as $bucket => $prior) {
    if ($prior) $db->prepare('INSERT INTO app_rate_limits (bucket, hits, expires_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE hits=VALUES(hits), expires_at=VALUES(expires_at)')->execute([$bucket, $prior['hits'], $prior['expires_at']]);
    else $db->prepare('DELETE FROM app_rate_limits WHERE bucket=?')->execute([$bucket]);
  }
}
echo "Team HTTP acceptance passed: $checks checks. Test accounts, uploads and local quota changes removed.\n";
