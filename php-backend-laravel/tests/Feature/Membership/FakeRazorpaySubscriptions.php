<?php

declare(strict_types=1);

namespace Tests\Feature\Membership;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * An in-memory Razorpay Subscriptions API for tests: creates, reads and cancels subscriptions
 * the way the real one does, and records every call so a test can assert what was (and
 * wasn't) sent — above all, that the key secret never appears in a request body.
 */
final class FakeRazorpaySubscriptions
{
    /** @var array<string, array<string, mixed>> */
    public array $subscriptions = [];

    /** @var list<array{method: string, url: string, body: array<string, mixed>}> */
    public array $calls = [];

    public bool $failCreates = false;

    public bool $failFetches = false;

    public static function install(): self
    {
        $fake = new self();

        Http::fake(function (Request $request) use ($fake) {
            return $fake->handle($request);
        });

        return $fake;
    }

    public function setStatus(string $id, string $status, array $extra = []): void
    {
        $this->subscriptions[$id] = array_merge($this->subscriptions[$id], ['status' => $status], $extra);
    }

    /** @return list<array{method: string, url: string, body: array<string, mixed>}> */
    public function callsTo(string $needle, string $method = 'POST'): array
    {
        return array_values(array_filter(
            $this->calls,
            fn (array $c) => $c['method'] === $method && str_contains($c['url'], $needle),
        ));
    }

    private function handle(Request $request)
    {
        $url = $request->url();
        $method = strtoupper($request->method());
        $body = $request->data();
        $this->calls[] = ['method' => $method, 'url' => $url, 'body' => $body];

        if (! str_starts_with($url, 'https://api.razorpay.com/v1/')) {
            return Http::response('unexpected host', 500);
        }

        $path = substr($url, strlen('https://api.razorpay.com/v1/'));

        if ($method === 'POST' && $path === 'plans') {
            return Http::response(['id' => 'plan_' . Str::random(14), 'entity' => 'plan'] + $body, 200);
        }

        if ($method === 'POST' && $path === 'subscriptions') {
            if ($this->failCreates) {
                return Http::response(['error' => ['description' => 'boom']], 400);
            }
            $id = 'sub_' . Str::random(14);
            $this->subscriptions[$id] = [
                'id' => $id,
                'entity' => 'subscription',
                'plan_id' => $body['plan_id'] ?? null,
                'status' => 'created',
                'current_start' => null,
                'current_end' => null,
                'start_at' => $body['start_at'] ?? null,
                'paid_count' => 0,
                'notes' => $body['notes'] ?? [],
                'short_url' => 'https://rzp.io/i/' . Str::random(6),
            ];

            return Http::response($this->subscriptions[$id], 200);
        }

        if (preg_match('#^subscriptions/([^/]+)/cancel$#', $path, $m) && $method === 'POST') {
            if (! isset($this->subscriptions[$m[1]])) {
                return Http::response(['error' => ['description' => 'not found']], 404);
            }
            if ((int) ($body['cancel_at_cycle_end'] ?? 0) === 0) {
                $this->subscriptions[$m[1]]['status'] = 'cancelled';
                $this->subscriptions[$m[1]]['ended_at'] = Carbon::now()->getTimestamp();
            }

            return Http::response($this->subscriptions[$m[1]], 200);
        }

        if (preg_match('#^subscriptions/([^/]+)$#', $path, $m) && $method === 'GET') {
            if ($this->failFetches) {
                return Http::response(['error' => ['description' => 'unavailable']], 503);
            }

            return isset($this->subscriptions[$m[1]])
                ? Http::response($this->subscriptions[$m[1]], 200)
                : Http::response(['error' => ['description' => 'not found']], 404);
        }

        return Http::response(['error' => ['description' => 'unhandled ' . $method . ' ' . $path]], 500);
    }
}
