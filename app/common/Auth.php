<?php
/** FOURWARD coursework | Author: 严苑毓 | Shared account and role checks for future modules. */
declare(strict_types=1);
namespace Fourward\common;

final class Auth {
  public function __construct(private readonly \PDO $db) {}

  public function user(): ?array {
    \startSession();
    if (!isset($_SESSION['user_id'])) return null;
    $query = $this->db->prepare('SELECT id, username, role, member_slug FROM users WHERE id = ?');
    $query->execute([$_SESSION['user_id']]);
    return $query->fetch() ?: null;
  }

  public function requireUser(): array {
    return $this->user() ?? throw new HttpError(401, '请先登录团队账号。');
  }

  public function requireAdmin(): array {
    $user = $this->requireUser();
    if ($user['role'] !== 'admin') throw new HttpError(403, '只有管理员可以审核访客留言。');
    return $user;
  }

  public function login(string $username, string $password): void {
    $query = $this->db->prepare('SELECT id, password_hash FROM users WHERE username = ?');
    $query->execute([$username]);
    $user = $query->fetch();
    // A valid fixed dummy hash avoids skipping password verification for unknown users.
    $hash = $user['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi';
    if (!password_verify($password, $hash) || !$user) throw new HttpError(401, '账号或密码不正确。');
    \startSession();
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
  }

  public function logout(): void {
    \startSession();
    $_SESSION = [];
    session_regenerate_id(true);
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
  }
}
