<?php
/** FOURWARD coursework | Author: 韩羽宸 | Transactional World Cup foundation acceptance checks. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require __DIR__ . '/../app/common/bootstrap.php';
use Fourward\common\HttpError;
use Fourward\worldcup\controllers\ApiController;
use Fourward\worldcup\controllers\PageController;

$checks = 0;
function wcVerify(bool $condition, string $label): void {
  global $checks;
  if (!$condition) throw new RuntimeException('FAILED: ' . $label);
  $checks++;
}
function wcRejects(callable $action, int $status): void {
  try { $action(); } catch (HttpError $error) {
    wcVerify($error->status === $status, 'HTTP ' . $status);
    return;
  }
  throw new RuntimeException('Expected HTTP ' . $status);
}
function wcSqlRejects(PDO $db, string $sql, array $values): void {
  try { $db->prepare($sql)->execute($values); } catch (PDOException $error) {
    wcVerify(str_starts_with((string) $error->getCode(), '23') || $error->getCode() === '22003', 'database constraint');
    return;
  }
  throw new RuntimeException('Expected a database constraint failure.');
}
function wcInsert(PDO $db, string $sql, array $values): int {
  $db->prepare($sql)->execute($values);
  return (int) $db->lastInsertId();
}

startSession();
$_SERVER['REQUEST_METHOD'] = 'GET';
$db = database();
$db->exec("SET time_zone = '+00:00'");
$api = new ApiController($db);
$baseline = $api->dispatch('overview', 'GET', [])['counts'];
wcVerify(count($baseline) === 8, 'all eight World Cup tables are queryable');
$db->beginTransaction();
try {
  $suffix = bin2hex(random_bytes(3));
  $name = '球队_%!' . $suffix . '<img src=x onerror=alert(1)>';
  $home = wcInsert($db, 'INSERT INTO wc_teams(code,name_zh,name_en) VALUES (?,?,?)', ['H' . $suffix, $name, 'Home ' . $suffix]);
  $away = wcInsert($db, 'INSERT INTO wc_teams(code,name_zh,name_en) VALUES (?,?,?)', ['A' . $suffix, '客队' . $suffix, 'Away ' . $suffix]);
  $third = wcInsert($db, 'INSERT INTO wc_teams(code,name_zh,name_en) VALUES (?,?,?)', ['X' . $suffix, '第三队' . $suffix, 'Other ' . $suffix]);
  $stage = wcInsert($db, 'INSERT INTO wc_stages(code,name,is_group_stage) VALUES (?,?,1)', ['test-' . $suffix, '测试阶段']);
  $group = wcInsert($db, 'INSERT INTO wc_groups(code,name) VALUES (?,?)', ['G' . $suffix, '测试小组']);
  $otherGroup = wcInsert($db, 'INSERT INTO wc_groups(code,name) VALUES (?,?)', ['Z' . $suffix, '另一小组']);
  $db->prepare('INSERT INTO wc_group_teams(group_id,team_id) VALUES (?,?),(?,?)')->execute([$group, $home, $group, $away]);
  wcSqlRejects($db, 'INSERT INTO wc_group_teams(group_id,team_id) VALUES (?,?)', [$otherGroup, $home]);
  $venue = wcInsert($db, 'INSERT INTO wc_venues(name,city,country,timezone) VALUES (?,?,?,?)', ['测试场馆' . $suffix, '测试城市', '测试国家', 'America/New_York']);
  wcInsert($db, 'INSERT INTO wc_players(team_id,name,shirt_number,position) VALUES (?,?,?,?)', [$home, '测试球员', 10, 'FW']);
  wcSqlRejects($db, 'INSERT INTO wc_players(team_id,name,shirt_number) VALUES (?,?,?)', [$home, '重复号码', 10]);
  wcSqlRejects($db, 'INSERT INTO wc_players(team_id,name) VALUES (?,?)', [PHP_INT_MAX, '孤立球员']);

  $matchNo = (int) $db->query('SELECT COALESCE(MAX(match_no),0)+1 FROM wc_matches')->fetchColumn();
  $insertMatch = 'INSERT INTO wc_matches(match_no,stage_id,group_id,home_team_id,away_team_id,venue_id,kickoff_at,status) VALUES (?,?,?,?,?,?,?,?)';
  $match = wcInsert($db, $insertMatch, [$matchNo, $stage, $group, $home, $away, $venue, '2026-06-28 22:00:00', 'scheduled']);
  wcInsert($db, $insertMatch, [$matchNo + 1, $stage, $group, $away, $home, $venue, '2026-06-29 16:00:00', 'postponed']);
  wcSqlRejects($db, $insertMatch, [$matchNo + 2, $stage, $group, $home, $home, $venue, '2026-06-28 22:00:00', 'scheduled']);
  wcSqlRejects($db, 'UPDATE wc_matches SET winner_team_id=? WHERE id=?', [$third, $match]);
  wcSqlRejects($db, 'UPDATE wc_matches SET home_score_90=1 WHERE id=?', [$match]);
  $user = wcInsert($db, 'INSERT INTO users(username,password_hash,role) VALUES (?,?,?)', ['wc-test-' . $suffix, password_hash(bin2hex(random_bytes(12)), PASSWORD_DEFAULT), 'member']);
  $otherUser = wcInsert($db, 'INSERT INTO users(username,password_hash,role) VALUES (?,?,?)', ['wc-other-' . $suffix, password_hash(bin2hex(random_bytes(12)), PASSWORD_DEFAULT), 'admin']);
  wcInsert($db, 'INSERT INTO wc_supports(user_id,match_id,team_id) VALUES (?,?,?)', [$user, $match, $home]);
  wcSqlRejects($db, 'INSERT INTO wc_supports(user_id,match_id,team_id) VALUES (?,?,?)', [$user, $match, $away]);
  wcSqlRejects($db, 'DELETE FROM wc_teams WHERE id=?', [$home]);

  $result = $api->dispatch('teams', 'GET', ['q' => '_%!' . $suffix, 'page_size' => '1']);
  wcVerify($result['meta']['total'] === 1 && $result['items'][0]['id'] === $home, 'literal LIKE wildcards and prepared search');
  $team = $api->dispatch('team', 'GET', ['id' => $home]);
  wcVerify($team['group_name'] === '测试小组', 'team group join');
  wcVerify($api->dispatch('players', 'GET', ['team_id' => $home])['meta']['total'] === 1, 'squad association');
  $result = $api->dispatch('matches', 'GET', ['team_id' => $home, 'date' => '2026-06-29', 'page_size' => 1]);
  wcVerify($result['meta']['total'] === 1 && $result['items'][0]['id'] === $match, 'Beijing date boundary excludes next midnight');
  $result = $api->dispatch('matches', 'GET', ['team_id' => $home, 'page_size' => 1, 'page' => 2]);
  wcVerify(count($result['items']) === 1 && $result['meta']['total'] === 2, 'fixture pagination');
  $result = $api->dispatch('matches', 'GET', ['stage_id' => $stage, 'status' => 'postponed']);
  wcVerify($result['meta']['total'] === 1, 'postponed status filter');
  $row = $api->dispatch('match', 'GET', ['id' => $match]);
  wcVerify($row['home_score'] === null && $row['away_score'] === null, 'unknown score stays null');
  wcVerify($row['home_support'] === 1 && $row['away_support'] === 0, 'support counts come from database');
  wcVerify($row['kickoff_at'] === '2026-06-28T22:00:00Z', 'UTC transport');
  $db->prepare('UPDATE wc_matches SET home_score_90=1,away_score_90=1,home_score_et=2,away_score_et=2,home_penalty_score=4,away_penalty_score=3,status=?,winner_team_id=? WHERE id=?')->execute(['finished', $home, $match]);
  $row = $api->dispatch('match', 'GET', ['id' => $match]);
  wcVerify($row['home_score'] === 2 && $row['home_penalty_score'] === 4, 'extra-time total excludes shootout score');
  $groups = $api->dispatch('groups', 'GET', ['page_size' => 100]);
  $entry = array_values(array_filter($groups['items'], fn(array $entry): bool => $entry['id'] === $group))[0];
  wcVerify(count($entry['teams']) === 2, 'group members');
  wcRejects(fn() => $api->dispatch('teams', 'GET', ['q' => ['invalid']]), 422);
  wcRejects(fn() => $api->dispatch('teams', 'GET', ['page' => 0]), 422);
  wcRejects(fn() => $api->dispatch('teams', 'GET', ['page_size' => 101]), 422);
  wcRejects(fn() => $api->dispatch('matches', 'GET', ['date' => '2026-02-30']), 422);
  wcRejects(fn() => $api->dispatch('matches', 'GET', ['status' => 'fake']), 422);
  wcRejects(fn() => $api->dispatch('team', 'GET', ['id' => PHP_INT_MAX]), 404);
  wcRejects(fn() => $api->dispatch('match', 'GET', []), 422);
  wcRejects(fn() => $api->dispatch('unknown', 'GET', []), 404);
  wcRejects(fn() => $api->dispatch('teams', 'POST', []), 405);
  wcRejects(fn() => $api->dispatch('support', 'GET', []), 405);
  wcRejects(fn() => $api->dispatch('my_supports', 'GET', []), 401);
  wcRejects(fn() => $api->dispatch('admin_overview', 'GET', []), 401);
  wcRejects(fn() => $api->dispatch('support', 'POST', [], csrfToken()), 401);
  $_SESSION['user_id'] = $user;
  wcVerify($api->dispatch('my_supports', 'GET', [])['meta']['total'] === 1, 'own support history');
  wcRejects(fn() => $api->dispatch('admin_overview', 'GET', []), 403);
  wcRejects(fn() => $api->dispatch('support', 'POST', [], 'invalid'), 403);
  wcRejects(fn() => $api->dispatch('support', 'POST', [], csrfToken()), 501);
  $bootstrap = $api->dispatch('bootstrap', 'GET', []);
  wcVerify(!isset($bootstrap['user']['password_hash']) && !$bootstrap['capabilities']['support_write'], 'public user fields and truthful capabilities');
  $_SESSION['user_id'] = $otherUser;
  wcVerify($api->dispatch('my_supports', 'GET', [])['meta']['total'] === 0, 'another user cannot see support history');
  wcVerify($api->dispatch('admin_overview', 'GET', [])['counts']['teams'] === $baseline['teams'] + 3, 'administrator overview');
  ob_start();
  (new PageController($db))->handle(['view' => 'teams', 'id' => $home]);
  $html = ob_get_clean();
  wcVerify(str_contains($html, '&lt;img src=x onerror=alert(1)&gt;') && !str_contains($html, '<img src=x onerror=alert(1)>'), 'HTML output escapes imported content');
  unset($_SESSION['user_id']);
} finally {
  if ($db->inTransaction()) $db->rollBack();
}
wcVerify($api->dispatch('overview', 'GET', [])['counts'] === $baseline, 'all fixtures rolled back');
echo "World Cup foundation acceptance passed: $checks checks. All fixtures rolled back.\n";
