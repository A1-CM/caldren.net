<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GroqForecastClient
{
    /**
     * @param  array<string, mixed>  $context
     * @return array{days: array<int, array<string, mixed>>, recommendations: array<int, array<string, mixed>>}
     */
    public function fullForecast(array $context): array
    {
        $text = $this->requestJson(
            'You are an energy demand analysis assistant. Produce cautious estimates from historical data and never claim guaranteed savings. Return exactly seven calendar days after the last input date. Peak kW estimates must be nonnegative and plausible. Give at most three actionable recommendations grounded in the tariff and observed history. Do not invent weather, occupancy, or equipment data. Return a JSON object with days (date, kwh, peak_kw) and recommendations (title, description, priority: high, medium, or low).',
            $context,
            1800,
        );
        $data = json_decode($text, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($data) || ! is_array($data['days'] ?? null) || ! is_array($data['recommendations'] ?? null)) {
            throw new RuntimeException('Groq returned an invalid forecast.');
        }

        return ['days' => $data['days'], 'recommendations' => $data['recommendations']];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function requestJson(string $instructions, array $input, int $maxTokens): string
    {
        $apiKey = config('services.groq.key');
        $model = config('services.groq.model');
        if (! is_string($apiKey) || $apiKey === '' || ! is_string($model) || $model === '') {
            throw new RuntimeException('The Groq AI service is not configured.');
        }

        $response = Http::withToken($apiKey)
            ->timeout((int) config('services.groq.timeout', 35))
            ->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $instructions],
                    ['role' => 'user', 'content' => json_encode($input, JSON_THROW_ON_ERROR)],
                ],
                'response_format' => ['type' => 'json_object'],
                'max_completion_tokens' => $maxTokens,
            ])
            ->throw()
            ->json();
        $text = $response['choices'][0]['message']['content'] ?? null;

        if (! is_string($text) || $text === '') {
            throw new RuntimeException('Groq returned no structured output.');
        }

        return $text;
    }
}
