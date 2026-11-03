"""FOURWARD coursework | Author: 韩羽宸 | Extension identity, coverage and count guards."""
import copy,json,sys,unittest
from pathlib import Path
sys.path.insert(0,str(Path(__file__).resolve().parents[1]/'scripts'))
import worldcup_player_metrics as metrics
from worldcup_data import DataError,encode_json
from worldcup_full import load,DIRECTORY

class PlayerMetricTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.bundle,cls.core_manifest=load()
        cls.corrections=json.loads((DIRECTORY/'corrections.json').read_text(encoding='utf-8'))
        cls.manifest=json.loads((metrics.TARGET/'manifest.json').read_text(encoding='utf-8'))
        cls.raw={k:json.loads((metrics.TARGET/'raw'/f'{k}.json').read_text(encoding='utf-8')) for k in ['attack','defence']}
    def build(self,raw):return metrics.build(raw,self.manifest,self.bundle,self.core_manifest,self.corrections)
    def test_reproducible_full_extension(self):
        result=self.build(self.raw)
        self.assertEqual(encode_json(result),(metrics.TARGET/'metrics.json').read_bytes())
        self.assertEqual(len(result['rows']),3288)
        self.assertEqual(len(result['fields']),17)
    def test_unreviewed_identity_is_rejected(self):
        bad=copy.deepcopy(self.raw);bad['defence'][0]['player_name']='Other'
        with self.assertRaisesRegex(DataError,'identity'):self.build(bad)
    def test_duplicate_rows_rejected(self):
        bad=copy.deepcopy(self.raw);bad['defence'].append(bad['defence'][0])
        with self.assertRaisesRegex(DataError,'Duplicate'):self.build(bad)
    def test_missing_rows_rejected(self):
        bad=copy.deepcopy(self.raw);bad['attack'].pop()
        with self.assertRaisesRegex(DataError,'coverage'):self.build(bad)
    def test_bad_tackle_pair_rejected(self):
        bad=copy.deepcopy(self.raw);bad['defence'][0]['tackles_made_won']='1 / 2'
        with self.assertRaisesRegex(DataError,'tackles'):self.build(bad)
    def test_negative_count_rejected(self):
        bad=copy.deepcopy(self.raw);bad['defence'][0]['interceptions']='-1'
        with self.assertRaises(DataError):self.build(bad)
    def test_missing_not_zero(self):
        bad=copy.deepcopy(self.raw);bad['defence'][0]['blocks']=''
        result=self.build(bad)
        self.assertIsNone(result['rows'][0]['metrics']['blocks'])

if __name__=='__main__':unittest.main()
