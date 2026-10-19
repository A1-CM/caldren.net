<?php

namespace App\Services;

use App\Models\Building;
use App\Models\ForecastModelRelease;
use App\Models\ForecastRun;
use App\Models\UsageReading;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ForecastService
{
    public function __construct(
        private readonly GpuForecastClient $gpuClient,
        private readonly OpenAIForecastClient $openAIClient,
    ) {}

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
        $activeModel = ForecastModelRelease::query()->where('status', 'active')->latest('activated_at')->first();
        $run = $building->forecasts()->create([
            'status' => 'pending',
            'model' => config('services.openai.model'),
            'source' => 'openai',
            'input_started_at' => $firstDay->toDateString(),
            'input_ended_at' => $yesterday->toDateString(),
            'baseline_daily_kwh' => $baseline,
            'historical_peak_kw' => $historicalPeak,
        ]);

        try {
            $gpuDays = $this->requestGpuForecast($activeModel, $context, $yesterday, $baseline, $historicalPeak);
            if ($gpuDays !== null) {
                $source = 'nvidia_triton';
                $modelVersion = $activeModel->version;
                $model = 'cuml-random-forest';
                try {
                    $recommendations = $this->openAIClient->recommendations($context, $gpuDays);
                } catch (\Throwable $error) {
                    Log::warning('OpenAI recommendations were unavailable for a valid GPU forecast.', ['forecast_run_id' => $run->id, 'error' => $error->getMessage()]);
                    $recommendations = [];
                }
                $days = $gpuDays;
            } else {
                $data = $this->openAIClient->fullForecast($context);
                $days = $data['days'];
                $recommendations = $data['recommendations'];
                $source = $activeModel === null ? 'openai' : 'openai_fallback';
                $modelVersion = null;
                $model = (string) config('services.openai.model');
                $this->validateForecastDays($days, $yesterday, $baseline, $historicalPeak);
            }

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
     * @param  array<string, mixed>  $context
     * @return array<int, array<string, mixed>>|null
     */
    private function requestGpuForecast(?ForecastModelRelease $release, array $context, CarbonImmutable $lastInputDay, float $baseline, float $historicalPeak): ?array
    {
        if ($release === null) {
            return null;
        }

        try {
            $gpuContext = [
                'building_timezone' => $context['building_timezone'],
                'interval_minutes' => $context['interval_minutes'],
                'historical_days' => $context['historical_days'],
                'baseline_daily_kwh' => $context['baseline_daily_kwh'],
                'historical_peak_kw' => $context['historical_peak_kw'],
                'energy_rate' => null,
                'peak_rate' => null,
                'peak_hours' => [null, null],
            ];
            $response = $this->gpuClient->forecast($gpuContext, $release->version);
            if ($response['model_version'] !== $release->version) {
                throw new RuntimeException('The NVIDIA forecast model version did not match the active release.');
            }
            $days = $response['days'];
            $this->validateForecastDays($days, $lastInputDay, $baseline, $historicalPeak);

            return $days;
        } catch (\Throwable $error) {
            Log::warning('NVIDIA forecast inference failed; using the OpenAI fallback.', ['model_version' => $release->version, 'error' => $error->getMessage()]);

            return null;
        }
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
