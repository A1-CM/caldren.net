<?php

namespace App\Services;

use App\Models\Building;
use App\Models\ForecastRun;
use App\Models\UsageReading;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ForecastService
{
    public function __construct(private readonly GroqForecastClient $groqClient) {}

    public function generate(Building $building): ForecastRun
    {
        $yesterday = CarbonImmutable::now($building->timezone)->subDay()->startOfDay();
        $firstDay = $yesterday->subDays(13);
        $readings = $building->readings()
            ->whereBetween('recorded_at', [$firstDay->startOfDay()->utc(), $yesterday->endOfDay()->utc()])
            ->orderBy('recorded_at')
            ->get();
        $interval = $this->intervalMinutes($readings);
        if ($interval < 15 || $interval > 60) {
            throw ValidationException::withMessages(['forecast' => 'Upload hourly or sub-hourly readings covering the last 14 complete days.']);
        }

        $history = $this->dailyHistory($readings, $building->timezone, $interval);
        for ($dayOffset = 0; $dayOffset < 14; $dayOffset++) {
            $date = $firstDay->addDays($dayOffset)->toDateString();
            $minimumReadings = (int) floor(1440 / $interval * .75);
            if (($history[$date]['count'] ?? 0) < $minimumReadings) {
                throw ValidationException::withMessages(['forecast' => 'The last 14 complete days need at least 75% interval coverage each. Add the missing readings and retry.']);
            }
        }

        ksort($history);
        $baseline = array_sum(array_column($history, 'kwh')) / 14;
        $historicalPeak = max(array_column($history, 'peak_kw'));
        if ($baseline <= 0 || $historicalPeak <= 0) {
            throw ValidationException::withMessages(['forecast' => 'Readings must contain positive usage to forecast demand.']);
        }

        $dailyHistory = [];
        foreach ($history as $date => $day) {
            $dailyHistory[] = ['date' => $date, 'kwh' => $day['kwh'], 'peak_kw' => $day['peak_kw']];
        }
        $context = $this->forecastContext($building, $dailyHistory, $interval, $baseline, $historicalPeak);
        $run = $building->forecasts()->create([
            'status' => 'pending',
            'model' => config('services.groq.model'),
            'source' => 'groq',
            'input_started_at' => $firstDay->toDateString(),
            'input_ended_at' => $yesterday->toDateString(),
            'baseline_daily_kwh' => $baseline,
            'historical_peak_kw' => $historicalPeak,
        ]);

        try {
            $data = $this->groqClient->fullForecast($context);
            $days = $data['days'];
            $recommendations = $data['recommendations'];
            $source = 'groq';
            $modelVersion = null;
            $model = (string) config('services.groq.model');
            $this->validateForecastDays($days, $yesterday, $baseline, $historicalPeak);

            if (count($days) !== 7 || count($recommendations) > 3) {
                throw new RuntimeException('The forecast response did not contain the required seven days or exceeded the recommendation limit.');
            }
            $this->validateForecastDays($days, $yesterday, $baseline, $historicalPeak);
            DB::transaction(function () use ($run, $days, $recommendations, $source, $model, $modelVersion): void {
                foreach ($days as $point) {
                    $run->points()->create(['forecast_date' => $point['date'], 'kwh' => $point['kwh'], 'peak_kw' => $point['peak_kw']]);
                }
                foreach ($recommendations as $item) {
                    if (! is_array($item) || ! isset($item['title'], $item['description'], $item['priority']) || ! in_array($item['priority'], ['high', 'medium', 'low'], true)) {
                        continue;
                    }
                    $run->recommendations()->create([
                        'title' => mb_substr((string) $item['title'], 0, 180),
                        'description' => mb_substr((string) $item['description'], 0, 1000),
                        'priority' => $item['priority'],
                    ]);
                }
                $run->update(['status' => 'complete', 'source' => $source, 'model' => $model, 'model_version' => $modelVersion]);
            });
        } catch (\Throwable $error) {
            $run->update(['status' => 'failed', 'failure_reason' => mb_substr($error->getMessage(), 0, 1000)]);
            report($error);
        }

        return $run->load(['points', 'recommendations']);
    }

    /**
     * @param  iterable<UsageReading>  $readings
     */
    private function intervalMinutes(iterable $readings): int
    {
        $intervals = [];
        $previous = null;
        foreach ($readings as $reading) {
            if ($previous !== null) {
                $minutes = $previous->recorded_at->diffInMinutes($reading->recorded_at);
                if ($minutes > 0 && $minutes <= 120) {
                    $intervals[] = $minutes;
                }
            }
            $previous = $reading;
        }
        sort($intervals);

        return $intervals === [] ? 0 : (int) $intervals[intdiv(count($intervals), 2)];
    }

    /**
     * @param  iterable<UsageReading>  $readings
     * @return array<string, array{kwh: float, peak_kw: float, count: int}>
     */
    private function dailyHistory(iterable $readings, string $timezone, int $interval): array
    {
        $history = [];
        foreach ($readings as $reading) {
            $date = $reading->recorded_at->setTimezone($timezone)->toDateString();
            $kwh = (float) $reading->kwh;
            $history[$date]['kwh'] = ($history[$date]['kwh'] ?? 0) + $kwh;
            $history[$date]['peak_kw'] = max($history[$date]['peak_kw'] ?? 0, $kwh * 60 / $interval);
            $history[$date]['count'] = ($history[$date]['count'] ?? 0) + 1;
        }

        return $history;
    }

    /**
     * @param  array<int, array{date: string, kwh: float, peak_kw: float}>  $dailyHistory
     * @return array<string, mixed>
     */
    private function forecastContext(Building $building, array $dailyHistory, int $interval, float $baseline, float $historicalPeak): array
    {
        return [
            'building_timezone' => $building->timezone,
            'interval_minutes' => $interval,
            'historical_days' => $dailyHistory,
            'baseline_daily_kwh' => round($baseline, 3),
            'historical_peak_kw' => round($historicalPeak, 3),
            'energy_rate' => $building->energy_rate,
            'peak_rate' => $building->peak_rate,
            'peak_hours' => [$building->peak_start_hour, $building->peak_end_hour],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $days
     */
    private function validateForecastDays(array $days, CarbonImmutable $lastInputDay, float $baseline, float $historicalPeak): void
    {
        if (count($days) !== 7) {
            throw new RuntimeException('The forecast did not contain seven days.');
        }
        foreach ($days as $index => $point) {
            $expectedDate = $lastInputDay->addDays($index + 1)->toDateString();
            if (! is_array($point)
                || ($point['date'] ?? '') !== $expectedDate
                || ! is_numeric($point['kwh'] ?? null)
                || ! is_numeric($point['peak_kw'] ?? null)
                || (float) $point['kwh'] < $baseline * .25
                || (float) $point['peak_kw'] < 0
                || (float) $point['kwh'] > $baseline * 3
                || (float) $point['peak_kw'] > $historicalPeak * 3) {
                throw new RuntimeException('The forecast failed historical plausibility checks.');
            }
        }
    }
}
