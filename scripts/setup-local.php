<?php
/** FOURWARD coursework | Author: 严苑毓 | Initialize only the isolated local database. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require __DIR__ . '/../app/common/bootstrap.php';
$dsn = getenv('DB_DSN');
if (!preg_match('/^mysql:host=127\.0\.0\.1;port=([0-9]+);dbname=worldcup2026;charset=utf8mb4$/', $dsn ?: '', $match)) {
  throw new RuntimeException('Local setup requires the worldcup2026 database on 127.0.0.1.');
}
$rootPassword = getenv('LOCAL_DB_ROOT_PASSWORD');
if (!$rootPassword) throw new RuntimeException('Missing isolated database credentials.');
$root = new PDO('mysql:host=127.0.0.1;port=' . $match[1] . ';charset=utf8mb4', 'root', $rootPassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$root->exec('CREATE DATABASE IF NOT EXISTS worldcup2026 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$root->exec("CREATE USER IF NOT EXISTS 'worldcup_app'@'127.0.0.1' IDENTIFIED BY " . $root->quote(getenv('DB_PASSWORD')));
$root->exec("GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, REFERENCES ON worldcup2026.* TO 'worldcup_app'@'127.0.0.1'");
database()->exec(file_get_contents(PROJECT_ROOT . '/data/install.sql'));
$accountFile = PROJECT_ROOT . '/storage/local-accounts.txt';
// Existing installations keep both their data and original account passwords.
if (!is_file($accountFile)) {
  $accounts = ['yan-yuanyu' => 'admin', 'lin-ziyuan' => 'member', 'ma-yuxiang' => 'member', 'han-yuchen' => 'member'];
  $text = "Local development only. Random credentials; do not commit or publish this file.\n\n";
  foreach ($accounts as $username => $role) {
    $query = database()->prepare('SELECT id FROM users WHERE username = ?');
    $query->execute([$username]);
    if ($query->fetch()) {
      $text .= "$username ($role): existing account, password unchanged\n";
      continue;
    }
    $password = bin2hex(random_bytes(10));
    $query = database()->prepare('INSERT INTO users (username, member_slug, role, password_hash) VALUES (?, ?, ?, ?)');
    $query->execute([$username, $username, $role, password_hash($password, PASSWORD_DEFAULT)]);
    $text .= "$username ($role): $password\n";
  }
  file_put_contents($accountFile, $text);
}
echo "Local schema and accounts ready. Existing content preserved.\n";
