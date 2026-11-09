<?php
/** FOURWARD coursework | Author: 韩羽宸 | 学号: 2410673 | World Cup query validation and pagination contract. */
declare(strict_types=1);
namespace Fourward\worldcup\controllers;
use Fourward\common\HttpError;

abstract class ReadController {
  public function __construct(protected readonly \PDO $db) {}

  protected function text(array $input, string $key, string $default = '', int $maxLength = 80): string {
    $value = $input[$key] ?? $default;
    if (!is_string($value) || mb_strlen($value) > $maxLength) throw new HttpError(422, '参数 ' . $key . ' 格式不正确。');
    return trim($value);
  }

  protected function integer(array $input, string $key, ?int $default = null, int $max = PHP_INT_MAX): ?int {
    if (!isset($input[$key]) || $input[$key] === '') return $default;
    $value = filter_var($input[$key], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => $max]]);
    if ($value === false) throw new HttpError(422, '参数 ' . $key . ' 必须是有效的正整数。');
    return $value;
  }

  protected function pagination(array $input): array {
    return [$this->integer($input, 'page', 1, 100000), $this->integer($input, 'page_size', 20, 100)];
  }
}
