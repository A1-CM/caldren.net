<?php

namespace App\Console\Commands;

use App\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Signature('caldren:export-forecast-training-data {--output=} {--manifest=}')]
#[Description('Export daily energy features from organizations with current owner consent')]
class ExportForecastTrainingData extends Command
{
    public function handle(): int
    {
        $organizations = Organization::query()->whereNotNull('model_training_opt_in_at')->with('buildings')->get();
        $consentTimestamps = $organizations->mapWithKeys(fn (Organization $organization): array => [$organization->id => $organization->model_training_opt_in_at->toIso8601String()])->all();
        $relativePath = (string) ($this->option('output') ?: 'model-training/forecast-training-'.now()->format('Ymd-His').'.csv');
        if ($relativePath === '' || str_contains($relativePath, '..') || str_starts_with($relativePath, '/')) {
            $this->error('Choose a relative output path on the private local storage disk.');

            return self::FAILURE;
        }
        $manifestPath = (string) ($this->option('manifest') ?: $relativePath.'.manifest.json');
        if (str_contains($manifestPath, '..') || str_starts_with($manifestPath, '/')) {
            $this->error('Choose a relative manifest path on the private local storage disk.');

            return self::FAILURE;
        }

        $disk = Storage::disk('local');
        if ($disk->exists($relativePath) || $disk->exists($manifestPath)) {
            $this->error('The training export or manifest already exists. Choose a new path.');

            return self::FAILURE;
        }
        $disk->makeDirectory(dirname($relativePath));
        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, ['series_id', 'day_index', 'weekday', 'daily_kwh', 'peak_kw']);
        $seriesCount = 0;
        $dayCount = 0;
        $contributingOrganizations = [];

        foreach ($organizations as $organization) {
            foreach ($organization->buildings as $building) {
                $intervalReadings = $building->readings()
                    ->where('recorded_at', '>=', $organization->model_training_opt_in_at)
                    ->select('recorded_at')
                    ->orderBy('recorded_at')
                    ->cursor();
                $intervals = [];
                $previousReading = null;
                foreach ($intervalReadings as $reading) {
                    if ($previousReading !== null) {
                        $minutes = $previousReading->recorded_at->diffInMinutes($reading->recorded_at);
                        if ($minutes >= 15 && $minutes <= 120) {
                            $intervals[] = $minutes;
                            if (count($intervals) >= 10000) {
                                break;
                            }
                        }
                    }
                    $previousReading = $reading;
                }
                sort($intervals);
                $interval = $intervals === [] ? 0 : (int) $intervals[intdiv(count($intervals), 2)];
                if ($interval < 15 || $interval > 60) {
                    continue;
                }

                $seriesId = (string) Str::uuid();
                $daily = [];
                $readings = $building->readings()
                    ->where('recorded_at', '>=', $organization->model_training_opt_in_at)
                    ->select(['recorded_at', 'kwh'])
                    ->orderBy('recorded_at')
                    ->cursor();
                foreach ($readings as $reading) {
                    $date = $reading->recorded_at->setTimezone($building->timezone)->toDateString();
                    $usage = (float) $reading->kwh;
                    $daily[$date]['kwh'] = ($daily[$date]['kwh'] ?? 0) + $usage;
                    $daily[$date]['peak_kw'] = max($daily[$date]['peak_kw'] ?? 0, $usage * 60 / $interval);
                    $daily[$date]['count'] = ($daily[$date]['count'] ?? 0) + 1;
                }
                if ($daily === []) {
                    continue;
                }
                ksort($daily);
                $firstDate = CarbonImmutable::parse((string) array_key_first($daily));
                $minimumReadings = (int) floor(1440 / $interval * .75);
                $previousDate = null;
                foreach ($daily as $date => $values) {
                    $currentDate = CarbonImmutable::parse($date);
                    if ($values['count'] < $minimumReadings || ($previousDate !== null && (int) $previousDate->diffInDays($currentDate) !== 1)) {
                        $previousDate = $currentDate;

                        continue;
                    }
                    $dayIndex = (int) $firstDate->diffInDays($currentDate);
                    $weekday = $currentDate->isoWeekday();
                    fputcsv($stream, [$seriesId, $dayIndex, $weekday, round($values['kwh'], 4), round($values['peak_kw'], 4)]);
                    $contributingOrganizations[$organization->id] = true;
                    $dayCount++;
                    $previousDate = $currentDate;
                }
                $seriesCount++;
            }
        }

        if ($dayCount === 0) {
            fclose($stream);
            $this->error('No complete daily reading history is available from opted-in organizations.');

            return self::FAILURE;
        }

        $organizationIds = array_map('intval', array_keys($contributingOrganizations));
        $contributingConsentTimestamps = array_intersect_key($consentTimestamps, $contributingOrganizations);
        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);
        $disk->put($relativePath, $contents);
        $disk->put($manifestPath, json_encode([
            'training_organization_ids' => $organizationIds,
            'training_consent_timestamps' => $contributingConsentTimestamps,
            'series_count' => $seriesCount,
            'daily_rows' => $dayCount,
            'consent_version' => config('services.nvidia.training_consent_version', 'v1'),
            'created_at' => now()->toIso8601String(),
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        $this->info("Wrote {$dayCount} de-identified daily rows across {$seriesCount} series to private storage: {$relativePath}");
        $this->warn("Private consent manifest: {$manifestPath}. Do not pass this manifest to the GPU training container.");

        return self::SUCCESS;
    }
}
