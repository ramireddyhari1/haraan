<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * A thin Gemini text client.
 *
 * Callers hand it a system prompt, a facts prompt and a token budget, and get back a
 * string or null. It knows nothing about cricket, and deliberately never throws: every
 * feature that writes prose here has to be complete without it, so a missing key, a
 * timeout or a 500 is a quiet null rather than a broken screen.
 *
 * NOTE: {@see PlayerCareerAnalysis} still carries its own copy of the endpoint logic.
 * That is duplication, and it is on purpose for now — the career path is live on prod and
 * not worth destabilising to save forty lines. Collapse the two once this one has run in
 * production for a while.
 */
class GeminiText
{
    private const DEFAULT_MODEL = 'gemini-3.6-flash';
    private const SCOPE = 'https://www.googleapis.com/auth/cloud-platform';
    private const TIMEOUT_SECONDS = 25;

    public function isConfigured(): bool
    {
        return $this->vertexKeyPath() !== '' || trim((string) config('services.gemini.key')) !== '';
    }

    /**
     * @param string $context a short label used only in log lines when something fails
     */
    public function generate(string $system, string $facts, int $maxTokens, string $context = 'gemini'): ?string
    {
        $model = (string) (config('services.gemini.model') ?: self::DEFAULT_MODEL);

        [$url, $headers] = $this->endpoint($model);
        if ($url === null) {
            return null;
        }

        $body = [
            'systemInstruction' => ['parts' => [['text' => $system]]],
            'contents' => [[
                'role' => 'user',
                'parts' => [['text' => $facts]],
            ]],
            'generationConfig' => [
                'temperature' => 0.5,
                // Thinking tokens count against this budget on Gemini 3.x, so a budget
                // sized to the visible answer comes back truncated mid-sentence.
                'maxOutputTokens' => $maxTokens,
                'candidateCount' => 1,
                'thinkingConfig' => ['thinkingBudget' => 0],
            ],
        ];

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)->withHeaders($headers)->post($url, $body);
            if (! $response->successful()) {
                Log::warning("$context: HTTP " . $response->status(), [
                    'body' => mb_substr((string) $response->body(), 0, 300),
                ]);

                return null;
            }

            $text = trim((string) data_get($response->json(), 'candidates.0.content.parts.0.text', ''));

            return $text === '' ? null : $text;
        } catch (\Throwable $e) {
            Log::warning("$context failed: " . $e->getMessage());

            return null;
        }
    }

    private function vertexKeyPath(): string
    {
        $path = trim((string) config('services.vertex.credentials'));
        if ($path === '') {
            return '';
        }
        if (! str_starts_with($path, '/') && ! preg_match('/^[A-Za-z]:/', $path)) {
            $path = base_path($path);
        }

        return is_readable($path) ? $path : '';
    }

    /** @return array{0: ?string, 1: array<string,string>} */
    private function endpoint(string $model): array
    {
        $keyPath = $this->vertexKeyPath();
        if ($keyPath !== '') {
            $project = (string) (config('services.vertex.project') ?: 'haraan');
            $location = (string) (config('services.vertex.location') ?: 'us-central1');
            $token = app(GoogleServiceAccountToken::class)->get($keyPath, self::SCOPE);
            if ($token === null) {
                return [null, []];
            }
            // "global" is its own HOST, not a region prefix — a us-central1-style URL
            // 404s for the current Gemini models.
            $host = $location === 'global'
                ? 'aiplatform.googleapis.com'
                : "{$location}-aiplatform.googleapis.com";

            return [
                "https://{$host}/v1/projects/{$project}"
                    . "/locations/{$location}/publishers/google/models/{$model}:generateContent",
                ['Authorization' => 'Bearer ' . $token],
            ];
        }

        $key = trim((string) config('services.gemini.key'));
        if ($key === '') {
            return [null, []];
        }

        return [
            "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent",
            ['x-goog-api-key' => $key],
        ];
    }
}
