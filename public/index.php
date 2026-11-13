<?php
/** FOURWARD coursework | Author: 严苑毓 | 学号: 2413682 | Serve the shared homepage in database mode. */
/** 资料接入：林子媛 | 学号：2410936 */
declare(strict_types=1);
require __DIR__ . '/../app/common/bootstrap.php';
header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
try {
  $profiles = (new Fourward\team\models\TeamProfile(database()))->all();
  foreach (array_keys(require PROJECT_ROOT . '/config/team.php') as $slug) if (!isset($profiles[$slug])) throw new RuntimeException('Missing database profile.');
  $user = (new Fourward\common\Auth(database()))->user();
  $html = str_replace(['name="guestbook-mode" content="demo"', 'href="downloads.html"'], ['name="guestbook-mode" content="server"', 'href="coursework.php"'], file_get_contents(__DIR__ . '/index.html'));
  $html = str_replace('<body data-scene="team">', '<body class="server-home" data-scene="team">', $html);
  $html = preg_replace('~<header class="site-header app-header"[^>]*>.*?</header>~s', Fourward\common\SiteNavigation::html($user, '', 'team', true), $html, 1);
  $data = '<script type="application/json" id="team-profile-data">' . json_encode($profiles, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) . '</script>';
  $labels = require PROJECT_ROOT . '/config/coursework.php';
  $coursework = ['catalog' => (new Fourward\team\models\CourseworkFile(database()))->catalog(), 'labels' => $labels['personal'], 'authenticated' => $user !== null];
  $data .= '<script type="application/json" id="member-coursework-data">' . json_encode($coursework, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) . '</script>';
  echo str_replace('</body>', $data . '</body>', $html);
} catch (Throwable $error) {
  error_log((string) $error);
  http_response_code(503);
  echo '<!doctype html><html lang="zh-CN"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>团队服务暂不可用</title><p>团队资料暂时无法读取，请稍后重试。</p></html>';
}
