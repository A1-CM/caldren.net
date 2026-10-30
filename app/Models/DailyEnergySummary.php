<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyEnergySummary extends Model
{
    protected $fillable = ['building_id', 'summary_date', 'kwh', 'peak_kw', 'interval_minutes', 'reading_count', 'expected_count', 'coverage_percent', 'first_reading_at', 'last_reading_at'];

    protected function casts(): array
    {
        return ['summary_date' => 'date', 'first_reading_at' => 'datetime', 'last_reading_at' => 'datetime'];
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }
}
