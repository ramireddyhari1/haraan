<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SectionTheme;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/section-themes — campaign skins for the Events and Pulse lanes.
 *
 * Returns what is running now AND what is scheduled inside the lookahead, each with its
 * validity window. The app picks the active one itself, so a campaign starts and ends on
 * the minute even when the phone is offline, and a revoked campaign disappears on the
 * next revalidation. Rows with a malformed primary colour are never shipped.
 */
final class SectionThemeController extends Controller
{
    public function index(): JsonResponse
    {
        $themes = SectionTheme::query()
            ->deliverable()
            ->orderByDesc('priority')
            ->orderBy('starts_at')
            ->get()
            ->map(fn (SectionTheme $theme): array => $theme->toApi())
            ->filter(fn (array $theme): bool => $theme['accent']['primary'] !== null)
            ->values();

        return response()->json([
            // Lets the app correct for a phone clock that is off, which would otherwise
            // start or end a campaign early.
            'server_time' => now()->getTimestamp(),
            'themes' => $themes,
        ]);
    }
}
