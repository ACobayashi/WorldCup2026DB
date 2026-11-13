<?php
/** 作者：林子媛 | 学号：2410936 */
declare(strict_types=1);
namespace Fourward\common;

final class Schema {
  public static function install(\PDO $db): void {
    self::migrateTeamProfiles($db);
    $db->exec(file_get_contents(\PROJECT_ROOT . '/data/install.sql'));
    $column = $db->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch();
    if (!str_contains($column['Type'], "'user'") || $column['Default'] !== 'user') $db->exec(file_get_contents(\PROJECT_ROOT . '/data/migrations/004-team-workspace.sql'));
    if (!$db->query("SHOW COLUMNS FROM coursework_files LIKE 'source_type'")->fetch()) $db->exec(file_get_contents(\PROJECT_ROOT . '/data/migrations/005-coursework-links.sql'));
  }

  public static function migrateTeamProfiles(\PDO $db, string $table = 'team_profiles'): void {
    if (!preg_match('/\A[a-z][a-z0-9_]*\z/D', $table)) throw new \InvalidArgumentException('Invalid profile table name.');
    $exists = $db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $exists->execute([$table]);
    if (!$exists->fetchColumn()) return;
    $limits = ['display_name' => 40, 'description' => 280, 'school' => 80, 'role_title' => 80, 'biography' => 280, 'task' => 280, 'tags_json' => 400, 'github_url' => 255, 'github' => 255, 'headline' => 100];
    $columns = array_column($db->query("SHOW COLUMNS FROM `$table`")->fetchAll(), null, 'Field');
    foreach ($limits as $field => $length) {
      if (!isset($columns[$field])) $db->exec("ALTER TABLE `$table` ADD COLUMN `$field` VARCHAR($length) NULL DEFAULT NULL");
    }
    $targets = require \PROJECT_ROOT . '/config/team.php';
    $defaults = require \PROJECT_ROOT . '/config/team-profile-defaults.php';
    foreach ($db->query("SELECT * FROM `$table`")->fetchAll() as $row) {
      $slug = $row['member_slug'];
      $fallback = $defaults[$slug] ?? [];
      $biography = $row['biography'] ?? ($slug === 'team' ? $row['description'] : $row['task']) ?? ($fallback[$slug === 'team' ? 'description' : 'task'] ?? '');
      $github = $row['github_url'] ?? $row['github'] ?? ($fallback['github'] ?? '');
      $backfill = [
        'display_name' => $targets[$slug]['name'] ?? $slug,
        'description' => $slug === 'team' ? $biography : '',
        'school' => $fallback['school'] ?? '', 'role_title' => $fallback['role'] ?? '',
        'biography' => $biography, 'task' => $slug === 'team' ? '' : $biography,
        'tags_json' => json_encode($fallback['tags'] ?? [], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        'github_url' => $github, 'github' => $github,
        'headline' => $slug === 'team' ? "各有热爱\n一起上场" : '',
      ];
      foreach ($backfill as $field => $value) {
        // NULL marks an unfinished additive migration, so retries never replace edited values.
        if ($row[$field] !== null) continue;
        $update = $db->prepare("UPDATE `$table` SET `$field`=? WHERE member_slug=? AND `$field` IS NULL");
        $update->execute([$value, $slug]);
      }
    }
    foreach ($db->query("SHOW COLUMNS FROM `$table`")->fetchAll() as $column) {
      $field = $column['Field'];
      if (!isset($limits[$field])) continue;
      preg_match('/varchar\((\d+)\)/i', $column['Type'], $length);
      if (!$length) throw new \RuntimeException('Unexpected profile column type: ' . $field);
      $width = max($limits[$field], (int) $length[1]);
      $default = $field === 'tags_json' ? '[]' : '';
      if ($column['Null'] !== 'NO' || (int) $length[1] < $width || $column['Default'] !== $default) {
        $db->exec("ALTER TABLE `$table` MODIFY COLUMN `$field` VARCHAR($width) NOT NULL DEFAULT " . $db->quote($default));
      }
    }
  }
}
