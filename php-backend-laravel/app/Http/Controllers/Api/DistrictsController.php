<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\DistrictService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * District Home — the local community snapshot for the GameHub District tab.
 */
final class DistrictsController extends Controller
{
    /**
     * GET /api/districts/summary
     * Defaults to the signed-in viewer's district/state; ?district= & ?state=
     * override (so any district page can be browsed). Optional auth.
     */
    public function summary(Request $request, DistrictService $service): JsonResponse
    {
        $viewer = $request->attributes->get('auth_user');
        $viewer = $viewer instanceof User ? $viewer : null;

        $district = $request->query('district') ?: $viewer?->district;
        $state = $request->query('state') ?: $viewer?->state;

        if (empty($district)) {
            return response()->json([
                'error' => 'No district yet. Complete your profile to unlock your District Home.',
            ], 422);
        }

        return response()->json([
            'data' => $service->summary((string) $district, $state !== null ? (string) $state : null),
        ]);
    }
}
