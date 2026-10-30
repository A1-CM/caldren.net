<?php

namespace App\Services;

use App\Models\Building;
use App\Models\DailyEnergySummary;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class DailyEnergySummaryService
{
    /** @param array<int, string>|null $localDates */
    public function rebuild(Building $building, ?array $localDates = null): int
    {
        $dates = $localDates ?? $building->readings()
            ->orderBy('recorded_at')
            ->get(['recorded_at'])
            ->map(fn ($reading): string => $reading->recorded_at->setTimezone($building->timezone)->toDateString())
            ->unique()
            ->values()
            ->all();

        $rebuilt = 0;
        foreach ($dates as $date) {
            $start = CarbonImmutable::parse($date, $building->timezone)->startOfDay();
            $end = $start->addDay();
            $readings = $building->readings()
                ->where('recorded_at', '>=', $start->utc())
                ->where('recorded_at', '<', $end->utc())
                ->orderBy('recorded_at')
                ->get();

            if ($readings->isEmpty()) {
                $building->dailySummaries()->where('summary_date', $date)->delete();

                continue;
            }

            $intervalMinutes = $this->inferIntervalMinutes($readings);
            $dayMinutes = $start->utc()->diffInMinutes($end->utc());
            $expectedCount = $intervalMinutes === null ? 0 : max(1, (int) round($dayMinutes / $intervalMinutes));
            $readingCount = $readings->count();
            $peakKw = $intervalMinutes === null ? 0 : $readings->max(fn ($reading): float => (float) $reading->kwh * 60 / $intervalMinutes);

            $summary = DailyEnergySummary::query()
                ->where('building_id', $building->id)
                ->whereDate('summary_date', $date)
                ->firstOrNew();
            $summary->fill([
                'building_id' => $building->id,
                'summary_date' => $date,
                'kwh' => $readings->sum(fn ($reading): float => (float) $reading->kwh),
                'peak_kw' => $peakKw,
                'interval_minutes' => $intervalMinutes,
                'reading_count' => $readingCount,
                'expected_count' => $expectedCount,
                'coverage_percent' => $expectedCount > 0 ? min(100, round($readingCount / $expectedCount * 100, 2)) : 0,
                'first_reading_at' => $readings->first()->recorded_at,
                'last_reading_at' => $readings->last()->recorded_at,
            ]);
            $summary->save();
            $rebuilt++;
        }

        return $rebuilt;
    }

    /** @param Collection<int, mixed> $readings */
    public function inferIntervalMinutes(Collection $readings): ?int
    {
        $intervals = [];
        $previous = null;
        foreach ($readings as $reading) {
            if ($previous !== null) {
                $minutes = (int) $previous->recorded_at->diffInMinutes($reading->recorded_at);
                if ($minutes > 0 && $minutes <= 240) {
                    $intervals[] = $minutes;
                }
            }
            $previous = $reading;
        }
        sort($intervals);

        return $intervals === [] ? null : $intervals[intdiv(count($intervals), 2)];
    }
}
