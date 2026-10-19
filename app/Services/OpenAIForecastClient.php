<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAIForecastClient
{
    /**
     * @param  array<string, mixed>  $context
     * @return array{days: array<int, array<string, mixed>>, recommendations: array<int, array<string, mixed>>}
     */
    public function fullForecast(array $context): array
    {
        $schema = [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'days' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => false, 'properties' => [
                    'date' => ['type' => 'string'], 'kwh' => ['type' => 'number'], 'peak_kw' => ['type' => 'number'],
                ], 'required' => ['date', 'kwh', 'peak_kw']]],
                'recommendations' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => false, 'properties' => [
                    'title' => ['type' => 'string'], 'description' => ['type' => 'string'], 'priority' => ['type' => 'string', 'enum' => ['high', 'medium', 'low']],
                ], 'required' => ['title', 'description', 'priority']]],
            ],
            'required' => ['days', 'recommendations'],
        ];
        $text = $this->requestStructuredOutput(
            'energy_forecast',
            $schema,
            'You are an energy demand analysis assistant. Produce cautious estimates from historical data, never claim guaranteed savings. Return exactly seven calendar days after the last input date. Peak kW estimates must be nonnegative and plausible. Give at most three actionable recommendations grounded in the tariff and observed history. Do not invent weather, occupancy, or equipment data.',
            $context,
            1800,
        );
        $data = json_decode($text, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($data) || ! is_array($data['days'] ?? null) || ! is_array($data['recommendations'] ?? null)) {
            throw new RuntimeException('The AI service returned an invalid forecast.');
        }

        return ['days' => $data['days'], 'recommendations' => $data['recommendations']];
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<int, array<string, mixed>>  $days
     * @return array<int, array<string, mixed>>
     */
    public function recommendations(array $context, array $days): array
    {
        $schema = [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'recommendations' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => false, 'properties' => [
                    'title' => ['type' => 'string'], 'description' => ['type' => 'string'], 'priority' => ['type' => 'string', 'enum' => ['high', 'medium', 'low']],
                ], 'required' => ['title', 'description', 'priority']]],
            ],
            'required' => ['recommendations'],
        ];
        $text = $this->requestStructuredOutput(
            'energy_recommendations',
            $schema,
            'You are an energy operations assistant. Suggest at most three cautious review opportunities grounded only in the supplied energy history, tariffs, and validated forecast. Do not alter or repeat numerical forecast values. Never promise savings or assume weather, occupancy, equipment, or actions not in the input.',
            ['context' => $context, 'validated_forecast' => $days],
            1000,
        );
        $data = json_decode($text, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($data) || ! is_array($data['recommendations'] ?? null)) {
            throw new RuntimeException('The AI service returned invalid recommendations.');
        }

        return $data['recommendations'];
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $input
     */
    private function requestStructuredOutput(string $name, array $schema, string $instructions, array $input, int $maxOutputTokens): string
    {
        $apiKey = config('services.openai.key');
        if (! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException('The OpenAI forecast service is not configured.');
        }

        $response = Http::withToken($apiKey)
            ->timeout(35)
            ->post('https://api.openai.com/v1/responses', [
                'model' => config('services.openai.model'),
                'instructions' => $instructions,
                'input' => json_encode($input, JSON_THROW_ON_ERROR),
                'text' => ['format' => ['type' => 'json_schema', 'name' => $name, 'strict' => true, 'schema' => $schema]],
                'max_output_tokens' => $maxOutputTokens,
            ])
            ->throw()
            ->json();
        $text = collect($response['output'] ?? [])
            ->flatMap(fn (array $item): array => $item['content'] ?? [])
            ->firstWhere('type', 'output_text')['text'] ?? null;

        if (! is_string($text) || $text === '') {
            throw new RuntimeException('The AI service returned no structured output.');
        }

        return $text;
    }
}
