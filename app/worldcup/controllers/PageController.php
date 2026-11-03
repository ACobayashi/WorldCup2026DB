<?php
/** FOURWARD coursework | Author: 韩羽宸 | Server-rendered World Cup MVC page assembly. */
declare(strict_types=1);
namespace Fourward\worldcup\controllers;
use Fourward\common\Auth;
use Fourward\common\HttpError;

final class PageController extends ReadController {
  public function handle(array $input): void {
    $section = 'matches';
    $user = null;
    $data = [];
    $titles = ['matches' => '赛程与赛果', 'teams' => '参赛球队', 'groups' => '赛事分组', 'venues' => '比赛场馆', 'support' => '我的支持', 'admin' => '赛事工作台'];
    try {
      if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') { header('Allow: GET'); throw new HttpError(405, '该页面仅接受 GET。'); }
      $section = $this->text($input, 'view', 'matches', 16);
      if (!isset($titles[$section])) throw new HttpError(404, '页面不存在。');
      $user = (new Auth($this->db))->user();
      $overview = (new CatalogController($this->db))->overview();
      $detail = $this->integer($input, 'id') !== null;
      $data = match ($section) {
        'teams' => $detail
          ? ['team' => (new TeamController($this->db))->show($input)]
          : (new TeamController($this->db))->index($input),
        'matches' => $detail ? ['match' => (new MatchController($this->db))->show($input)] : (new MatchController($this->db))->index($input),
        'groups', 'venues' => (new CatalogController($this->db))->index($section, $input),
        'support' => (new SupportController($this->db))->index($input),
        'admin' => (new AdminController($this->db))->index(),
      };
      $view = $section;
    } catch (HttpError $error) {
      http_response_code($error->status);
      $errorMessage = $error->getMessage();
      $view = 'error';
    } catch (\Throwable $error) {
      error_log((string) $error);
      http_response_code(503);
      $errorMessage = '赛事服务暂时不可用，请稍后重试。';
      $view = 'error';
    }
    $title = $titles[$section] ?? '赛事中心';
    $url = static fn(string $target, array $params = []): string => './?' . http_build_query(['view' => $target] + $params);
    $beijingTime = static fn(?string $value): string => $value ? (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('Asia/Shanghai'))->format('Y-m-d H:i') : '待确认';
    $score = static fn(array $match): string => $match['home_score'] === null ? '— : —' : $match['home_score'] . ' : ' . $match['away_score'];
    $statusLabels = ['scheduled' => '未开赛', 'live' => '进行中', 'finished' => '已结束', 'postponed' => '已延期', 'cancelled' => '已取消'];
    require \PROJECT_ROOT . '/app/worldcup/views/layout.php';
  }
}
