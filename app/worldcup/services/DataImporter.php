<?php
/** FOURWARD coursework | Author: 韩羽宸 | 学号: 2410673 | Atomic CLI dataset import and source identity mapping. */
declare(strict_types=1);
namespace Fourward\worldcup\services;

final class DataImporter {
  private array $ids = [];
  private array $players = [];
  private array $matches = [];
  private array $appearances = [];
  private int $batch;
  public function __construct(private readonly \PDO $db) {
    $this->db->exec("SET time_zone = '+00:00'");
  }

  private function check(bool $ok, string $message): void {
    if (!$ok) throw new \InvalidArgumentException($message);
  }
  private function row(string $sql, array $args): ?array {
    $query = $this->db->prepare($sql); $query->execute($args);
    return $query->fetch() ?: null;
  }
  private function write(string $sql, array $args): void {
    $this->db->prepare($sql)->execute($args);
  }
  private function id(string $key, string $kind): int {
    $this->check(isset($this->ids[$kind][$key]), 'Unresolved '.$kind.' reference: '.$key);
    return $this->ids[$kind][$key];
  }
  private function upsert(string $table, array $values, array $keys): int {
    // Table/column names originate only in the fixed code below, never dataset text.
    $where = implode(' AND ', array_map(fn($k) => "`$k`=?", $keys));
    $existing = $this->row("SELECT * FROM `$table` WHERE $where", array_map(fn($k) => $values[$k], $keys));
    $fields = array_keys($values);
    if ($existing) {
      $this->write("UPDATE `$table` SET ".implode(',', array_map(fn($k) => "`$k`=?", $fields))." WHERE $where",
        [...array_values($values), ...array_map(fn($k) => $values[$k], $keys)]);
      return (int)($existing['id'] ?? 0);
    }
    $this->write("INSERT INTO `$table` (`".implode('`,`', $fields)."`) VALUES (".implode(',', array_fill(0,count($fields),'?')).")", array_values($values));
    return (int)$this->db->lastInsertId();
  }
  private function mapping(string $provider, string $kind, string $source, int $id): void {
    $this->check(strlen($provider) <= 40 && strlen($source) <= 160 && $source !== '', 'Invalid source identifier');
    $old = $this->row('SELECT * FROM wc_source_mappings WHERE provider=? AND entity_type=? AND source_id=?', [$provider,$kind,$source]);
    $column = $kind.'_id';
    $this->check(!$old || (int)$old[$column] === $id, 'Source identity collision: '.$provider.'/'.$source);
    $this->upsert('wc_source_mappings', ['provider'=>$provider,'entity_type'=>$kind,'source_id'=>$source,$column=>$id,'batch_id'=>$this->batch], ['provider','entity_type','source_id']);
  }
  private function canonical(string $key, string $kind, int $id): void {
    $parts = explode(':', $key, 3);
    $this->check(count($parts)===3 && $parts[1]===$kind, 'Invalid canonical identifier');
    $this->check(!isset($this->ids[$kind][$key]), 'Duplicate canonical identifier');
    $this->mapping($parts[0],$kind,$parts[2],$id);
    $this->ids[$kind][$key]=$id;
  }
  private function source(array $data): array {
    return ['source_url'=>$data['source_url'] ?? 'https://api.fifa.com/api/v3/calendar/matches?idSeason=285023&idCompetition=17&language=en-US',
            'source_retrieved_at'=>$data['source_retrieved_at'] ?? null];
  }
  private function matchPlayer(array $row): array {
    $player=$this->players[$row['player_id']] ?? null;
    $match=$this->matches[$row['match_id']] ?? null;
    $this->check($player!==null && $match!==null, 'Unknown match/player');
    $this->check(in_array($player['team_id'],[$match['home_team_id'],$match['away_team_id']],true), 'Player outside match teams');
    return [$this->id($row['match_id'],'match'),$this->id($row['player_id'],'player'),$this->id($player['team_id'],'team')];
  }
  public function import(array $data, string $hash, bool $dryRun=false): array {
    $this->check(($data['import_schema']??null)===1, 'Unsupported import contract');
    foreach (['teams','players','stages','groups','group_teams','venues','matches','appearances','player_stats','team_stats','events','player_mappings'] as $key) {
      $this->check(isset($data[$key]) && is_array($data[$key]) && array_is_list($data[$key]), 'Missing dataset section: '.$key);
    }
    $this->check(!$this->db->inTransaction(), 'Importer needs its own transaction');
    $lock=$this->row("SELECT GET_LOCK('fourward_worldcup_import',10) AS acquired",[]);
    $this->check((int)$lock['acquired']===1, 'Another import is running');
    try {
      $this->db->beginTransaction();
      $previous=$this->row('SELECT id,counts_json FROM wc_import_batches WHERE content_sha256=?',[$hash]);
      if ($previous) {
        $this->db->rollBack();
        return ['status'=>'already_imported','batch_id'=>(int)$previous['id'],'counts'=>json_decode($previous['counts_json'],true)];
      }
      $this->ids=$this->players=$this->matches=$this->appearances=[];
      $counts=[];
      foreach (['teams','players','venues','stages','groups','group_teams','matches','appearances','player_stats','team_stats','events','player_mappings'] as $key) $counts[$key]=count($data[$key]);
      $counts['entity_mappings']=count($data['entity_mappings']??[]);
      $this->batch=$this->upsert('wc_import_batches',[
        'dataset_name'=>$data['dataset_name'],'content_sha256'=>$hash,
        'source_manifest'=>json_encode($data['manifest'],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),
        'counts_json'=>json_encode($counts,JSON_THROW_ON_ERROR)],['content_sha256']);
      foreach ($data['teams'] as $r) {
        $id=$this->upsert('wc_teams',['code'=>$r['code'],'name_zh'=>$r['name_zh'],'name_en'=>$r['name'],...$this->source($r)],['code']);
        $this->canonical($r['id'],'team',$id);
      }
      foreach ($data['stages'] as $r) $this->ids['stage'][$r['code']]=$this->upsert('wc_stages',array_intersect_key($r,array_flip(['code','name','sort_order','is_group_stage'])),['code']);
      foreach ($data['groups'] as $r) $this->ids['group'][$r['code']]=$this->upsert('wc_groups',array_intersect_key($r,array_flip(['code','name','sort_order'])),['code']);
      foreach ($data['group_teams'] as $r) {
        $team=$this->id($r['team_id'],'team'); $group=$this->id($r['group_code'],'group');
        $old=$this->row('SELECT group_id FROM wc_group_teams WHERE team_id=?',[$team]);
        $this->check(!$old || (int)$old['group_id']===$group,'Conflicting group membership');
        $this->upsert('wc_group_teams',['group_id'=>$group,'team_id'=>$team],['team_id']);
      }
      foreach ($data['venues'] as $r) {
        $this->check(in_array($r['timezone'],\DateTimeZone::listIdentifiers(),true),'Unknown IANA timezone');
        $id=$this->upsert('wc_venues',['name'=>$r['name'],'city'=>$r['city'],'country'=>$r['country'],'timezone'=>$r['timezone'],...$this->source($r)],['country','city','name']);
        $this->canonical($r['id'],'venue',$id);
      }
      foreach ($data['players'] as $r) {
        $team=$this->id($r['team_id'],'team');
        $old=$this->row('SELECT * FROM wc_players WHERE team_id=? AND shirt_number=?',[$team,$r['shirt_number']]);
        $this->check(!$old || $old['name']===$r['name'],'Unreviewed player identity/number change');
        $id=$this->upsert('wc_players',['team_id'=>$team,'name'=>$r['name'],'shirt_number'=>$r['shirt_number'],'position'=>$r['position'],...$this->source($r)],['team_id','shirt_number']);
        $this->canonical($r['id'],'player',$id); $this->players[$r['id']]=$r;
      }
      foreach ($data['matches'] as $r) {
        $stage=$this->id($r['stage_code'],'stage');
        $stageRow=$this->row('SELECT is_group_stage FROM wc_stages WHERE id=?',[$stage]);
        $group=$r['group_code']===null ? null : $this->id($r['group_code'],'group');
        $this->check((bool)$stageRow['is_group_stage']===($group!==null),'Stage/group disagreement');
        $home=$this->id($r['home_team_id'],'team'); $away=$this->id($r['away_team_id'],'team');
        if ($group!==null) foreach ([$home,$away] as $team) $this->check($this->row('SELECT team_id FROM wc_group_teams WHERE group_id=? AND team_id=?',[$group,$team])!==null,'Match outside assigned group');
        $this->check(preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D',$r['kickoff_at'])===1,'Kickoff must be explicit UTC');
        $parts=explode(':',$r['id'],3);
        $values=['match_no'=>$r['match_no'],'stage_id'=>$stage,'group_id'=>$group,'home_team_id'=>$home,'away_team_id'=>$away,
          'venue_id'=>$r['venue_id']===null ? null : $this->id($r['venue_id'],'venue'),
          'kickoff_at'=>str_replace(['T','Z'],[' ',''],$r['kickoff_at']),'status'=>$r['status'],
          'winner_team_id'=>$r['winner_team_id']===null ? null : $this->id($r['winner_team_id'],'team'),
          'source_provider'=>$parts[0],'source_id'=>$parts[2],...$this->source($r)];
        foreach (['home_score_90','away_score_90','home_score_et','away_score_et','home_penalty_score','away_penalty_score'] as $field) $values[$field]=$r[$field];
        $id=$this->upsert('wc_matches',$values,['match_no']);
        $this->canonical($r['id'],'match',$id); $this->matches[$r['id']]=$r;
      }
      foreach ($data['player_mappings'] as $r) $this->mapping($r['provider'],'player',$r['source_key'],$this->id($r['player_id'],'player'));
      foreach ($data['entity_mappings']??[] as $r) {
        $this->check(in_array($r['entity_type'],['team','player','match','venue'],true),'Invalid mapping entity');
        $this->mapping($r['provider'],$r['entity_type'],$r['source_key'],$this->id($r['target_id'],$r['entity_type']));
      }
      foreach ($data['appearances'] as $r) {
        [$match,$player,$team]=$this->matchPlayer($r); $p=$this->players[$r['player_id']];
        $key=$match.'/'.$player;
        $this->check(!isset($this->appearances[$key]),'Duplicate appearance');
        $this->check(is_bool($r['starting']) && is_bool($r['appeared']),'Appearance flags must be boolean');
        $this->upsert('wc_match_appearances',['match_id'=>$match,'player_id'=>$player,'team_id'=>$team,'shirt_number'=>$p['shirt_number'],
          'position'=>$r['position']??$p['position'],'is_starter'=>(int)$r['starting'],'appeared'=>(int)$r['appeared'],'batch_id'=>$this->batch],['match_id','player_id']);
        $this->appearances[$key]=$r;
      }
      $seen=[];
      foreach ($data['player_stats'] as $r) {
        [$match,$player]=$this->matchPlayer($r); $key=$match.'/'.$player;
        $this->check(!isset($seen[$key]) && ($this->appearances[$key]['appeared']??false),'Unexpected/duplicate player statistics'); $seen[$key]=true;
        $values=['match_id'=>$match,'player_id'=>$player,'batch_id'=>$this->batch];
        foreach (['goals','assists','minutes_played','shots','passes_attempted','passes_completed','pass_completion_pct'] as $field) $values[$field]=$r[$field];
        $this->upsert('wc_player_match_stats',$values,['match_id','player_id']);
      }
      $seen=[];
      foreach ($data['team_stats'] as $r) {
        $m=$this->matches[$r['match_id']]??null; $this->check($m!==null && in_array($r['team_id'],[$m['home_team_id'],$m['away_team_id']],true),'Team stats outside match');
        $match=$this->id($r['match_id'],'match'); $team=$this->id($r['team_id'],'team'); $key=$match.'/'.$team;
        $this->check(!isset($seen[$key]),'Duplicate team statistics'); $seen[$key]=true;
        $values=['match_id'=>$match,'team_id'=>$team,'batch_id'=>$this->batch];
        foreach (['goals','possession_pct','shots','shots_on_target','passes_attempted','passes_completed','pass_completion_pct'] as $field) $values[$field]=$r[$field];
        $this->upsert('wc_team_match_stats',$values,['match_id','team_id']);
      }
      $seen=[];
      foreach ($data['events'] as $r) {
        [$match,$player]=$this->matchPlayer($r);
        $this->check(isset($this->appearances[$match.'/'.$player]) && !isset($seen[$r['source_id']]),'Unknown/duplicate event participant'); $seen[$r['source_id']]=true;
        $this->upsert('wc_match_events',['match_id'=>$match,'player_id'=>$player,'event_type'=>$r['type'],'minute'=>$r['minute'],'added_minute'=>$r['added_minute'],
          'minute_label'=>$r['label'],'source_provider'=>'worldcup26','source_id'=>$r['source_id'],'batch_id'=>$this->batch],['source_provider','source_id']);
      }
      if ($dryRun) $this->db->rollBack(); else $this->db->commit();
      return ['status'=>$dryRun?'validated_rolled_back':'imported','batch_id'=>$this->batch,'counts'=>$counts];
    } catch (\Throwable $error) {
      if ($this->db->inTransaction()) $this->db->rollBack();
      throw $error;
    } finally { $this->row("SELECT RELEASE_LOCK('fourward_worldcup_import')",[]); }
  }
}
