<?php

$appUrl = rtrim((string) env('APP_URL', 'http://localhost'), '/');
$callbackUrl = static function (string $environmentKey, string $path) use ($appUrl): string {
    $configuredUrl = env($environmentKey);

    if (! is_string($configuredUrl) || $configuredUrl === '' || str_contains($configuredUrl, '${APP_URL}')) {
        return $appUrl.$path;
    }

    return $configuredUrl;
};

return [
    'groq' => [
        'key' => env('GROQ_API_KEY'),
        'model' => env('GROQ_MODEL', 'openai/gpt-oss-120b'),
        'timeout' => (int) env('GROQ_TIMEOUT', 35),
    ],

    'nvidia' => [
        'forecast_url' => env('NVIDIA_FORECAST_URL'),
        'api_token' => env('NVIDIA_FORECAST_API_TOKEN'),
        'model_version' => env('NVIDIA_FORECAST_MODEL_VERSION'),
        'request_timeout' => (int) env('NVIDIA_FORECAST_TIMEOUT', 8),
        'training_consent_version' => env('MODEL_TRAINING_CONSENT_VERSION', 'v1'),
    ],

    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => $callbackUrl('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
    ],

    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => $callbackUrl('GITHUB_REDIRECT_URI', '/auth/github/callback'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
