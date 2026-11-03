"""FOURWARD coursework | Author: 韩羽宸 | Full-edition provenance and identity guards."""
import copy
import json
import sys
import unittest
from pathlib import Path
sys.path.insert(0,str(Path(__file__).resolve().parents[1]/'scripts'))
import worldcup_full as full
from worldcup_data import DataError, encode_json


class FullCoreTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.bundle,cls.manifest=full.load()

    def test_full_core_counts_and_reproducibility(self):
        data,_=full.build_core(self.bundle,self.manifest)
        self.assertEqual((len(data['teams']),len(data['players']),len(data['matches']),len(data['venues'])),(48,1248,104,16))
        self.assertEqual(encode_json(data),(full.DIRECTORY/'core.json').read_bytes())

    def test_reference_order_is_not_used_as_match_number(self):
        shuffled=copy.deepcopy(self.bundle)
        shuffled['openfootball-matches.json']['matches'].reverse()
        shuffled['openfootball-full.json']['matches'].reverse()
        data,_=full.build_core(shuffled,self.manifest)
        expected,_=full.build_core(self.bundle,self.manifest)
        self.assertEqual(data,expected)

    def test_penalties_and_extra_time_are_separate(self):
        data,_=full.build_core(self.bundle,self.manifest)
        penalties=[m for m in data['matches'] if m['home_penalty_score'] is not None]
        self.assertEqual(len(penalties),4)
        self.assertTrue(all(m['home_score_et']==m['away_score_et'] for m in penalties))
        final=next(m for m in data['matches'] if m['match_no']==104)
        self.assertEqual((final['home_score_90'],final['away_score_90'],final['home_score_et'],final['away_score_et']),(0,0,1,0))

    def test_known_source_disagreements_are_reported(self):
        data,_=full.build_core(self.bundle,self.manifest)
        self.assertEqual({r['match_no'] for r in data['report']['kickoff_discrepancies']},{79,92})
        self.assertEqual([r['match_no'] for r in data['report']['halftime_discrepancies']],[79])

    def test_score_change_is_rejected(self):
        bad=copy.deepcopy(self.bundle);bad['matches.json'][0]['HomeTeamScore']=99
        with self.assertRaisesRegex(DataError,'score disagreement'): full.build_core(bad,self.manifest)

    def test_unknown_status_is_rejected(self):
        bad=copy.deepcopy(self.bundle);bad['matches.json'][0]['MatchStatus']=99
        with self.assertRaisesRegex(DataError,'status'): full.build_core(bad,self.manifest)

    def test_missing_fixture_is_rejected(self):
        bad=copy.deepcopy(self.bundle);bad['matches.json'].pop()
        with self.assertRaises(DataError): full.build_core(bad,self.manifest)

    def test_foreign_squad_identity_is_rejected(self):
        bad=copy.deepcopy(self.bundle);bad['squad-ALG.json']['Players'][0]['IdTeam']='invalid'
        with self.assertRaises(DataError): full.build_core(bad,self.manifest)

    def test_duplicate_player_id_is_rejected(self):
        bad=copy.deepcopy(self.bundle);bad['squad-ALG.json']['Players'][1]['IdPlayer']=bad['squad-ALG.json']['Players'][0]['IdPlayer']
        with self.assertRaises(DataError): full.build_core(bad,self.manifest)


class FullDetailTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.bundle,cls.manifest=full.load()
        cls.corrections=json.loads((full.DIRECTORY/'corrections.json').read_text(encoding='utf-8'))
        cls.data=full.build_details(cls.bundle,cls.manifest,cls.corrections)

    def test_full_detail_reproducibility_and_coverage(self):
        self.assertEqual(encode_json(self.data),(full.DIRECTORY/'complete.json').read_bytes())
        self.assertEqual([len(self.data[k]) for k in ['appearances','player_stats','team_stats','events','player_mappings']],[5392,3288,208,2587,1277])
        self.assertTrue(all(n==104 for n in self.data['report']['coverage'].values()))

    def test_scoped_code_fix_does_not_rewrite_other_ghana_rows(self):
        code={t['id']:t['code'] for t in self.data['teams']}
        player={p['id']:code[p['team_id']] for p in self.data['players']}
        matches={m['id']:m for m in self.data['matches']}
        for r in self.data['appearances']:
            if matches[r['match_id']]['match_no']==47: self.assertIn(player[r['player_id']],['POR','UZB'])
        self.assertTrue(any(player[r['player_id']]=='GHA' for r in self.data['appearances']))
        self.assertEqual(self.data['report']['corrected_rows']['appearances'],26)

    def test_removing_required_correction_rejects_data(self):
        bad=copy.deepcopy(self.corrections);bad['match_team_corrections']=[]
        with self.assertRaisesRegex(DataError,'team-code'):full.build_details(self.bundle,self.manifest,bad)

    def test_wrong_canonical_correction_is_rejected(self):
        bad=copy.deepcopy(self.corrections);bad['match_team_corrections'][0]['canonical_team']='GHA'
        with self.assertRaises(DataError):full.build_details(self.bundle,self.manifest,bad)

    def test_short_name_alias_requires_independent_identity(self):
        bad=copy.deepcopy(self.corrections);bad['player_name_corrections'][0]['player_id']='fifa:player:wrong'
        with self.assertRaisesRegex(DataError,'name mismatch'):full.build_details(self.bundle,self.manifest,bad)

    def test_unknown_name_is_not_joined_by_number(self):
        bad=copy.deepcopy(self.bundle);bad['report-appearances.json'][0]['player_name']='Unknown Player'
        with self.assertRaisesRegex(DataError,'name mismatch'):full.build_details(bad,self.manifest,self.corrections)

    def test_source_player_id_collision_is_rejected(self):
        bad=copy.deepcopy(self.bundle);rows=bad['report-appearances.json'];rows[1]['player_id']=rows[0]['player_id']
        with self.assertRaisesRegex(DataError,'ID collision'):full.build_details(bad,self.manifest,self.corrections)

    def test_missing_starter_is_rejected(self):
        bad=copy.deepcopy(self.bundle);bad['report-appearances.json'].pop(0)
        with self.assertRaisesRegex(DataError,'Starting XI'):full.build_details(bad,self.manifest,self.corrections)

    def test_statistics_cannot_reference_another_appearance(self):
        bad=copy.deepcopy(self.bundle);bad['report-player_stats.json'][0]['appearance_id']='wrong'
        with self.assertRaisesRegex(DataError,'Unlinked'):full.build_details(bad,self.manifest,self.corrections)

    def test_own_goals_are_not_counted_as_player_goals(self):
        self.assertEqual(sum(r['goals'] for r in self.data['player_stats']),294)
        self.assertEqual(sum(r['type']=='own_goal' for r in self.data['events']),14)
        self.assertEqual(sum(r['goals'] for r in self.data['team_stats']),308)

    def test_unused_player_can_receive_bench_card(self):
        self.assertEqual(len(self.data['report']['bench_cards']),1)
        self.assertEqual(self.data['report']['bench_cards'][0]['event_type'],'yellow_card')
        appearances={(r['match_id'],r['player_id']):r for r in self.data['appearances']}
        self.assertEqual(sum(not appearances[(r['match_id'],r['player_id'])]['appeared'] for r in self.data['events']),1)

    def test_short_match_squads_are_not_filled_with_fake_rows(self):
        self.assertEqual(len(self.data['report']['shorter_match_squads']),13)
        self.assertEqual(len(self.data['appearances']),5392)

    def test_missing_assists_minutes_stay_null(self):
        self.assertTrue(all(r['assists'] is None and r['minutes_played'] is None for r in self.data['player_stats']))

    def test_negative_or_invalid_metrics_are_rejected(self):
        bad=copy.deepcopy(self.bundle);bad['report-player_stats.json'][0]['pass_completion_pct']='NaN'
        with self.assertRaises(DataError):full.build_details(bad,self.manifest,self.corrections)

    def test_duplicate_events_are_rejected(self):
        bad=copy.deepcopy(self.bundle);bad['report-events.json'].append(bad['report-events.json'][0])
        with self.assertRaisesRegex(DataError,'Duplicate event'):full.build_details(bad,self.manifest,self.corrections)

    def test_statistic_goal_disagreement_is_rejected(self):
        bad=copy.deepcopy(self.bundle);bad['report-player_stats.json'][0]['goals']='99'
        with self.assertRaisesRegex(DataError,'goal statistic'):full.build_details(bad,self.manifest,self.corrections)


if __name__=='__main__': unittest.main()
