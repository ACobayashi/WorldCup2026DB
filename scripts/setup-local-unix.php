<?php
/** 作者：林子媛 | 学号：2410936 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require __DIR__ . '/../app/common/bootstrap.php';

$socket = $argv[1] ?? '';
if (!preg_match('~^/[A-Za-z0-9_./-]+$~D', $socket) || !file_exists($socket)) {
  fwrite(STDERR, "Usage: php scripts/setup-local-unix.php /absolute/path/to/local-mysql.sock\n");
  exit(1);
}
if (is_file(PROJECT_ROOT . '/.env')) {
  fwrite(STDERR, "An existing .env is preserved. Use its connection and migrate-team-workspace.php instead.\n");
  exit(1);
}
$oldMask = umask(0077);
try {
  $adminUser = getenv('LOCAL_DB_ADMIN_USER') ?: (getenv('USER') ?: '');
  $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false];
  $admin = new PDO('mysql:unix_socket=' . $socket . ';charset=utf8mb4', $adminUser, getenv('LOCAL_DB_ADMIN_PASSWORD') ?: '', $options);
  $port = (int) $admin->query('SELECT @@port')->fetchColumn();
  if ($port < 1 || $port > 65535) throw new RuntimeException('The local server must have a valid TCP port.');
  $databaseUser = 'worldcup_coursework';
  $query = $admin->prepare("SELECT COUNT(*) FROM mysql.user WHERE User=? AND Host IN ('127.0.0.1','localhost')");
  $query->execute([$databaseUser]);
  if ((int) $query->fetchColumn() !== 0) throw new RuntimeException('The dedicated account already exists; its password will not be reset.');
  $databasePassword = bin2hex(random_bytes(24));
  $appKey = bin2hex(random_bytes(32));
  $admin->exec('CREATE DATABASE IF NOT EXISTS worldcup2026 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
  foreach (['127.0.0.1', 'localhost'] as $host) {
    $account = $admin->quote($databaseUser) . '@' . $admin->quote($host);
    $admin->exec('CREATE USER ' . $account . ' IDENTIFIED BY ' . $admin->quote($databasePassword));
    $admin->exec('GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, REFERENCES ON worldcup2026.* TO ' . $account);
  }
  $dsn = 'mysql:host=127.0.0.1;port=' . $port . ';dbname=worldcup2026;charset=utf8mb4';
  $db = new PDO($dsn, $databaseUser, $databasePassword, $options);
  Fourward\common\Schema::install($db);

  $accountFile = PROJECT_ROOT . '/storage/local-accounts.txt';
  if (!is_file($accountFile)) {
    $accounts = ['yan-yuanyu' => 'admin', 'lin-ziyuan' => 'member', 'ma-yuxiang' => 'member', 'han-yuchen' => 'member'];
    $text = "Local development only. Do not commit or publish these random passwords.\n\n";
    foreach ($accounts as $username => $role) {
      $query = $db->prepare('SELECT id FROM users WHERE username=?');
      $query->execute([$username]);
      if ($query->fetch()) {
        $text .= "$username ($role): existing account, password unchanged\n";
        continue;
      }
      $password = bin2hex(random_bytes(10));
      $db->prepare('INSERT INTO users (username, member_slug, role, password_hash) VALUES (?, ?, ?, ?)')->execute([$username, $username, $role, password_hash($password, PASSWORD_DEFAULT)]);
      $text .= "$username ($role): $password\n";
    }
    if (file_put_contents($accountFile, $text, LOCK_EX) === false) throw new RuntimeException('Cannot save local account credentials.');
    chmod($accountFile, 0600);
  }
  $env = fopen(PROJECT_ROOT . '/.env', 'x');
  if (!$env) throw new RuntimeException('Cannot create local configuration without overwriting.');
  fwrite($env, "DB_DSN=$dsn\nDB_USER=$databaseUser\nDB_PASSWORD=$databasePassword\nAPP_KEY=$appKey\n");
  fclose($env);
  chmod(PROJECT_ROOT . '/.env', 0600);
  echo "Local project schema ready on 127.0.0.1:$port; existing data preserved.\n";
  echo "Database credentials: .env (private, Git ignored).\nTeam account credentials: storage/local-accounts.txt (private, Git ignored).\n";
} catch (Throwable $error) {
  // PDO traces can include SQL arguments, so never print credential-bearing traces.
  fwrite(STDERR, "Local initialization failed (" . get_class($error) . ", code " . $error->getCode() . "). No passwords printed.\n");
  exit(1);
} finally {
  umask($oldMask);
}
