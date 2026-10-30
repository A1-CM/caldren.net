<?php

namespace App\Http\Controllers\Dashboard;

use App\Services\ForecastReadinessService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class DataHealthController extends DashboardController
{
    public function __invoke(Request $request, ForecastReadinessService $readiness)
    {
        $organization = $this->organization($request);
        $buildings = $organization->buildings()->with(['dailySummaries' => fn ($query) => $query->latest('summary_date')->limit(30), 'dataImports' => fn ($query) => $query->with('uploader')->latest()->limit(10), 'bills' => fn ($query) => $query->latest('period_end')->limit(12)])->orderBy('name')->get();
        $healthRows = $buildings->map(function ($building) use ($readiness): array {
            $latest = $building->dailySummaries->sortByDesc('summary_date')->first();
            $latestReading = $building->readings()->latest('recorded_at')->first();
            $completeDays = $readiness->assess($building)['complete_days'];
            $stale = $latestReading === null || $latestReading->recorded_at->lt(CarbonImmutable::now()->subHours((int) $building->stale_after_hours));
            $tariffComplete = (float) $building->energy_rate > 0 && $building->peak_start_hour !== null && $building->peak_end_hour !== null;

            return compact('building', 'latest', 'latestReading', 'completeDays', 'stale', 'tariffComplete');
        });

        return view('dashboard.data-health', compact('organization', 'buildings', 'healthRows'));
    }
}
