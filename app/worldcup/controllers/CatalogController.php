<?php
/** FOURWARD coursework | Author: 韩羽宸 | 学号: 2410673 | Venue, stage, group and overview read endpoints. */
declare(strict_types=1);
namespace Fourward\worldcup\controllers;
use Fourward\worldcup\models\Catalog;

final class CatalogController extends ReadController {
  public function index(string $resource, array $input): array {
    return (new Catalog($this->db))->listing($resource, ...$this->pagination($input));
  }

  public function overview(): array {
    return ['counts' => (new Catalog($this->db))->counts(), 'edition' => 2026, 'source' => 'database', 'capabilities' => ['read' => true, 'admin_write' => false, 'support_write' => false, 'import' => false, 'standings' => true]];
  }
}
