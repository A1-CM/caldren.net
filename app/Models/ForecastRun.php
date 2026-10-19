<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ForecastRun extends Model
{
    protected $fillable = ['building_id', 'status', 'model', 'input_started_at', 'input_ended_at', 'baseline_daily_kwh', 'historical_peak_kw', 'failure_reason'];

    protected function casts(): array
    {
        return ['input_started_at' => 'datetime', 'input_ended_at' => 'datetime'];
    }

    public function points(): HasMany
    {
        return $this->hasMany(ForecastPoint::class)->orderBy('forecast_date');
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(Recommendation::class);
    }
}
