<?php

namespace Tests\Feature;

use App\Mail\ContactInquiry;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CaldrenTest extends TestCase
{
    use RefreshDatabase;

    private function workspace(): array
    {
        $user = User::factory()->create();
        $organization = Organization::create(['name' => 'Riverside Facilities', 'trial_ends_at' => now()->addDays(30)]);
        $organization->users()->attach($user->id, ['role' => 'owner']);
        $organization->subscription()->create(['status' => 'trialing', 'monthly_price_cents' => 9900]);

        return [$user, $organization];
    }

    public function test_public_pages_render(): void
    {
        foreach (['/', '/product', '/how-it-works', '/pricing', '/contact', '/privacy', '/terms', '/register', '/login'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_contact_inquiry_is_saved_and_sent_to_the_configured_inbox(): void
    {
        Mail::fake();
        config()->set('mail.contact_to', 'hello@caldren.net');

        $this->post(route('contact.store'), [
            'name' => 'Ada North',
            'email' => 'ada@example.com',
            'company' => 'North Estates',
            'message' => 'We manage three offices and have monthly bills.',
        ])->assertRedirect()->assertSessionHas('contact_success');

        $this->assertDatabaseHas('contact_leads', ['name' => 'Ada North', 'email' => 'ada@example.com']);
        $this->get(route('contact'))->assertOk()->assertSee('Thanks. We received your message.');
        Mail::assertSent(ContactInquiry::class, function (ContactInquiry $mail): bool {
            return $mail->hasTo('hello@caldren.net')
                && $mail->hasReplyTo('ada@example.com', 'Ada North')
                && $mail->inquiry['message'] === 'We manage three offices and have monthly bills.';
        });
    }

    public function test_invalid_contact_inquiry_is_not_saved_or_sent(): void
    {
        Mail::fake();

        $this->post(route('contact.store'), [
            'name' => '',
            'email' => 'not-an-email',
            'message' => '',
        ])->assertSessionHasErrors(['name', 'email', 'message']);

        $this->assertDatabaseCount('contact_leads', 0);
        Mail::assertNothingSent();
    }

    public function test_contact_inquiry_remains_saved_when_notification_fails(): void
    {
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('Mail transport unavailable'));

        $this->post(route('contact.store'), [
            'name' => 'Ada North',
            'email' => 'ada@example.com',
            'message' => 'We have bills for two sites.',
        ])->assertRedirect()->assertSessionHas('warning');

        $this->assertDatabaseHas('contact_leads', ['name' => 'Ada North', 'email' => 'ada@example.com']);
        $this->get(route('contact'))->assertOk()->assertSee('Your message was saved')->assertSee('hello@caldren.net');
    }

    public function test_signup_starts_trial_and_creates_workspace(): void
    {
        $this->post('/register', ['name' => 'Ada North', 'email' => 'ada@example.com', 'organization' => 'North Estates', 'password' => 'very-long-secret', 'password_confirmation' => 'very-long-secret'])->assertRedirect('/dashboard');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('organizations', ['name' => 'North Estates']);
        $this->assertTrue(Organization::first()->trial_ends_at->isFuture());
        $this->assertDatabaseHas('organization_user', ['role' => 'owner']);
    }

    public function test_building_routes_are_scoped_to_organization(): void
    {
        [$user] = $this->workspace();
        [, $other] = $this->workspace();
        $building = $other->buildings()->create(['name' => 'Private Building', 'timezone' => 'UTC', 'currency' => 'USD', 'energy_rate' => 0.15]);
        $this->actingAs($user)->get(route('buildings.show', $building))->assertNotFound();
        $this->actingAs($user)->post(route('buildings.upload', $building), ['file' => UploadedFile::fake()->createWithContent('usage.csv', "timestamp,kwh\n2026-10-01 00:00,4\n")])->assertNotFound();
    }

    public function test_csv_import_validates_duplicates_and_saves_readings(): void
    {
        [$user,$organization] = $this->workspace();
        $building = $organization->buildings()->create(['name' => 'Tower A', 'timezone' => 'UTC', 'currency' => 'USD', 'energy_rate' => 0.15]);
        $valid = UploadedFile::fake()->createWithContent('usage.csv', "timestamp,kwh\n2026-10-01 00:00,4.5\n2026-10-01T02:00:00+01:00,3.5\n");
        $this->actingAs($user)->post(route('buildings.upload', $building), ['file' => $valid])->assertRedirect();
        $this->assertSame(2, $building->readings()->count());
        $duplicate = UploadedFile::fake()->createWithContent('usage.csv', "timestamp,kwh\n2026-10-01 00:00,4.5\n2026-10-01 00:00,3.5\n");
        $this->actingAs($user)->post(route('buildings.upload', $building), ['file' => $duplicate])->assertSessionHasErrors('file');
        $this->assertSame(2, $building->readings()->count());
    }

    public function test_expired_trial_blocks_writes_until_demo_payment(): void
    {
        [$user,$organization] = $this->workspace();
        $organization->update(['trial_ends_at' => now()->subDay()]);
        $this->actingAs($user)->post(route('buildings.store'), ['name' => 'Tower A', 'timezone' => 'UTC', 'currency' => 'USD', 'energy_rate' => 0.15])->assertSessionHasErrors('access');
        $this->actingAs($user)->post(route('billing.demo'), ['demo_card' => '4242 4242 4242 4242'])->assertRedirect();
        $this->assertDatabaseCount('demo_payments', 1);
        $this->assertTrue($organization->fresh()->load('subscription')->hasAccess());
        $this->actingAs($user)->post(route('buildings.store'), ['name' => 'Tower A', 'timezone' => 'UTC', 'currency' => 'USD', 'energy_rate' => 0.15])->assertRedirect();
    }

    public function test_forecast_is_saved_only_when_valid(): void
    {
        config()->set('services.openai.key', 'test-key');
        [$user,$organization] = $this->workspace();
        $building = $organization->buildings()->create(['name' => 'Tower A', 'timezone' => 'UTC', 'currency' => 'USD', 'energy_rate' => 0.15]);
        $first = CarbonImmutable::now('UTC')->subDays(14)->startOfDay();
        for ($day = 0; $day < 14; $day++) {
            for ($hour = 0; $hour < 24; $hour++) {
                $building->readings()->create(['recorded_at' => $first->addDays($day)->addHours($hour), 'kwh' => 5]);
            }
        }
        $last = $first->addDays(13);
        $days = [];
        for ($i = 1; $i <= 7; $i++) {
            $days[] = ['date' => $last->copy()->addDays($i)->toDateString(), 'kwh' => 126, 'peak_kw' => 6];
        }
        Http::fake(['api.openai.com/*' => Http::response(['output' => [['content' => [['type' => 'output_text', 'text' => json_encode(['days' => $days, 'recommendations' => [['title' => 'Review peak loads', 'description' => 'Check flexible loads in peak hours.', 'priority' => 'medium']]])]]]]], 200)]);
        $this->actingAs($user)->post(route('buildings.forecast', $building))->assertRedirect();
        $this->assertDatabaseHas('forecast_runs', ['building_id' => $building->id, 'status' => 'complete']);
        $this->assertDatabaseCount('forecast_points', 7);
    }

    public function test_dashboard_and_bill_only_building_show_spending_trend(): void
    {
        [$user,$organization] = $this->workspace();
        $building = $organization->buildings()->create(['name' => 'Riverside Plaza', 'timezone' => 'UTC', 'currency' => 'USD', 'energy_rate' => 0.15]);
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('Riverside Plaza');
        $this->actingAs($user)->get(route('buildings.index'))->assertOk()->assertSee('Find a building');
        foreach ([['2026-08-01', '2026-08-31', 1000, 160], ['2026-09-01', '2026-09-30', 1200, 190]] as [$start,$end,$kwh,$amount]) {
            $this->actingAs($user)->post(route('buildings.bills', $building), ['period_start' => $start, 'period_end' => $end, 'kwh' => $kwh, 'amount' => $amount])->assertRedirect();
        }
        $this->actingAs($user)->get(route('buildings.show', $building))->assertOk()->assertSee('Usage is rising')->assertSee('20.0%');
    }

    public function test_overlapping_bills_are_rejected(): void
    {
        [$user, $organization] = $this->workspace();
        $building = $organization->buildings()->create(['name' => 'Tower A', 'timezone' => 'UTC', 'currency' => 'USD', 'energy_rate' => 0.15]);
        $this->actingAs($user)->post(route('buildings.bills', $building), ['period_start' => '2026-09-01', 'period_end' => '2026-09-30', 'kwh' => 1200, 'amount' => 180])->assertRedirect();
        $this->actingAs($user)->post(route('buildings.bills', $building), ['period_start' => '2026-09-15', 'period_end' => '2026-10-14', 'kwh' => 1100, 'amount' => 170])->assertSessionHasErrors('period_start');
        $this->assertSame(1, $building->bills()->count());
    }

    public function test_existing_user_can_accept_invitation_and_switch_workspace(): void
    {
        [$owner,$organization] = $this->workspace();
        [$teammate,$ownOrganization] = $this->workspace();
        $token = 'test-invitation-token';
        Invitation::create(['organization_id' => $organization->id, 'email' => $teammate->email, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(7)]);
        $this->actingAs($teammate)->get(route('invitation.accept', $token))->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('organization_user', ['organization_id' => $organization->id, 'user_id' => $teammate->id, 'role' => 'member']);
        $this->actingAs($teammate)->get(route('dashboard'))->assertOk()->assertSee($organization->name);
        $this->actingAs($teammate)->post(route('organization.switch'), ['organization_id' => $ownOrganization->id])->assertRedirect(route('dashboard'));
        $this->actingAs($teammate)->get(route('dashboard'))->assertOk()->assertSee($ownOrganization->name);
    }

    public function test_demo_checkout_rejects_other_numbers(): void
    {
        [$user] = $this->workspace();
        $this->actingAs($user)->post(route('billing.demo'), ['demo_card' => '4111 1111 1111 1111'])->assertSessionHasErrors('demo_card');
        $this->assertDatabaseCount('demo_payments', 0);
    }

    public function test_sparse_readings_cannot_generate_a_forecast(): void
    {
        config()->set('services.openai.key', 'test-key');
        [$user,$organization] = $this->workspace();
        $building = $organization->buildings()->create(['name' => 'Tower A', 'timezone' => 'UTC', 'currency' => 'USD', 'energy_rate' => 0.15]);
        for ($i = 1; $i <= 14; $i++) {
            $building->readings()->create(['recorded_at' => now()->subDays($i)->startOfDay(), 'kwh' => 100]);
        }
        $this->actingAs($user)->post(route('buildings.forecast', $building))->assertSessionHasErrors('forecast');
        $this->assertDatabaseCount('forecast_runs', 0);
    }

    public function test_invalid_ai_numbers_are_not_published(): void
    {
        config()->set('services.openai.key', 'test-key');
        [$user,$organization] = $this->workspace();
        $building = $organization->buildings()->create(['name' => 'Tower A', 'timezone' => 'UTC', 'currency' => 'USD', 'energy_rate' => 0.15]);
        $first = CarbonImmutable::now('UTC')->subDays(14)->startOfDay();
        for ($day = 0; $day < 14; $day++) {
            for ($hour = 0; $hour < 24; $hour++) {
                $building->readings()->create(['recorded_at' => $first->addDays($day)->addHours($hour), 'kwh' => 5]);
            }
        }
        $days = [];
        for ($i = 1; $i <= 7; $i++) {
            $days[] = ['date' => $first->addDays(13 + $i)->toDateString(), 'kwh' => 999999, 'peak_kw' => 6];
        }
        Http::fake(['api.openai.com/*' => Http::response(['output' => [['content' => [['type' => 'output_text', 'text' => json_encode(['days' => $days, 'recommendations' => []])]]]]], 200)]);
        $this->actingAs($user)->post(route('buildings.forecast', $building))->assertSessionHas('error');
        $this->assertDatabaseHas('forecast_runs', ['building_id' => $building->id, 'status' => 'failed']);
        $this->assertDatabaseCount('forecast_points', 0);
    }
}
