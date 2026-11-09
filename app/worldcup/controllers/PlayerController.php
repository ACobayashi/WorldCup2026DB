<?php
/** FOURWARD coursework | Author: 韩羽宸 | 学号: 2410673 | Individual player and aggregate ranking input boundaries. */
declare(strict_types=1);
namespace Fourward\worldcup\controllers;
use Fourward\worldcup\models\PlayerBoard;
use Fourward\worldcup\models\PlayerAbility;
use Fourward\common\HttpError;
final class PlayerController extends ReadController {
  public function show(array $input):array {
    $id=$this->integer($input,'id');if($id===null)throw new HttpError(422,'缺少球员编号。');
    $player=(new PlayerBoard($this->db))->find($id)??throw new HttpError(404,'球员不存在。');
    $matchId=$this->integer($input,'match_id');$selected=null;
    foreach($player['matches'] as $m)if($matchId!==null?$m['id']===$matchId:(bool)$m['appeared'])$selected=$m;
    if($matchId!==null&&$selected===null)throw new HttpError(404,'该比赛不属于这名球员的球队赛程。');
    if($selected===null&&$player['matches'])$selected=$player['matches'][array_key_last($player['matches'])];
    $player['selected_match']=$selected;
    return $player;
  }
  public function compare(array $input):array {
    $board=new PlayerBoard($this->db);$options=$board->comparisonOptions();
    $first=$this->integer($input,'player_a');$second=$this->integer($input,'player_b');
    $available=array_values(array_filter($options,fn($p)=>$p['has_ability']));
    if($first===null)$first=(int)($available[0]['id']??$options[0]['id']??0);
    if($second===null){foreach($available?:$options as $option)if((int)$option['id']!==$first){$second=(int)$option['id'];break;}}
    if(!$first||!$second)throw new HttpError(404,'暂无可对比的球员。');
    if($first===$second)throw new HttpError(422,'请选择两名不同的球员。');
    $a=$board->find($first)??throw new HttpError(404,'球员A不存在。');
    $b=$board->find($second)??throw new HttpError(404,'球员B不存在。');
    return ['players'=>[$a,$b],'options'=>$options,'ability_coverage'=>PlayerAbility::coverage()];
  }
  public function index(array $input):array {
    $metric=$this->text($input,'metric','goals',40);$position=$this->text($input,'position','',2);
    if(!isset(PlayerBoard::RANKINGS[$metric]))throw new HttpError(422,'暂不支持该统计项目。');
    if(!in_array($position,['','GK','DF','MF','FW'],true))throw new HttpError(422,'球员位置不正确。');
    return (new PlayerBoard($this->db))->leaders($metric,$position,...$this->pagination($input));
  }
}
