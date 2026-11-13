<?php
/** 作者：林子媛 | 学号：2410936 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require __DIR__ . '/../app/common/bootstrap.php';
use Fourward\common\{Auth, HttpError, Portal, RateLimiter};
use Fourward\team\models\{CourseworkFile, TeamProfile, UserAccount};

$checks = 0;
function verify(bool $condition, string $label): void {
  global $checks;
  if (!$condition) throw new RuntimeException('FAILED: ' . $label);
  $checks++;
}
function rejects(callable $operation, int $status): void {
  try { $operation(); } catch (HttpError $error) {
    verify($error->status === $status, 'HTTP ' . $status);
    return;
  }
  throw new RuntimeException('Operation unexpectedly succeeded.');
}
startSession();
$db = database();
$db->beginTransaction();
$path = null;
$directory = PROJECT_ROOT . '/data/personal/lin-ziyuan/uploads';
$directoryExisted = is_dir($directory);
try {
  $suffix = bin2hex(random_bytes(6));
  $password = bin2hex(random_bytes(12));
  $accounts = new UserAccount($db);
  $id = $accounts->register('check-user-' . $suffix, $password, $password);
  $query = $db->prepare('SELECT * FROM users WHERE id=?');
  $query->execute([$id]);
  $row = $query->fetch();
  $visitor = ['id' => $id, 'role' => 'user', 'member_slug' => null];
  verify($row['role'] === 'user' && $row['member_slug'] === null, 'registration never grants membership');
  verify($row['password_hash'] !== $password && password_verify($password, $row['password_hash']), 'password is hashed');
  rejects(fn() => $accounts->register('check-user-' . $suffix, $password, $password), 409);
  foreach (['a', 'invalid name', '<script>', str_repeat('a', 49)] as $name) rejects(fn() => $accounts->register($name, $password, $password), 422);
  foreach (['short', str_repeat('a', 73), "long-password\0"] as $invalid) rejects(fn() => $accounts->register('check-' . $suffix, $invalid, $invalid), 422);
  rejects(fn() => $accounts->register('check-' . $suffix, $password, 'different'), 422);
  $auth = new Auth($db);
  $beforeCsrf = csrfToken();
  $auth->login('check-user-' . $suffix, $password);
  verify($auth->user()['role'] === 'user', 'ordinary account can log in');
  verify(csrfToken() !== $beforeCsrf, 'login rotates CSRF token');
  rejects(fn() => $auth->requireAdmin(), 403);
  rejects(fn() => $auth->login('check-user-' . $suffix, 'wrong'), 401);
  rejects(fn() => $auth->login('missing-' . $suffix, $password), 401);
  rejects(fn() => Portal::editor($visitor), 403);
  rejects(fn() => checkCsrf('wrong'), 403);
  checkCsrf(csrfToken());
  $auth->logout();
  verify($auth->user() === null, 'logout clears identity');
  foreach (['https://example.test', '//example.test', '../data/install.sql', 'download.php?id=-1', "index.php\r\nX-Test: yes", ['invalid']] as $unsafe) verify(Portal::returnPath($unsafe) === 'coursework.php', 'redirect allowlist');
  foreach (['coursework.php', 'workspace.php', 'index.php', './', 'download.php?id=12', 'downloads.php', 'downloads.php?member=lin-ziyuan&assignment=1'] as $safe) verify(Portal::returnPath($safe) === $safe, 'valid return path');
  verify(Portal::returnPath('downloads.php?member=../team&assignment=1') === 'coursework.php', 'legacy download return rejects path traversal');
  rejects(fn() => Portal::text(['field' => []], 'field'), 422);

  $insertUser = $db->prepare('INSERT INTO users (username, password_hash, role, member_slug) VALUES (?, ?, ?, ?)');
  $insertUser->execute(['check-member-' . $suffix, password_hash($password, PASSWORD_DEFAULT), 'member', 'lin-ziyuan']);
  $member = ['id' => (int) $db->lastInsertId(), 'role' => 'member', 'member_slug' => 'lin-ziyuan'];
  $insertUser->execute(['check-admin-' . $suffix, password_hash($password, PASSWORD_DEFAULT), 'admin', 'yan-yuanyu']);
  $admin = ['id' => (int) $db->lastInsertId(), 'role' => 'admin', 'member_slug' => 'yan-yuanyu'];
  $profiles = new TeamProfile($db);
  $all = $profiles->all();
  verify(array_keys($all) === array_keys(require PROJECT_ROOT . '/config/team.php'), 'team and member ordering');
  verify(TeamProfile::canEdit($admin, 'team') && TeamProfile::canEdit($member, 'lin-ziyuan'), 'valid editors');
  verify(!TeamProfile::canEdit($member, 'team') && !TeamProfile::canEdit($member, 'yan-yuanyu') && !TeamProfile::canEdit($visitor, 'lin-ziyuan'), 'ownership restrictions');
  $input = array_intersect_key($all['lin-ziyuan'], array_flip(['display_name', 'school', 'role_title', 'biography', 'github_url', 'headline']));
  $input['biography'] = '<script>alert(1)</script> SQL \' --';
  $profiles->save('lin-ziyuan', $input, $member);
  verify($profiles->all()['lin-ziyuan']['biography'] === $input['biography'], 'profile stores literal text');
  verify(!str_contains(e($input['biography']), '<script>'), 'profile is escaped');
  rejects(fn() => $profiles->save('yan-yuanyu', $input, $member), 403);
  rejects(fn() => $profiles->save('lin-ziyuan', $input, $visitor), 403);
  rejects(fn() => $profiles->save('lin-ziyuan', array_replace($input, ['github_url' => 'javascript:alert(1)']), $member), 422);
  rejects(fn() => $profiles->save('lin-ziyuan', array_replace($input, ['biography' => str_repeat('字', 281)]), $member), 422);
  rejects(fn() => $profiles->save('lin-ziyuan', array_replace($input, ['display_name' => '']), $member), 422);
  rejects(fn() => $profiles->save('lin-ziyuan', [], $member), 422);
  $teamInput = array_intersect_key($all['team'], $input);
  rejects(fn() => $profiles->save('team', array_replace($teamInput, ['headline' => "one\ntwo\nthree"]), $admin), 422);
  $profiles->save('team', array_replace($teamInput, ['headline' => "测试\n团队"]), $admin);
  verify($profiles->all()['team']['headline'] === "测试\n团队", 'administrator edits team profile');
  $legacyInput = ['description' => '独立简介', 'school' => '测试学院', 'role' => '测试分工', 'task' => '旧表单工作内容', 'tags' => '甲、乙、甲', 'github' => 'https://github.com/course-test'];
  $profiles->save('lin-ziyuan', $legacyInput, $member);
  $legacySaved = $profiles->all()['lin-ziyuan'];
  verify($legacySaved['display_name'] === $input['display_name'] && $legacySaved['biography'] === $legacyInput['task'], 'legacy edits preserve names and synchronize work fields');
  verify($legacySaved['tags'] === ['甲', '乙'] && $legacySaved['github_url'] === $legacyInput['github'], 'legacy tags and links remain editable');
  $profiles->save('lin-ziyuan', $input, $member);
  $combined = $profiles->all()['lin-ziyuan'];
  verify($combined['description'] === '独立简介' && $combined['tags'] === ['甲', '乙'] && $combined['task'] === $input['biography'], 'workspace edits preserve separate introduction and tags');
  rejects(fn() => $profiles->save('lin-ziyuan', $legacyInput, $visitor), 403);

  $files = new CourseworkFile($db);
  $upload = ['owner_slug' => 'lin-ziyuan', 'assignment_no' => '1', 'title' => '测试作业'];
  rejects(fn() => $files->upload($upload, [], $visitor), 403);
  rejects(fn() => $files->upload(array_replace($upload, ['owner_slug' => 'team']), [], $member), 403);
  rejects(fn() => $files->upload(array_replace($upload, ['assignment_no' => '4']), [], $member), 422);
  rejects(fn() => $files->upload(array_replace($upload, ['title' => '']), [], $member), 422);
  rejects(fn() => $files->upload($upload, ['error' => UPLOAD_ERR_INI_SIZE], $member), 413);
  rejects(fn() => $files->upload($upload, [], $member), 422);
  rejects(fn() => $files->upload($upload, ['error' => UPLOAD_ERR_OK, 'tmp_name' => __FILE__], $member), 422);

  foreach (['javascript:alert(1)', 'http://example.test/file.zip', '//example.test/file.zip', 'https://user:password@example.test/file.zip', "https://example.test/\r\nX-Test: yes", ['url']] as $invalid) rejects(fn() => CourseworkFile::externalUrl($invalid), 422);
  $externalUrl = 'https://example.test/coursework/acceptance.zip';
  verify(CourseworkFile::externalUrl($externalUrl) === $externalUrl, 'valid HTTPS resource');
  rejects(fn() => $files->createLink($upload + ['external_url' => $externalUrl], $visitor), 403);
  $linkId = $files->createLink($upload + ['external_url' => $externalUrl], $member);
  $link = $files->find($linkId);
  verify($link['source_type'] === 'link' && $link['stored_name'] === null && $link['file_size'] === null && $link['sha256'] === null, 'external resources do not invent file metadata');
  rejects(fn() => $files->downloadable($linkId, $visitor), 404);
  $files->publish($linkId, 'published', $member);
  verify($files->downloadable($linkId, $visitor)['external_url'] === $externalUrl, 'published link available to authenticated users');
  verify(!array_key_exists('external_url', $files->catalog()['lin-ziyuan'][1]), 'public catalog never discloses raw external URL');
  $files->publish($linkId, 'draft', $member);
  rejects(fn() => $files->downloadable($linkId, $visitor), 404);

  if (!$directoryExisted && !mkdir($directory, 0700, true)) throw new RuntimeException('Cannot create fixture folder.');
  $path = $directory . '/check-' . $suffix . '.pdf';
  $contents = "%PDF-1.4\n% Acceptance fixture, never published outside this transaction.\n%%EOF\n";
  file_put_contents($path, $contents);
  $stored = 'personal/lin-ziyuan/uploads/' . basename($path);
  $insert = $db->prepare('INSERT INTO coursework_files (owner_slug, assignment_no, title, original_name, stored_name, mime_type, file_size, sha256, uploaded_by) VALUES (?, 1, ?, ?, ?, ?, ?, ?, ?)');
  $insert->execute(['lin-ziyuan', '<img src=x onerror=alert(1)>', '测试.pdf', $stored, 'application/pdf', strlen($contents), hash('sha256', $contents), $member['id']]);
  $fileId = (int) $db->lastInsertId();
  verify(!in_array($fileId, array_map('intval', array_column($files->listing(), 'id')), true), 'draft is not in public catalog');
  verify($files->downloadable($fileId, $member)['path'] === realpath($path), 'owner can check draft');
  rejects(fn() => $files->downloadable($fileId, $visitor), 404);
  rejects(fn() => $files->publish($fileId, 'published', $visitor), 403);
  rejects(fn() => $files->publish($fileId, 'deleted', $member), 422);
  rejects(fn() => $files->publish(PHP_INT_MAX, 'published', $admin), 404);
  $files->publish($fileId, 'published', $member);
  verify($files->find($fileId)['published_at'] !== null, 'publishing records timestamp');
  verify($files->downloadable($fileId, $visitor)['sha256'] === hash('sha256', $contents), 'ordinary users can download published files');
  $public = $files->listing();
  verify(in_array($fileId, array_map('intval', array_column($public, 'id')), true), 'publication appears in catalog');
  verify(!array_key_exists('stored_name', $public[0]), 'catalog does not expose storage path');
  $files->publish($fileId, 'draft', $admin);
  rejects(fn() => $files->downloadable($fileId, $visitor), 404);
  verify($files->find($fileId)['published_at'] === null, 'withdrawal clears public timestamp');
  $update = $db->prepare('UPDATE coursework_files SET stored_name=? WHERE id=?');
  $update->execute(['../README.md', $fileId]);
  rejects(fn() => $files->publish($fileId, 'published', $admin), 404);
  $update->execute([$stored, $fileId]);
  unlink($path);
  rejects(fn() => $files->publish($fileId, 'published', $member), 404);
  verify($files->statistics() !== [], 'file statistics derive from database');
  verify((int) $accounts->statistics()['roles']['user'] >= 1, 'account statistics derive from database');
  $limiter = new RateLimiter($db);
  $limiter->consume('workspace-check-' . $suffix, 1, 60);
  rejects(fn() => $limiter->consume('workspace-check-' . $suffix, 1, 60), 429);
} finally {
  if ($db->inTransaction()) $db->rollBack();
  if ($path && is_file($path)) unlink($path);
  if (!$directoryExisted && is_dir($directory) && count(scandir($directory)) === 2) rmdir($directory);
}
echo "Team workspace acceptance passed: $checks checks. Database fixtures rolled back; temporary file removed.\n";
