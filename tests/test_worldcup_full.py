"""FOURWARD coursework | Author: 韩羽宸 | Full-edition provenance and identity guards."""
import copy
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


if __name__=='__main__': unittest.main()
