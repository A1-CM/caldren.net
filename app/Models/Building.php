<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Building extends Model
{
    protected $fillable = ['organization_id', 'name', 'location', 'timezone', 'currency', 'energy_rate', 'peak_rate', 'peak_start_hour', 'peak_end_hour', 'forecast_peak_threshold_percent', 'bill_change_threshold_percent', 'coverage_alert_threshold_percent', 'stale_after_hours', 'alerts_enabled'];

    protected function casts(): array
    {
        return ['alerts_enabled' => 'boolean'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function readings(): HasMany
    {
        return $this->hasMany(UsageReading::class);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(UtilityBill::class);
    }

    public function forecasts(): HasMany
    {
        return $this->hasMany(ForecastRun::class);
    }

    public function dailySummaries(): HasMany
    {
        return $this->hasMany(DailyEnergySummary::class);
    }

    public function dataImports(): HasMany
    {
        return $this->hasMany(DataImport::class);
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(OperationalAlert::class);
    }
}
