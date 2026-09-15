<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\FeedItem;
use App\Models\HomeBlock;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use App\Services\AdTracker;
use Illuminate\Http\Request;

final class AppContentController extends Controller
{
    /**
     * GET /api/home/layout — the ordered, admin-curated home composition.
     * Blocks are resolved for the viewer (schedule + district targeting +
     * feature-flag gate). Anonymous-safe; app version via X-App-Version header.
     */
    public function layout(Request $request): JsonResponse
    {
        $user = $request->attributes->get('auth_user');
        $user = $user instanceof User ? $user : null;
        $appVersion = $request->header('X-App-Version') ?? $request->query('app_version');

        $blocks = HomeBlock::query()->live()->orderBy('sort_order')->get()
            ->filter(fn (HomeBlock $b): bool => $b->isVisibleFor($user, $appVersion))
            ->map(fn (HomeBlock $b): array => $b->toAppArray())
            ->values();

        return response()->json(['blocks' => $blocks]);
    }

    /** GET /api/ads — active promo/ad cards, optionally filtered by ?placement= */
    public function ads(): JsonResponse
    {
        $ads = Ad::query()
            ->serving(request('placement') ? (string) request('placement') : null)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Ad $a) => [
                'id' => $a->id,
                'sponsor' => $a->sponsor,
                'title' => $a->title,
                'subtitle' => $a->subtitle,
                'image' => \App\Support\MediaUrl::resolve($a->image),
                'logo' => \App\Support\MediaUrl::resolve($a->logo),
                'cta_text' => $a->cta_text,
                'cta_url' => $a->link_url,
                'placement' => $a->placement,
            ]);

        return response()->json(['data' => $ads]);
    }

    /**
     * POST /api/ads/{id}/impression and /click — the app reports what a viewer saw and tapped.
     * Body: placement, match_id (optional). The viewer is the X-Install-Id header (hashed
     * server-side) plus the signed-in user when there is one. Always 200 for a serving ad —
     * `counted` says whether this call was new or a de-duplicated repeat.
     */
    public function trackImpression(Request $request, string $id, AdTracker $tracker): JsonResponse
    {
        return $this->track($request, $id, $tracker, 'impression');
    }

    public function trackClick(Request $request, string $id, AdTracker $tracker): JsonResponse
    {
        return $this->track($request, $id, $tracker, 'click');
    }

    private function track(Request $request, string $id, AdTracker $tracker, string $kind): JsonResponse
    {
        $data = $request->validate([
            'placement' => ['required', 'string', 'max:40'],
            'match_id' => ['nullable', 'integer'],
        ]);

        $ad = Ad::query()->find((int) $id);
        if ($ad === null || ! $ad->isServing()) {
            return response()->json(['error' => 'Ad not found'], 404);
        }
        // An event for a slot the ad isn't booked into is noise, not a sponsor's number.
        if ($data['placement'] !== $ad->placement) {
            return response()->json(['error' => 'This ad does not run in that placement.'], 422);
        }

        $user = $request->attributes->get('auth_user');
        $userId = $user instanceof User ? (int) $user->id : null;
        $viewer = (string) $request->header('X-Install-Id', '');

        $counted = $kind === 'click'
            ? $tracker->click($ad, $data['placement'], 'app', $viewer, $userId, $data['match_id'] ?? null)
            : $tracker->impression($ad, $data['placement'], 'app', $viewer, $userId, $data['match_id'] ?? null);

        return response()->json(['ok' => true, 'counted' => $counted]);
    }

    /** GET /api/home/feed — curated For You + Trending cards, grouped by section. */
    public function feed(): JsonResponse
    {
        $items = FeedItem::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->groupBy('section')
            ->map(fn ($group) => $group->map(fn (FeedItem $f) => [
                'id' => $f->id,
                'title' => $f->title,
                'subtitle' => $f->subtitle,
                'image' => \App\Support\MediaUrl::resolve($f->image),
                'badge' => $f->badge,
                'rating' => $f->rating,
                'link_type' => $f->link_type,
                'link_id' => $f->link_id,
            ])->values());

        return response()->json([
            'for_you' => $items->get('for_you', []),
            'trending' => $items->get('trending', []),
        ]);
    }
}
