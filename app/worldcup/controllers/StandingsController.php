<?php
/** FOURWARD coursework | Author: 韩羽宸 | Standings and knockout read boundaries. */
declare(strict_types=1);
namespace Fourward\worldcup\controllers;
use Fourward\worldcup\models\Standings;
use Fourward\worldcup\models\Knockout;
final class StandingsController extends ReadController {
  public function index():array {return (new Standings($this->db))->table();}
  public function bracket():array {return (new Knockout($this->db))->bracket();}
}
