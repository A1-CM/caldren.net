<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = ['organization_id', 'status', 'monthly_price_cents', 'current_period_ends_at'];

    protected function casts(): array
    {
        return ['current_period_ends_at' => 'datetime'];
    }
}
