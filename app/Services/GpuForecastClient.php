<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GpuForecastClient
{
    /**
     * @param  array<string, mixed>  $history
     * @return array<string, mixed>
     */
    public function forecast(array $history, string $modelVersion): array
    {
        $baseUrl = rtrim((string) config('services.nvidia.forecast_url'), '/');
        $token = (string) config('services.nvidia.api_token');
        if ($baseUrl === '' || $token === '') {
            throw new RuntimeException('The NVIDIA forecast endpoint is not configured.');
        }
        if (app()->environment('production') && parse_url($baseUrl, PHP_URL_SCHEME) !== 'https') {
            throw new RuntimeException('The NVIDIA forecast endpoint must use HTTPS in production.');
        }
        $configuredVersion = (string) config('services.nvidia.model_version');
        if ($configuredVersion !== '' && $configuredVersion !== $modelVersion) {
            throw new RuntimeException('The requested NVIDIA model does not match the configured release.');
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout((int) config('services.nvidia.request_timeout', 8))
            ->post($baseUrl.'/v1/forecast', ['model_version' => $modelVersion] + $history);
        $response->throw();

        $data = $response->json();
        if (! is_array($data) || ! is_string($data['model_version'] ?? null) || ! is_array($data['days'] ?? null)) {
            throw new RuntimeException('The NVIDIA forecast service returned an invalid response.');
        }

        return $data;
    }

    public function modelIsReady(string $modelVersion): bool
    {
        $baseUrl = rtrim((string) config('services.nvidia.forecast_url'), '/');
        $token = (string) config('services.nvidia.api_token');
        if ($baseUrl === '' || $token === ''
            || (app()->environment('production') && parse_url($baseUrl, PHP_URL_SCHEME) !== 'https')) {
            return false;
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout((int) config('services.nvidia.request_timeout', 8))
                ->get($baseUrl.'/healthz');

            return $response->successful()
                && $response->json('ready') === true
                && $response->json('model_version') === $modelVersion;
        } catch (\Throwable) {
            return false;
        }
    }
}
