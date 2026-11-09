<?php
/** FOURWARD coursework | Author: 韩羽宸 | 学号: 2410673 | World Cup read-only administrator overview. */
declare(strict_types=1);
namespace Fourward\worldcup\controllers;
use Fourward\common\Auth;

final class AdminController extends ReadController {
  public function index(): array {
    (new Auth($this->db))->requireAdmin();
    return (new CatalogController($this->db))->overview();
  }
}
