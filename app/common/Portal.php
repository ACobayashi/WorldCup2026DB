<?php
/** 作者：林子媛 | 学号：2410936 */
declare(strict_types=1);
namespace Fourward\common;

final class Portal {
  public static function text(array $input, string $key, string $default = ''): string {
    if (!isset($input[$key])) return $default;
    if (!is_string($input[$key])) throw new HttpError(422, '请求字段格式不正确。');
    return $input[$key];
  }

  public static function returnPath(mixed $path): string {
    if (!is_string($path)) return 'coursework.php';
    return preg_match('~^(?:account\.php|admin\.php|coursework\.php|downloads\.php(?:\?member=(?:lin-ziyuan|yan-yuanyu|ma-yuxiang|han-yuchen)&assignment=[123])?|workspace\.php(?:\?section=(?:profile|files|stats))?|index\.php|download\.php\?id=[1-9][0-9]{0,17}|\./)$~D', $path) ? $path : 'coursework.php';
  }

  public static function editor(array $user): void {
    $targets = require \PROJECT_ROOT . '/config/team.php';
    if ($user['role'] !== 'admin' && !($user['role'] === 'member' && $user['member_slug'] !== 'team' && isset($targets[$user['member_slug']]))) throw new HttpError(403, '此工作台仅向团队成员开放，普通用户可以前往作业下载。');
  }

  public static function render(string $view, array $variables): void {
    extract($variables, EXTR_SKIP);
    $user ??= null;
    $title ??= '团队服务';
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    require \PROJECT_ROOT . '/app/team/views/portal/layout.php';
  }

  public static function run(callable $handler): void {
    try { $handler(); }
    catch (HttpError $error) {
      http_response_code($error->status);
      self::render('error', ['title' => '暂时无法完成', 'errorMessage' => $error->getMessage()]);
    } catch (\Throwable $error) {
      error_log((string) $error);
      http_response_code(503);
      self::render('error', ['title' => '服务暂不可用', 'errorMessage' => '服务暂时不可用，请稍后重试。']);
    }
  }
}
