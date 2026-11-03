<?php
/** FOURWARD coursework | Author: 韩羽宸 | World Cup database-mode page entry. */
declare(strict_types=1);
require __DIR__ . '/../../app/common/bootstrap.php';
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
try {
  $db = database();
  $db->exec("SET time_zone = '+00:00'");
  (new Fourward\worldcup\controllers\PageController($db))->handle($_GET);
} catch (Throwable $error) {
  error_log((string) $error);
  http_response_code(503);
  echo '<!doctype html><html lang="zh-CN"><meta charset="utf-8"><title>赛事服务暂时不可用</title><p>赛事服务暂时不可用，请稍后重试。</p><a href="../">返回团队主页</a></html>';
}
