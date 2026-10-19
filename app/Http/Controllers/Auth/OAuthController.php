<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use App\Services\TurnstileVerifier;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Throwable;

class OAuthController extends Controller
{
    public function start(Request $request, TurnstileVerifier $turnstile): RedirectResponse
    {
        $origin = $request->input('origin') === 'register' ? 'register' : 'login';
        $turnstile->verify($request, 'default', route($origin));

        $data = $request->validate([
            'provider' => 'required|in:google,github',
            'origin' => 'nullable|in:login,register',
            'invite' => 'nullable|string|max:255',
        ]);
        $provider = $data['provider'];

        if (! $this->providerIsConfigured($provider)) {
            return redirect()->route($origin)->with('oauth_error', 'This sign-in option is not configured yet. Please use your email and password.');
        }

        $request->session()->put('oauth_context', [
            'provider' => $provider,
            'origin' => $origin,
            'invite' => $data['invite'] ?? null,
        ]);

        return Socialite::driver($provider)->redirect();
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        $context = $request->session()->get('oauth_context');

        if (! in_array($provider, ['google', 'github'], true) || ! is_array($context) || ($context['provider'] ?? null) !== $provider || ! $this->providerIsConfigured($provider)) {
            return $this->oauthError($request, 'The sign-in request could not be confirmed. Please try again.');
        }

        try {
            /** @var SocialiteUser $providerUser */
            $providerUser = Socialite::driver($provider)->user();
        } catch (Throwable) {
            return $this->oauthError($request, 'The provider sign-in did not complete. Please try again.');
        }

        $providerId = (string) $providerUser->getId();
        if ($providerId === '') {
            return $this->oauthError($request, 'The provider did not return a usable account identity. Please try again.');
        }

        $providerColumn = $this->providerColumn($provider);
        $linkedUser = User::query()->where($providerColumn, $providerId)->first();
        $inviteToken = $context['invite'] ?? null;
        $invitation = null;
        $verifiedEmail = null;

        if ($linkedUser === null || $inviteToken !== null) {
            $verifiedEmail = $this->verifiedEmail($provider, $providerUser);
            if ($verifiedEmail === null) {
                return $this->oauthError($request, 'This provider did not provide a verified email address. Verify an email with the provider or use your Caldren password.');
            }
        }

        if ($inviteToken !== null) {
            $invitation = Invitation::query()
                ->where('token_hash', hash('sha256', $inviteToken))
                ->whereNull('accepted_at')
                ->where('expires_at', '>', now())
                ->first();

            if ($invitation === null || strcasecmp($invitation->email, (string) $verifiedEmail) !== 0) {
                return $this->oauthError($request, 'The verified provider email must match a current workspace invitation.');
            }
        }

        if ($linkedUser !== null) {
            if ($invitation !== null) {
                $this->acceptInvitation($linkedUser, $invitation);
            }

            return $this->completeSignIn($request, $linkedUser, $invitation);
        }

        $existingUser = User::query()
            ->whereRaw('LOWER(email) = ?', [Str::lower((string) $verifiedEmail)])
            ->first();

        if ($existingUser !== null) {
            if (! $this->attachProvider($existingUser, $provider, $providerId)) {
                return $this->oauthError($request, 'This provider is already linked to another Caldren account. Sign in with that account or use your Caldren password.');
            }

            if ($invitation !== null) {
                $this->acceptInvitation($existingUser, $invitation);
            }

            return $this->completeSignIn($request, $existingUser, $invitation);
        }

        $name = trim((string) ($providerUser->getName() ?: $providerUser->getNickname() ?: Str::before((string) $verifiedEmail, '@')));
        $pendingProfile = [
            'provider' => $provider,
            'provider_id' => $providerId,
            'email' => $verifiedEmail,
            'name' => $name !== '' ? $name : 'Caldren user',
        ];

        if ($invitation !== null) {
            try {
                $newUser = $this->createInvitedUser($pendingProfile, $invitation);
            } catch (ValidationException) {
                return $this->oauthError($request, 'This provider is already linked to another Caldren account. Sign in with that account or use your Caldren password.');
            } catch (Throwable) {
                return $this->oauthError($request, 'We could not join the invited workspace. Please try again.');
            }

            return $this->completeSignIn($request, $newUser, $invitation);
        }

        $request->session()->forget('oauth_context');
        $request->session()->put('oauth_pending_user', $pendingProfile);

        return redirect()->route('oauth.complete');
    }

    public function complete(Request $request): View|RedirectResponse
    {
        $pendingProfile = $request->session()->get('oauth_pending_user');
        if (! is_array($pendingProfile) || ! $this->pendingProfileIsValid($pendingProfile)) {
            return redirect()->route('register')->with('oauth_error', 'Your provider sign-in has expired. Please start again.');
        }

        return view('auth.oauth-complete', ['providerName' => ucfirst($pendingProfile['provider']), 'profileName' => $pendingProfile['name']]);
    }

    public function finishRegistration(Request $request, TurnstileVerifier $turnstile): RedirectResponse
    {
        $turnstile->verify($request, 'default', route('oauth.complete'));

        $pendingProfile = $request->session()->get('oauth_pending_user');
        if (! is_array($pendingProfile) || ! $this->pendingProfileIsValid($pendingProfile)) {
            return redirect()->route('register')->with('oauth_error', 'Your provider sign-in has expired. Please start again.');
        }

        $data = $request->validate(['organization' => 'required|string|max:160']);
        $provider = $pendingProfile['provider'];
        $providerId = $pendingProfile['provider_id'];
        $providerColumn = $this->providerColumn($provider);

        try {
            $user = DB::transaction(function () use ($data, $pendingProfile, $provider, $providerId, $providerColumn): User {
                $linkedUser = User::query()->where($providerColumn, $providerId)->lockForUpdate()->first();
                if ($linkedUser !== null) {
                    return $linkedUser;
                }

                $existingUser = User::query()
                    ->whereRaw('LOWER(email) = ?', [Str::lower($pendingProfile['email'])])
                    ->lockForUpdate()
                    ->first();

                if ($existingUser !== null) {
                    if (! $this->attachProvider($existingUser, $provider, $providerId)) {
                        throw ValidationException::withMessages(['email' => 'This provider is already linked to another Caldren account. Please sign in with your Caldren password.']);
                    }

                    return $existingUser;
                }

                $user = User::create([
                    'name' => $pendingProfile['name'],
                    'email' => $pendingProfile['email'],
                    'password' => Hash::make(Str::random(64)),
                    $providerColumn => $providerId,
                    'email_verified_at' => now(),
                ]);
                $organization = Organization::create([
                    'name' => $data['organization'],
                    'trial_ends_at' => now()->addDays(30),
                ]);
                $organization->users()->attach($user->id, ['role' => 'owner']);
                $organization->subscription()->create([
                    'plan_key' => 'site',
                    'status' => 'trialing',
                    'monthly_price_cents' => 4900,
                ]);

                return $user;
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable) {
            return redirect()->route('register')->with('oauth_error', 'We could not finish creating your workspace. Please try again.');
        }

        return $this->completeSignIn($request, $user);
    }

    private function providerIsConfigured(string $provider): bool
    {
        return filled(config("services.$provider.client_id"))
            && filled(config("services.$provider.client_secret"))
            && filled(config("services.$provider.redirect"));
    }

    private function providerColumn(string $provider): string
    {
        return $provider.'_id';
    }

    private function verifiedEmail(string $provider, SocialiteUser $providerUser): ?string
    {
        $email = $providerUser->getEmail();
        if (! is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        if ($provider === 'google' && ! filter_var($providerUser->getRaw()['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        // Socialite's GitHub driver only maps the primary email when GitHub marks it verified.
        return Str::lower($email);
    }

    private function attachProvider(User $user, string $provider, string $providerId): bool
    {
        $column = $this->providerColumn($provider);
        $linkedUser = User::query()->where($column, $providerId)->first();
        if ($linkedUser !== null && $linkedUser->isNot($user)) {
            return false;
        }

        if ($user->{$column} !== null && $user->{$column} !== $providerId) {
            return false;
        }

        $user->forceFill([$column => $providerId, 'email_verified_at' => $user->email_verified_at ?? now()])->save();

        return true;
    }

    private function acceptInvitation(User $user, Invitation $invitation): void
    {
        DB::transaction(function () use ($user, $invitation): void {
            $organization = Organization::query()->findOrFail($invitation->organization_id);
            $organization->users()->syncWithoutDetaching([$user->id => ['role' => 'member']]);
            $invitation->update(['accepted_at' => now()]);
        });
    }

    /**
     * @param  array{provider: string, provider_id: string, email: string, name: string}  $profile
     */
    private function createInvitedUser(array $profile, Invitation $invitation): User
    {
        return DB::transaction(function () use ($profile, $invitation): User {
            $user = User::query()
                ->whereRaw('LOWER(email) = ?', [Str::lower($profile['email'])])
                ->first();

            if ($user === null) {
                $user = User::create([
                    'name' => $profile['name'],
                    'email' => $profile['email'],
                    'password' => Hash::make(Str::random(64)),
                    $this->providerColumn($profile['provider']) => $profile['provider_id'],
                    'email_verified_at' => now(),
                ]);
            } elseif (! $this->attachProvider($user, $profile['provider'], $profile['provider_id'])) {
                throw ValidationException::withMessages(['email' => 'This provider is already linked to another Caldren account. Please sign in with your Caldren password.']);
            }

            $organization = Organization::query()->findOrFail($invitation->organization_id);
            $organization->users()->syncWithoutDetaching([$user->id => ['role' => 'member']]);
            $invitation->update(['accepted_at' => now()]);

            return $user;
        });
    }

    private function completeSignIn(Request $request, User $user, ?Invitation $invitation = null): RedirectResponse
    {
        Auth::login($user);
        if ($invitation !== null) {
            $request->session()->put('organization_id', $invitation->organization_id);
        }
        $request->session()->forget(['oauth_context', 'oauth_pending_user']);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Welcome to Caldren. Your workspace is ready.');
    }

    /**
     * @param  array<mixed>  $pendingProfile
     */
    private function pendingProfileIsValid(array $pendingProfile): bool
    {
        return in_array($pendingProfile['provider'] ?? null, ['google', 'github'], true)
            && is_string($pendingProfile['provider_id'] ?? null)
            && $pendingProfile['provider_id'] !== ''
            && is_string($pendingProfile['email'] ?? null)
            && filter_var($pendingProfile['email'], FILTER_VALIDATE_EMAIL) !== false
            && is_string($pendingProfile['name'] ?? null);
    }

    private function oauthError(Request $request, string $message): RedirectResponse
    {
        $context = $request->session()->get('oauth_context', []);
        $origin = is_array($context) && ($context['origin'] ?? null) === 'register' ? 'register' : 'login';
        $request->session()->forget(['oauth_context', 'oauth_pending_user']);

        return redirect()->route($origin)->with('oauth_error', $message);
    }
}
