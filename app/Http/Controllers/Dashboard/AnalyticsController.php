<?php

namespace App\Http\Controllers\Dashboard;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class AnalyticsController extends DashboardController
{
    public function __invoke(Request $request)
    {
        $organization = $this->organization($request);
        $data = $request->validate(['range' => 'nullable|in:30,90,365', 'building_id' => 'nullable|integer']);
        $days = (int) ($data['range'] ?? 30);
        $end = CarbonImmutable::today();
        $start = $end->subDays($days - 1);
        $previousStart = $start->subDays($days);
        $buildingId = isset($data['building_id']) ? (int) $data['building_id'] : null;
        $buildings = $organization->buildings()->orderBy('name')->get();
        if ($buildingId !== null) {
            abort_unless($buildings->contains('id', $buildingId), 404);
        }
        $query = $organization->buildings()->with([
            'dailySummaries' => fn ($query) => $query->whereBetween('summary_date', [$previousStart, $end])->orderBy('summary_date'),
            'bills' => fn ($query) => $query->where('period_end', '>=', $previousStart)->where('period_start', '<=', $end),
        ]);
        if ($buildingId !== null) {
            $query->whereKey($buildingId);
        }
        $selectedBuildings = $query->get();
        $series = [];
        for ($date = $start; $date->lte($end); $date = $date->addDay()) {
            $dateString = $date->toDateString();
            $summaries = $selectedBuildings->flatMap->dailySummaries->filter(fn ($summary): bool => $summary->summary_date->toDateString() === $dateString);
            $series[] = ['date' => $dateString, 'kwh' => round($summaries->sum('kwh'), 2), 'peak_kw' => round((float) $summaries->max('peak_kw'), 2)];
        }
        $currentKwh = $selectedBuildings->flatMap->dailySummaries->filter(fn ($summary): bool => $summary->summary_date->betweenIncluded($start, $end))->sum('kwh');
        $previousKwh = $selectedBuildings->flatMap->dailySummaries->filter(fn ($summary): bool => $summary->summary_date->betweenIncluded($previousStart, $start->subDay()))->sum('kwh');
        $costs = $selectedBuildings->groupBy('currency')->map(fn ($group): float => round($group->sum(fn ($building): float => $building->dailySummaries->filter(fn ($summary): bool => $summary->summary_date->betweenIncluded($start, $end))->sum('kwh') * (float) $building->energy_rate), 2));
        $billSpend = $selectedBuildings->groupBy('currency')->map(fn ($group): float => round($group->sum(fn ($building): float => $building->bills->sum('amount')), 2));
        $rankings = $selectedBuildings->map(fn ($building): array => ['id' => $building->id, 'name' => $building->name, 'kwh' => round($building->dailySummaries->filter(fn ($summary): bool => $summary->summary_date->betweenIncluded($start, $end))->sum('kwh'), 2), 'coverage' => round($building->dailySummaries->filter(fn ($summary): bool => $summary->summary_date->betweenIncluded($start, $end))->avg('coverage_percent') ?? 0, 1)])->sortByDesc('kwh')->values();

        return view('dashboard.analytics', compact('organization', 'buildings', 'buildingId', 'days', 'series', 'currentKwh', 'previousKwh', 'costs', 'billSpend', 'rankings'));
    }
}
