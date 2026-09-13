<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueBusinessSuggestion;
use App\Models\VenueOperationsAlert;
use App\Services\OwnerOperationsCenterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class OwnerOperationsController extends Controller
{
    public function __construct(
        private readonly OwnerOperationsCenterService $operationsService,
    ) {}

    /**
     * Complete operational executive summary.
     */
    public function overview(Request $request, int $id): JsonResponse
    {
        $venue = Venue::findOrFail($id);
        $data = $this->operationsService->getOperationsOverview($venue);

        return response()->json([
            'status' => 'success',
            'data'   => $data,
        ]);
    }

    /**
     * Real-time Revenue & Run-rate Forecasts.
     */
    public function revenue(Request $request, int $id): JsonResponse
    {
        $venue = Venue::findOrFail($id);
        $data = $this->operationsService->getRevenueMetrics($venue);

        return response()->json([
            'status' => 'success',
            'data'   => $data,
        ]);
    }

    /**
     * Court Occupancy Heatmap (7 Days x 18 Hours).
     */
    public function occupancy(Request $request, int $id): JsonResponse
    {
        $venue = Venue::findOrFail($id);
        $matrix = $this->operationsService->getOccupancyHeatmap($venue);

        return response()->json([
            'status' => 'success',
            'data'   => [
                'heatmap' => $matrix,
            ],
        ]);
    }

    /**
     * Staff Performance Leaderboard.
     */
    public function staff(Request $request, int $id): JsonResponse
    {
        $venue = Venue::findOrFail($id);
        $data = $this->operationsService->getStaffPerformance($venue);

        return response()->json([
            'status' => 'success',
            'data'   => $data,
        ]);
    }

    /**
     * WhatsApp Conversion Funnel.
     */
    public function funnel(Request $request, int $id): JsonResponse
    {
        $venue = Venue::findOrFail($id);
        $data = $this->operationsService->getWhatsAppFunnel($venue);

        return response()->json([
            'status' => 'success',
            'data'   => $data,
        ]);
    }

    /**
     * Revenue Leakage Alerts.
     */
    public function alerts(Request $request, int $id): JsonResponse
    {
        $venue = Venue::findOrFail($id);
        $data = $this->operationsService->getRevenueLeakageAlerts($venue);

        return response()->json([
            'status' => 'success',
            'data'   => $data,
        ]);
    }

    /**
     * Resolve a Revenue Leakage Alert.
     */
    public function resolveAlert(Request $request, int $id, int $alertId): JsonResponse
    {
        $venue = Venue::findOrFail($id);
        $alert = VenueOperationsAlert::where('venue_id', $venue->id)->findOrFail($alertId);
        $user = $request->user() ?: User::first();

        $alert->update([
            'is_resolved' => true,
            'resolved_at' => now(),
            'resolved_by' => $user->id,
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Operational alert marked as resolved',
            'data'    => $alert,
        ]);
    }

    /**
     * AI-Driven Business Suggestions.
     */
    public function suggestions(Request $request, int $id): JsonResponse
    {
        $venue = Venue::findOrFail($id);
        $data = $this->operationsService->getBusinessSuggestions($venue);

        return response()->json([
            'status' => 'success',
            'data'   => $data,
        ]);
    }

    /**
     * 1-Tap Apply an AI Suggestion.
     */
    public function applySuggestion(Request $request, int $id, int $suggestionId): JsonResponse
    {
        $venue = Venue::findOrFail($id);
        $suggestion = VenueBusinessSuggestion::where('venue_id', $venue->id)->findOrFail($suggestionId);
        $user = $request->user() ?: User::first();

        $this->operationsService->applySuggestion($suggestion, $user);

        return response()->json([
            'status'  => 'success',
            'message' => 'AI suggestion successfully applied',
            'data'    => $suggestion,
        ]);
    }

    /**
     * Dismiss an AI Suggestion.
     */
    public function dismissSuggestion(Request $request, int $id, int $suggestionId): JsonResponse
    {
        $venue = Venue::findOrFail($id);
        $suggestion = VenueBusinessSuggestion::where('venue_id', $venue->id)->findOrFail($suggestionId);

        $suggestion->update(['status' => 'dismissed']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Suggestion dismissed',
        ]);
    }
}