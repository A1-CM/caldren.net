<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Building extends Model
{
    protected $fillable = ['organization_id', 'name', 'location', 'timezone', 'currency', 'energy_rate', 'peak_rate', 'peak_start_hour', 'peak_end_hour'];

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
}
