<?php
/** FOURWARD coursework | Author: 韩羽宸 | 学号: 2410673 | World Cup JSON entry and stable error envelope. */
declare(strict_types=1);
require __DIR__ . '/../../app/common/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
try {
  $action = $_GET['action'] ?? 'overview';
  if (!is_string($action)) throw new Fourward\common\HttpError(422, '接口参数格式不正确。');
  $db = database();
  $db->exec("SET time_zone = '+00:00'");
  $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf'] ?? null;
  $data = (new Fourward\worldcup\controllers\ApiController($db))->dispatch($action, $_SERVER['REQUEST_METHOD'], $_GET, is_string($csrf) ? $csrf : null);
  $response = ['code' => 'OK', 'message' => '', 'data' => $data, 'errors' => null];
} catch (Fourward\common\HttpError $error) {
  http_response_code($error->status);
  $response = ['code' => 'HTTP_' . $error->status, 'message' => $error->getMessage(), 'data' => null, 'errors' => null];
} catch (Throwable $error) {
  error_log((string) $error);
  http_response_code(503);
  $response = ['code' => 'UNAVAILABLE', 'message' => '赛事服务暂时不可用，请稍后重试。', 'data' => null, 'errors' => null];
}
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
