<?php

namespace App\Services;

use App\Models\Building;
use App\Models\ForecastRun;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ForecastService
{
    public function generate(Building $building): ForecastRun
    {
        $yesterday = CarbonImmutable::now($building->timezone)->subDay()->startOfDay();
        $firstDay = $yesterday->subDays(13);
        $readings = $building->readings()->whereBetween('recorded_at', [$firstDay->startOfDay()->utc(), $yesterday->endOfDay()->utc()])->orderBy('recorded_at')->get();
        $intervals = [];
        for ($i = 1; $i < $readings->count(); $i++) {
            $minutes = $readings[$i - 1]->recorded_at->diffInMinutes($readings[$i]->recorded_at);
            if ($minutes > 0 && $minutes <= 120) {
                $intervals[] = $minutes;
            }
        }
        sort($intervals);
        $interval = $intervals ? $intervals[intdiv(count($intervals), 2)] : 0;
        if ($interval < 15 || $interval > 60) {
            throw ValidationException::withMessages(['forecast' => 'Upload hourly or sub-hourly readings covering the last 14 complete days.']);
        }
        $history = [];
        foreach ($readings as $reading) {
            $date = $reading->recorded_at->setTimezone($building->timezone)->toDateString();
            $history[$date]['kwh'] = ($history[$date]['kwh'] ?? 0) + (float) $reading->kwh;
            $history[$date]['peak_kw'] = max($history[$date]['peak_kw'] ?? 0, (float) $reading->kwh * 60 / $interval);
            $history[$date]['count'] = ($history[$date]['count'] ?? 0) + 1;
        }
        $minimumPerDay = (int) floor(1440 / $interval * .75);
        for ($i = 0; $i < 14; $i++) {
            $date = $firstDay->addDays($i)->toDateString();
            if (($history[$date]['count'] ?? 0) < $minimumPerDay) {
                throw ValidationException::withMessages(['forecast' => 'The last 14 complete days need at least 75% interval coverage each. Add the missing readings and retry.']);
            }
        }
        ksort($history);
        $baseline = array_sum(array_column($history, 'kwh')) / 14;
        $historicalPeak = max(array_column($history, 'peak_kw'));
        if ($baseline <= 0 || $historicalPeak <= 0) {
            throw ValidationException::withMessages(['forecast' => 'Readings must contain positive usage to forecast demand.']);
        }
        if (! config('services.openai.key')) {
            throw ValidationException::withMessages(['forecast' => 'Forecasting is not configured yet. Add the OpenAI API key.']);
        }
        $model = config('services.openai.model');
        $run = $building->forecasts()->create(['status' => 'pending', 'model' => $model, 'input_started_at' => $firstDay->toDateString(), 'input_ended_at' => $yesterday->toDateString(), 'baseline_daily_kwh' => $baseline, 'historical_peak_kw' => $historicalPeak]);
        try {
            $schema = ['type' => 'object', 'additionalProperties' => false, 'properties' => [
                'days' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => false, 'properties' => [
                    'date' => ['type' => 'string'], 'kwh' => ['type' => 'number'], 'peak_kw' => ['type' => 'number'],
                ], 'required' => ['date', 'kwh', 'peak_kw']]],
                'recommendations' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => false, 'properties' => [
                    'title' => ['type' => 'string'], 'description' => ['type' => 'string'], 'priority' => ['type' => 'string', 'enum' => ['high', 'medium', 'low']],
                ], 'required' => ['title', 'description', 'priority']]],
            ], 'required' => ['days', 'recommendations']];
            $response = Http::withToken(config('services.openai.key'))->timeout(35)->post('https://api.openai.com/v1/responses', [
                'model' => $model,
                'instructions' => 'You are an energy demand analysis assistant. Produce cautious estimates from historical data, never claim guaranteed savings. Return exactly seven calendar days after the last input date. Peak kW estimates must be nonnegative and plausible. Give at most three actionable recommendations grounded in the tariff and observed history. Do not invent weather, occupancy, or equipment data.',
                'input' => json_encode(['building_timezone' => $building->timezone, 'interval_minutes' => $interval, 'historical_days' => $history, 'baseline_daily_kwh' => round($baseline, 3), 'historical_peak_kw' => round($historicalPeak, 3), 'energy_rate' => $building->energy_rate, 'peak_rate' => $building->peak_rate, 'peak_hours' => [$building->peak_start_hour, $building->peak_end_hour]], JSON_THROW_ON_ERROR),
                'text' => ['format' => ['type' => 'json_schema', 'name' => 'energy_forecast', 'strict' => true, 'schema' => $schema]],
                'max_output_tokens' => 1800,
            ])->throw()->json();
            $text = collect($response['output'] ?? [])->flatMap(fn ($item) => $item['content'] ?? [])->firstWhere('type', 'output_text')['text'] ?? null;
            if (! $text) {
                throw new RuntimeException('The AI service returned no forecast.');
            }
            $data = json_decode($text, true, flags: JSON_THROW_ON_ERROR);
            if (count($data['days'] ?? []) !== 7 || count($data['recommendations'] ?? []) > 3) {
                throw new RuntimeException('The AI forecast did not contain seven days.');
            }
            foreach ($data['days'] as $index => $point) {
                $expected = $yesterday->addDays($index + 1)->toDateString();
                if (($point['date'] ?? '') !== $expected || ! is_numeric($point['kwh'] ?? null) || ! is_numeric($point['peak_kw'] ?? null) || $point['kwh'] < $baseline * .25 || $point['peak_kw'] < 0 || $point['kwh'] > $baseline * 3 || $point['peak_kw'] > $historicalPeak * 3) {
                    throw new RuntimeException('The AI forecast failed plausibility checks.');
                }
            }
            DB::transaction(function () use ($run, $data) {
                foreach ($data['days'] as $point) {
                    $run->points()->create(['forecast_date' => $point['date'], 'kwh' => $point['kwh'], 'peak_kw' => $point['peak_kw']]);
                }
                foreach ($data['recommendations'] as $item) {
                    $run->recommendations()->create(['title' => mb_substr($item['title'], 0, 180), 'description' => mb_substr($item['description'], 0, 1000), 'priority' => $item['priority']]);
                }
                $run->update(['status' => 'complete']);
            });
        } catch (\Throwable $error) {
            $run->update(['status' => 'failed', 'failure_reason' => mb_substr($error->getMessage(), 0, 1000)]);
            report($error);
        }

        return $run->load(['points', 'recommendations']);
    }
}
