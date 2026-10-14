<?php
/** FOURWARD coursework | Author: 严苑毓 | Create an account without committing passwords. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require __DIR__ . '/../app/common/bootstrap.php';
[$script, $username, $member, $role] = array_pad($argv, 4, '');
$password = getenv('ACCOUNT_PASSWORD') ?: '';
$targets = require PROJECT_ROOT . '/config/team.php';
if (!preg_match('/^[a-zA-Z0-9_-]{3,48}$/', $username) || !isset($targets[$member]) || $member === 'team'
  || !in_array($role, ['member', 'admin'], true) || strlen($password) < 12 || strlen($password) > 72) {
  fwrite(STDERR, "Usage: set ACCOUNT_PASSWORD (12-72 bytes), then php scripts/create-user.php username member-slug member|admin\n");
  exit(1);
}
$query = database()->prepare('INSERT INTO users (username, member_slug, role, password_hash) VALUES (?, ?, ?, ?)');
$query->execute([$username, $member, $role, password_hash($password, PASSWORD_DEFAULT)]);
echo "Account created: $username ($role).\n";
