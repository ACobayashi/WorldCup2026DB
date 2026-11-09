<?php
/** FOURWARD coursework | Author: 韩羽宸 | 学号: 2410673 | Shared World Cup query pagination. */
declare(strict_types=1);
namespace Fourward\worldcup\models;

abstract class ReadModel {
  public function __construct(protected readonly \PDO $db) {}

  protected function rows(string $sql, array $params = []): array {
    $query = $this->db->prepare($sql);
    foreach ($params as $key => $value) {
      $query->bindValue(':' . $key, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
    }
    $query->execute();
    return $query->fetchAll();
  }

  protected function paginate(string $sql, string $countSql, array $params, int $page, int $pageSize): array {
    $total = (int) $this->rows($countSql, $params)[0]['total'];
    $items = $this->rows($sql . ' LIMIT :limit OFFSET :offset', $params + ['limit' => $pageSize, 'offset' => ($page - 1) * $pageSize]);
    return ['items' => $items, 'meta' => ['page' => $page, 'page_size' => $pageSize, 'total' => $total, 'total_pages' => (int) ceil($total / $pageSize)]];
  }
}
