<?php
/** FOURWARD coursework | Author: 韩羽宸 | 学号: 2410673 | Shared-login support boundary; writes intentionally unimplemented. */
declare(strict_types=1);
namespace Fourward\worldcup\controllers;
use Fourward\common\Auth;
use Fourward\common\HttpError;
use Fourward\worldcup\models\Support;

final class SupportController extends ReadController {
  public function index(array $input): array {
    $user = (new Auth($this->db))->requireUser();
    return (new Support($this->db))->listing((int) $user['id'], ...$this->pagination($input));
  }

  public function write(?string $csrf): never {
    (new Auth($this->db))->requireUser();
    \checkCsrf($csrf);
    throw new HttpError(501, '支持写入功能尚未开放。');
  }
}
