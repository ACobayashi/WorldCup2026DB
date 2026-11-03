"""Offline acceptance tests for the real-data pilot and corrupted-source guards."""
import copy
import json
import shutil
import sys
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / 'scripts'))
import worldcup_data as data


class WorldCupSampleTests(unittest.TestCase):
    def setUp(self):
        self.bundle = data.load_bundle(data.DEFAULT_SAMPLE)

    def test_real_snapshot_build_is_reproducible(self):
        output, report = data.clean(self.bundle)
        self.assertEqual(data.encode_json(output), (data.DEFAULT_SAMPLE / 'sample.json').read_bytes())
        self.assertEqual(data.encode_json(report), (data.DEFAULT_SAMPLE / 'validation-report.json').read_bytes())
        self.assertEqual(report['counts']['players'], 26)
        self.assertEqual(report['counts']['matches'], 2)
        self.assertEqual(report['counts']['appearances'], 52)
        self.assertEqual(report['counts']['player_stats'], 32)
        self.assertFalse(report['ready_for_database_import'])

    def test_core_fields_exclude_unrequested_personal_details(self):
        output, _ = data.clean(self.bundle)
        for player in output['players']:
            self.assertEqual(set(player), {'id', 'name', 'team_id', 'shirt_number', 'position'})
        raw = json.dumps(self.bundle['fifa-squad.json'])
        for key in ['BirthDate', 'Height', 'Weight', 'PlayerPicture']:
            self.assertNotIn(key, raw)

    def test_normalizes_accents_hyphens_and_pdf_ligatures(self):
        self.assertEqual(data.normalized_name('Raﬁk BELGHALI'), data.normalized_name('Rafik Belghali'))
        self.assertEqual(data.normalized_name('Rayan Aït-Nouri'), data.normalized_name('Rayan AIT-NOURI'))
        self.assertNotEqual(data.normalized_name('Yacine Titraoui'), data.normalized_name('Yassine Titraoui'))

    def test_conflicting_name_is_not_mapped_by_number_alone(self):
        output, report = data.clean(self.bundle)
        self.assertEqual(len(report['review_queue']), 1)
        self.assertEqual(report['review_queue'][0]['source_key'], 'ALG/24')
        self.assertNotIn('ALG/24', [m['source_key'] for m in output['player_mappings'] if m['provider'] == 'openfootball'])

    def test_csv_player_ids_are_all_verified(self):
        output, _ = data.clean(self.bundle)
        canonical = {p['id'] for p in output['players']}
        mapped = [m for m in output['player_mappings'] if m['provider'] == 'worldcup26']
        self.assertEqual(len(mapped), 26)
        self.assertTrue(all(m['player_id'] in canonical for m in mapped))

    def test_known_bad_control_is_outside_accepted_matches(self):
        output, report = data.clean(self.bundle)
        self.assertEqual({m['match_no'] for m in output['matches']}, {19, 44})
        control = report['excluded_control_matches'][0]
        self.assertFalse(control['accepted'])
        self.assertFalse(control['team_codes_match'])
        self.assertEqual(control['expected'], ['POR', 'UZB'])
        self.assertEqual(control['observed'], ['POR', 'GHA'])

    def test_bad_control_would_fail_if_promoted_to_pilot(self):
        with patch.dict(data.SCOPE, {'match_numbers': [19, 47], 'control_match_numbers': [44]}):
            with self.assertRaisesRegex(data.DataError, 'team-code mismatch'):
                data.clean(self.bundle)

    def test_changed_edition_is_rejected(self):
        self.bundle['fifa-squad.json']['IdSeason'] = '255711'
        with self.assertRaisesRegex(data.DataError, 'edition'):
            data.clean(self.bundle)

    def test_duplicate_squad_number_is_rejected(self):
        rows = self.bundle['fifa-squad.json']['Players']
        rows[1]['JerseyNum'] = rows[0]['JerseyNum']
        with self.assertRaisesRegex(data.DataError, 'squad number'):
            data.clean(self.bundle)

    def test_changed_player_name_is_not_silently_joined(self):
        self.bundle['appearances.json'][0]['player_name'] = 'Different Person'
        with self.assertRaisesRegex(data.DataError, 'identity'):
            data.clean(self.bundle)

    def test_foreign_team_is_rejected(self):
        self.bundle['player-stats.json'][0]['team_id'] = 'ARG'
        with self.assertRaisesRegex(data.DataError, 'outside pilot'):
            data.clean(self.bundle)

    def test_score_disagreement_is_rejected(self):
        self.bundle['report-matches.json'][0]['home_score'] = '99'
        with self.assertRaisesRegex(data.DataError, 'score mismatch'):
            data.clean(self.bundle)

    def test_utc_is_authoritative_over_unzoned_report_time(self):
        self.bundle['report-matches.json'][0]['kickoff'] = '23:59'
        output, _ = data.clean(self.bundle)
        match = next(m for m in output['matches'] if m['match_no'] == 19)
        self.assertEqual(match['kickoff_at'], '2026-06-17T01:00:00Z')
        self.assertEqual(match['kickoff_beijing'], '2026-06-17T09:00:00+08:00')

    def test_timezone_free_official_time_is_rejected(self):
        self.bundle['fifa-matches.json'][0]['Date'] = '2026-06-17T01:00:00'
        with self.assertRaisesRegex(data.DataError, 'timezone'):
            data.clean(self.bundle)

    def test_duplicate_appearance_is_rejected(self):
        self.bundle['appearances.json'].append(copy.deepcopy(self.bundle['appearances.json'][0]))
        with self.assertRaisesRegex(data.DataError, 'Duplicate match/player'):
            data.clean(self.bundle)

    def test_missing_participant_stat_is_rejected(self):
        self.bundle['player-stats.json'].pop()
        with self.assertRaisesRegex(data.DataError, 'participant statistics'):
            data.clean(self.bundle)

    def test_unknown_values_stay_null_and_real_zero_is_preserved(self):
        output, _ = data.clean(self.bundle)
        self.assertTrue(all(r['minutes_played'] is None and r['assists'] is None for r in output['player_stats']))
        self.assertTrue(any(r['goals'] == 0 for r in output['player_stats']))
        self.assertIsNone(data.integer(''))
        self.assertEqual(data.integer('0'), 0)

    def test_statistic_units_and_ranges(self):
        self.assertEqual(data.pair('7 (1)'), (7, 1))
        self.assertEqual(data.percentage('50.8%'), 50.8)
        for value in ['-1', '101', 'NaN', 'inf']:
            with self.assertRaises(data.DataError):
                data.percentage(value)
        with self.assertRaises(data.DataError):
            data.pair('3 (7)')

    def test_stoppage_time_is_preserved(self):
        self.assertEqual(data.event_minute("90+2'"), {'minute': 90, 'added_minute': 2, 'label': "90+2'"})
        with self.assertRaises(data.DataError):
            data.event_minute('90.2')

    def test_duplicate_event_is_rejected(self):
        self.bundle['events.json'].append(copy.deepcopy(self.bundle['events.json'][0]))
        with self.assertRaisesRegex(data.DataError, 'Duplicate event'):
            data.clean(self.bundle)

    def test_missing_team_metric_is_rejected(self):
        self.bundle['team-stats.json'].pop()
        with self.assertRaisesRegex(data.DataError, 'Missing team metrics'):
            data.clean(self.bundle)

    def test_checksum_detects_modified_snapshot(self):
        with tempfile.TemporaryDirectory(prefix='worldcup-data-test-') as directory:
            target = Path(directory) / 'sample'
            shutil.copytree(data.DEFAULT_SAMPLE, target)
            with (target / 'raw/fifa-squad.json').open('ab') as stream:
                stream.write(b' ')
            with self.assertRaisesRegex(data.DataError, 'checksum'):
                data.load_bundle(target)


if __name__ == '__main__':
    unittest.main()
