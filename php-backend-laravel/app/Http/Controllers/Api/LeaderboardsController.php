<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LeaderboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public read-only ActionBoard leaderboards.
 */
final class LeaderboardsController extends Controller
{
    public function __construct(private readonly LeaderboardService $leaderboards)
    {
    }

    /**
     * Monthly ranked board. GET /api/leaderboards/{scope}
     *   scope = india | state | district
     *   ?month=YYYY-MM  ?location=<state|district name>  ?limit=N
     */
    public function monthly(Request $request, string $scope): JsonResponse
    {
        if (!LeaderboardService::isValidScope($scope)) {
            return response()->json(['error' => 'scope must be india, state or district'], 422);
        }

        $location = $request->query('location');
        if (in_array($scope, ['state', 'district'], true) && empty($location)) {
            return response()->json(['error' => "location is required for {$scope} scope"], 422);
        }

        $month = $request->query('month');
        $limit = min(200, max(1, (int) $request->query('limit', '100')));

        $data = $this->leaderboards->monthly(
            $scope,
            $month !== null ? (string) $month : null,
            $location !== null ? (string) $location : null,
            $limit,
        );

        return response()->json([
            'scope'    => $scope,
            'month'    => $month ?: now()->format('Y-m'),
            'location' => $location,
            'data'     => $data,
        ]);
    }

    /**
     * All-Time Hall of Fame. GET /api/leaderboards/all-time
     */
    public function allTime(Request $request): JsonResponse
    {
        $limit = min(200, max(1, (int) $request->query('limit', '100')));

        return response()->json([
            'scope' => 'all-time',
            'data'  => $this->leaderboards->allTime($limit),
        ]);
    }
}
