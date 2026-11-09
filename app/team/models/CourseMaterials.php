<?php
/** FOURWARD coursework | Author: 严苑毓 | 学号: 2413682 | Private course archives. */
declare(strict_types=1);
namespace Fourward\team\models;

final class CourseMaterials {
  public static function path(string $member, string $assignment, array $user): ?string {
    if ($member === 'team' || !isset((require \PROJECT_ROOT . '/config/team.php')[$member])) return null;
    if (!in_array($assignment, ['1', '2', '3'], true)) return null;
    if ($user['role'] !== 'admin' && $user['member_slug'] !== $member) return null;
    $base = \PROJECT_ROOT . '/storage/course-downloads';
    $root = realpath($base);
    $directory = realpath($base . '/' . $member);
    if ($root === false || $directory !== $root . DIRECTORY_SEPARATOR . $member) return null;
    $expected = $directory . DIRECTORY_SEPARATOR . 'assignment-' . $assignment . '.zip';
    $file = realpath($expected);
    return $file === $expected && is_file($file) && is_readable($file) ? $file : null;
  }
}
