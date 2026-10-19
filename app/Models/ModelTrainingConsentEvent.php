<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModelTrainingConsentEvent extends Model
{
    protected $fillable = ['organization_id', 'user_id', 'status', 'consent_version'];
}
