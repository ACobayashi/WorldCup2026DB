<?php
/** 作者：林子媛 | 学号：2410936 */
/** FOURWARD coursework | Author: 严苑毓 | 学号: 2413682 | Public team profiles. */
declare(strict_types=1);
namespace Fourward\team\models;

use Fourward\common\HttpError;

final class TeamProfile {
  public function __construct(private readonly \PDO $db) {}

  public static function canEdit(array $user, string $slug): bool {
    $targets = require \PROJECT_ROOT . '/config/team.php';
    return isset($targets[$slug]) && ($user['role'] === 'admin' || ($user['role'] === 'member' && $slug !== 'team' && $user['member_slug'] === $slug));
  }

  public function all(): array {
    $targets = require \PROJECT_ROOT . '/config/team.php';
    $rows = [];
    foreach ($this->db->query('SELECT member_slug, display_name, description, school, role_title, biography, tags_json, github_url, headline, updated_at FROM team_profiles') as $row) {
      $slug = $row['member_slug'];
      if (!isset($targets[$slug])) continue;
      $row['description'] = $slug === 'team' ? $row['biography'] : $row['description'];
      $row['role'] = $row['role_title'];
      $row['task'] = $row['biography'];
      $row['github'] = $row['github_url'];
      $row['tags'] = json_decode($row['tags_json'], true, 512, JSON_THROW_ON_ERROR);
      unset($row['tags_json']);
      $rows[$slug] = $row;
    }
    return array_replace(array_intersect_key($targets, $rows), $rows);
  }

  public function save(string $slug, array $input, array $user): void {
    if (!self::canEdit($user, $slug)) throw new HttpError(403, '只能维护自己的档案，团队介绍仅管理员可维护。');
    $current = $this->all()[$slug] ?? null;
    if (!$current) throw new HttpError(404, '资料不存在，请先初始化项目。');
    $tags = $current['tags'];
    $description = $current['description'];
    if (array_key_exists('display_name', $input)) {
      $limits = ['display_name' => 40, 'school' => 80, 'role_title' => 80, 'biography' => 280, 'github_url' => 255, 'headline' => 100];
      $values = [];
      foreach ($limits as $key => $limit) {
        if (!array_key_exists($key, $input)) throw new HttpError(422, '资料字段格式不正确。');
        $values[$key] = $this->field($input, $key, $limit, '资料字段');
      }
      if ($values['display_name'] === '' || $values['biography'] === '') throw new HttpError(422, '名称和简介不能为空。');
      if ($slug === 'team' && ($values['headline'] === '' || substr_count($values['headline'], "\n") > 1)) throw new HttpError(422, '团队主标题需为一至两行。');
      $github = $values['github_url'];
    } else {
      $team = $slug === 'team';
      $description = $this->field($input, 'description', $team ? 240 : 160, '简介', $team);
      $school = $team ? '' : $this->field($input, 'school', 64, '学院', true);
      $role = $team ? '' : $this->field($input, 'role', 72, '分工', true);
      $task = $team ? '' : $this->field($input, 'task', 140, '工作内容', true);
      $github = $team ? '' : $this->field($input, 'github', 200, 'GitHub 链接');
      $rawTags = $team ? '' : $this->field($input, 'tags', 84, '成员标签');
      $tags = array_values(array_unique(preg_split('/[、，,]\s*/u', $rawTags, -1, PREG_SPLIT_NO_EMPTY)));
      $tags = array_values(array_filter(array_map('trim', $tags), static fn(string $tag): bool => $tag !== ''));
      if (count($tags) > 4 || array_filter($tags, static fn(string $tag): bool => mb_strlen($tag) > 18)) throw new HttpError(422, '最多填写 4 个标签，每个不超过 18 字。');
      $values = ['display_name' => $current['display_name'], 'school' => $school, 'role_title' => $role, 'biography' => $team ? $description : $task, 'github_url' => $github, 'headline' => $current['headline']];
    }
    if ($github !== '' && !preg_match('~\Ahttps://github\.com/[a-z0-9](?:[a-z0-9-]{0,37}[a-z0-9])?/?\z~i', $github)) {
      throw new HttpError(422, '请输入 https://github.com/用户名 格式的个人主页链接。');
    }
    $values += ['description' => $slug === 'team' ? $values['biography'] : $description, 'tags_json' => json_encode($tags, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)];
    $query = $this->db->prepare('UPDATE team_profiles SET display_name=:display_name, description=:description, school=:school, role_title=:role_title, biography=:biography, task=:task, tags_json=:tags_json, github_url=:github_url, github=:github, headline=:headline, updated_by=:editor, updated_at=UTC_TIMESTAMP() WHERE member_slug=:slug');
    $query->execute($values + ['task' => $slug === 'team' ? '' : $values['biography'], 'github' => $values['github_url'], 'editor' => $user['id'], 'slug' => $slug]);
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
