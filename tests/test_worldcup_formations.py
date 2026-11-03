"""FOURWARD coursework | Author: 韩羽宸 | Source formation identity and corrupted coordinate rejection."""
import copy
import json
import sys
import unittest
from pathlib import Path
sys.path.insert(0,str(Path(__file__).resolve().parents[1]/'scripts'))
from worldcup_formations import ROOT,coordinate,verify_side


class FormationChecks(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.core=json.loads((ROOT/'data/worldcup/full/complete.json').read_text(encoding='utf-8'))
        cls.snapshot=json.loads((ROOT/'data/worldcup/formations.json').read_text(encoding='utf-8'))
        cls.native=json.loads((ROOT/'data/worldcup/player-photos/headshots.json').read_text(encoding='utf-8'))['players']

    def test_full_edition_and_exact_starters(self):
        matches={m['id']:m for m in self.core['matches']};players={p['id']:p for p in self.core['players']}
        self.assertEqual(set(matches),set(self.snapshot['matches']))
        for mid,entry in self.snapshot['matches'].items():
            for side,t in entry['sides'].items():
                with self.subTest(mid=mid,side=side):
                    self.assertEqual(t['team_id'],matches[mid][side+'_team_id'])
                    expected={a['player_id'] for a in self.core['appearances'] if a['match_id']==mid and a['starting'] and players[a['player_id']]['team_id']==t['team_id']}
                    self.assertEqual(expected,{p['player_id'] for p in t['starters']})
                    self.assertEqual(len(t['starters']),11)
                    self.assertEqual(sum(map(int,t['formation'].split('-'))),10)
                    self.assertEqual(sum(players[p['player_id']]['position']=='GK' for p in t['starters']),1)
                    self.assertLessEqual(sum(p['captain'] for p in t['starters']),1)
                    for p in t['starters']:
                        self.assertEqual(p['shirt_number'],players[p['player_id']]['shirt_number'])
                        self.assertEqual(coordinate(p['x']),p['x']);self.assertEqual(coordinate(p['y']),p['y'])

    def source(self):
        mid,entry=next(iter(self.snapshot['matches'].items()));side=entry['sides']['home']
        raw={'formation':side['formation'],'lineups':[{'shirtnumber':p['shirt_number'],'person_id':p['dqd_id'],'position_x':p['x'],'position_y':p['y'],'person':p['source_name'],'captain':p['captain']} for p in side['starters']]}
        return raw,side['team_id'],mid

    def test_bad_coordinates(self):
        for value in [True,None,'nan','1; color:red',-1,0,100,101,float('inf')]:
            with self.subTest(value=value),self.assertRaises(ValueError):coordinate(value)

    def test_wrong_identity_and_duplicate(self):
        for mutation in ['shirt','external','duplicate','missing','formation']:
            raw,team,mid=self.source()
            if mutation=='shirt':raw['lineups'][0]['shirtnumber']=99
            elif mutation=='external':raw['lineups'][0]['person_id']='999999999'
            elif mutation=='duplicate':raw['lineups'][0]=copy.deepcopy(raw['lineups'][1])
            elif mutation=='missing':raw['lineups'].pop()
            else:raw['formation']='4-4-4'
            with self.subTest(mutation=mutation),self.assertRaises(ValueError):verify_side(raw,team,mid,self.core,self.native)


if __name__=='__main__':unittest.main()
