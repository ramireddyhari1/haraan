<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HostProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public host (organiser) API — Phase 2. Currently just follow/unfollow; the
 * host object itself rides on each event via {@see \App\Http\Resources\EventResource}.
 */
final class HostController extends Controller
{
    /** POST /api/host/{slug}/follow — toggle following this organiser. */
    public function follow(Request $request, string $slug): JsonResponse
    {
        $profile = HostProfile::query()->where('slug', $slug)->first();

        if ($profile === null || ! $profile->isLive()) {
            return response()->json(['error' => 'Host not found'], 404);
        }

        $following = $profile->toggleFollow($request->user());

        return response()->json([
            'following' => $following,
            'followers' => $profile->followersCount(),
        ]);
    }
}
