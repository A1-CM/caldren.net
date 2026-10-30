<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationalAlert extends Model
{
    public const STATUSES = ['open', 'acknowledged', 'resolved'];

    protected $fillable = ['organization_id', 'building_id', 'forecast_run_id', 'type', 'severity', 'title', 'message', 'fingerprint', 'status', 'detected_at', 'acknowledged_by', 'acknowledged_at', 'resolved_by', 'resolved_at'];

    protected function casts(): array
    {
        return ['detected_at' => 'datetime', 'acknowledged_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function forecastRun(): BelongsTo
    {
        return $this->belongsTo(ForecastRun::class);
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
