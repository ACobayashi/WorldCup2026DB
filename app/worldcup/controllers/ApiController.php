<?php
// 2410933_马禹翔
/** FOURWARD coursework | Author: 韩羽宸 | 学号: 2410673 | World Cup API dispatch; independent of team API contracts. */
declare(strict_types=1);
namespace Fourward\worldcup\controllers;
use Fourward\common\Auth;
use Fourward\common\HttpError;

final class ApiController extends ReadController {
  public function dispatch(string $action, string $method, array $input, ?string $csrf = null): array {
    $known = ['bootstrap', 'overview', 'teams', 'team', 'team_compare', 'players', 'player', 'player_compare', 'player_rankings', 'matches', 'match', 'groups', 'standings', 'bracket', 'venues', 'stages', 'my_supports', 'admin_overview', 'support'];
    if (!in_array($action, $known, true)) throw new HttpError(404, '接口不存在。');
    if ($action === 'support') {
      if ($method !== 'POST') { header('Allow: POST'); throw new HttpError(405, '该接口仅接受 POST。'); }
      (new SupportController($this->db))->write($csrf);
    }
    if ($method !== 'GET') { header('Allow: GET'); throw new HttpError(405, '该接口仅接受 GET。'); }
    return match ($action) {
      'bootstrap' => ['user' => $this->publicUser(), 'csrf_token' => \csrfToken(), 'capabilities' => (new CatalogController($this->db))->overview()['capabilities']],
      'overview' => (new CatalogController($this->db))->overview(),
      'teams' => (new TeamController($this->db))->index($input),
      'team' => (new TeamController($this->db))->show($input),
      'team_compare' => (new TeamController($this->db))->compare($input),
      'players' => (new TeamController($this->db))->players($input),
      'player' => (new PlayerController($this->db))->show($input),
      'player_compare' => (new PlayerController($this->db))->compare($input),
      'player_rankings' => (new PlayerController($this->db))->index($input),
      'matches' => (new MatchController($this->db))->index($input),
      'match' => (new MatchController($this->db))->show($input),
      'standings' => (new StandingsController($this->db))->index(),
      'bracket' => (new StandingsController($this->db))->bracket(),
      'groups', 'venues', 'stages' => (new CatalogController($this->db))->index($action, $input),
      'my_supports' => (new SupportController($this->db))->index($input),
      'admin_overview' => (new AdminController($this->db))->index(),
    };
  }

  private function publicUser(): ?array {
    $user = (new Auth($this->db))->user();
    return $user ? array_intersect_key($user, array_flip(['id', 'username', 'role'])) : null;
  }
}
