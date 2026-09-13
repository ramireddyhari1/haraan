<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Venue;
use App\Services\Hrms\LaborForecastingService;
use App\Services\Hrms\RosterAutoSchedulerService;
use App\Services\Hrms\StatutoryPayrollService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

final class WorkforceIntelligenceController extends Controller
{
    public function __construct(
        private readonly RosterAutoSchedulerService $schedulerService,
        private readonly LaborForecastingService $forecastingService,
        private readonly StatutoryPayrollService $payrollService
    ) {}

    /**
     * Trigger automated shift roster scheduling for a venue based on booking demand.
     */
    public function autoSchedule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'venue_id' => ['required', 'integer', 'exists:venues,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'min_staff_per_shift' => ['nullable', 'integer', 'min:1'],
        ]);

        $venue = Venue::findOrFail((int) $validated['venue_id']);
        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);
        $minStaff = (int) ($validated['min_staff_per_shift'] ?? 1);

        $result = $this->schedulerService->autoSchedule(
            $venue,
            $startDate,
            $endDate,
            $minStaff,
            $request->user()
        );

        return response()->json($result, 201);
    }

    /**
     * Retrieve labor expense projections, LCP ratio, and statutory overtime warnings.
     */
    public function forecastLabor(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'venue_id' => ['required', 'integer', 'exists:venues,id'],
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $venue = Venue::findOrFail((int) $validated['venue_id']);
        $month = $validated['month'] ?? Carbon::now()->format('Y-m');

        $forecast = $this->forecastingService->forecastMonthly($venue, $month);

        return response()->json([
            'success' => true,
            'forecast' => $forecast,
        ]);
    }

    /**
     * Compute and generate monthly payroll batch for a venue.
     */
    public function generatePayrollBatch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'venue_id' => ['required', 'integer', 'exists:venues,id'],
            'month' => ['required', 'date_format:Y-m'],
        ]);

        $venue = Venue::findOrFail((int) $validated['venue_id']);
        $batch = $this->payrollService->generateBatchForVenue($venue, $validated['month']);

        return response()->json([
            'success' => true,
            'batch' => $batch,
        ], 201);
    }

    /**
     * Lock monthly payroll records with cryptographic audit chaining.
     */
    public function lockPayrollBatch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'venue_id' => ['required', 'integer', 'exists:venues,id'],
            'month' => ['required', 'date_format:Y-m'],
        ]);

        $venue = Venue::findOrFail((int) $validated['venue_id']);
        $receipt = $this->payrollService->lockBatch($venue, $validated['month'], $request->user());

        return response()->json([
            'success' => true,
            'receipt' => $receipt,
            'lock_receipt' => $receipt,
        ]);
    }

    /**
     * Generate statutory compliance filing manifest (PF ECR, ESI, Professional Tax).
     */
    public function complianceExport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'venue_id' => ['required', 'integer', 'exists:venues,id'],
            'month' => ['required', 'date_format:Y-m'],
        ]);

        $venue = Venue::findOrFail((int) $validated['venue_id']);
        $manifest = $this->payrollService->generateComplianceExport($venue, $validated['month']);

        return response()->json($manifest);
    }
}
