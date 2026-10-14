<?php
/** FOURWARD coursework | Author: 严苑毓 | JSON entry point; internal errors stay private. */
declare(strict_types=1);
require __DIR__ . '/../app/common/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
try {
  $action = $_GET['action'] ?? '';
  if (!is_string($action)) throw new Fourward\common\HttpError(422, '无效的接口参数。');
  $data = (new Fourward\team\controllers\GuestbookController(database()))->dispatch($action);
  echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (Fourward\common\HttpError $error) {
  http_response_code($error->status);
  echo json_encode(['ok' => false, 'error' => ['message' => $error->getMessage()]], JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
  error_log((string) $error);
  http_response_code(503);
  echo json_encode(['ok' => false, 'error' => ['message' => '留言服务暂时不可用，请稍后重试。']], JSON_UNESCAPED_UNICODE);
}
