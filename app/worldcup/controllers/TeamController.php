<?php
/** FOURWARD coursework | Author: 韩羽宸 | Team and squad read endpoints. */
declare(strict_types=1);
namespace Fourward\worldcup\controllers;
use Fourward\common\HttpError;
use Fourward\worldcup\models\Team;
use Fourward\worldcup\models\Player;

final class TeamController extends ReadController {
  public function index(array $input): array {
    return (new Team($this->db))->listing($this->text($input, 'q'), ...$this->pagination($input));
  }

  public function show(array $input): array {
    $id = $this->integer($input, 'id');
    if ($id === null) throw new HttpError(422, '缺少球队编号。');
    return (new Team($this->db))->find($id) ?? throw new HttpError(404, '球队不存在。');
  }

  public function players(array $input): array {
    $team = $this->show(['id' => $input['team_id'] ?? null]);
    return (new Player($this->db))->listing((int) $team['id'], ...$this->pagination($input));
  }
}
