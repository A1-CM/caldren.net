<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsageReading extends Model
{
    protected $fillable = ['building_id', 'recorded_at', 'kwh'];

    protected function casts(): array
    {
        return ['recorded_at' => 'datetime'];
    }
}
