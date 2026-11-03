<?php
/** FOURWARD coursework | Author: 韩羽宸 | Team and squad read endpoints. */
declare(strict_types=1);
namespace Fourward\worldcup\controllers;
use Fourward\common\HttpError;
use Fourward\worldcup\models\Team;
use Fourward\worldcup\models\Player;
use Fourward\worldcup\models\MatchModel;

final class TeamController extends ReadController {
  public function index(array $input): array {
    return (new Team($this->db))->listing($this->text($input, 'q'), ...$this->pagination($input));
  }

  public function show(array $input): array {
    $id = $this->integer($input, 'id');
    if ($id === null) throw new HttpError(422, '缺少球队编号。');
    $model=new Team($this->db);
    $team=$model->find($id) ?? throw new HttpError(404, '球队不存在。');
    $team['squad']=$model->squad($id);
    $team['matches']=(new MatchModel($this->db))->listing(['status'=>'all','date'=>'','team_id'=>$id,'stage_id'=>null,'group_id'=>null],1,100)['items'];
    $team['summary']=['players'=>count($team['squad']),'played'=>0,'goals_for'=>0,'goals_against'=>0];
    foreach ($team['matches'] as $m) if ($m['status']==='finished' && $m['home_score']!==null && $m['away_score']!==null) {
      $home=$m['home_team_id']===$id;$team['summary']['played']++;
      $team['summary']['goals_for']+=$m[$home?'home_score':'away_score'];
      $team['summary']['goals_against']+=$m[$home?'away_score':'home_score'];
    }
    return $team;
  }

  public function players(array $input): array {
    $id=$this->integer(['id'=>$input['team_id']??null],'id');
    if ($id===null) throw new HttpError(422,'缺少球队编号。');
    if (!(new Team($this->db))->find($id)) throw new HttpError(404,'球队不存在。');
    return (new Player($this->db))->listing($id, ...$this->pagination($input));
  }
}
