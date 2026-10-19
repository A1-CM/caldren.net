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

    private function configureSuccessfulTurnstile(): array
    {
        config()->set('services.turnstile.site_key', '1x00000000000000000000AA');
        config()->set('services.turnstile.secret_key', '1x0000000000000000000000000000000AA');
        Http::preventStrayRequests();
        Http::fake([
            'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response(['success' => true], 200),
        ]);

        return ['cf-turnstile-response' => 'test-turnstile-token'];
    }

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
        foreach (['/', '/product', '/register', '/login'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_homepage_renders_all_illustrative_testimonials_and_manual_controls(): void
    {
        config()->set('services.turnstile.site_key', 'test-site-key');

        $this->get(route('home'))
            ->assertSee('class="cf-turnstile"', false)
            ->assertSee('Sample statements for design preview only. These are not real customer testimonials.')
            ->assertSee('A clear view of utility bills and interval readings would help our energy team prepare for the week.')
            ->assertSee('Putting a demand outlook beside its historical baseline would give us a better starting point for review.')
            ->assertSee('Comparing demand patterns across sites would help us see where our team should look closer.')
            ->assertSee('Tariff-aware opportunities could help our operations and finance teams decide what to investigate next.')
            ->assertSee('data-testimonial-carousel', false)
            ->assertSee('data-testimonial-select="0"', false)
            ->assertSee('data-testimonial-next', false)
            ->assertSee('Previous testimonial');
    }

    public function test_marketing_copy_positions_caldren_around_energy_forecasting_and_optimization(): void
    {
        $this->get(route('home'))
            ->assertSee('AI ENERGY INTELLIGENCE FOR BUILDINGS')
            ->assertSee('See tomorrow’s energy demand.')
            ->assertSee('Make better decisions today.')
            ->assertSee('ENERGY SIGNALS TO DECISIONS')
            ->assertSee('Where is there an opportunity to review?')
            ->assertSee('Caldren supports optimization decisions; your team remains in control.')
            ->assertSee('USD 49/month')
            ->assertSee('USD 99/month')
            ->assertSee('Contact sales')
            ->assertDontSee('$249')
            ->assertSee('no card details are stored');

        $this->get(route('product'))
            ->assertSee('AI ENERGY FORECASTING &amp; OPTIMIZATION INSIGHTS', false)
            ->assertSee('Forecast demand.')
            ->assertSee('Find where to optimize.')
            ->assertSee('Caldren does not guarantee savings or control equipment.');
    }

    public function test_previous_marketing_urls_redirect_to_home_sections(): void
    {
        $home = $this->get(route('home'))->assertOk();

        foreach (['how-it-works', 'pricing', 'contact', 'privacy', 'terms'] as $section) {
            $this->get('/'.$section)->assertStatus(301)->assertRedirect('/#'.$section);
            $home->assertSee('id="'.$section.'"', false);
        }
    }

    public function test_contact_inquiry_is_saved_and_sent_to_the_configured_inbox(): void
    {
        Mail::fake();
        $turnstile = $this->configureSuccessfulTurnstile();
        config()->set('mail.contact_to', 'hello@caldren.net');

        $this->post(route('contact.store'), $turnstile + [
            'name' => 'Ada North',
            'email' => 'ada@example.com',
            'company' => 'North Estates',
            'message' => 'We manage three offices and have monthly bills.',
        ])->assertRedirect(route('home').'#contact')->assertSessionHas('contact_success');

        $this->assertDatabaseHas('contact_leads', ['name' => 'Ada North', 'email' => 'ada@example.com', 'inquiry_type' => 'general']);
        $this->get(route('home'))->assertOk()->assertSee('Thanks. We received your message.');
        Mail::assertSent(ContactInquiry::class, function (ContactInquiry $mail): bool {
            return $mail->hasTo('hello@caldren.net')
                && $mail->hasReplyTo('ada@example.com', 'Ada North')
                && $mail->inquiry['message'] === 'We manage three offices and have monthly bills.';
        });
    }

    public function test_campus_sales_inquiry_saves_portfolio_details_and_emails_the_configured_inbox(): void
    {
        Mail::fake();
        $turnstile = $this->configureSuccessfulTurnstile();
        config()->set('mail.contact_to', 'hello@caldren.net');
        $inquiry = $turnstile + [
            'name' => 'Ada North',
            'email' => 'ada@north.example',
            'company' => 'North Estates',
            'estimated_building_count' => 84,
            'operating_regions' => 'United Kingdom and Ireland',
            'available_energy_data' => 'both',
            'goals' => 'Review demand peaks and tariff opportunities across sites.',
            'target_timeline' => 'within_3_months',
            'message' => 'We are planning a portfolio review.',
        ];

        $this->post(route('contact.sales.store'), $inquiry)
            ->assertRedirect(route('home').'#pricing')
            ->assertSessionHas('sales_success')
            ->assertSessionHas('open_sales_modal', true);

        $this->assertDatabaseHas('contact_leads', [
            'name' => 'Ada North',
            'email' => 'ada@north.example',
            'company' => 'North Estates',
            'inquiry_type' => 'campus_sales',
            'estimated_building_count' => 84,
            'operating_regions' => 'United Kingdom and Ireland',
            'available_energy_data' => 'both',
            'goals' => 'Review demand peaks and tariff opportunities across sites.',
            'target_timeline' => 'within_3_months',
            'message' => 'We are planning a portfolio review.',
        ]);
        Mail::assertSent(ContactInquiry::class, function (ContactInquiry $mail): bool {
            return $mail->hasTo('hello@caldren.net')
                && $mail->hasReplyTo('ada@north.example', 'Ada North')
                && $mail->inquiry['inquiry_type'] === 'campus_sales'
                && $mail->inquiry['estimated_building_count'] === 84;
        });
    }

    public function test_invalid_campus_sales_inquiry_reopens_the_dialog_without_saving(): void
    {
        Mail::fake();
        $turnstile = $this->configureSuccessfulTurnstile();

        $this->post(route('contact.sales.store'), $turnstile + [
            'name' => '',
            'email' => 'invalid',
            'company' => '',
            'estimated_building_count' => 'many',
            'operating_regions' => '',
            'available_energy_data' => 'meter_files',
            'goals' => '',
            'target_timeline' => 'someday',
        ])->assertRedirect(route('home').'#pricing')
            ->assertSessionHas('open_sales_modal', true)
            ->assertSessionHasErrorsIn('sales', ['name', 'email', 'company', 'estimated_building_count', 'operating_regions', 'available_energy_data', 'goals', 'target_timeline']);

        $this->assertDatabaseCount('contact_leads', 0);
        Mail::assertNothingSent();
    }

    public function test_campus_sales_inquiry_remains_saved_when_notification_fails(): void
    {
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('Mail transport unavailable'));
        $turnstile = $this->configureSuccessfulTurnstile();

        $this->post(route('contact.sales.store'), $turnstile + [
            'name' => 'Ada North',
            'email' => 'ada@north.example',
            'company' => 'North Estates',
            'estimated_building_count' => 84,
            'operating_regions' => 'United Kingdom',
            'available_energy_data' => 'monthly_bills',
            'goals' => 'Understand energy use across sites.',
            'target_timeline' => 'exploring',
        ])->assertRedirect(route('home').'#pricing')
            ->assertSessionHas('open_sales_modal', true)
            ->assertSessionHas('sales_warning');

        $this->assertDatabaseHas('contact_leads', ['email' => 'ada@north.example', 'inquiry_type' => 'campus_sales']);
        $this->get(route('home'))->assertSee('Your inquiry was saved')->assertSee('hello@caldren.net');
    }

    public function test_invalid_contact_inquiry_is_not_saved_or_sent(): void
    {
        Mail::fake();
        $turnstile = $this->configureSuccessfulTurnstile();

        $this->post(route('contact.store'), $turnstile + [
            'name' => '',
            'email' => 'not-an-email',
            'message' => '',
        ])->assertRedirect(route('home').'#contact')->assertSessionHasErrors(['name', 'email', 'message'])->assertSessionHasInput('email', 'not-an-email');

        $this->assertDatabaseCount('contact_leads', 0);
        Mail::assertNothingSent();
    }

    public function test_contact_inquiry_remains_saved_when_notification_fails(): void
    {
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('Mail transport unavailable'));
        $turnstile = $this->configureSuccessfulTurnstile();

        $this->post(route('contact.store'), $turnstile + [
            'name' => 'Ada North',
            'email' => 'ada@example.com',
            'message' => 'We have bills for two sites.',
        ])->assertRedirect(route('home').'#contact')->assertSessionHas('warning');

        $this->assertDatabaseHas('contact_leads', ['name' => 'Ada North', 'email' => 'ada@example.com']);
        $this->get(route('home'))->assertOk()->assertSee('Your message was saved')->assertSee('hello@caldren.net');
    }

    public function test_turnstile_widget_renders_on_both_contact_forms_login_and_registration(): void
    {
        config()->set('services.turnstile.site_key', 'test-site-key');

        $home = $this->get(route('home'))->assertOk();
        self::assertSame(2, substr_count($home->getContent(), 'class="cf-turnstile"'));

        $this->get(route('login'))->assertOk()->assertSee('class="cf-turnstile"', false);
        $this->get(route('register'))->assertOk()->assertSee('class="cf-turnstile"', false);
    }

    public function test_missing_turnstile_token_rejects_contact_without_saving_or_sending(): void
    {
        Mail::fake();
        config()->set('services.turnstile.secret_key', 'test-secret-key');
        Http::preventStrayRequests();

        $this->post(route('contact.store'), [
            'name' => 'Ada North',
            'email' => 'ada@example.com',
            'message' => 'Review demand patterns.',
        ])->assertRedirect(route('home').'#contact')
            ->assertSessionHasErrors(['turnstile']);

        $this->assertDatabaseCount('contact_leads', 0);
        Mail::assertNothingSent();
    }

    public function test_missing_turnstile_secret_rejects_registration_without_contacting_cloudflare(): void
    {
        config()->set('services.turnstile.secret_key', null);
        Http::preventStrayRequests();

        $this->post(route('register.store'), [
            'name' => 'Ada North',
            'email' => 'ada@example.com',
            'organization' => 'North Estates',
            'password' => 'very-long-secret',
            'password_confirmation' => 'very-long-secret',
            'cf-turnstile-response' => 'test-token',
        ])->assertRedirect(route('register'))
            ->assertSessionHasErrors(['turnstile']);

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('organizations', 0);
    }

    public function test_invalid_turnstile_token_rejects_login_without_authenticating(): void
    {
        $user = User::factory()->create(['password' => bcrypt('very-long-secret')]);
        config()->set('services.turnstile.secret_key', '1x0000000000000000000000000000000AA');
        Http::preventStrayRequests();
        Http::fake([
            'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response(['success' => false, 'error-codes' => ['timeout-or-duplicate']], 200),
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'very-long-secret',
            'cf-turnstile-response' => 'expired-or-reused-token',
        ])->assertRedirect(route('login'))
            ->assertSessionHasErrors(['turnstile']);

        $this->assertGuest();
    }

    public function test_turnstile_service_failure_rejects_registration_without_creating_an_account(): void
    {
        config()->set('services.turnstile.secret_key', '1x0000000000000000000000000000000AA');
        Http::preventStrayRequests();
        Http::fake([
            'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response(['success' => false], 503),
        ]);

        $this->post(route('register.store'), [
            'name' => 'Ada North',
            'email' => 'ada@example.com',
            'organization' => 'North Estates',
            'password' => 'very-long-secret',
            'password_confirmation' => 'very-long-secret',
            'cf-turnstile-response' => 'test-token',
        ])->assertRedirect(route('register'))
            ->assertSessionHasErrors(['turnstile']);

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('organizations', 0);
    }

    public function test_login_succeeds_after_cloudflare_verifies_the_token(): void
    {
        $user = User::factory()->create(['password' => bcrypt('very-long-secret')]);
        $turnstile = $this->configureSuccessfulTurnstile();

        $this->post(route('login.store'), $turnstile + [
            'email' => $user->email,
            'password' => 'very-long-secret',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://challenges.cloudflare.com/turnstile/v0/siteverify'
                && $request['secret'] === '1x0000000000000000000000000000000AA'
                && $request['response'] === 'test-turnstile-token';
        });
    }

    public function test_signup_starts_trial_and_creates_workspace(): void
    {
        $turnstile = $this->configureSuccessfulTurnstile();
        $this->post('/register', $turnstile + ['name' => 'Ada North', 'email' => 'ada@example.com', 'organization' => 'North Estates', 'password' => 'very-long-secret', 'password_confirmation' => 'very-long-secret'])->assertRedirect('/dashboard');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('organizations', ['name' => 'North Estates']);
        $organization = Organization::first();
        $this->assertTrue($organization->trial_ends_at->isFuture());
        $this->assertSame('site', $organization->subscription->plan_key);
        $this->assertSame(4900, $organization->subscription->monthly_price_cents);
        $this->assertSame(3, $organization->buildingLimit());
        $this->assertDatabaseHas('organization_user', ['role' => 'owner']);
    }

    public function test_pricing_shows_a_site_trial_portfolio_subscription_and_campus_sales_dialog(): void
    {
        $this->get(route('home'))
            ->assertSee('30 days')
            ->assertSee('No card required')
            ->assertSee('up to 3 buildings')
            ->assertSee('Site')
            ->assertSee('$49')
            ->assertSee('Portfolio')
            ->assertSee('$99')
            ->assertSee('10 buildings')
            ->assertSee('Contact sales')
            ->assertSee('campus-sales-dialog', false)
            ->assertSee('aria-haspopup="dialog"', false)
            ->assertSee('Estimated building count')
            ->assertSee('Operating regions')
            ->assertSee('Available energy data')
            ->assertSee('Target timeline')
            ->assertDontSee('$249')
            ->assertDontSee('DEMO CHECKOUT / NO REAL PAYMENT')
            ->assertSee('Portfolio activation is currently a demo checkout');
    }

    public function test_trial_workspace_can_add_its_third_building_but_not_a_fourth(): void
    {
        [$user, $organization] = $this->workspace();
        for ($number = 1; $number <= 2; $number++) {
            $organization->buildings()->create(['name' => 'Trial Site '.$number, 'timezone' => 'UTC', 'currency' => 'USD', 'energy_rate' => 0.15]);
        }

        $this->actingAs($user)->post(route('buildings.store'), ['name' => 'Trial Site 3', 'timezone' => 'UTC', 'currency' => 'USD', 'energy_rate' => 0.15])->assertRedirect();
        $this->assertSame(3, $organization->buildings()->count());
        $this->assertDatabaseHas('buildings', ['organization_id' => $organization->id, 'name' => 'Trial Site 1']);
        $this->actingAs($user)->post(route('buildings.store'), ['name' => 'Trial Site 4', 'timezone' => 'UTC', 'currency' => 'USD', 'energy_rate' => 0.15])->assertSessionHasErrors('name');
        $this->assertSame(3, $organization->buildings()->count());
    }

    public function test_trial_limit_does_not_delete_existing_buildings_above_three(): void
    {
        [$user, $organization] = $this->workspace();
        for ($number = 1; $number <= 5; $number++) {
            $organization->buildings()->create(['name' => 'Existing Site '.$number, 'timezone' => 'UTC', 'currency' => 'USD', 'energy_rate' => 0.15]);
        }

        $this->actingAs($user)->post(route('buildings.store'), ['name' => 'New Site', 'timezone' => 'UTC', 'currency' => 'USD', 'energy_rate' => 0.15])->assertSessionHasErrors('name');

        $this->assertSame(5, $organization->buildings()->count());
        $this->assertDatabaseHas('buildings', ['organization_id' => $organization->id, 'name' => 'Existing Site 5']);
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
        $this->actingAs($user)->get(route('billing'))->assertSee('Choose a self-serve plan')->assertSee('USD 49/month')->assertSee('up to 3 buildings')->assertDontSee('Campus — USD 249/month');
        $this->actingAs($user)->post(route('billing.demo'), ['demo_card' => '4242 4242 4242 4242', 'plan_key' => 'site'])->assertRedirect()->assertSessionHas('success');
        $this->actingAs($user)->get(route('billing'))->assertSee('Site')->assertSee('$49')->assertSee('Up to 3 buildings');
        $this->assertDatabaseHas('subscriptions', ['organization_id' => $organization->id, 'plan_key' => 'site', 'monthly_price_cents' => 4900, 'status' => 'active']);
        $this->assertDatabaseHas('demo_payments', ['organization_id' => $organization->id, 'amount_cents' => 4900, 'status' => 'simulated']);
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

    public function test_selected_site_plan_preserves_existing_buildings_and_blocks_additions_over_three(): void
    {
        [$user, $organization] = $this->workspace();
        $organization->update(['trial_ends_at' => now()->subDay()]);
        for ($number = 1; $number <= 3; $number++) {
            $organization->buildings()->create(['name' => 'Existing Site '.$number, 'timezone' => 'UTC', 'currency' => 'USD', 'energy_rate' => 0.15]);
        }

        $this->actingAs($user)->post(route('billing.demo'), ['demo_card' => '4242 4242 4242 4242', 'plan_key' => 'site'])->assertRedirect();
        $this->actingAs($user)->post(route('buildings.store'), ['name' => 'Extra Site', 'timezone' => 'UTC', 'currency' => 'USD', 'energy_rate' => 0.15])->assertSessionHasErrors('name');

        $this->assertSame(3, $organization->buildings()->count());
        $this->assertDatabaseHas('buildings', ['organization_id' => $organization->id, 'name' => 'Existing Site 1']);
        $this->actingAs($user)->get(route('buildings.index'))->assertSee('Existing Site 1');
    }

    public function test_portfolio_demo_checkout_records_its_price_and_building_limit(): void
    {
        [$user, $organization] = $this->workspace();
        $organization->update(['trial_ends_at' => now()->subDay()]);

        $this->actingAs($user)->post(route('billing.demo'), ['demo_card' => '4242 4242 4242 4242', 'plan_key' => 'portfolio'])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('subscriptions', ['organization_id' => $organization->id, 'plan_key' => 'portfolio', 'monthly_price_cents' => 9900, 'status' => 'active']);
        $this->assertDatabaseHas('demo_payments', ['organization_id' => $organization->id, 'amount_cents' => 9900, 'status' => 'simulated']);
        $this->assertSame(10, $organization->fresh()->buildingLimit());
    }

    public function test_existing_campus_demo_access_remains_active_but_new_campus_checkout_is_rejected(): void
    {
        [$user, $organization] = $this->workspace();
        $organization->update(['trial_ends_at' => now()->subDay()]);
        $organization->subscription()->update(['plan_key' => 'campus', 'status' => 'active', 'monthly_price_cents' => 24900, 'current_period_ends_at' => now()->addDays(10)]);
        $organization->buildings()->create(['name' => 'Campus Site', 'timezone' => 'UTC', 'currency' => 'USD', 'energy_rate' => 0.15]);

        $this->actingAs($user)->get(route('billing'))->assertSee('Campus')->assertSee('$249')->assertDontSee('<option value="campus"', false);
        $this->actingAs($user)->post(route('billing.demo'), ['demo_card' => '4242 4242 4242 4242', 'plan_key' => 'campus'])->assertSessionHasErrors('plan_key');

        $this->assertTrue($organization->fresh()->hasAccess());
        $this->assertSame(1, $organization->buildings()->count());
        $this->assertDatabaseCount('demo_payments', 0);
    }

    public function test_demo_checkout_rejects_an_unknown_plan_without_recording_payment(): void
    {
        [$user, $organization] = $this->workspace();
        $organization->update(['trial_ends_at' => now()->subDay()]);

        $this->actingAs($user)->post(route('billing.demo'), ['demo_card' => '4242 4242 4242 4242', 'plan_key' => 'unlimited'])->assertSessionHasErrors('plan_key');

        $this->assertDatabaseCount('demo_payments', 0);
        $this->assertDatabaseHas('subscriptions', ['organization_id' => $organization->id, 'status' => 'trialing']);
    }

    public function test_demo_checkout_rejects_other_numbers(): void
    {
        [$user] = $this->workspace();
        $this->actingAs($user)->post(route('billing.demo'), ['demo_card' => '4111 1111 1111 1111', 'plan_key' => 'portfolio'])->assertSessionHasErrors('demo_card');
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
