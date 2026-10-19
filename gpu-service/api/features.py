from datetime import date, timedelta
from typing import Any


def build_horizon_features(history: list[dict[str, Any]]) -> tuple[list[list[float]], list[str]]:
    if len(history) != 14:
        raise ValueError('Exactly 14 daily history rows are required.')

    parsed_dates = [date.fromisoformat(str(row['date'])) for row in history]
    if parsed_dates != sorted(parsed_dates) or any(
        (parsed_dates[index] - parsed_dates[index - 1]).days != 1
        for index in range(1, len(parsed_dates))
    ):
        raise ValueError('History dates must be consecutive and chronological.')

    features: list[list[float]] = []
    forecast_dates: list[str] = []
    for horizon in range(1, 8):
        forecast_date = parsed_dates[-1] + timedelta(days=horizon)
        row: list[float] = []
        for history_day in history:
            row.extend([float(history_day['kwh']), float(history_day['peak_kw'])])
        row.extend([float(horizon), float(forecast_date.weekday() + 1)])
        features.append(row)
        forecast_dates.append(forecast_date.isoformat())

    return features, forecast_dates
