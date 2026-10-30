<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ForecastPoint extends Model
{
    public $timestamps = false;

    protected $fillable = ['forecast_date', 'kwh', 'peak_kw'];

    protected function casts(): array
    {
        return ['forecast_date' => 'date'];
    }

    public function forecastRun(): BelongsTo
    {
        return $this->belongsTo(ForecastRun::class);
    }
}
