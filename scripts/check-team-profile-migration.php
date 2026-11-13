<?php
/** 作者：林子媛 | 学号：2410936 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require __DIR__ . '/../app/common/bootstrap.php';

use Fourward\common\Schema;
use Fourward\team\models\TeamProfile;

$socket = $argv[1] ?? '';
if (!preg_match('~\A/[A-Za-z0-9_./-]+\z~D', $socket) || !file_exists($socket)) {
  fwrite(STDERR, "Usage: php scripts/check-team-profile-migration.php /absolute/path/to/local-mysql.sock\n");
  exit(1);
}
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false];
$db = new PDO('mysql:unix_socket=' . $socket . ';charset=utf8mb4', getenv('LOCAL_DB_ADMIN_USER') ?: (getenv('USER') ?: ''), getenv('LOCAL_DB_ADMIN_PASSWORD') ?: '', $options);
$name = 'worldcup_profile_check_' . bin2hex(random_bytes(8));
$created = false;
$checks = 0;
function profileCheck(bool $condition, string $label): void {
  global $checks;
  if (!$condition) throw new RuntimeException('FAILED: ' . $label);
  $checks++;
}
function profileSnapshot(PDO $db): array {
  return $db->query('SELECT * FROM team_profiles ORDER BY member_slug')->fetchAll();
}
try {
  $db->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
  $created = true;
  $db->exec("USE `$name`");
  $db->exec("CREATE TABLE team_profiles (member_slug VARCHAR(24) PRIMARY KEY, description VARCHAR(240) NOT NULL, school VARCHAR(64) NOT NULL DEFAULT '', role_title VARCHAR(72) NOT NULL DEFAULT '', task VARCHAR(140) NOT NULL DEFAULT '', tags_json VARCHAR(400) NOT NULL DEFAULT '[]', github VARCHAR(200) NOT NULL DEFAULT '', updated_by BIGINT UNSIGNED NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)");
  $insert = $db->prepare('INSERT INTO team_profiles (member_slug, description, school, role_title, task, tags_json, github) VALUES (?, ?, ?, ?, ?, ?, ?)');
  $insert->execute(['team', '保留原团队简介', '', '', '', '[]', '']);
  $insert->execute(['lin-ziyuan', '保留独立个人简介', '保留学院', '保留分工', '保留工作内容', '["已有标签"]', 'https://github.com/course-test']);
  $legacy = profileSnapshot($db);
  Schema::install($db);
  $model = new TeamProfile($db);
  $all = $model->all();
  profileCheck(count($all) === 5, 'legacy schema receives missing defaults');
  profileCheck($all['team']['biography'] === '保留原团队简介', 'legacy team description migrates');
  profileCheck($all['lin-ziyuan']['description'] === '保留独立个人简介', 'separate legacy biography survives');
  profileCheck($all['lin-ziyuan']['biography'] === '保留工作内容' && $all['lin-ziyuan']['github_url'] === 'https://github.com/course-test', 'legacy work and link migrate');
  profileCheck($all['lin-ziyuan']['tags'] === ['已有标签'] && $all['lin-ziyuan']['display_name'] === '林子媛', 'legacy tags survive with a new display name');
  $rows = array_column(profileSnapshot($db), null, 'member_slug');
  foreach ($legacy as $row) profileCheck(array_replace($rows[$row['member_slug']], $row) === $rows[$row['member_slug']], 'legacy persisted values remain unchanged');
  $before = profileSnapshot($db);
  Schema::install($db);
  profileCheck(profileSnapshot($db) === $before, 'legacy migration is idempotent');
  $db->exec('DROP TABLE team_profiles');
  $db->exec("CREATE TABLE team_profiles (member_slug VARCHAR(24) PRIMARY KEY, display_name VARCHAR(40) NOT NULL, school VARCHAR(80) NOT NULL DEFAULT '', role_title VARCHAR(80) NOT NULL DEFAULT '', biography VARCHAR(280) NOT NULL DEFAULT '', github_url VARCHAR(255) NOT NULL DEFAULT '', headline VARCHAR(100) NOT NULL DEFAULT '', updated_by BIGINT UNSIGNED NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)");
  $insert = $db->prepare('INSERT INTO team_profiles (member_slug, display_name, school, role_title, biography, github_url, headline) VALUES (?, ?, ?, ?, ?, ?, ?)');
  $insert->execute(['team', '保留队名', '', '', '保留团队内容', '', "保留\n标题"]);
  $insert->execute(['lin-ziyuan', '保留姓名', '已有学院', '已有分工', '已有工作内容', 'https://github.com/course-test', '']);
  $workspace = profileSnapshot($db);
  Schema::install($db);
  $all = $model->all();
  profileCheck($all['team']['display_name'] === '保留队名' && $all['team']['headline'] === "保留\n标题", 'workspace team name and headline survive');
  profileCheck($all['lin-ziyuan']['display_name'] === '保留姓名' && $all['lin-ziyuan']['biography'] === '已有工作内容', 'workspace member edits survive');
  profileCheck($all['lin-ziyuan']['tags'] === ['密码安全', '页面交互'], 'workspace receives default tags');
  $rows = array_column(profileSnapshot($db), null, 'member_slug');
  foreach ($workspace as $row) profileCheck(array_replace($rows[$row['member_slug']], $row) === $rows[$row['member_slug']], 'workspace persisted values remain unchanged');
  $db->exec('ALTER TABLE team_profiles MODIFY COLUMN task VARCHAR(280) NULL DEFAULT NULL');
  $db->exec("UPDATE team_profiles SET task=NULL WHERE member_slug='lin-ziyuan'");
  Schema::install($db);
  profileCheck($db->query("SELECT task FROM team_profiles WHERE member_slug='lin-ziyuan'")->fetchColumn() === '已有工作内容', 'interrupted nullable backfill resumes');
  $before = profileSnapshot($db);
  Schema::install($db);
  profileCheck(profileSnapshot($db) === $before, 'workspace migration is idempotent');
  $db->exec('DROP TABLE team_profiles');
  Schema::install($db);
  profileCheck(count($model->all()) === 5 && $model->all()['lin-ziyuan']['tags'] === ['密码安全', '页面交互'], 'fresh installation seeds both form contracts');
  profileCheck((int) $db->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()')->fetchColumn() === 20, 'all installation paths retain 20 tables');
} finally {
  if ($created) $db->exec("DROP DATABASE `$name`");
}
echo "Profile migration acceptance passed: $checks checks. Isolated database removed.\n";
