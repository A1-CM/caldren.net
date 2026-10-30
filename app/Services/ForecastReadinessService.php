<?php

namespace App\Services;

use App\Models\Building;
use Carbon\CarbonImmutable;

class ForecastReadinessService
{
    /** @return array{complete_days: int, ready: bool} */
    public function assess(Building $building): array
    {
        $yesterday = CarbonImmutable::now($building->timezone)->subDay();
        $firstDay = $yesterday->subDays(13);
        $summaries = $building->dailySummaries()
            ->whereBetween('summary_date', [$firstDay->toDateString(), $yesterday->toDateString()])
            ->get()
            ->keyBy(fn ($summary): string => $summary->summary_date->toDateString());
        $completeDays = 0;
        for ($offset = 0; $offset < 14; $offset++) {
            $summary = $summaries->get($firstDay->addDays($offset)->toDateString());
            if ($summary !== null && (float) $summary->coverage_percent >= 75 && $summary->interval_minutes >= 15 && $summary->interval_minutes <= 60) {
                $completeDays++;
            }
        }

        return ['complete_days' => $completeDays, 'ready' => $completeDays === 14];
    }
}
