<?php

namespace App\Services;

use App\Models\Building;
use App\Models\ForecastRun;
use App\Models\OperationalAlert;
use App\Models\UtilityBill;

class OperationalAlertService
{
    public function afterImport(Building $building): void
    {
        if (! $building->alerts_enabled) {
            return;
        }

        $threshold = (float) $building->coverage_alert_threshold_percent;
        foreach ($building->dailySummaries()->where('coverage_percent', '<', $threshold)->get() as $summary) {
            $this->open($building, 'low_coverage', 'medium', 'Interval coverage needs attention', sprintf('%s has %.1f%% data coverage for %s. Forecast eligibility still requires at least 75%% coverage.', $building->name, $summary->coverage_percent, $summary->summary_date->toDateString()), 'coverage:'.$building->id.':'.$summary->summary_date->toDateString());
        }
    }

    public function afterBill(Building $building, UtilityBill $bill): void
    {
        if (! $building->alerts_enabled) {
            return;
        }
        $previous = $building->bills()->where('id', '!=', $bill->id)->where('period_end', '<', $bill->period_start)->latest('period_end')->first();
        if ($previous === null || (float) $previous->kwh <= 0) {
            return;
        }
        $change = abs(((float) $bill->kwh - (float) $previous->kwh) / (float) $previous->kwh * 100);
        if ($change >= (float) $building->bill_change_threshold_percent) {
            $this->open($building, 'bill_usage_change', $change >= 25 ? 'high' : 'medium', 'Bill usage changed', sprintf('%s usage changed by %.1f%% compared with the prior recorded bill.', $building->name, $change), 'bill-change:'.$building->id.':'.$bill->id);
        }
    }

    public function afterForecast(Building $building, ForecastRun $run): void
    {
        if (! $building->alerts_enabled) {
            return;
        }
        if ($run->status !== 'complete') {
            $this->open($building, 'forecast_failed', 'medium', 'Forecast needs a retry', 'The latest forecast could not be completed. Historical energy data remains available.', 'forecast-failed:'.$building->id.':'.$run->id, $run);

            return;
        }
        $predictedPeak = (float) $run->points()->max('peak_kw');
        $historicalPeak = (float) $run->historical_peak_kw;
        $thresholdPeak = $historicalPeak * (1 + (float) $building->forecast_peak_threshold_percent / 100);
        if ($historicalPeak > 0 && $predictedPeak >= $thresholdPeak) {
            $this->open($building, 'forecast_peak', 'high', 'Forecast peak requires review', sprintf('%s has a predicted peak of %.1f kW, above its configured %.1f%% threshold.', $building->name, $predictedPeak, $building->forecast_peak_threshold_percent), 'forecast-peak:'.$building->id.':'.$run->id, $run);
        }
    }

    public function open(Building $building, string $type, string $severity, string $title, string $message, string $fingerprint, ?ForecastRun $forecastRun = null): OperationalAlert
    {
        $alert = OperationalAlert::firstOrNew(['organization_id' => $building->organization_id, 'fingerprint' => $fingerprint]);
        $alert->fill([
            'building_id' => $building->id,
            'forecast_run_id' => $forecastRun?->id,
            'type' => $type,
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'status' => 'open',
            'detected_at' => now(),
            'acknowledged_by' => null,
            'acknowledged_at' => null,
            'resolved_by' => null,
            'resolved_at' => null,
        ]);
        $alert->save();

        return $alert;
    }
}
