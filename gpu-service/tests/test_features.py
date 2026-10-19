import unittest
from datetime import date, timedelta

from api.features import build_horizon_features


class ForecastFeaturesTest(unittest.TestCase):
    def setUp(self):
        start = date(2026, 1, 1)
        self.history = [
            {'date': (start + timedelta(days=index)).isoformat(), 'kwh': 100 + index, 'peak_kw': 8 + index / 10}
            for index in range(14)
        ]

    def test_builds_seven_days_of_30_feature_rows(self):
        rows, dates = build_horizon_features(self.history)
        self.assertEqual(7, len(rows))
        self.assertTrue(all(len(row) == 30 for row in rows))
        self.assertEqual('2026-01-15', dates[0])
        self.assertEqual('2026-01-21', dates[-1])
        self.assertEqual([1.0, 4.0], rows[0][-2:])

    def test_rejects_a_gap_in_history(self):
        self.history[7]['date'] = '2026-01-09'
        with self.assertRaisesRegex(ValueError, 'consecutive'):
            build_horizon_features(self.history)

    def test_requires_exactly_fourteen_days(self):
        with self.assertRaisesRegex(ValueError, 'Exactly 14'):
            build_horizon_features(self.history[:-1])


if __name__ == '__main__':
    unittest.main()
