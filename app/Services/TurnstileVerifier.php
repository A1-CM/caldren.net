<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class TurnstileVerifier
{
    public function verify(Request $request, string $errorBag = 'default', ?string $redirectTo = null): void
    {
        $token = $request->input('cf-turnstile-response');
        $request->merge(['cf-turnstile-response' => null]);
        $siteKey = config('services.turnstile.site_key');
        $secret = config('services.turnstile.secret_key');

        if (! is_string($token) || $token === '' || ! is_string($siteKey) || $siteKey === '' || ! is_string($secret) || $secret === '') {
            $this->reject($errorBag, $redirectTo);
        }

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->timeout(5)
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => $request->ip(),
                ]);

            if ($response->successful() && $response->json('success') === true) {
                return;
            }
        } catch (\Throwable) {
            // Fail closed when the verification service cannot be reached.
        }

        $this->reject($errorBag, $redirectTo);
    }

    private function reject(string $errorBag, ?string $redirectTo): never
    {
        $exception = ValidationException::withMessages([
            'turnstile' => 'Please complete the security check and try again.',
        ])->errorBag($errorBag);

        if ($redirectTo !== null) {
            $exception->redirectTo($redirectTo);
        }

        throw $exception;
    }
}
