<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Operations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/app/update?app=member|partner&version=1.0.52 — what an Android app's update sheet
 * shows. Public and always open (an app the server is refusing must still learn how to update),
 * and edited in /control → Platform → App updates.
 */
final class AppUpdateController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $app = $request->query('app') === Operations::APP_PARTNER ? Operations::APP_PARTNER : Operations::APP_MEMBER;
        $version = $request->query('version') ?? $request->header('X-App-Version');

        return response()->json(Operations::updateState($app, is_string($version) ? $version : null))
            ->header('Cache-Control', 'no-store');
    }
}
