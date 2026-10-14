<?php
/**
 * FOURWARD coursework — shared configuration, sessions and database connection.
 * Author: 严苑毓 | Module: team guestbook | PHP 8.2+, PDO MySQL, mbstring.
 */
declare(strict_types=1);

const PROJECT_ROOT = __DIR__ . '/../..';

// Real process variables override the optional, untracked local .env file.
if (is_file(PROJECT_ROOT . '/.env')) {
  foreach (file(PROJECT_ROOT . '/.env', FILE_IGNORE_NEW_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
    [$key, $value] = explode('=', $line, 2);
    if (preg_match('/^[A-Z][A-Z0-9_]*$/', $key) && getenv($key) === false) {
      putenv($key . '=' . trim($value, " \t\r\n\"'"));
    }
  }
}
date_default_timezone_set('UTC');
spl_autoload_register(function (string $class): void {
  $prefix = 'Fourward\\';
  if (!str_starts_with($class, $prefix)) return;
  $path = PROJECT_ROOT . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
  if (is_file($path)) require $path;
});

function database(): PDO {
  static $connection;
  if ($connection) return $connection;
  $dsn = getenv('DB_DSN');
  if (!$dsn || !str_starts_with($dsn, 'mysql:')) throw new RuntimeException('Configure a MySQL DB_DSN first.');
  return $connection = new PDO($dsn, getenv('DB_USER') ?: '', getenv('DB_PASSWORD') ?: '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);
}

function startSession(): void {
  if (session_status() === PHP_SESSION_ACTIVE) return;
  session_name('fourward_session');
  session_start([
    'use_strict_mode' => 1,
    'use_only_cookies' => 1,
    'cookie_httponly' => true,
    'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_samesite' => 'Lax',
    'cookie_path' => '/',
  ]);
  $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function csrfToken(): string {
  startSession();
  return $_SESSION['csrf'];
}

function checkCsrf(?string $token): void {
  if (!$token || !hash_equals(csrfToken(), $token)) {
    throw new Fourward\common\HttpError(403, '页面已过期，请刷新后重试。');
  }
}

function e(mixed $value): string {
  return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
