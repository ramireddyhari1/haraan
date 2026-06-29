<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\FeedItem;
use App\Models\HomeBlock;
use App\Models\User;
use Illuminate\Http\JsonResponse;
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
            ->where('is_active', true)
            ->when(request('placement'), fn ($q, $p) => $q->where('placement', $p))
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Ad $a) => [
                'id' => $a->id,
                'sponsor' => $a->sponsor,
                'title' => $a->title,
                'subtitle' => $a->subtitle,
                'image' => $a->image,
                'logo' => $a->logo,
                'cta_text' => $a->cta_text,
                'cta_url' => $a->cta_url,
                'placement' => $a->placement,
            ]);

        return response()->json(['data' => $ads]);
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
                'image' => $f->image,
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
