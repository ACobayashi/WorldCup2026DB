<?php
/** FOURWARD coursework | Author: 韩羽宸 | Match list/detail input boundary. */
declare(strict_types=1);
namespace Fourward\worldcup\controllers;
use Fourward\common\HttpError;
use Fourward\worldcup\models\MatchModel;

final class MatchController extends ReadController {
  public function index(array $input): array {
    $status = $this->text($input, 'status', 'all', 16);
    if (!in_array($status, ['all', 'scheduled', 'live', 'finished', 'postponed', 'cancelled'], true)) throw new HttpError(422, '比赛状态不正确。');
    $date = $this->text($input, 'date', '', 10);
    if ($date !== '') {
      $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
      if (!$parsed || $parsed->format('Y-m-d') !== $date) throw new HttpError(422, '日期格式应为 YYYY-MM-DD。');
    }
    $filters = ['status' => $status, 'date' => $date];
    foreach (['team_id', 'stage_id', 'group_id'] as $key) $filters[$key] = $this->integer($input, $key);
    return (new MatchModel($this->db))->listing($filters, ...$this->pagination($input));
  }

  public function show(array $input): array {
    $id = $this->integer($input, 'id');
    if ($id === null) throw new HttpError(422, '缺少比赛编号。');
    return (new MatchModel($this->db))->find($id) ?? throw new HttpError(404, '比赛不存在。');
  }
}
