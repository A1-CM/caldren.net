<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    public function register(Request $request)
    {
        $invite = $request->query('invite');
        $invitation = $invite ? Invitation::where('token_hash', hash('sha256', $invite))->whereNull('accepted_at')->where('expires_at', '>', now())->first() : null;

        return view('auth.register', compact('invitation', 'invite'));
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:120', 'email' => 'required|email|max:255|unique:users,email', 'password' => 'required|string|min:12|confirmed', 'organization' => 'nullable|string|max:160', 'invite' => 'nullable|string']);
        $invite = ! empty($data['invite']) ? Invitation::where('token_hash', hash('sha256', $data['invite']))->whereNull('accepted_at')->where('expires_at', '>', now())->first() : null;
        if (! empty($data['invite']) && (! $invite || strcasecmp($invite->email, $data['email']) !== 0)) {
            throw ValidationException::withMessages(['email' => 'This invitation is invalid or belongs to another email address.']);
        }
        if (! $invite && empty($data['organization'])) {
            throw ValidationException::withMessages(['organization' => 'Enter an organization name.']);
        }
        $user = DB::transaction(function () use ($data, $invite) {
            $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => Hash::make($data['password'])]);
            if ($invite) {
                $invite->update(['accepted_at' => now()]);
                $organization = Organization::findOrFail($invite->organization_id);
                $organization->users()->attach($user->id, ['role' => 'member']);
            } else {
                $organization = Organization::create(['name' => $data['organization'], 'trial_ends_at' => now()->addDays(30)]);
                $organization->users()->attach($user->id, ['role' => 'owner']);
                $organization->subscription()->create(['plan_key' => 'site', 'status' => 'trialing', 'monthly_price_cents' => 4900]);
            }

            return $user;
        });
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Welcome to Caldren. Your workspace is ready.');
    }

    public function login()
    {
        return view('auth.login');
    }

    public function authenticate(Request $request)
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::attempt($data, $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function acceptInvitation(Request $request, string $token)
    {
        $invitation = Invitation::where('token_hash', hash('sha256', $token))->whereNull('accepted_at')->where('expires_at', '>', now())->firstOrFail();
        if (! $request->user()) {
            return User::where('email', $invitation->email)->exists()
                ? redirect()->guest(route('login'))
                : redirect()->route('register', ['invite' => $token]);
        }
        abort_unless(strcasecmp($request->user()->email, $invitation->email) === 0, 403);
        DB::transaction(function () use ($request, $invitation) {
            $organization = Organization::findOrFail($invitation->organization_id);
            $organization->users()->syncWithoutDetaching([$request->user()->id => ['role' => 'member']]);
            $invitation->update(['accepted_at' => now()]);
            $request->session()->put('organization_id', $organization->id);
        });

        return redirect()->route('dashboard')->with('success', 'You joined the workspace.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
