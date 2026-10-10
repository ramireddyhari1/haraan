<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PartnerUpdate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The partner app's line to "what Haraan changed on my account":
 *  - GET  /api/partner/updates            the feed + unread count + how to listen live
 *  - POST /api/partner/updates/seen       mark read (all, or up to an id)
 *  - POST /api/partner/realtime/auth      sign the app into its private Reverb channel
 *
 * Owner only. Desk staff get an empty feed and no channel: these updates talk about
 * money and the account itself, which an owner may not share with the desk.
 */
final class PartnerUpdatesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $owner = $user->parent_partner_id === null;
        $partnerId = $user->effectivePartnerId();

        $q = PartnerUpdate::query()->where('partner_id', $partnerId)->orderByDesc('id');
        $after = (int) $request->query('after', 0);
        if ($after > 0) {
            $q->where('id', '>', $after);
        }
        $rows = $owner ? $q->limit(40)->get() : collect();

        return response()->json([
            'data' => $rows->map(fn (PartnerUpdate $u) => $u->toApi())->values(),
            'unread' => $owner ? PartnerUpdate::query()->where('partner_id', $partnerId)->whereNull('seen_at')->count() : 0,
            'latest_id' => $owner ? (int) (PartnerUpdate::query()->where('partner_id', $partnerId)->max('id') ?? 0) : 0,
            'realtime' => $owner ? $this->realtime($partnerId) : ['enabled' => false],
        ]);
    }

    public function seen(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->parent_partner_id !== null) {
            return response()->json(['status' => 'ok']);
        }
        $q = PartnerUpdate::query()->where('partner_id', $user->id)->whereNull('seen_at');
        if (($upTo = (int) $request->input('up_to', 0)) > 0) {
            $q->where('id', '<=', $upTo);
        }
        $q->update(['seen_at' => now()]);

        return response()->json(['status' => 'ok']);
    }

    /**
     * Pusher-protocol auth for `private-partner.{id}`: HMAC-SHA256 of
     * "{socket_id}:{channel}" with the Reverb app secret. Only the owner, only their
     * own channel.
     */
    public function auth(Request $request): JsonResponse
    {
        $data = $request->validate([
            'socket_id' => ['required', 'string', 'max:64', 'regex:/^\d+\.\d+$/'],
            'channel_name' => ['required', 'string', 'max:80'],
        ]);
        $user = $request->user();
        $mine = 'private-partner.' . $user->effectivePartnerId();
        if ($user->parent_partner_id !== null || $data['channel_name'] !== $mine) {
            return response()->json(['message' => 'Not your channel.'], 403);
        }
        $reverb = config('broadcasting.connections.reverb');
        $key = (string) ($reverb['key'] ?? '');
        $secret = (string) ($reverb['secret'] ?? '');
        if ($key === '' || $secret === '') {
            return response()->json(['message' => 'Realtime is off.'], 503);
        }

        return response()->json([
            'auth' => $key . ':' . hash_hmac('sha256', $data['socket_id'] . ':' . $data['channel_name'], $secret),
        ]);
    }

    /** @return array<string, mixed> */
    private function realtime(int $partnerId): array
    {
        $enabled = config('broadcasting.default') === 'reverb';
        $reverb = config('broadcasting.connections.reverb');

        return [
            'enabled' => $enabled,
            'key' => $enabled ? ($reverb['key'] ?? null) : null,
            'host' => $reverb['options']['host'] ?? null,
            'port' => (int) ($reverb['options']['port'] ?? 443),
            'scheme' => $reverb['options']['scheme'] ?? 'https',
            'channel' => 'private-partner.' . $partnerId,
        ];
    }
}
