<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ForecastPoint extends Model
{
    public $timestamps = false;

    protected $fillable = ['forecast_date', 'kwh', 'peak_kw'];
}
