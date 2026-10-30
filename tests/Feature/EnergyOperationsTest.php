<?php

namespace Tests\Feature;

use App\Models\OperationalAlert;
use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\User;
use App\Services\DailyEnergySummaryService;
use App\Services\OperationalAlertService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnergyOperationsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{User, Organization} */
    private function workspace(string $role = 'owner'): array
    {
        $user = User::factory()->create();
        $organization = Organization::create(['name' => 'Energy Operations', 'trial_ends_at' => now()->addDays(30)]);
        $organization->users()->attach($user->id, ['role' => $role]);
        $organization->subscription()->create(['plan_key' => 'site', 'status' => 'trialing', 'monthly_price_cents' => 4900]);

        return [$user, $organization];
    }

    public function test_daily_summaries_use_local_day_length_for_daylight_saving_coverage(): void
    {
        [, $organization] = $this->workspace();
        $building = $organization->buildings()->create(['name' => 'Eastern Site', 'timezone' => 'America/New_York', 'currency' => 'USD', 'energy_rate' => 0.15]);
        $start = CarbonImmutable::parse('2026-03-08 00:00', 'America/New_York');
        $end = $start->addDay();
        for ($timestamp = $start->utc(); $timestamp->lt($end->utc()); $timestamp = $timestamp->addHour()) {
            $building->readings()->create(['recorded_at' => $timestamp, 'kwh' => 2]);
        }

        app(DailyEnergySummaryService::class)->rebuild($building, ['2026-03-08']);

        $summary = $building->dailySummaries()->firstOrFail();
        $this->assertSame('2026-03-08', $summary->summary_date->toDateString());
        $this->assertSame(23, $summary->reading_count);
        $this->assertSame(23, $summary->expected_count);
        $this->assertSame(100.0, (float) $summary->coverage_percent);
    }

    public function test_new_dashboard_pages_and_exports_are_scoped_to_the_active_organization(): void
    {
        [$user, $organization] = $this->workspace();
        [, $otherOrganization] = $this->workspace();
        $building = $organization->buildings()->create(['name' => 'Visible Site', 'timezone' => 'UTC', 'currency' => 'USD', 'energy_rate' => 0.15]);
        $otherBuilding = $otherOrganization->buildings()->create(['name' => 'Private Site', 'timezone' => 'UTC', 'currency' => 'USD', 'energy_rate' => 0.15]);
        $organization->opportunities()->create(['building_id' => $building->id, 'source' => 'manual', 'title' => 'Visible action', 'priority' => 'medium', 'status' => 'new']);
        $privateOpportunity = $otherOrganization->opportunities()->create(['building_id' => $otherBuilding->id, 'source' => 'manual', 'title' => 'Private action', 'priority' => 'high', 'status' => 'new']);
        $privateAlert = OperationalAlert::create(['organization_id' => $otherOrganization->id, 'building_id' => $otherBuilding->id, 'type' => 'low_coverage', 'severity' => 'high', 'title' => 'Private alert', 'message' => 'Private signal.', 'fingerprint' => 'private:alert', 'status' => 'open', 'detected_at' => now()]);

        foreach (['dashboard', 'analytics', 'forecasts.index', 'reports.index', 'opportunities.index', 'alerts.index', 'data-health'] as $route) {
            $this->actingAs($user)->get(route($route))->assertOk()->assertDontSee('Private action')->assertDontSee('Private Site');
        }
        $response = $this->actingAs($user)->get(route('reports.export', ['type' => 'opportunities']))->assertOk();
        ob_start();
        $response->baseResponse->sendContent();
        $csv = (string) ob_get_clean();
        self::assertStringContainsString('Visible action', $csv);
        self::assertStringNotContainsString('Private action', $csv);
        $this->actingAs($user)->get(route('reports.export', ['type' => 'energy', 'building_id' => $otherBuilding->id]))->assertNotFound();
        $this->actingAs($user)->patch(route('opportunities.update', $privateOpportunity), ['status' => 'planned', 'priority' => 'high'])->assertNotFound();
        $this->actingAs($user)->patch(route('alerts.update', $privateAlert), ['status' => 'resolved'])->assertNotFound();
        $this->actingAs($user)->patch(route('buildings.settings.update', $otherBuilding), ['name' => 'Changed'])->assertNotFound();
        $this->actingAs($user)->post(route('opportunities.store'), ['building_id' => $otherBuilding->id, 'title' => 'Cross-workspace action', 'priority' => 'high'])->assertStatus(422);
    }

    public function test_analytics_keeps_currency_estimates_separate_and_compares_prior_periods(): void
    {
        [$user, $organization] = $this->workspace();
        $usd = $organization->buildings()->create(['name' => 'USD Site', 'timezone' => 'UTC', 'currency' => 'USD', 'energy_rate' => 0.15]);
        $eur = $organization->buildings()->create(['name' => 'EUR Site', 'timezone' => 'UTC', 'currency' => 'EUR', 'energy_rate' => 0.30]);
        foreach ([$usd, $eur] as $building) {
            $building->dailySummaries()->create(['summary_date' => now()->toDateString(), 'kwh' => 100, 'peak_kw' => 10, 'interval_minutes' => 60, 'reading_count' => 24, 'expected_count' => 24, 'coverage_percent' => 100]);
            $building->dailySummaries()->create(['summary_date' => now()->subDays(30)->toDateString(), 'kwh' => 50, 'peak_kw' => 6, 'interval_minutes' => 60, 'reading_count' => 24, 'expected_count' => 24, 'coverage_percent' => 100]);
        }

        $this->actingAs($user)->get(route('analytics', ['range' => 30]))
            ->assertOk()
            ->assertSee('USD 15.00')
            ->assertSee('EUR 30.00')
            ->assertSee('100.0% versus the previous period.');
    }

    public function test_members_can_manage_opportunities_and_alerts_but_cannot_activate_plans(): void
    {
        [$owner, $organization] = $this->workspace();
        $member = User::factory()->create();
        $organization->users()->attach($member->id, ['role' => 'member']);
        $building = $organization->buildings()->create(['name' => 'Member Site', 'timezone' => 'UTC', 'currency' => 'USD', 'energy_rate' => 0.15]);

        $this->actingAs($member)->post(route('opportunities.store'), ['building_id' => $building->id, 'title' => 'Review schedule', 'priority' => 'high'])->assertRedirect();
        $opportunity = Opportunity::firstOrFail();
        $this->actingAs($member)->patch(route('opportunities.update', $opportunity), ['status' => 'planned', 'priority' => 'high', 'note' => 'Assigned in weekly review.'])->assertRedirect();
        $this->assertDatabaseHas('opportunity_activities', ['opportunity_id' => $opportunity->id, 'user_id' => $member->id, 'to_status' => 'planned']);

        $alert = OperationalAlert::create(['organization_id' => $organization->id, 'building_id' => $building->id, 'type' => 'low_coverage', 'severity' => 'medium', 'title' => 'Coverage warning', 'message' => 'Coverage is below threshold.', 'fingerprint' => 'coverage:member', 'status' => 'open', 'detected_at' => now()]);
        $this->actingAs($member)->patch(route('alerts.update', $alert), ['status' => 'acknowledged'])->assertRedirect();
        $this->assertDatabaseHas('operational_alerts', ['id' => $alert->id, 'status' => 'acknowledged', 'acknowledged_by' => $member->id]);

        $this->actingAs($member)->post(route('billing.activate'), ['plan_key' => 'site', 'activation_code' => '4242 4242 4242 4242'])->assertForbidden();
        $this->actingAs($owner)->post(route('billing.activate'), ['plan_key' => 'site', 'activation_code' => '4242 4242 4242 4242'])->assertRedirect();
    }

    public function test_a_new_matching_alert_event_reopens_a_resolved_alert(): void
    {
        [, $organization] = $this->workspace();
        $building = $organization->buildings()->create(['name' => 'Alert Site', 'timezone' => 'UTC', 'currency' => 'USD', 'energy_rate' => 0.15]);
        $alert = OperationalAlert::create(['organization_id' => $organization->id, 'building_id' => $building->id, 'type' => 'low_coverage', 'severity' => 'medium', 'title' => 'Old signal', 'message' => 'Old event.', 'fingerprint' => 'coverage:site:day', 'status' => 'resolved', 'detected_at' => now()->subDay(), 'resolved_at' => now()]);

        app(OperationalAlertService::class)->open($building, 'low_coverage', 'high', 'New signal', 'Coverage remains low.', 'coverage:site:day');

        $alert->refresh();
        $this->assertSame('open', $alert->status);
        $this->assertSame('New signal', $alert->title);
        $this->assertNull($alert->resolved_at);
    }

    public function test_backfill_is_idempotent_for_summaries_and_recommendation_opportunities(): void
    {
        [, $organization] = $this->workspace();
        $building = $organization->buildings()->create(['name' => 'Backfill Site', 'timezone' => 'UTC', 'currency' => 'USD', 'energy_rate' => 0.15]);
        foreach (range(0, 23) as $hour) {
            $building->readings()->create(['recorded_at' => CarbonImmutable::parse('2026-10-01', 'UTC')->addHours($hour), 'kwh' => 2]);
        }
        $forecast = $building->forecasts()->create(['status' => 'complete', 'source' => 'groq']);
        $forecast->recommendations()->create(['title' => 'Review operating hours', 'description' => 'Compare load with schedules.', 'priority' => 'medium']);

        $this->artisan('caldren:backfill-energy-operations')->assertSuccessful();
        $this->artisan('caldren:backfill-energy-operations')->assertSuccessful();

        $this->assertDatabaseCount('daily_energy_summaries', 1);
        $this->assertDatabaseCount('opportunities', 1);
    }

    public function test_customer_pages_use_plan_activation_language(): void
    {
        [$user] = $this->workspace();
        $responses = [
            $this->get(route('home')),
            $this->get(route('product')),
            $this->get(route('login')),
            $this->get(route('register')),
            $this->actingAs($user)->get(route('dashboard')),
            $this->actingAs($user)->get(route('buildings.index')),
            $this->actingAs($user)->get(route('billing')),
        ];

        foreach ($responses as $response) {
            $response->assertOk();
            self::assertDoesNotMatchRegularExpression('/\b(?:sandbox|test|demo|simulation|simulated|design-preview)\b/i', $response->getContent());
        }
        $responses[6]->assertSee('Plan activation code')->assertSee('Activation history');
    }
}
