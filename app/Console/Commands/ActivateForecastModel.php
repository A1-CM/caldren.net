<?php

namespace App\Console\Commands;

use App\Models\ForecastModelRelease;
use App\Models\Organization;
use App\Services\GpuForecastClient;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

#[Signature('caldren:activate-forecast-model {version} {--manifest=} {--evaluation=}')]
#[Description('Activate a ready Triton release trained only on currently opted-in organizations')]
class ActivateForecastModel extends Command
{
    public function handle(GpuForecastClient $gpuClient): int
    {
        $version = (string) $this->argument('version');
        if (preg_match('/^[A-Za-z0-9._-]{1,80}$/', $version) !== 1) {
            $this->error('The model version contains unsupported characters.');

            return self::FAILURE;
        }

        $manifestPath = (string) $this->option('manifest');
        if ($manifestPath === '' || str_contains($manifestPath, '..') || str_starts_with($manifestPath, '/')) {
            $this->error('Provide a relative manifest path on the private local storage disk.');

            return self::FAILURE;
        }
        $disk = Storage::disk('local');
        if (! $disk->exists($manifestPath)) {
            $this->error('The private training manifest could not be found.');

            return self::FAILURE;
        }
        $manifest = json_decode((string) $disk->get($manifestPath), true);
        $evaluationPath = (string) $this->option('evaluation');
        if ($evaluationPath === '' || str_contains($evaluationPath, '..') || str_starts_with($evaluationPath, '/') || ! $disk->exists($evaluationPath)) {
            $this->error('Provide a private walk-forward evaluation artifact.');

            return self::FAILURE;
        }
        $evaluation = json_decode((string) $disk->get($evaluationPath), true);
        if (! is_array($evaluation)
            || ($evaluation['approved'] ?? false) !== true
            || ($evaluation['version'] ?? null) !== $version
            || ! is_numeric($evaluation['candidate_daily_kwh_mae'] ?? null)
            || ! is_numeric($evaluation['seasonal_daily_kwh_mae'] ?? null)
            || ! is_numeric($evaluation['candidate_peak_kw_mae'] ?? null)
            || ! is_numeric($evaluation['seasonal_peak_kw_mae'] ?? null)
            || $evaluation['candidate_daily_kwh_mae'] >= $evaluation['seasonal_daily_kwh_mae']
            || $evaluation['candidate_peak_kw_mae'] >= $evaluation['seasonal_peak_kw_mae']) {
            $this->error('The model has not passed walk-forward evaluation against the seasonal baseline for both kWh and peak estimates.');

            return self::FAILURE;
        }
        $organizationIds = $manifest['training_organization_ids'] ?? null;
        $consentTimestamps = $manifest['training_consent_timestamps'] ?? null;
        if (! is_array($organizationIds) || $organizationIds === [] || ! is_array($consentTimestamps)) {
            $this->error('The private training manifest must list contributors and the consent snapshot.');

            return self::FAILURE;
        }
        $organizationIds = array_values(array_unique(array_map('intval', $organizationIds)));
        $optedInIds = Organization::query()->whereNotNull('model_training_opt_in_at')->whereIn('id', $organizationIds)->pluck('id')->map(fn ($id): int => (int) $id)->all();
        if (count($optedInIds) !== count($organizationIds)) {
            $this->error('The release includes an organization without current training consent. Export and train a replacement without it.');

            return self::FAILURE;
        }
        foreach ($organizationIds as $organizationId) {
            $consentedAt = $consentTimestamps[$organizationId] ?? $consentTimestamps[(string) $organizationId] ?? null;
            $currentTimestamp = Organization::query()->whereKey($organizationId)->value('model_training_opt_in_at');
            if ($consentedAt === null || $currentTimestamp === null || CarbonImmutable::parse($currentTimestamp)->toIso8601String() !== $consentedAt) {
                $this->error('Training consent changed since this dataset was exported. Export and train a replacement.');

                return self::FAILURE;
            }
        }
        if (! $gpuClient->modelIsReady($version)) {
            $this->error('The configured NVIDIA endpoint does not report this model version as ready.');

            return self::FAILURE;
        }

        $activated = DB::transaction(function () use ($version, $organizationIds, $consentTimestamps): bool {
            $contributors = Organization::query()->whereIn('id', $organizationIds)->lockForUpdate()->get()->keyBy('id');
            if ($contributors->count() !== count($organizationIds)) {
                return false;
            }
            foreach ($organizationIds as $organizationId) {
                $consentedAt = $consentTimestamps[$organizationId] ?? $consentTimestamps[(string) $organizationId] ?? null;
                $organization = $contributors->get($organizationId);
                if ($organization === null || $consentedAt === null || ! $organization->hasOptedIntoModelTraining()
                    || $organization->model_training_opt_in_at->toIso8601String() !== $consentedAt) {
                    return false;
                }
            }

            ForecastModelRelease::query()->where('status', 'active')->lockForUpdate()->update(['status' => 'retired']);
            ForecastModelRelease::create([
                'version' => $version,
                'training_organization_ids' => $organizationIds,
                'status' => 'active',
                'activated_by' => auth()->id(),
                'activated_at' => now(),
            ]);

            return true;
        });
        if (! $activated) {
            $this->error('Training consent changed during activation. Export and train a replacement.');

            return self::FAILURE;
        }

        $this->info("Forecast model {$version} activated for GPU serving.");

        return self::SUCCESS;
    }
}
