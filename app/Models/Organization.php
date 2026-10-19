<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Organization extends Model
{
    protected $fillable = ['name', 'trial_ends_at'];

    protected function casts(): array
    {
        return ['trial_ends_at' => 'datetime'];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    public function buildings(): HasMany
    {
        return $this->hasMany(Building::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    public function hasAccess(): bool
    {
        return $this->trial_ends_at->isFuture() || ($this->subscription?->status === 'active' && $this->subscription->current_period_ends_at?->isFuture());
    }

    public function accessStatus(): string
    {
        if ($this->subscription?->status === 'active' && $this->subscription->current_period_ends_at?->isFuture()) {
            return 'Demo active';
        }

        return $this->trial_ends_at->isFuture() ? 'Trialing' : 'Trial ended';
    }
}
