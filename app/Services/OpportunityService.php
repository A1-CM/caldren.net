<?php

namespace App\Services;

use App\Models\ForecastRun;
use App\Models\Opportunity;

class OpportunityService
{
    public function createFromForecast(ForecastRun $forecastRun): int
    {
        $forecastRun->loadMissing(['building', 'recommendations']);
        $created = 0;
        foreach ($forecastRun->recommendations as $recommendation) {
            $opportunity = Opportunity::firstOrCreate(
                ['recommendation_id' => $recommendation->id],
                [
                    'organization_id' => $forecastRun->building->organization_id,
                    'building_id' => $forecastRun->building_id,
                    'source' => 'forecast',
                    'title' => $recommendation->title,
                    'description' => $recommendation->description,
                    'priority' => $recommendation->priority,
                    'status' => 'new',
                ],
            );
            if ($opportunity->wasRecentlyCreated) {
                $opportunity->activities()->create(['action' => 'created']);
                $created++;
            }
        }

        return $created;
    }
}
