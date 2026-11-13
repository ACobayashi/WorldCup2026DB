<?php
/** 作者：林子媛 | 学号：2410936 */
declare(strict_types=1);
namespace Fourward\common;

final class SiteNavigation {
  public static function html(?array $user, string $prefix = '', string $active = '', bool $home = false): string {
    ob_start();
    require __DIR__ . '/views/header.php';
    return ob_get_clean();
  }
}
