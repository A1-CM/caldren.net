<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\DailyEnergySummary;
use App\Models\ForecastPoint;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends DashboardController
{
    public function index(Request $request)
    {
        $organization = $this->organization($request);
        $buildings = $organization->buildings()->orderBy('name')->get();

        return view('dashboard.reports', compact('organization', 'buildings'));
    }

    public function export(Request $request, string $type): StreamedResponse
    {
        abort_unless(in_array($type, ['energy', 'forecasts', 'opportunities'], true), 404);
        $organization = $this->organization($request);
        $filters = $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from', 'building_id' => 'nullable|integer']);
        $from = CarbonImmutable::parse($filters['from'] ?? now()->subDays(29)->toDateString());
        $to = CarbonImmutable::parse($filters['to'] ?? now()->toDateString());
        $buildingId = isset($filters['building_id']) ? (int) $filters['building_id'] : null;
        if ($buildingId !== null) {
            abort_unless($organization->buildings()->whereKey($buildingId)->exists(), 404);
        }

        return response()->streamDownload(function () use ($organization, $type, $from, $to, $buildingId): void {
            $output = fopen('php://output', 'wb');
            if ($type === 'energy') {
                fputcsv($output, ['Building', 'Date', 'Energy kWh', 'Peak kW', 'Coverage %', 'Currency', 'Estimated standard-rate cost']);
                $query = DailyEnergySummary::query()->with('building')->whereHas('building', fn ($query) => $query->where('organization_id', $organization->id))->whereBetween('summary_date', [$from, $to]);
                if ($buildingId !== null) {
                    $query->where('building_id', $buildingId);
                }
                $query->orderBy('summary_date')->each(function ($row) use ($output): void {
                    fputcsv($output, [$row->building->name, $row->summary_date->toDateString(), $row->kwh, $row->peak_kw, $row->coverage_percent, $row->building->currency, round((float) $row->kwh * (float) $row->building->energy_rate, 2)]);
                });
            } elseif ($type === 'forecasts') {
                fputcsv($output, ['Building', 'Forecast date', 'Estimated kWh', 'Actual kWh', 'Absolute variance %', 'Source', 'Model']);
                $query = ForecastPoint::query()->with('forecastRun.building')->whereHas('forecastRun.building', fn ($query) => $query->where('organization_id', $organization->id))->whereBetween('forecast_date', [$from, $to]);
                if ($buildingId !== null) {
                    $query->whereHas('forecastRun', fn ($query) => $query->where('building_id', $buildingId));
                }
                $query->orderBy('forecast_date')->each(function ($point) use ($output): void {
                    $actual = $point->forecastRun->building->dailySummaries()->where('coverage_percent', '>=', 75)->whereDate('summary_date', $point->forecast_date)->first();
                    $variance = $actual && (float) $actual->kwh > 0 ? round(abs((float) $point->kwh - (float) $actual->kwh) / (float) $actual->kwh * 100, 2) : null;
                    fputcsv($output, [$point->forecastRun->building->name, $point->forecast_date->toDateString(), $point->kwh, $actual?->kwh, $variance, $point->forecastRun->source, $point->forecastRun->model]);
                });
            } else {
                fputcsv($output, ['Building', 'Opportunity', 'Source', 'Priority', 'Status', 'Assignee', 'Due date', 'Created']);
                $query = $organization->opportunities()->with(['building', 'assignee'])->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()]);
                if ($buildingId !== null) {
                    $query->where('building_id', $buildingId);
                }
                $query->orderBy('created_at')->each(fn ($row) => fputcsv($output, [$row->building?->name ?? 'Portfolio', $row->title, $row->source, $row->priority, $row->status, $row->assignee?->name, $row->due_date?->toDateString(), $row->created_at->toDateString()]));
            }
            fclose($output);
        }, 'caldren-'.$type.'-'.$from->toDateString().'-'.$to->toDateString().'.csv', ['Content-Type' => 'text/csv']);
    }

    public function print(Request $request): Response
    {
        $organization = $this->organization($request);
        $buildings = $organization->buildings()->with(['dailySummaries' => fn ($query) => $query->where('summary_date', '>=', now()->subDays(29))->orderBy('summary_date'), 'forecasts' => fn ($query) => $query->latest()->limit(1), 'bills' => fn ($query) => $query->latest('period_end')->limit(3)])->orderBy('name')->get();
        $alerts = $organization->alerts()->whereIn('status', ['open', 'acknowledged'])->with('building')->latest('detected_at')->get();
        $opportunities = $organization->opportunities()->whereNotIn('status', ['completed', 'dismissed'])->with(['building', 'assignee'])->latest()->get();

        return response()->view('dashboard.report-print', compact('organization', 'buildings', 'alerts', 'opportunities'));
    }
}
