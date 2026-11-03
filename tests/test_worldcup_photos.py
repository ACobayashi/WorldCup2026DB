"""FOURWARD coursework | Author: 韩羽宸 | Portrait identity and download boundary regression checks."""
import io
import json
import sys
import unittest
from pathlib import Path
from PIL import Image
sys.path.insert(0, str(Path(__file__).resolve().parents[1]/'scripts'))
from worldcup_photos import DATA, validated_url, approved_download, inspect_image, safe_file
from worldcup_headshots import headshot_url


class PortraitChecks(unittest.TestCase):
    def test_wrong_fifa_identity_rejected(self):
        url='https://digitalhub.fifa.com/transform/937ed103-c12b-4931-83f1-ee615a67858e/HASSAN-ALTAMBAKTI_403335'
        with self.assertRaises(ValueError): validated_url(url,'486259','Mohamed KONE')

    def test_older_id_requires_exact_full_name(self):
        url='https://digitalhub.fifa.com/transform/f71b54fc-4cf8-463c-b8cc-7837e3d9eca8/FRUK-Toni_390197'
        self.assertIn('format=webp',validated_url(url,'485065','Toni FRUK'))
        with self.assertRaises(ValueError): validated_url(url,'485065','Other FRUK')

    def test_network_destinations(self):
        for url in ['file:///etc/passwd','http://127.0.0.1/a.jpg','https://sd.qunliao.info.evil/a.jpg','https://user@sd.qunliao.info/a.jpg']:
            with self.subTest(url=url),self.assertRaises(ValueError): headshot_url(url)
        with self.assertRaises(ValueError): approved_download({'source_url':'https://evil/a','download_url':'https://evil/a'})

    def test_cache_traversal(self):
        for value in ['../1.jpg','1.svg','1.jpg/../../x','1.jpg?foo=bar']:
            with self.subTest(value=value),self.assertRaises(ValueError): safe_file(value)

    def test_decoding_bounds(self):
        buf=io.BytesIO();Image.new('RGB',(150,150)).save(buf,format='JPEG')
        self.assertEqual(inspect_image(buf.getvalue())['width'],150)
        for payload in [b'<html>Error</html>',b'x'*2_000_001,buf.getvalue()[:20]]:
            with self.subTest(size=len(payload)),self.assertRaises((ValueError,OSError)): inspect_image(payload)

    def test_snapshot_identity_and_coverage(self):
        catalog=json.loads((DATA/'catalog.json').read_text(encoding='utf-8'))
        native=json.loads((DATA/'headshots.json').read_text(encoding='utf-8'))
        core=json.loads((DATA.parent/'full/core.json').read_text(encoding='utf-8'))
        canonical={p['id'].split(':')[-1]:p for p in core['players']}
        self.assertEqual(set(catalog['players']),set(canonical))
        self.assertEqual(len(native['players']),1238)
        external=set()
        for pid,entry in native['players'].items():
            self.assertEqual(entry['name'],canonical[pid]['name'])
            self.assertEqual(entry['team_id'],canonical[pid]['team_id'])
            self.assertEqual(entry['shirt_number'],canonical[pid]['shirt_number'])
            self.assertNotIn(entry['dqd_id'],external);external.add(entry['dqd_id'])
            self.assertGreaterEqual(len(set(entry['evidence_matches'])),2)
            self.assertTrue(set(entry['evidence_matches'])<=set(native['sources']))
            self.assertEqual(headshot_url(entry['source_url']),catalog['players'][pid]['download_url'])
        self.assertEqual(catalog['players']['448196']['kind'],'headshot')
        self.assertEqual(catalog['players']['486259']['kind'],'headshot')


if __name__=='__main__':unittest.main()
