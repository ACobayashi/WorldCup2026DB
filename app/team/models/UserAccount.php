<?php
/** 作者：林子媛 | 学号：2410936 */
declare(strict_types=1);
namespace Fourward\team\models;
use Fourward\common\HttpError;

final class UserAccount {
  public function __construct(private readonly \PDO $db) {}

  public function register(string $username, string $password, string $confirmation): int {
    $username = trim($username);
    if (!preg_match('/^[A-Za-z0-9_-]{3,48}$/D', $username)) throw new HttpError(422, '账号需为 3–48 位英文字母、数字、下划线或短横线。');
    if (strlen($password) < 12 || strlen($password) > 72 || str_contains($password, "\0")) throw new HttpError(422, '密码需为 12–72 字节，且不能包含空字符。');
    if ($password !== $confirmation) throw new HttpError(422, '两次输入的密码不一致。');
    try {
      $query = $this->db->prepare("INSERT INTO users (username, password_hash, role, member_slug) VALUES (?, ?, 'user', NULL)");
      $query->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
    } catch (\PDOException $error) {
      if (($error->errorInfo[1] ?? null) === 1062) throw new HttpError(409, '这个账号已被使用，请换一个。');
      throw $error;
    }
    return (int) $this->db->lastInsertId();
  }

  public function statistics(): array {
    $roles = $this->db->query('SELECT role, COUNT(*) AS total FROM users GROUP BY role')->fetchAll();
    return ['roles' => array_column($roles, 'total', 'role')];
  }
}
