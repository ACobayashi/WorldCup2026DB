<?php
/** FOURWARD coursework | Author: 严苑毓 | 学号: 2413682 | Public team profiles. */
declare(strict_types=1);
namespace Fourward\team\models;

use Fourward\common\HttpError;

final class TeamProfile {
  public function __construct(private readonly \PDO $db) {}

  public function all(): array {
    $defaults = require \PROJECT_ROOT . '/config/team-profile-defaults.php';
    $rows = $this->db->query('SELECT member_slug, description, school, role_title, task, tags_json, github FROM team_profiles');
    foreach ($rows as $row) {
      $slug = $row['member_slug'];
      if (!isset($defaults[$slug])) continue;
      $defaults[$slug] = $slug === 'team' ? ['description' => $row['description']] : [
        'description' => $row['description'], 'school' => $row['school'], 'role' => $row['role_title'],
        'task' => $row['task'], 'tags' => json_decode($row['tags_json'], true, 512, JSON_THROW_ON_ERROR),
        'github' => $row['github'],
      ];
    }
    return $defaults;
  }

  public function save(string $slug, array $input, array $user): void {
    $targets = require \PROJECT_ROOT . '/config/team.php';
    if (!isset($targets[$slug])) throw new HttpError(422, '无效的资料对象。');
    if ($user['role'] !== 'admin' && ($slug === 'team' || $user['member_slug'] !== $slug)) {
      throw new HttpError(403, '只能修改自己的资料。');
    }
    $team = $slug === 'team';
    $description = $this->field($input, 'description', $team ? 240 : 160, '简介', $team);
    $school = $team ? '' : $this->field($input, 'school', 64, '学院', true);
    $role = $team ? '' : $this->field($input, 'role', 72, '分工', true);
    $task = $team ? '' : $this->field($input, 'task', 140, '工作内容', true);
    $github = $team ? '' : $this->field($input, 'github', 200, 'GitHub 链接');
    if ($github !== '' && !preg_match('~\Ahttps://github\.com/[a-z0-9](?:[a-z0-9-]{0,37}[a-z0-9])?/?\z~i', $github)) {
      throw new HttpError(422, '请输入 https://github.com/用户名 格式的个人主页链接。');
    }
    $rawTags = $team ? '' : $this->field($input, 'tags', 84, '成员标签');
    $tags = array_values(array_unique(preg_split('/[、，,]\s*/u', $rawTags, -1, PREG_SPLIT_NO_EMPTY)));
    $tags = array_values(array_filter(array_map('trim', $tags), static fn(string $tag): bool => $tag !== ''));
    if (count($tags) > 4 || array_filter($tags, static fn(string $tag): bool => mb_strlen($tag) > 18)) {
      throw new HttpError(422, '最多填写 4 个标签，每个不超过 18 字。');
    }
    $query = $this->db->prepare('INSERT INTO team_profiles
      (member_slug, description, school, role_title, task, tags_json, github, updated_by)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?)
      ON DUPLICATE KEY UPDATE description=VALUES(description), school=VALUES(school),
        role_title=VALUES(role_title), task=VALUES(task), tags_json=VALUES(tags_json),
        github=VALUES(github), updated_by=VALUES(updated_by), updated_at=UTC_TIMESTAMP()');
    $query->execute([$slug, $description, $school, $role, $task, json_encode($tags, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $github, $user['id']]);
  }

  private function field(array $input, string $key, int $max, string $label, bool $required = false): string {
    $value = $input[$key] ?? '';
    if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || str_contains($value, "\0")) throw new HttpError(422, $label . '格式不正确。');
    $value = trim($value);
    if (($required && $value === '') || mb_strlen($value) > $max) {
      throw new HttpError(422, $label . ($required ? '不能为空，且' : '') . '最多 ' . $max . ' 字。');
    }
    return $value;
  }
}
