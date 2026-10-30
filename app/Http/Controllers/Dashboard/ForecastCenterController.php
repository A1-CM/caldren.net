<?php

namespace App\Http\Controllers\Dashboard;

use App\Services\ForecastReadinessService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class ForecastCenterController extends DashboardController
{
    public function __invoke(Request $request, ForecastReadinessService $readiness)
    {
        $organization = $this->organization($request);
        $buildings = $organization->buildings()->with(['dailySummaries' => fn ($query) => $query->latest('summary_date')->limit(14), 'forecasts' => fn ($query) => $query->latest()->with('points')->limit(10)])->orderBy('name')->get();
        $today = CarbonImmutable::today();
        $readinessByBuilding = $buildings->mapWithKeys(fn ($building): array => [$building->id => $readiness->assess($building)]);
        $forecastRows = $buildings->flatMap(function ($building) use ($today) {
            return $building->forecasts->map(function ($forecast) use ($building, $today): array {
                $actuals = $building->dailySummaries()->where('coverage_percent', '>=', 75)->whereIn('summary_date', $forecast->points->filter(fn ($point): bool => $point->forecast_date->lt($today))->pluck('forecast_date'))->get()->keyBy(fn ($summary): string => $summary->summary_date->toDateString());
                $variances = $forecast->points->filter(fn ($point): bool => $actuals->has($point->forecast_date->toDateString()) && (float) $actuals[$point->forecast_date->toDateString()]->kwh > 0)->map(fn ($point): float => abs((float) $point->kwh - (float) $actuals[$point->forecast_date->toDateString()]->kwh) / (float) $actuals[$point->forecast_date->toDateString()]->kwh * 100);

                return ['building' => $building, 'forecast' => $forecast, 'variance' => $variances->isEmpty() ? null : round($variances->avg(), 1)];
            });
        })->sortByDesc(fn (array $row) => $row['forecast']->created_at)->values();

        return view('dashboard.forecasts', compact('organization', 'buildings', 'forecastRows', 'readinessByBuilding'));
    }
}
