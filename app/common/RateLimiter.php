<?php
/** FOURWARD coursework | Author: 严苑毓 | Atomic database quotas shared across browser sessions. */
declare(strict_types=1);
namespace Fourward\common;

final class RateLimiter {
  public function __construct(private readonly \PDO $db) {}

  public function consume(string $scope, int $limit, int $seconds): void {
    $key = getenv('APP_KEY');
    if (!$key || strlen($key) < 32) throw new \RuntimeException('Configure an APP_KEY of at least 32 characters.');
    $window = intdiv(time(), $seconds);
    // Never trust a visitor-supplied proxy header or persist raw IP addresses.
    $bucket = hash_hmac('sha256', $scope . ':' . ($_SERVER['REMOTE_ADDR'] ?? 'cli') . ':' . $window, $key);
    $query = $this->db->prepare('INSERT INTO app_rate_limits (bucket, hits, expires_at) VALUES (?, 1, ?) ON DUPLICATE KEY UPDATE hits = hits + 1');
    $query->execute([$bucket, ($window + 1) * $seconds]);
    $query = $this->db->prepare('SELECT hits FROM app_rate_limits WHERE bucket = ?');
    $query->execute([$bucket]);
    $hits = (int) $query->fetchColumn();
    $query = $this->db->prepare('DELETE FROM app_rate_limits WHERE expires_at < ?');
    $query->execute([time()]);
    if ($hits > $limit) throw new HttpError(429, '操作太频繁，请稍后再试。');
  }
}
