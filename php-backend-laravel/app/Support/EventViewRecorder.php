<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Event;
use App\Models\EventView;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Records one event-detail open into event_views and bumps the fast events.views counter.
 * Best-effort: a tracking failure must never break serving the event, so everything is wrapped
 * and swallowed. Source is read from an explicit ?src / utm_source first (so the app/web can tag
 * WhatsApp / Instagram / shared links), then inferred from the referrer, else "direct".
 */
final class EventViewRecorder
{
    public static function record(Event $event, Request $request): void
    {
        try {
            $user = $request->user();
            $ua = (string) $request->userAgent();

            $visitorKey = $user
                ? 'user:' . $user->id
                : 'anon:' . substr(hash('sha256', $request->ip() . '|' . $ua), 0, 40);

            EventView::create([
                'event_id' => $event->id,
                'user_id' => $user?->id,
                'visitor_key' => $visitorKey,
                'source' => self::source($request),
                'device' => self::device($ua),
                'district' => $user?->district,
                'state' => $user?->state,
            ]);

            // Keep the denormalised counter in sync via the base builder so we don't fire model
            // events (Event uses BroadcastsContentChanges — a save would broadcast on every view).
            DB::table('events')->where('id', $event->id)->increment('views');
        } catch (Throwable) {
            // Tracking is never allowed to disturb the response.
        }
    }

    private static function source(Request $request): string
    {
        $explicit = strtolower(trim((string) ($request->query('src') ?? $request->query('utm_source') ?? '')));
        $allowed = ['instagram', 'whatsapp', 'search', 'home', 'shared', 'app', 'web', 'facebook', 'google', 'direct'];
        if ($explicit !== '') {
            return match ($explicit) {
                'ig', 'insta' => 'instagram',
                'wa', 'wsp' => 'whatsapp',
                'fb' => 'facebook',
                default => in_array($explicit, $allowed, true) ? $explicit : 'other',
            };
        }

        $ref = strtolower((string) $request->header('referer'));
        return match (true) {
            $ref === '' => 'direct',
            str_contains($ref, 'instagram') => 'instagram',
            str_contains($ref, 'whatsapp') || str_contains($ref, 'wa.me') => 'whatsapp',
            str_contains($ref, 'facebook') || str_contains($ref, 'fb.') => 'facebook',
            str_contains($ref, 'google') || str_contains($ref, 'bing') => 'search',
            str_contains($ref, 'haraan.app') => 'home',
            default => 'other',
        };
    }

    private static function device(string $ua): string
    {
        if ($ua === '') {
            return 'other';
        }

        return match (true) {
            str_contains($ua, 'iPhone') => 'iphone',
            str_contains($ua, 'iPad') => 'ipad',
            str_contains($ua, 'Android') => 'android',
            (bool) preg_match('/okhttp|Dalvik/', $ua) => 'android', // native app HTTP client
            (bool) preg_match('/Windows|Macintosh|Linux|X11|CrOS/', $ua) => 'web',
            default => 'other',
        };
    }
}
