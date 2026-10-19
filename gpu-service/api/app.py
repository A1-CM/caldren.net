from __future__ import annotations

import os
import secrets
from datetime import date
from typing import Annotated, Any

import numpy as np
import tritonclient.http as httpclient
from fastapi import FastAPI, Header, HTTPException
from pydantic import BaseModel, ConfigDict, Field

try:
    from .features import build_horizon_features
except ImportError:
    from features import build_horizon_features


class HistoryDay(BaseModel):
    model_config = ConfigDict(extra='forbid')
    date: date
    kwh: float = Field(gt=0)
    peak_kw: float = Field(ge=0)


class ForecastRequest(BaseModel):
    model_config = ConfigDict(extra='forbid')
    model_version: str = Field(min_length=1, max_length=80)
    building_timezone: str = Field(min_length=1, max_length=64)
    interval_minutes: int = Field(ge=15, le=60)
    historical_days: list[HistoryDay] = Field(min_length=14, max_length=14)
    baseline_daily_kwh: float = Field(gt=0)
    historical_peak_kw: float = Field(gt=0)
    energy_rate: float | None = Field(default=None, ge=0)
    peak_rate: float | None = Field(default=None, ge=0)
    peak_hours: list[int | None] = Field(min_length=2, max_length=2)


class ForecastPoint(BaseModel):
    date: date
    kwh: float = Field(gt=0)
    peak_kw: float = Field(ge=0)


class ForecastResponse(BaseModel):
    model_version: str
    days: list[ForecastPoint] = Field(min_length=7, max_length=7)


def create_app(triton_client: Any | None = None) -> FastAPI:
    application = FastAPI(title='Caldren GPU Forecast Gateway', version='1.0.0')
    application.state.triton = triton_client or httpclient.InferenceServerClient(
        url=os.environ.get('TRITON_URL', 'triton:8000'),
        connection_timeout=3.0,
        network_timeout=8.0,
    )
    application.state.api_token = os.environ.get('CALDREN_GPU_API_TOKEN', '')
    application.state.model_version = os.environ.get('CALDREN_GPU_MODEL_VERSION', '')

    def authorize(authorization: str | None) -> None:
        expected = application.state.api_token
        supplied = authorization.removeprefix('Bearer ').strip() if authorization else ''
        if not expected or not secrets.compare_digest(supplied, expected):
            raise HTTPException(status_code=401, detail='Unauthorized')

    @application.get('/healthz')
    def health(authorization: Annotated[str | None, Header()] = None) -> dict[str, Any]:
        authorize(authorization)
        model_version = application.state.model_version
        try:
            ready = bool(model_version) and application.state.triton.is_server_live() and all(
                application.state.triton.is_model_ready(model_name)
                for model_name in ('caldren_kwh', 'caldren_peak')
            )
        except Exception:
            ready = False
        return {'ready': ready, 'model_version': model_version if ready else None}

    @application.post('/v1/forecast', response_model=ForecastResponse)
    def forecast(
        request: ForecastRequest,
        authorization: Annotated[str | None, Header()] = None,
    ) -> dict[str, Any]:
        authorize(authorization)
        if request.model_version != application.state.model_version:
            raise HTTPException(status_code=409, detail='Requested model version is not active.')
        if not application.state.model_version:
            raise HTTPException(status_code=503, detail='No forecast model is active.')

        history = [day.model_dump(mode='json') for day in request.historical_days]
        try:
            feature_rows, forecast_dates = build_horizon_features(history)
        except (KeyError, TypeError, ValueError) as error:
            raise HTTPException(status_code=422, detail='History must contain 14 consecutive daily rows.') from error
        input_tensor = np.asarray(feature_rows, dtype=np.float32)
        kwh = _infer(application.state.triton, 'caldren_kwh', input_tensor)
        peak = _infer(application.state.triton, 'caldren_peak', input_tensor)
        days = [
            {'date': forecast_dates[index], 'kwh': max(float(kwh[index]), 0.0), 'peak_kw': max(float(peak[index]), 0.0)}
            for index in range(7)
        ]
        return {'model_version': application.state.model_version, 'days': days}

    return application


def _infer(client: Any, model_name: str, input_tensor: np.ndarray) -> np.ndarray:
    model_input = httpclient.InferInput('input__0', input_tensor.shape, 'FP32')
    model_input.set_data_from_numpy(input_tensor)
    result = client.infer(
        model_name=model_name,
        inputs=[model_input],
        outputs=[httpclient.InferRequestedOutput('output__0')],
    )
    values = np.asarray(result.as_numpy('output__0'), dtype=np.float32).reshape(-1)
    if values.shape != (7,) or not np.isfinite(values).all():
        raise HTTPException(status_code=502, detail='The forecast model returned invalid values.')
    return values


app = create_app()
