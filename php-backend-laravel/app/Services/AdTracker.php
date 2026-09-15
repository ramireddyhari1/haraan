<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Ad;
use App\Models\AdEvent;
use Illuminate\Support\Facades\DB;

/**
 * Records ad impressions and clicks honestly enough to put in front of a sponsor.
 *
 *  - An IMPRESSION counts once per viewer, per ad, per placement, per 30 minutes. The live
 *    board refreshes every few seconds and a viewer scrolls past the same bar repeatedly;
 *    none of that is a new person seeing the ad.
 *  - A CLICK counts once per viewer per ad per 10 seconds, so a double tap is one click.
 *  - A click with no impression from that viewer still counts (the bar can be tapped before
 *    the impression beacon lands), but it never creates an impression.
 *  - Without any viewer identity at all, nothing can be de-duplicated, so nothing is counted.
 */
final class AdTracker
{
    public const IMPRESSION_WINDOW_MINUTES = 30;
    public const CLICK_WINDOW_SECONDS = 10;

    /** Salted so the stored value can't be joined back to a raw install id or session. */
    public static function viewerHash(?string $viewerKey, ?int $userId = null): ?string
    {
        $key = trim((string) $viewerKey);
        if ($key === '' && $userId === null) {
            return null;
        }

        return hash_hmac('sha256', $key !== '' ? 'k:' . $key : 'u:' . $userId, (string) config('app.key'));
    }

    /** @return bool whether this call counted a new impression */
    public function impression(Ad $ad, string $placement, string $surface, ?string $viewerKey, ?int $userId = null, ?int $matchId = null): bool
    {
        return $this->record($ad, AdEvent::IMPRESSION, $placement, $surface, $viewerKey, $userId, $matchId,
            now()->subMinutes(self::IMPRESSION_WINDOW_MINUTES));
    }

    /** @return bool whether this call counted a new click */
    public function click(Ad $ad, string $placement, string $surface, ?string $viewerKey, ?int $userId = null, ?int $matchId = null): bool
    {
        return $this->record($ad, AdEvent::CLICK, $placement, $surface, $viewerKey, $userId, $matchId,
            now()->subSeconds(self::CLICK_WINDOW_SECONDS));
    }

    private function record(Ad $ad, string $kind, string $placement, string $surface, ?string $viewerKey, ?int $userId, ?int $matchId, \DateTimeInterface $since): bool
    {
        $hash = self::viewerHash($viewerKey, $userId);
        if ($hash === null) {
            return false;
        }

        return DB::transaction(function () use ($ad, $kind, $placement, $surface, $hash, $userId, $matchId, $since): bool {
            $seen = AdEvent::query()
                ->where('ad_id', $ad->id)
                ->where('viewer_hash', $hash)
                ->where('kind', $kind)
                ->where('placement', $placement)
                ->where('created_at', '>=', $since)
                ->exists();
            if ($seen) {
                return false;
            }

            AdEvent::query()->create([
                'ad_id' => $ad->id,
                'kind' => $kind,
                'placement' => mb_substr($placement, 0, 40),
                'surface' => $surface === 'web' ? 'web' : 'app',
                'viewer_hash' => $hash,
                'user_id' => $userId,
                'match_id' => $matchId,
                'created_at' => now(),
            ]);
            Ad::query()->whereKey($ad->id)->increment($kind === AdEvent::CLICK ? 'clicks_count' : 'impressions_count');

            return true;
        });
    }

    /**
     * Daily impressions / clicks for one ad, oldest first — the console's per-ad breakdown.
     *
     * @return array<int, array{date: string, impressions: int, clicks: int}>
     */
    public function daily(Ad $ad, int $days = 14): array
    {
        $rows = AdEvent::query()
            ->where('ad_id', $ad->id)
            ->where('created_at', '>=', now()->subDays($days)->startOfDay())
            ->selectRaw('date(created_at) as day, kind, count(*) as n')
            ->groupBy('day', 'kind')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[$r->day] ??= ['date' => (string) $r->day, 'impressions' => 0, 'clicks' => 0];
            $out[$r->day][$r->kind === AdEvent::CLICK ? 'clicks' : 'impressions'] = (int) $r->n;
        }
        ksort($out);

        return array_values($out);
    }
}
