<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\ForecastModelRelease;
use App\Models\Organization;
use App\Models\UsageReading;
use App\Models\User;
use App\Services\ForecastService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ForecastGpuIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Building $building;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->organization = Organization::create(['name' => 'Energy Team', 'trial_ends_at' => now()->addDays(30)]);
        $this->organization->users()->attach($this->owner->id, ['role' => 'owner']);
        $this->building = Building::create(['organization_id' => $this->organization->id, 'name' => 'North Site', 'timezone' => 'UTC']);
        $this->addRecentReadings($this->building);
        config()->set('services.openai.key', 'openai-test-key');
        config()->set('services.openai.model', 'test-model');
        config()->set('services.nvidia.forecast_url', 'https://gpu.example.test');
        config()->set('services.nvidia.api_token', 'gpu-test-token');
        config()->set('services.nvidia.request_timeout', 1);
    }

    public function test_valid_gpu_output_is_saved_with_source_and_model_version(): void
    {
        $this->activateModel();
        Http::preventStrayRequests();
        Http::fake([
            'gpu.example.test/v1/forecast' => Http::response($this->gpuDays(), 200),
            'api.openai.com/v1/responses' => Http::response($this->openAiResponse(['recommendations' => []]), 200),
        ]);

        $run = app(ForecastService::class)->generate($this->building);

        $this->assertSame('complete', $run->status);
        $this->assertSame('nvidia_triton', $run->source);
        $this->assertSame('rf-v1', $run->model_version);
        $this->assertCount(7, $run->points);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://gpu.example.test/v1/forecast'
            && $request->hasHeader('Authorization', 'Bearer gpu-test-token'));
    }

    public function test_invalid_gpu_output_uses_openai_fallback_and_labels_the_source(): void
    {
        $this->activateModel();
        $invalid = $this->gpuDays();
        $invalid['days'][0]['kwh'] = 9000;
        Http::preventStrayRequests();
        Http::fake([
            'gpu.example.test/v1/forecast' => Http::response($invalid, 200),
            'api.openai.com/v1/responses' => Http::response($this->openAiResponse($this->openAiForecast()), 200),
        ]);

        $run = app(ForecastService::class)->generate($this->building);

        $this->assertSame('complete', $run->status);
        $this->assertSame('openai_fallback', $run->source);
        $this->assertNull($run->model_version);
        $this->assertCount(7, $run->points);
    }

    public function test_gpu_timeout_uses_openai_fallback(): void
    {
        $this->activateModel();
        Http::preventStrayRequests();
        Http::fake([
            'gpu.example.test/v1/forecast' => fn () => throw new ConnectionException('GPU gateway timed out.'),
            'api.openai.com/v1/responses' => Http::response($this->openAiResponse($this->openAiForecast()), 200),
        ]);

        $run = app(ForecastService::class)->generate($this->building);

        $this->assertSame('complete', $run->status);
        $this->assertSame('openai_fallback', $run->source);
    }

    public function test_model_release_requires_a_passing_walk_forward_evaluation(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('model-training/manifest.json', json_encode([
            'training_organization_ids' => [$this->organization->id],
            'training_consent_timestamps' => [(string) $this->organization->id => now()->toIso8601String()],
        ], JSON_THROW_ON_ERROR));
        Storage::disk('local')->put('model-training/evaluation.json', json_encode([
            'version' => 'candidate-v2',
            'approved' => false,
            'candidate_daily_kwh_mae' => 20,
            'seasonal_daily_kwh_mae' => 22,
            'candidate_peak_kw_mae' => 2,
            'seasonal_peak_kw_mae' => 2.2,
        ], JSON_THROW_ON_ERROR));

        $exitCode = Artisan::call('caldren:activate-forecast-model', [
            'version' => 'candidate-v2',
            '--manifest' => 'model-training/manifest.json',
            '--evaluation' => 'model-training/evaluation.json',
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertSame(0, ForecastModelRelease::query()->count());
    }

    public function test_gpu_and_openai_failures_keep_readings_and_record_retryable_failure(): void
    {
        $this->activateModel();
        Http::preventStrayRequests();
        Http::fake([
            'gpu.example.test/v1/forecast' => Http::response(['message' => 'unavailable'], 503),
            'api.openai.com/v1/responses' => Http::response(['error' => 'unavailable'], 503),
        ]);

        $run = app(ForecastService::class)->generate($this->building);

        $this->assertSame('failed', $run->status);
        $this->assertNotEmpty($run->failure_reason);
        $this->assertSame(14 * 96, $this->building->readings()->count());
        $this->assertCount(0, $run->points);
    }

    public function test_workspace_training_is_off_by_default_and_only_owner_can_change_it(): void
    {
        $this->assertFalse($this->organization->fresh()->hasOptedIntoModelTraining());
        $member = User::factory()->create();
        $this->organization->users()->attach($member->id, ['role' => 'member']);
        $this->actingAs($member)->post(route('team.model-training-consent'), ['enabled' => '1', 'consent' => '1'])->assertForbidden();
        $this->assertDatabaseCount('model_training_consent_events', 0);

        $this->actingAs($this->owner)->post(route('team.model-training-consent'), ['enabled' => '1', 'consent' => '1'])->assertRedirect();
        $this->assertTrue($this->organization->fresh()->hasOptedIntoModelTraining());
        $this->assertDatabaseHas('model_training_consent_events', ['organization_id' => $this->organization->id, 'status' => 'opted_in']);
    }

    public function test_withdrawal_retires_a_contributing_model_and_requires_replacement(): void
    {
        $this->organization->update(['model_training_opt_in_at' => now(), 'model_training_consent_version' => 'v1', 'model_training_consented_by' => $this->owner->id]);
        $release = $this->activateModel([$this->organization->id]);

        $this->actingAs($this->owner)->post(route('team.model-training-consent'), ['enabled' => '0'])->assertRedirect();

        $this->assertSame('withdrawn', $release->fresh()->status);
        $this->assertNull($this->organization->fresh()->model_training_opt_in_at);
        $this->assertDatabaseHas('model_training_consent_events', ['organization_id' => $this->organization->id, 'status' => 'withdrawn']);
    }

    public function test_training_export_contains_only_post_consent_readings_and_no_workspace_identifiers(): void
    {
        Storage::fake('local');
        $this->organization->update(['model_training_opt_in_at' => now()->subDays(2), 'model_training_consent_version' => 'v1']);
        $other = Organization::create(['name' => 'Private Org Label', 'trial_ends_at' => now()->addDays(30)]);
        $otherBuilding = Building::create(['organization_id' => $other->id, 'name' => 'Private Building Label', 'timezone' => 'UTC']);
        $this->addRecentReadings($otherBuilding);

        $exitCode = Artisan::call('caldren:export-forecast-training-data', ['--output' => 'exports/training.csv', '--manifest' => 'exports/manifest.json']);
        $this->assertSame(0, $exitCode, Artisan::output());
        $csv = Storage::disk('local')->get('exports/training.csv');
        $manifest = json_decode(Storage::disk('local')->get('exports/manifest.json'), true);

        $this->assertStringNotContainsString('Private Org Label', $csv);
        $this->assertStringNotContainsString('Private Building Label', $csv);
        $this->assertSame([$this->organization->id], $manifest['training_organization_ids']);
        $this->assertStringContainsString('series_id,day_index,weekday,daily_kwh,peak_kw', $csv);
    }

    private function activateModel(?array $trainingOrganizationIds = null): ForecastModelRelease
    {
        return ForecastModelRelease::create([
            'version' => 'rf-v1',
            'training_organization_ids' => $trainingOrganizationIds ?? [],
            'status' => 'active',
            'activated_at' => now(),
        ]);
    }

    private function addRecentReadings(Building $building): void
    {
        $start = CarbonImmutable::now('UTC')->startOfDay()->subDays(14);
        $rows = [];
        for ($day = 0; $day < 14; $day++) {
            for ($interval = 0; $interval < 96; $interval++) {
                $rows[] = [
                    'building_id' => $building->id,
                    'recorded_at' => $start->addDays($day)->addMinutes($interval * 15)->toDateTimeString(),
                    'kwh' => 1.5,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }
        UsageReading::query()->insert($rows);
    }

    private function gpuDays(): array
    {
        $yesterday = CarbonImmutable::now('UTC')->startOfDay()->subDay();
        $days = [];
        for ($index = 1; $index <= 7; $index++) {
            $days[] = ['date' => $yesterday->addDays($index)->toDateString(), 'kwh' => 144, 'peak_kw' => 6];
        }

        return ['model_version' => 'rf-v1', 'days' => $days];
    }

    private function openAiForecast(): array
    {
        return ['days' => $this->gpuDays()['days'], 'recommendations' => []];
    }

    private function openAiResponse(array $data): array
    {
        return ['output' => [['content' => [['type' => 'output_text', 'text' => json_encode($data, JSON_THROW_ON_ERROR)]]]]];
    }
}
