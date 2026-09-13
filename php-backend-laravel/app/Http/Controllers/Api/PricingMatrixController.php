<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PricingRule;
use App\Models\Venue;
use App\Models\VenueCourt;
use App\Services\CourtHierarchyService;
use App\Services\PricingMatrixService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PricingMatrixController extends Controller
{
    public function __construct(
        private readonly PricingMatrixService $pricingService,
        private readonly CourtHierarchyService $hierarchyService,
    ) {
    }

    private function branch(Request $request, string|int $id): Venue
    {
        return $request->user()->branches()->findOrFail($id);
    }

    /**
     * GET /api/partner/venues/{id}/pricing/dashboard
     * High-level KPI yield summary and quick statistics.
     */
    public function dashboard(Request $request, string $id): JsonResponse
    {
        $venue = $this->branch($request, $id);

        $rulesCount = PricingRule::where('venue_id', $venue->id)->where('is_active', true)->count();
        $compositeCount = VenueCourt::where('venue_id', $venue->id)->where('is_composite', true)->count();
        $matrix = $this->pricingService->getWeeklyMatrix($venue);
        $recs = $this->pricingService->generateYieldRecommendations($venue);

        return response()->json([
            'average_hourly_rate'   => $matrix['average_rate'],
            'min_rate'              => $matrix['min_rate'],
            'max_rate'              => $matrix['max_rate'],
            'active_rules_count'    => $rulesCount,
            'composite_courts_count'=> $compositeCount,
            'recommendations_count' => count($recs),
            'top_recommendations'   => $recs,
        ]);
    }

    /**
     * GET /api/partner/venues/{id}/pricing/matrix
     * Complete 7-day x 24-hour rate grid for a court or venue default.
     */
    public function matrix(Request $request, string $id): JsonResponse
    {
        $venue = $this->branch($request, $id);
        $courtId = $request->query('court_id') ? (int) $request->query('court_id') : null;

        $grid = $this->pricingService->getWeeklyMatrix($venue, $courtId);

        return response()->json($grid);
    }

    /**
     * GET /api/partner/venues/{id}/pricing/rules
     * List pricing rules with optional court filter.
     */
    public function rules(Request $request, string $id): JsonResponse
    {
        $venue = $this->branch($request, $id);
        $courtId = $request->query('court_id') ? (int) $request->query('court_id') : null;
        $activeOnly = $request->has('active_only') ? filter_var($request->query('active_only'), FILTER_VALIDATE_BOOLEAN) : null;

        $rules = $this->pricingService->getRules($venue, $courtId, $activeOnly);

        return response()->json(['rules' => $rules]);
    }

    /**
     * POST /api/partner/venues/{id}/pricing/rules
     * Create a new pricing rule.
     */
    public function storeRule(Request $request, string $id): JsonResponse
    {
        $venue = $this->branch($request, $id);

        $validated = $request->validate([
            'venue_court_id' => ['nullable', 'integer', 'exists:venue_courts,id'],
            'name'           => ['required', 'string', 'max:120'],
            'rule_type'      => ['nullable', 'string', 'in:time_of_day,day_of_week,seasonal_date_range,occupancy_surge,last_minute'],
            'weekdays'       => ['nullable', 'array'],
            'weekdays.*'     => ['string'],
            'start_time'     => ['required', 'string', 'regex:/^\d{1,2}:\d{2}$/'],
            'end_time'       => ['required', 'string', 'regex:/^\d{1,2}:\d{2}$/'],
            'date_from'      => ['nullable', 'date'],
            'date_to'        => ['nullable', 'date', 'after_or_equal:date_from'],
            'pricing_mode'   => ['required', 'string', 'in:absolute,delta,percentage'],
            'amount'         => ['required', 'numeric'],
            'min_price'      => ['nullable', 'numeric', 'min:0'],
            'max_price'      => ['nullable', 'numeric', 'gte:min_price'],
            'priority'       => ['nullable', 'integer', 'min:1'],
            'is_active'      => ['nullable', 'boolean'],
        ]);

        $rule = $this->pricingService->createRule($venue, $validated, $request->user(), $request->ip());

        return response()->json([
            'message' => 'Pricing rule created successfully.',
            'rule'    => $rule,
        ], 201);
    }

    /**
     * PUT /api/partner/venues/{id}/pricing/rules/{ruleId}
     * Update an existing pricing rule.
     */
    public function updateRule(Request $request, string $id, int $ruleId): JsonResponse
    {
        $venue = $this->branch($request, $id);

        $validated = $request->validate([
            'venue_court_id' => ['nullable', 'integer', 'exists:venue_courts,id'],
            'name'           => ['sometimes', 'string', 'max:120'],
            'rule_type'      => ['sometimes', 'string', 'in:time_of_day,day_of_week,seasonal_date_range,occupancy_surge,last_minute'],
            'weekdays'       => ['nullable', 'array'],
            'weekdays.*'     => ['string'],
            'start_time'     => ['sometimes', 'string', 'regex:/^\d{1,2}:\d{2}$/'],
            'end_time'       => ['sometimes', 'string', 'regex:/^\d{1,2}:\d{2}$/'],
            'date_from'      => ['nullable', 'date'],
            'date_to'        => ['nullable', 'date', 'after_or_equal:date_from'],
            'pricing_mode'   => ['sometimes', 'string', 'in:absolute,delta,percentage'],
            'amount'         => ['sometimes', 'numeric'],
            'min_price'      => ['nullable', 'numeric', 'min:0'],
            'max_price'      => ['nullable', 'numeric'],
            'priority'       => ['sometimes', 'integer', 'min:1'],
            'is_active'      => ['sometimes', 'boolean'],
        ]);

        $rule = $this->pricingService->updateRule($venue, $ruleId, $validated, $request->user(), $request->ip());

        return response()->json([
            'message' => 'Pricing rule updated successfully.',
            'rule'    => $rule,
        ]);
    }

    /**
     * POST /api/partner/venues/{id}/pricing/rules/{ruleId}/toggle
     * Toggle active state.
     */
    public function toggleRule(Request $request, string $id, int $ruleId): JsonResponse
    {
        $venue = $this->branch($request, $id);
        $rule = $this->pricingService->toggleRule($venue, $ruleId, $request->user(), $request->ip());

        return response()->json([
            'message'   => $rule->is_active ? 'Pricing rule activated.' : 'Pricing rule paused.',
            'is_active' => $rule->is_active,
            'rule'      => $rule,
        ]);
    }

    /**
     * DELETE /api/partner/venues/{id}/pricing/rules/{ruleId}
     * Delete pricing rule.
     */
    public function destroyRule(Request $request, string $id, int $ruleId): JsonResponse
    {
        $venue = $this->branch($request, $id);
        $this->pricingService->deleteRule($venue, $ruleId, $request->user(), $request->ip());

        return response()->json([
            'message' => 'Pricing rule deleted successfully.',
        ]);
    }

    /**
     * GET /api/partner/venues/{id}/pricing/hierarchy
     * Get court hierarchy (composite parent grounds and child partitions).
     */
    public function hierarchy(Request $request, string $id): JsonResponse
    {
        $venue = $this->branch($request, $id);
        $hierarchy = $this->hierarchyService->getHierarchy($venue);

        return response()->json($hierarchy);
    }

    /**
     * POST /api/partner/venues/{id}/pricing/hierarchy/split
     * Split a court into sub-courts.
     */
    public function splitCourt(Request $request, string $id): JsonResponse
    {
        $venue = $this->branch($request, $id);

        $validated = $request->validate([
            'court_id'               => ['required', 'integer', 'exists:venue_courts,id'],
            'split_type'             => ['required', 'string', 'in:half,third,quarter,custom'],
            'partitions'             => ['required', 'array', 'min:2'],
            'partitions.*.name'      => ['required', 'string', 'max:60'],
            'partitions.*.label'     => ['nullable', 'string', 'max:50'],
            'partitions.*.price'     => ['nullable', 'numeric', 'min:0'],
        ]);

        $res = $this->hierarchyService->splitCourt(
            venue: $venue,
            parentCourtId: (int) $validated['court_id'],
            splitType: $validated['split_type'],
            partitions: $validated['partitions'],
            actor: $request->user(),
            ip: $request->ip(),
        );

        return response()->json([
            'message' => "Court successfully split into " . count($validated['partitions']) . " partitions.",
            'parent'  => $res['parent'],
            'children'=> $res['children'],
        ], 201);
    }

    /**
     * POST /api/partner/venues/{id}/pricing/hierarchy/merge
     * Merge sub-courts back into the parent court.
     */
    public function mergeCourts(Request $request, string $id): JsonResponse
    {
        $venue = $this->branch($request, $id);

        $validated = $request->validate([
            'court_id' => ['required', 'integer', 'exists:venue_courts,id'],
        ]);

        $this->hierarchyService->mergeCourts(
            venue: $venue,
            parentCourtId: (int) $validated['court_id'],
            actor: $request->user(),
            ip: $request->ip(),
        );

        return response()->json([
            'message' => 'Sub-courts merged back into parent arena successfully.',
        ]);
    }

    /**
     * GET /api/partner/venues/{id}/pricing/recommendations
     * Intelligent yield recommendations with concrete rupee values.
     */
    public function recommendations(Request $request, string $id): JsonResponse
    {
        $venue = $this->branch($request, $id);
        $recs = $this->pricingService->generateYieldRecommendations($venue);

        return response()->json(['recommendations' => $recs]);
    }

    /**
     * POST /api/partner/venues/{id}/pricing/recommendations/apply
     * 1-tap apply recommendation to generate active rule.
     */
    public function applyRecommendation(Request $request, string $id): JsonResponse
    {
        $venue = $this->branch($request, $id);

        $validated = $request->validate([
            'name'         => ['required', 'string', 'max:120'],
            'rule_type'    => ['required', 'string'],
            'weekdays'     => ['required', 'array'],
            'start_time'   => ['required', 'string'],
            'end_time'     => ['required', 'string'],
            'pricing_mode' => ['required', 'string', 'in:absolute,delta,percentage'],
            'amount'       => ['required', 'numeric'],
            'priority'     => ['nullable', 'integer'],
        ]);

        $rule = $this->pricingService->createRule($venue, $validated, $request->user(), $request->ip());

        return response()->json([
            'message' => 'Yield recommendation applied! Dynamic pricing rule created.',
            'rule'    => $rule,
        ], 201);
    }
}
