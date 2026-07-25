<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Writes a first-draft event description for a host from the few facts they've
 * typed so far (title, category, city, venue). Same shape as {@see PartnerSupportAI}:
 * a thin raw-HTTP wrapper over Claude's Messages API — no SDK to install on the
 * tar-deployed VPS — reading the key from config('services.anthropic.key').
 *
 * It only ever drafts marketing copy for the host to edit; it never invents facts
 * like line-up, prices or timings the host hasn't supplied. When no key is set or
 * the call fails, {@see draftDescription()} returns null and the caller leaves the
 * field untouched (with a "try again / write your own" notice).
 */
class EventCopyAI
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    private const VERSION = '2023-06-01';

    private const SYSTEM = <<<'TXT'
You write short, punchy event descriptions for Haraan, an Indian events + ticketing
app (think BookMyShow). You are given a few facts a host has entered. Write the
event description they'd put on the event page.

Rules:
- 2 short paragraphs, 40–90 words total. Plain text only — no markdown, no headings,
  no emojis, no hashtags, no quotes around the whole thing.
- Warm and inviting but not cheesy; write for an Indian urban audience.
- Use ONLY the facts given. Do NOT invent a line-up, prices, exact timings, guest
  names, or claims like "sold out" / "award-winning" that you weren't told.
- If details are thin, keep it general and evocative rather than making things up.
- End with a light call to action to book (no exclamation-mark spam).
- Output ONLY the description text, nothing else.
TXT;

    /**
     * Draft a description from whatever the host has filled in so far.
     *
     * @param  array{title?:?string, category?:?string, city?:?string, venue?:?string, existing?:?string}  $facts
     * @return string|null  the drafted copy, or null if unavailable (no key / API error)
     */
    public function draftDescription(array $facts): ?string
    {
        $title = trim((string) ($facts['title'] ?? ''));
        if ($title === '') {
            return null;
        }

        $key = (string) config('services.anthropic.key');
        if ($key === '') {
            return null;
        }

        $lines = ["Event title: {$title}"];
        foreach (['category' => 'Category', 'city' => 'City', 'venue' => 'Venue'] as $k => $label) {
            $v = trim((string) ($facts[$k] ?? ''));
            if ($v !== '') {
                $lines[] = "{$label}: {$v}";
            }
        }
        $existing = trim((string) ($facts['existing'] ?? ''));
        if ($existing !== '') {
            $lines[] = "The host's rough notes (polish these, keep their facts): {$existing}";
        }
        $prompt = implode("\n", $lines) . "\n\nWrite the event description.";

        try {
            $res = Http::withHeaders([
                'x-api-key' => $key,
                'anthropic-version' => self::VERSION,
                'content-type' => 'application/json',
            ])->timeout(30)->retry(1, 400)->post(self::ENDPOINT, [
                'model' => (string) config('services.anthropic.model', 'claude-opus-4-8'),
                'max_tokens' => 400,
                'system' => self::SYSTEM,
                'messages' => [['role' => 'user', 'content' => mb_substr($prompt, 0, 2000)]],
            ]);
        } catch (\Throwable $e) {
            Log::warning('EventCopyAI request failed', ['error' => $e->getMessage()]);

            return null;
        }

        if (! $res->successful()) {
            Log::warning('EventCopyAI non-2xx', ['status' => $res->status(), 'body' => $res->body()]);

            return null;
        }

        $body = $res->json();
        if (($body['stop_reason'] ?? null) === 'refusal') {
            return null;
        }

        $text = '';
        foreach ((array) ($body['content'] ?? []) as $block) {
            if (($block['type'] ?? null) === 'text') {
                $text .= $block['text'] ?? '';
            }
        }
        $text = trim($text);

        return $text === '' ? null : $text;
    }
}
