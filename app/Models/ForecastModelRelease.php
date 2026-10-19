<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ForecastModelRelease extends Model
{
    protected $fillable = ['version', 'training_organization_ids', 'status', 'activated_by', 'activated_at'];

    protected function casts(): array
    {
        return [
            'training_organization_ids' => 'array',
            'activated_at' => 'datetime',
        ];
    }
}
