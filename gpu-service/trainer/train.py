from __future__ import annotations

import argparse
import json
import math
from collections import defaultdict
from pathlib import Path

import cudf
import cupy as cp
from cuml.ensemble import RandomForestRegressor

FEATURE_COUNT = 30


def load_series(path: Path) -> dict[str, list[dict[str, float | int]]]:
    frame = cudf.read_csv(path)
    required = {'series_id', 'day_index', 'weekday', 'daily_kwh', 'peak_kw'}
    if set(frame.columns) != required:
        raise ValueError('Training CSV must contain only de-identified daily energy fields.')
    if frame[['daily_kwh', 'peak_kw']].isnull().any().any():
        raise ValueError('Training data contains missing energy values.')
    if (frame[['daily_kwh', 'peak_kw']] < 0).any().any():
        raise ValueError('Training data contains negative energy values.')
    frame['series_id'] = frame['series_id'].astype('str')
    rows = frame.to_pandas().sort_values(['series_id', 'day_index'])
    series: dict[str, list[dict[str, float | int]]] = defaultdict(list)
    for row in rows.itertuples(index=False):
        series[str(row.series_id)].append({
            'day_index': int(row.day_index),
            'weekday': int(row.weekday),
            'daily_kwh': float(row.daily_kwh),
            'peak_kw': float(row.peak_kw),
        })
    return dict(series)


def make_walk_forward_rows(series: dict[str, list[dict[str, float | int]]]) -> tuple[list[list[float]], list[float], list[float], list[list[float]], list[float], list[float], list[float], list[float]]:
    train_x: list[list[float]] = []
    train_kwh: list[float] = []
    train_peak: list[float] = []
    test_x: list[list[float]] = []
    test_kwh: list[float] = []
    test_peak: list[float] = []
    seasonal_kwh: list[float] = []
    seasonal_peak: list[float] = []

    for days in series.values():
        if len(days) < 35:
            continue
        for target_index in range(14, len(days)):
            if any(int(days[i]['day_index']) + 1 != int(days[i + 1]['day_index']) for i in range(target_index - 14, target_index)):
                continue
            target_day = days[target_index]
            history = days[target_index - 14:target_index]
            for horizon in range(1, 8):
                forecast_day_index = int(target_day['day_index']) + horizon - 1
                if target_index + horizon - 1 >= len(days):
                    break
                label = days[target_index + horizon - 1]
                if int(label['day_index']) != forecast_day_index:
                    break
                features = [float(value) for day in history for value in (day['daily_kwh'], day['peak_kw'])]
                features.extend([float(horizon), float(label['weekday'])])
                if len(features) != FEATURE_COUNT:
                    raise ValueError('Unexpected feature width.')
                # A temporal holdout: final fifth of each series is reserved for walk-forward evaluation.
                cutoff = max(28, math.floor(len(days) * 0.8))
                if target_index + horizon - 1 < cutoff:
                    train_x.append(features)
                    train_kwh.append(float(label['daily_kwh']))
                    train_peak.append(float(label['peak_kw']))
                else:
                    test_x.append(features)
                    test_kwh.append(float(label['daily_kwh']))
                    test_peak.append(float(label['peak_kw']))
                    seasonal_index = target_index + horizon - 1 - 7
                    if seasonal_index < 0:
                        seasonal_kwh.append(float('nan'))
                        seasonal_peak.append(float('nan'))
                    else:
                        seasonal_kwh.append(float(days[seasonal_index]['daily_kwh']))
                        seasonal_peak.append(float(days[seasonal_index]['peak_kw']))

    return train_x, train_kwh, train_peak, test_x, test_kwh, test_peak, seasonal_kwh, seasonal_peak


def mae(actual: cp.ndarray, predicted: cp.ndarray) -> float:
    return float(cp.mean(cp.abs(actual - predicted)).get())


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument('--input', type=Path, required=True)
    parser.add_argument('--version', required=True)
    parser.add_argument('--model-repository', type=Path, default=Path('/output/candidate-model-repository'))
    parser.add_argument('--metrics', type=Path, default=Path('/output/evaluation.json'))
    args = parser.parse_args()

    series = load_series(args.input)
    train_x, train_kwh, train_peak, test_x, test_kwh, test_peak, seasonal_kwh, seasonal_peak = make_walk_forward_rows(series)
    if len(train_x) < 100 or len(test_x) < 20:
        raise SystemExit('Insufficient continuous opted-in history for a walk-forward evaluation.')

    train_features = cp.asarray(train_x, dtype=cp.float32)
    test_features = cp.asarray(test_x, dtype=cp.float32)
    model_kwh = RandomForestRegressor(n_estimators=300, max_depth=12, random_state=29, n_streams=1)
    model_peak = RandomForestRegressor(n_estimators=300, max_depth=12, random_state=31, n_streams=1)
    model_kwh.fit(train_features, cp.asarray(train_kwh, dtype=cp.float32))
    model_peak.fit(train_features, cp.asarray(train_peak, dtype=cp.float32))
    predicted_kwh = model_kwh.predict(test_features)
    predicted_peak = model_peak.predict(test_features)
    actual_kwh = cp.asarray(test_kwh, dtype=cp.float32)
    actual_peak = cp.asarray(test_peak, dtype=cp.float32)
    baseline_kwh = cp.asarray(seasonal_kwh, dtype=cp.float32)
    baseline_peak = cp.asarray(seasonal_peak, dtype=cp.float32)
    valid_kwh = cp.isfinite(baseline_kwh)
    valid_peak = cp.isfinite(baseline_peak)
    candidate_kwh_mae = mae(actual_kwh, predicted_kwh)
    candidate_peak_mae = mae(actual_peak, predicted_peak)
    seasonal_kwh_mae = mae(actual_kwh[valid_kwh], baseline_kwh[valid_kwh])
    seasonal_peak_mae = mae(actual_peak[valid_peak], baseline_peak[valid_peak])

    metrics = {
        'version': args.version,
        'method': 'per-series walk-forward temporal holdout; 7-day seasonal naive baseline',
        'training_rows': len(train_x),
        'evaluation_rows': len(test_x),
        'candidate_daily_kwh_mae': candidate_kwh_mae,
        'seasonal_daily_kwh_mae': seasonal_kwh_mae,
        'candidate_peak_kw_mae': candidate_peak_mae,
        'seasonal_peak_kw_mae': seasonal_peak_mae,
        'approved': candidate_kwh_mae < seasonal_kwh_mae and candidate_peak_mae < seasonal_peak_mae,
    }
    args.metrics.parent.mkdir(parents=True, exist_ok=True)
    args.metrics.write_text(json.dumps(metrics, indent=2) + '\n')
    if not metrics['approved']:
        raise SystemExit('Candidate did not beat the seasonal baseline on both metrics; no model artifacts exported.')

    for name, model in [('caldren_kwh', model_kwh), ('caldren_peak', model_peak)]:
        destination = args.model_repository / name / '1' / 'checkpoint.tl'
        destination.parent.mkdir(parents=True, exist_ok=True)
        model.as_treelite_checkpoint(str(destination))
    print(json.dumps(metrics, indent=2))
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
