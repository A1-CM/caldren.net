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
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
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

    private function configureOAuthProvider(string $provider): void
    {
        config()->set("services.$provider.client_id", "$provider-client-id");
        config()->set("services.$provider.client_secret", "$provider-client-secret");
        config()->set("services.$provider.redirect", "http://localhost/auth/$provider/callback");
    }

    private function verifiedGoogleUser(string $id, string $email, ?string $name = 'OAuth Energy User'): SocialiteUser
    {
        return SocialiteUser::fake(['id' => $id, 'email' => $email, 'name' => $name])
            ->setRaw(['sub' => $id, 'email' => $email, 'email_verified' => true, 'name' => $name]);
    }

    private function groqResponse(array $data): array
    {
        return ['choices' => [['message' => ['content' => json_encode($data, JSON_THROW_ON_ERROR)]]]];
    }

    private function workspace(): array
    {
        $user = User::factory()->create();
        $organization = Organization::create(['name' => 'Riverside Facilities', 'trial_ends_at' => now()->addDays(30)]);
        $organization->users()->attach($user->id, ['role' => 'owner']);
        $organization->subscription()->create(['status' => 'trialing', 'monthly_price_cents' => 9900]);

        return [$user, $organization];
    }

    public function test_oauth_buttons_only_render_for_configured_providers(): void
    {
        config()->set('services.google.client_id', 'google-client');
        config()->set('services.google.client_secret', 'google-secret');
        config()->set('services.github.client_id', null);
        config()->set('services.github.client_secret', null);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Continue with Google')
            ->assertSee('fa-google', false)
            ->assertDontSee('Continue with GitHub');

        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Continue with Google')
            ->assertDontSee('Continue with GitHub');

        config()->set('services.google.client_id', null);
        config()->set('services.google.client_secret', null);
        $this->get(route('login'))->assertDontSee('oauth-options', false);
    }

    public function test_oauth_start_does_not_require_turnstile_for_google_or_github(): void
    {
        Http::preventStrayRequests();

        foreach (['google', 'github'] as $provider) {
            $this->configureOAuthProvider($provider);
            Socialite::fake($provider);

            $this->post(route('oauth.start'), ['provider' => $provider, 'origin' => 'login'])
                ->assertRedirect("https://socialite.fake/{$provider}/authorize");
        }

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_google_oauth_creates_a_site_trial_after_organization_setup(): void
    {
        $this->configureOAuthProvider('google');
        Socialite::fake('google', $this->verifiedGoogleUser('google-123', 'energy@example.com', 'Alex Energy'));

        $this->post(route('oauth.start'), ['provider' => 'google', 'origin' => 'register'])
            ->assertRedirect('https://socialite.fake/google/authorize');
        $this->get(route('oauth.callback', 'google'))->assertRedirect(route('oauth.complete'));
        $this->get(route('oauth.complete'))->assertOk()->assertSee('Alex Energy')->assertSee('Set up your organization');

        $turnstile = $this->configureSuccessfulTurnstile();
        $this->post(route('oauth.finish'), $turnstile + ['organization' => 'Energy Operations Group'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
        $user = User::where('email', 'energy@example.com')->firstOrFail();
        $this->assertSame('google-123', $user->google_id);
        $this->assertNotNull($user->email_verified_at);
        $this->assertDatabaseHas('organizations', ['name' => 'Energy Operations Group']);
        $organization = Organization::where('name', 'Energy Operations Group')->firstOrFail();
        $this->assertSame('site', $organization->subscription->plan_key);
        $this->assertSame('trialing', $organization->subscription->status);
        $this->assertDatabaseHas('organization_user', ['organization_id' => $organization->id, 'user_id' => $user->id, 'role' => 'owner']);
    }

    public function test_github_oauth_links_a_verified_primary_email_to_an_existing_account(): void
    {
        $this->configureOAuthProvider('github');
        $user = User::factory()->create(['email' => 'energy@example.com', 'github_id' => null]);
        Socialite::fake('github', SocialiteUser::fake(['id' => 'github-789', 'email' => 'energy@example.com', 'name' => 'Alex Energy']));

        $this->post(route('oauth.start'), ['provider' => 'github', 'origin' => 'login'])
            ->assertRedirect('https://socialite.fake/github/authorize');
        $this->get(route('oauth.callback', 'github'))->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'github_id' => 'github-789']);
    }

    public function test_unverified_google_email_cannot_register_an_oauth_account(): void
    {
        $this->configureOAuthProvider('google');
        $providerUser = SocialiteUser::fake(['id' => 'google-unverified', 'email' => 'unverified@example.com']);
        $providerUser->setRaw(['email' => 'unverified@example.com', 'email_verified' => false]);
        Socialite::fake('google', $providerUser);

        $this->post(route('oauth.start'), ['provider' => 'google', 'origin' => 'register'])
            ->assertRedirect('https://socialite.fake/google/authorize');
        $this->get(route('oauth.callback', 'google'))
            ->assertRedirect(route('register'))
            ->assertSessionHas('oauth_error');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_oauth_invitation_requires_a_matching_verified_email_and_joins_without_a_trial(): void
    {
        [$owner, $organization] = $this->workspace();
        $token = 'oauth-invitation-token';
        $invitation = Invitation::create([
            'organization_id' => $organization->id,
            'email' => 'invitee@example.com',
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDay(),
        ]);
        $this->configureOAuthProvider('google');
        Socialite::fake('google', $this->verifiedGoogleUser('google-invitee', 'invitee@example.com'));

        $this->post(route('oauth.start'), ['provider' => 'google', 'origin' => 'register', 'invite' => $token])
            ->assertRedirect('https://socialite.fake/google/authorize');
        $this->get(route('oauth.callback', 'google'))->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
        $invitee = User::where('email', 'invitee@example.com')->firstOrFail();
        $this->assertDatabaseHas('organization_user', ['organization_id' => $organization->id, 'user_id' => $invitee->id, 'role' => 'member']);
        $this->assertNotNull($invitation->fresh()->accepted_at);
        $this->assertDatabaseCount('organizations', 1);
        $this->assertDatabaseCount('subscriptions', 1);
    }

    public function test_oauth_invitation_rejects_a_different_verified_email(): void
    {
        [$owner, $organization] = $this->workspace();
        $token = 'oauth-mismatched-invitation-token';
        Invitation::create([
            'organization_id' => $organization->id,
            'email' => 'invited@example.com',
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDay(),
        ]);
        $this->configureOAuthProvider('google');
        Socialite::fake('google', $this->verifiedGoogleUser('google-wrong-email', 'different@example.com'));

        $this->post(route('oauth.start'), ['provider' => 'google', 'origin' => 'register', 'invite' => $token])
            ->assertRedirect('https://socialite.fake/google/authorize');
        $this->get(route('oauth.callback', 'google'))
            ->assertRedirect(route('register'))
            ->assertSessionHas('oauth_error');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('organizations', 1);
    }

    public function test_oauth_workspace_creation_requires_a_fresh_turnstile_verification(): void
    {
        $this->configureOAuthProvider('google');
        Socialite::fake('google', $this->verifiedGoogleUser('google-no-finish-token', 'new@example.com'));

        $this->post(route('oauth.start'), ['provider' => 'google', 'origin' => 'register']);
        $this->get(route('oauth.callback', 'google'))->assertRedirect(route('oauth.complete'));
        $this->get(route('oauth.complete'))->assertOk();

        config()->set('services.turnstile.site_key', 'test-site-key');
        config()->set('services.turnstile.secret_key', 'test-secret-key');
        Http::fake(['https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response(['success' => false], 200)]);

        $this->post(route('oauth.finish'), ['organization' => 'Energy Team'])
            ->assertRedirect(route('oauth.complete'))
            ->assertSessionHasErrors(['turnstile']);

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('organizations', 0);
    }

    public function test_oauth_rejects_provider_identity_conflicts_and_unavailable_emails(): void
    {
        $this->configureOAuthProvider('google');
        $existingUser = User::factory()->create(['email' => 'energy@example.com', 'google_id' => 'another-google-id']);
        Socialite::fake('google', $this->verifiedGoogleUser('google-conflict', 'energy@example.com'));
        $this->post(route('oauth.start'), ['provider' => 'google', 'origin' => 'login']);

        $this->get(route('oauth.callback', 'google'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('oauth_error');
        $this->assertGuest();
        $this->assertDatabaseHas('users', ['id' => $existingUser->id, 'google_id' => 'another-google-id']);

        $this->configureOAuthProvider('github');
        Socialite::fake('github', SocialiteUser::fake(['id' => 'github-no-email', 'email' => null]));
        $this->post(route('oauth.start'), ['provider' => 'github', 'origin' => 'register']);
        $this->get(route('oauth.callback', 'github'))->assertRedirect(route('register'))->assertSessionHas('oauth_error');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_oauth_callback_provider_error_returns_to_auth_form_without_creating_user(): void
    {
        $this->configureOAuthProvider('google');
        Socialite::fake('google', fn () => throw new \RuntimeException('Provider unavailable'));
        $this->post(route('oauth.start'), ['provider' => 'google', 'origin' => 'register']);

        $this->get(route('oauth.callback', 'google'))->assertRedirect(route('register'))->assertSessionHas('oauth_error');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_public_navigation_and_marketing_heroes_prioritize_the_product(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('href="'.route('home').'#about"', false)
            ->assertSee('class="console-trial-link" href="'.route('product').'"', false)
            ->assertDontSee('console-signin-link', false)
            ->assertSee('class="button" href="'.route('product').'">Explore Caldren Pulse</a>', false)
            ->assertSee('class="obs-outline-button" href="'.route('register').'">Start Site trial</a>', false);

        $this->get(route('product'))
            ->assertOk()
            ->assertSee('class="button" href="'.route('register').'">Start a 30-day Site trial</a>', false)
            ->assertSee('class="obs-outline-button" href="'.route('login').'">Sign in</a>', false);
    }

    public function test_public_and_account_pages_render_the_shared_observatory_footer(): void
    {
        foreach (['/', '/product', '/register', '/login', '/forgot-password', '/reset-password/footer-preview-token'] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('footer-contact-band', false)
                ->assertSee('https://embed.tawk.to/6ad5af224bf5ff34c946e7a0/1k59b88is', false)
                ->assertSee('Have an energy question? Let’s bring it into focus.')
                ->assertSee('Talk with the Caldren team')
                ->assertSee(asset('images/caldren-building-dusk.webp'), false)
                ->assertSee('href="'.route('home').'#contact"', false)
                ->assertSee('href="'.route('home').'#privacy"', false)
                ->assertSee('href="'.route('home').'#terms"', false);
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
            ->assertSee('Powering Smarter Energy Decisions')
            ->assertSee('Through Predictive Intelligence.')
            ->assertSee('Connecting Energy Intelligence')
            ->assertSee('to a Smarter, More Resilient Future.')
            ->assertSee('We unify energy assets, forecasting insights, and optimization scenarios')
            ->assertSee('How Do You Bring Every Energy Asset Into One View?')
            ->assertSee('How Can You Turn Forecasts Into Meaningful Insights?')
            ->assertSee('How Do You Turn Energy Insights Into Better Decisions?')
            ->assertSee('Caldren gives teams a structured way to review storage dispatch, load shifting, and procurement scenarios.')
            ->assertSee('Your team reviews and approves recommendations with a clear history.')
            ->assertSee('$49')
            ->assertSee('$99')
            ->assertSee('Contact sales')
            ->assertDontSee('$249')
            ->assertSee('no card details are stored');

        $product = $this->get(route('product'))
            ->assertSee('AI ENERGY FORECASTING &amp; OPTIMIZATION', false)
            ->assertSee('Groq Chat Completions')
            ->assertDontSee('OpenAI')
            ->assertSee('Caldren Pulse')
            ->assertSee('BETA PRODUCT')
            ->assertSee('seven-day demand')
            ->assertSee('The current forecast starts with a bounded energy summary.')
            ->assertSee('CUDA + RAPIDS / cuML')
            ->assertSee('Triton inference behind the gateway')
            ->assertSee('Training contribution is optional and off by default.')
            ->assertSee('Omniverse is a possible digital-twin direction.')
            ->assertSee('No 3D or BIM import');
        preg_match('/<main id="main-content"[^>]*>(.*?)<\/main>/s', $product->getContent(), $productMain);
        $this->assertSame(1, substr_count($productMain[1] ?? '', '<img '));
    }

    public function test_homepage_about_section_shows_company_profiles_and_footer_has_social_links(): void
    {
        $home = $this->get(route('home'))->assertOk();
        preg_match('/<div id="about".*?<\/div>/s', $home->getContent(), $aboutSection);
        $aboutContent = $aboutSection[0] ?? '';

        self::assertStringContainsString('aria-label="Caldren company profiles"', $aboutContent);
        self::assertStringContainsString('https://www.crunchbase.com/organization/caldren-solutions', $aboutContent);
        self::assertStringContainsString('https://www.f6s.com/caldren-solutions', $aboutContent);
        self::assertStringNotContainsString('medium.com', $aboutContent);
        self::assertStringNotContainsString('facebook.com', $aboutContent);
        self::assertStringNotContainsString('youtube.com', $aboutContent);
        self::assertStringNotContainsString('linkedin.com', $aboutContent);

        $home->assertSee('aria-label="Caldren social media"', false)
            ->assertSee('href="https://medium.com/@caldren.net" target="_blank" rel="noopener noreferrer"', false)
            ->assertSee('href="https://www.facebook.com/CaldrenSolutions/" target="_blank" rel="noopener noreferrer"', false)
            ->assertSee('href="https://www.youtube.com/@CaldrenSolutions" target="_blank" rel="noopener noreferrer"', false)
            ->assertSee('href="https://www.linkedin.com/company/caldren-solutions/" target="_blank" rel="noopener noreferrer"', false)
            ->assertSee('https://www.google.com/s2/favicons?domain=medium.com&sz=64', false)
            ->assertSee('https://www.google.com/s2/favicons?domain=facebook.com&sz=64', false)
            ->assertSee('https://www.google.com/s2/favicons?domain=youtube.com&sz=64', false)
            ->assertSee('https://www.google.com/s2/favicons?domain=linkedin.com&sz=64', false);
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
        config()->set('services.google.client_id', 'google-client');
        config()->set('services.google.client_secret', 'google-secret');
        config()->set('services.github.client_id', 'github-client');
        config()->set('services.github.client_secret', 'github-secret');
        config()->set('services.turnstile.site_key', 'test-site-key');

        $home = $this->get(route('home'))->assertOk();
        self::assertSame(2, substr_count($home->getContent(), 'class="cf-turnstile"'));

        $login = $this->get(route('login'))->assertOk()->assertSee('class="cf-turnstile"', false);
        $register = $this->get(route('register'))->assertOk()->assertSee('class="cf-turnstile"', false);
        self::assertStringNotContainsString('cf-turnstile', substr($login->getContent(), strpos($login->getContent(), 'class="oauth-options"')));
        self::assertStringNotContainsString('cf-turnstile', substr($register->getContent(), strpos($register->getContent(), 'class="oauth-options"')));
        $register->assertSee('data-password-strength-input', false)
            ->assertSee('role="meter"', false)
            ->assertSee('data-password-strength-label', false);
        $login->assertDontSee('data-password-strength-input', false);
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
        $response = $this->get(route('home'));
        $response->assertSee('30 days')
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
        preg_match_all('/<article class="tier-card tier-card-[^"]+".*?<\/article>/s', $response->getContent(), $tierCards);
        $this->assertCount(3, $tierCards[0]);
        foreach ($tierCards[0] as $tierCard) {
            self::assertStringNotContainsString('<img ', $tierCard);
        }
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
        config()->set('services.groq.key', 'test-key');
        config()->set('services.groq.model', 'openai/gpt-oss-120b');
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
        Http::preventStrayRequests();
        Http::fake(['api.groq.com/openai/v1/chat/completions' => Http::response($this->groqResponse(['days' => $days, 'recommendations' => [['title' => 'Review peak loads', 'description' => 'Check flexible loads in peak hours.', 'priority' => 'medium']]]), 200)]);
        $this->actingAs($user)->post(route('buildings.forecast', $building))->assertRedirect();
        $this->assertDatabaseHas('forecast_runs', ['building_id' => $building->id, 'status' => 'complete', 'source' => 'groq', 'model' => 'openai/gpt-oss-120b']);
        $this->assertDatabaseCount('forecast_points', 7);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.groq.com/openai/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer test-key')
            && $request['model'] === 'openai/gpt-oss-120b'
            && $request['response_format']['type'] === 'json_object'
            && count(json_decode($request['messages'][1]['content'], true)['historical_days']) === 14
            && array_key_exists('peak_hours', json_decode($request['messages'][1]['content'], true))
            && ! array_key_exists('organization_id', json_decode($request['messages'][1]['content'], true)));
    }

    public function test_dashboard_and_bill_only_building_show_spending_trend(): void
    {
        [$user,$organization] = $this->workspace();
        $building = $organization->buildings()->create(['name' => 'Riverside Plaza', 'timezone' => 'UTC', 'currency' => 'USD', 'energy_rate' => 0.15]);
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('Riverside Plaza')->assertDontSee('site-footer')->assertDontSee('embed.tawk.to');
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
        config()->set('services.groq.key', 'test-key');
        config()->set('services.groq.model', 'openai/gpt-oss-120b');
        [$user,$organization] = $this->workspace();
        $building = $organization->buildings()->create(['name' => 'Tower A', 'timezone' => 'UTC', 'currency' => 'USD', 'energy_rate' => 0.15]);
        for ($i = 1; $i <= 14; $i++) {
            $building->readings()->create(['recorded_at' => now()->subDays($i)->startOfDay(), 'kwh' => 100]);
        }
        $this->actingAs($user)->post(route('buildings.forecast', $building))->assertSessionHasErrors('forecast');
        $this->assertDatabaseCount('forecast_runs', 0);
    }

    public function test_groq_service_failure_keeps_readings_and_records_retryable_failure(): void
    {
        config()->set('services.groq.key', 'test-key');
        config()->set('services.groq.model', 'openai/gpt-oss-120b');
        [$user, $organization] = $this->workspace();
        $building = $organization->buildings()->create(['name' => 'Tower A', 'timezone' => 'UTC', 'currency' => 'USD', 'energy_rate' => 0.15]);
        $first = CarbonImmutable::now('UTC')->subDays(14)->startOfDay();
        for ($day = 0; $day < 14; $day++) {
            for ($hour = 0; $hour < 24; $hour++) {
                $building->readings()->create(['recorded_at' => $first->addDays($day)->addHours($hour), 'kwh' => 5]);
            }
        }
        Http::preventStrayRequests();
        Http::fake(['api.groq.com/openai/v1/chat/completions' => Http::response(['error' => ['message' => 'temporarily unavailable']], 503)]);

        $this->actingAs($user)->post(route('buildings.forecast', $building))->assertSessionHas('error');

        $this->assertDatabaseHas('forecast_runs', ['building_id' => $building->id, 'status' => 'failed', 'source' => 'groq']);
        $this->assertSame(14 * 24, $building->readings()->count());
        $this->assertDatabaseCount('forecast_points', 0);
    }

    public function test_invalid_ai_numbers_are_not_published(): void
    {
        config()->set('services.groq.key', 'test-key');
        config()->set('services.groq.model', 'openai/gpt-oss-120b');
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
        Http::preventStrayRequests();
        Http::fake(['api.groq.com/openai/v1/chat/completions' => Http::response($this->groqResponse(['days' => $days, 'recommendations' => []]), 200)]);
        $this->actingAs($user)->post(route('buildings.forecast', $building))->assertSessionHas('error');
        $this->assertDatabaseHas('forecast_runs', ['building_id' => $building->id, 'status' => 'failed']);
        $this->assertDatabaseCount('forecast_points', 0);
    }
}
