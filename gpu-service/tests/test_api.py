import unittest
from datetime import date, timedelta
from unittest.mock import Mock

import numpy as np
from fastapi.testclient import TestClient

from api.app import create_app


class FakeInferenceResult:
    def as_numpy(self, name):
        return np.ones((7, 1), dtype=np.float32) * 100 if name == 'output__0' else None


class GpuForecastApiTest(unittest.TestCase):
    def setUp(self):
        self.triton = Mock()
        self.triton.is_server_live.return_value = True
        self.triton.is_model_ready.return_value = True
        self.triton.infer.return_value = FakeInferenceResult()
        self.app = create_app(self.triton)
        self.app.state.api_token = 'test-secret'
        self.app.state.model_version = 'rf-1'
        self.client = TestClient(self.app)
        start = date(2026, 1, 1)
        self.payload = {
            'model_version': 'rf-1',
            'building_timezone': 'UTC',
            'interval_minutes': 15,
            'historical_days': [
                {'date': (start + timedelta(days=index)).isoformat(), 'kwh': 100 + index, 'peak_kw': 8 + index / 10}
                for index in range(14)
            ],
            'baseline_daily_kwh': 110,
            'historical_peak_kw': 10,
            'energy_rate': None,
            'peak_rate': None,
            'peak_hours': [None, None],
        }

    def test_authenticated_inference_returns_numeric_estimates(self):
        response = self.client.post('/v1/forecast', json=self.payload, headers={'Authorization': 'Bearer test-secret'})
        self.assertEqual(200, response.status_code)
        self.assertEqual('rf-1', response.json()['model_version'])
        self.assertEqual(7, len(response.json()['days']))
        self.assertEqual('2026-01-15', response.json()['days'][0]['date'])
        self.assertEqual(100, response.json()['days'][0]['kwh'])
        self.assertEqual(2, self.triton.infer.call_count)

    def test_rejects_wrong_model_or_missing_authorization(self):
        self.assertEqual(401, self.client.post('/v1/forecast', json=self.payload).status_code)
        self.payload['model_version'] = 'wrong'
        response = self.client.post('/v1/forecast', json=self.payload, headers={'Authorization': 'Bearer test-secret'})
        self.assertEqual(409, response.status_code)

    def test_rejects_nonconsecutive_history_and_invalid_model_output(self):
        self.payload['historical_days'][7]['date'] = '2026-01-09'
        response = self.client.post('/v1/forecast', json=self.payload, headers={'Authorization': 'Bearer test-secret'})
        self.assertEqual(422, response.status_code)

        self.setUp()
        invalid_result = Mock()
        invalid_result.as_numpy.return_value = np.ones((3, 1), dtype=np.float32)
        self.triton.infer.return_value = invalid_result
        response = self.client.post('/v1/forecast', json=self.payload, headers={'Authorization': 'Bearer test-secret'})
        self.assertEqual(502, response.status_code)


if __name__ == '__main__':
    unittest.main()
