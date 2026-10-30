<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Opportunity extends Model
{
    public const STATUSES = ['new', 'reviewing', 'planned', 'completed', 'dismissed'];

    public const PRIORITIES = ['high', 'medium', 'low'];

    protected $fillable = ['organization_id', 'building_id', 'recommendation_id', 'assignee_id', 'source', 'title', 'description', 'priority', 'status', 'due_date', 'completed_at', 'dismissed_at'];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'completed_at' => 'datetime', 'dismissed_at' => 'datetime'];
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function recommendation(): BelongsTo
    {
        return $this->belongsTo(Recommendation::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(OpportunityActivity::class)->latest();
    }
}
