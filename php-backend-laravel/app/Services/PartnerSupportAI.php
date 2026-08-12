<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The Haraan partner-support AI assistant.
 *
 * A thin wrapper over Claude's Messages API (raw HTTP via Laravel's client — no
 * SDK dependency to install on the tar-deployed VPS). One request per question:
 * a fixed system prompt carrying accurate Haraan partner-console knowledge, plus
 * the recent turn history, answered concisely and grounded only in what the
 * console actually does. When it can't help — or no API key is configured — it
 * hands the partner off to the human team instead of guessing.
 *
 * The API key lives in config('services.anthropic.key') and never reaches the
 * browser; the model answers server-side only.
 */
class PartnerSupportAI
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    private const VERSION = '2023-06-01';

    /** Grounding for the assistant — what the partner console can actually do. */
    private const SYSTEM = <<<'TXT'
You are the Haraan Partner support assistant, helping event organisers and venue
(turf) owners use the Haraan partner console. Be warm, concise and practical —
2–5 sentences, no preamble, plain text (no markdown headings or code blocks).

What the partner console offers, and where things live:

- Dashboard: recent bookings, revenue/KPIs, a "Needs your attention" row
  (sellout risk, pending settlement, refund watch), and quick actions.
- Events: create and edit events under Events → Create; set details, ticket
  tiers, capacity, coupons; view per-event Analytics.
- Venues / turf: manage courts and per-court pricing; a day slot-grid for
  bookings; add offline/walk-in bookings; close a day.
- Bookings: the full booking list; each shows customer, amount, quantity,
  payment status and how it arrived (App or Walk-in).
- Earnings: "Collected" is money taken on the partner's bookings; Haraan settles
  it to them as payouts. The Pending tile shows what's awaiting settlement; the
  ledger marks each row Settled or Awaiting payout, with the payout date once
  processed.
- Check-in: a QR ticket scanner in the console for events.
- People / Staff: invite staff and set roles and permissions (which sections
  they can see and act on).
- Public host profile: an organiser brand page at /host/{slug}.

Rules:
- Only answer questions about running a business on Haraan (events, venues,
  bookings, payments, settlements, staff, check-in, the console UI). If asked
  about something unrelated, politely say you can only help with Haraan.
- You do NOT have access to the partner's specific account data (their exact
  numbers, a particular booking, a specific payout status). For anything
  account-specific, or an action you can't complete, tell them to send the
  question to the Haraan team using the chat below — do not invent details.
- Never give legal, tax or financial advice, and never ask for or handle
  passwords, card numbers or OTPs.
- When genuinely unsure, say so and point them to the human team rather than
  guessing.
TXT;

    /**
     * Answer a partner's question.
     *
     * @param  string $question       the new question
     * @param  array<int, array{role:string, text:string}> $history  prior turns (oldest first)
     * @return array{ok: bool, text: string, handoff: bool}
     */
    public function answer(string $question, array $history = []): array
    {
        $question = trim($question);
        if ($question === '') {
            return ['ok' => false, 'text' => 'Ask me anything about your events, bookings or payouts.', 'handoff' => false];
        }

        $key = (string) config('services.anthropic.key');
        if ($key === '') {
            // No key configured — degrade gracefully to a human hand-off.
            return [
                'ok' => false,
                'handoff' => true,
                'text' => 'The AI assistant isn’t available right now — send your question to the Haraan team in the chat below and a real person will help you out.',
            ];
        }

        // Recent turns (cap to keep the prompt small), then the new question.
        $messages = [];
        foreach (array_slice($history, -8) as $turn) {
            $role = ($turn['role'] ?? 'user') === 'assistant' ? 'assistant' : 'user';
            $text = trim((string) ($turn['text'] ?? ''));
            if ($text !== '') {
                $messages[] = ['role' => $role, 'content' => $text];
            }
        }
        $messages[] = ['role' => 'user', 'content' => mb_substr($question, 0, 2000)];

        try {
            $res = Http::withHeaders([
                'x-api-key' => $key,
                'anthropic-version' => self::VERSION,
                'content-type' => 'application/json',
            ])->timeout(30)->retry(1, 400)->post(self::ENDPOINT, [
                'model' => (string) config('services.anthropic.model', 'claude-opus-4-8'),
                'max_tokens' => 1024,
                'system' => self::SYSTEM,
                'messages' => $messages,
            ]);
        } catch (\Throwable $e) {
            Log::warning('PartnerSupportAI request failed', ['error' => $e->getMessage()]);
            return $this->fallback();
        }

        if (! $res->successful()) {
            Log::warning('PartnerSupportAI non-2xx', ['status' => $res->status(), 'body' => $res->body()]);
            return $this->fallback();
        }

        $body = $res->json();

        // Safety refusal → hand off to a human rather than showing an empty reply.
        if (($body['stop_reason'] ?? null) === 'refusal') {
            return [
                'ok' => false,
                'handoff' => true,
                'text' => 'I can’t help with that one — please send it to the Haraan team in the chat below.',
            ];
        }

        // Concatenate the text blocks of the response.
        $text = '';
        foreach ((array) ($body['content'] ?? []) as $block) {
            if (($block['type'] ?? null) === 'text') {
                $text .= $block['text'] ?? '';
            }
        }
        $text = trim($text);

        if ($text === '') {
            return $this->fallback();
        }

        return ['ok' => true, 'text' => $text, 'handoff' => false];
    }

    /** @return array{ok: bool, text: string, handoff: bool} */
    private function fallback(): array
    {
        return [
            'ok' => false,
            'handoff' => true,
            'text' => 'I couldn’t reach the assistant just now. Send your question to the Haraan team in the chat below and they’ll help you out.',
        ];
    }
}
