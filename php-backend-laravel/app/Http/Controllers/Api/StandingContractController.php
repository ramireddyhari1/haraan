<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StandingContract;
use App\Models\StandingContractLog;
use App\Models\StandingContractPayment;
use App\Models\StandingContractSession;
use App\Models\Venue;
use App\Services\RecurringConflictEngine;
use App\Services\StandingContractService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StandingContractController extends Controller
{
    public function __construct(
        private readonly StandingContractService $contractService,
        private readonly RecurringConflictEngine $conflictEngine,
    ) {
    }

    private function branch(Request $request, string|int $id): Venue
    {
        return $request->user()->branches()->findOrFail($id);
    }

    /**
     * GET /api/partner/venues/{id}/standing-contracts/dashboard
     * Aggregated KPI metrics, revenue health, and at-risk churn warnings.
     */
    public function dashboard(Request $request, string $id): JsonResponse
    {
        $venue = $this->branch($request, $id);

        $contracts = StandingContract::query()->where('venue_id', $venue->id)->get();

        $active = $contracts->whereIn('status', [StandingContract::STATUS_ACTIVE, StandingContract::STATUS_AT_RISK]);
        $atRisk = $contracts->where('is_at_risk', true);
        $paused = $contracts->where('status', StandingContract::STATUS_PAUSED);

        // Monthly Recurring Revenue estimate = (Weekly Price * 4.33) across active contracts
        $mrr = $active->sum(fn ($c) => ($c->monthly_package_price ?: ($c->price_per_session * 4.33)));

        $totalDeposits = $contracts->sum('security_deposit');

        $avgAttendance = $active->count() > 0 ? round($active->avg('attendance_rate'), 1) : 100.0;

        // Today's recurring sessions
        $todaySessions = StandingContractSession::query()
            ->whereHas('contract', fn ($q) => $q->where('venue_id', $venue->id))
            ->whereDate('session_date', today())
            ->with(['contract', 'court'])
            ->orderBy('start_time')
            ->get()
            ->map(fn ($s) => [
                'session_id' => $s->id,
                'contract_id' => $s->standing_contract_id,
                'customer_name' => $s->contract->customer_name,
                'customer_phone' => $s->contract->customer_phone,
                'court_name' => $s->court->name,
                'start_time' => $s->start_time,
                'end_time' => $s->end_time,
                'attendance_status' => $s->attendance_status,
                'payment_status' => $s->payment_status,
                'price' => (float) $s->price,
            ]);

        $atRiskAlerts = $atRisk->map(fn ($c) => [
            'id' => $c->id,
            'customer_name' => $c->customer_name,
            'customer_phone' => $c->customer_phone,
            'weekday' => ucfirst($c->day_of_week),
            'time' => "{$c->start_time}-{$c->end_time}",
            'missed_streak' => $c->consecutive_missed_sessions,
            'attendance_rate' => (float) $c->attendance_rate,
            'risk_reason' => $c->risk_reason,
        ]);

        return response()->json([
            'metrics' => [
                'total_contracts' => $contracts->count(),
                'active_contracts' => $active->count(),
                'at_risk_contracts' => $atRisk->count(),
                'paused_contracts' => $paused->count(),
                'monthly_recurring_revenue' => round($mrr, 2),
                'total_security_deposits' => round($totalDeposits, 2),
                'average_attendance_rate' => $avgAttendance,
            ],
            'today_sessions' => $todaySessions,
            'at_risk_alerts' => $atRiskAlerts,
        ]);
    }

    /**
     * GET /api/partner/venues/{id}/standing-contracts
     * Searchable, filterable list of standing contracts.
     */
    public function index(Request $request, string $id): JsonResponse
    {
        $venue = $this->branch($request, $id);

        $status = $request->query('status', 'all');
        $search = $request->query('search');
        $courtId = $request->query('court_id');
        $weekday = $request->query('weekday');

        $query = StandingContract::query()
            ->where('venue_id', $venue->id)
            ->with(['court'])
            ->withCount([
                'sessions as upcoming_sessions_count' => fn ($q) => $q->whereDate('session_date', '>=', today())->where('attendance_status', 'scheduled'),
            ])
            ->when($status !== 'all', function ($q) use ($status): void {
                if ($status === 'at_risk') {
                    $q->where('is_at_risk', true);
                } else {
                    $q->where('status', $status);
                }
            })
            ->when(! empty($courtId), fn ($q) => $q->where('venue_court_id', $courtId))
            ->when(! empty($weekday), fn ($q) => $q->where('day_of_week', strtolower($weekday)))
            ->when(! empty($search), function ($q) use ($search): void {
                $q->where(function ($sub) use ($search): void {
                    $sub->where('customer_name', 'like', "%{$search}%")
                        ->orWhere('customer_phone', 'like', "%{$search}%");
                });
            })
            ->orderByRaw("case when is_at_risk = 1 then 0 else 1 end")
            ->latest('id');

        $contracts = $query->paginate(20);

        return response()->json([
            'data' => collect($contracts->items())->map(fn (StandingContract $c) => $this->formatContractSummary($c)),
            'current_page' => $contracts->currentPage(),
            'last_page' => $contracts->lastPage(),
            'total' => $contracts->total(),
        ]);
    }

    /**
     * POST /api/partner/venues/{id}/standing-contracts/check-conflicts
     * Pre-creation collision test against bookings, blocks, and existing contracts.
     */
    public function checkConflicts(Request $request, string $id): JsonResponse
    {
        $venue = $this->branch($request, $id);

        $data = $request->validate([
            'court_id' => ['required', 'integer'],
            'day_of_week' => ['required', 'string'],
            'start_time' => ['required', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'end_time' => ['required', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'active_from' => ['required', 'date'],
            'active_until' => ['nullable', 'date', 'after_or_equal:active_from'],
            'weeks' => ['nullable', 'integer', 'min:1', 'max:52'],
            'exclude_contract_id' => ['nullable', 'integer'],
        ]);

        $report = $this->conflictEngine->checkConflicts(
            venueId: (int) $venue->id,
            courtId: (int) $data['court_id'],
            dayOfWeek: $data['day_of_week'],
            startTime: $data['start_time'],
            endTime: $data['end_time'],
            startDate: (string) $data['active_from'],
            endDate: $data['active_until'] ?? null,
            weeksToCheck: isset($data['weeks']) ? (int) $data['weeks'] : 12,
            excludeContractId: isset($data['exclude_contract_id']) ? (int) $data['exclude_contract_id'] : null,
        );

        return response()->json($report);
    }

    /**
     * POST /api/partner/venues/{id}/standing-contracts
     * Creates contract and generates 30-day schedule.
     */
    public function store(Request $request, string $id): JsonResponse
    {
        $venue = $this->branch($request, $id);

        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'user_id' => ['nullable', 'integer'],
            'court_id' => ['required', 'integer'],
            'sport' => ['nullable', 'string', 'max:50'],
            'day_of_week' => ['required', 'string'],
            'start_time' => ['required', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'end_time' => ['required', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'duration_minutes' => ['nullable', 'integer', 'min:30', 'max:240'],
            'price_per_session' => ['required', 'numeric', 'min:0'],
            'monthly_package_price' => ['nullable', 'numeric', 'min:0'],
            'security_deposit' => ['nullable', 'numeric', 'min:0'],
            'advance_paid' => ['nullable', 'numeric', 'min:0'],
            'active_from' => ['required', 'date'],
            'active_until' => ['nullable', 'date', 'after_or_equal:active_from'],
            'auto_renew' => ['nullable', 'boolean'],
            'auto_skip_conflicts' => ['nullable', 'boolean'],
            'max_members' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['nullable', 'string', 'in:cash,upi,card,online,wallet'],
        ]);

        $data['venue_id'] = $venue->id;
        $data['venue_court_id'] = $data['court_id'];

        $contract = $this->contractService->createContract($request->user(), $data);

        return response()->json([
            'message' => 'Standing Contract created successfully.',
            'contract' => $this->formatContractDetail($contract),
        ], 201);
    }

    /**
     * GET /api/partner/venues/{id}/standing-contracts/{contractId}
     * Inspect full contract details, upcoming schedule, attendance, payments, and audit logs.
     */
    public function show(Request $request, string $id, string $contractId): JsonResponse
    {
        $venue = $this->branch($request, $id);

        $contract = StandingContract::query()
            ->where('venue_id', $venue->id)
            ->with(['court', 'customer', 'creator'])
            ->findOrFail($contractId);

        return response()->json([
            'contract' => $this->formatContractDetail($contract),
        ]);
    }

    /**
     * POST /api/partner/venues/{id}/standing-contracts/{contractId}/skip-date
     * Skip a single upcoming session date.
     */
    public function skipDate(Request $request, string $id, string $contractId): JsonResponse
    {
        $venue = $this->branch($request, $id);

        $data = $request->validate([
            'date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $contract = StandingContract::query()->where('venue_id', $venue->id)->findOrFail($contractId);

        $session = $this->contractService->skipSession(
            $contract,
            (string) $data['date'],
            $data['reason'] ?? 'Manual Skip',
            $request->user()
        );

        return response()->json([
            'message' => "Session on {$data['date']} skipped and slot freed on grid.",
            'session' => [
                'id' => $session->id,
                'session_date' => $session->session_date->toDateString(),
                'attendance_status' => $session->attendance_status,
                'notes' => $session->notes,
            ],
            'contract' => $this->formatContractDetail($contract->fresh()),
        ]);
    }

    /**
     * POST /api/partner/venues/{id}/standing-contracts/{contractId}/pause
     */
    public function pause(Request $request, string $id, string $contractId): JsonResponse
    {
        $venue = $this->branch($request, $id);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        $contract = StandingContract::query()->where('venue_id', $venue->id)->findOrFail($contractId);
        $updated = $this->contractService->pauseContract($contract, $request->user(), $data['reason'] ?? null);

        return response()->json([
            'message' => 'Contract paused successfully.',
            'contract' => $this->formatContractDetail($updated),
        ]);
    }

    /**
     * POST /api/partner/venues/{id}/standing-contracts/{contractId}/resume
     */
    public function resume(Request $request, string $id, string $contractId): JsonResponse
    {
        $venue = $this->branch($request, $id);

        $contract = StandingContract::query()->where('venue_id', $venue->id)->findOrFail($contractId);
        $updated = $this->contractService->resumeContract($contract, $request->user());

        return response()->json([
            'message' => 'Contract resumed and upcoming slots materialized.',
            'contract' => $this->formatContractDetail($updated),
        ]);
    }

    /**
     * POST /api/partner/venues/{id}/standing-contracts/{contractId}/terminate
     */
    public function terminate(Request $request, string $id, string $contractId): JsonResponse
    {
        $venue = $this->branch($request, $id);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        $contract = StandingContract::query()->where('venue_id', $venue->id)->findOrFail($contractId);
        $updated = $this->contractService->terminateContract($contract, $request->user(), $data['reason'] ?? null);

        return response()->json([
            'message' => 'Contract terminated and future slots cancelled.',
            'contract' => $this->formatContractDetail($updated),
        ]);
    }

    /**
     * POST /api/partner/venues/{id}/standing-contracts/{contractId}/transfer-court
     */
    public function transferCourt(Request $request, string $id, string $contractId): JsonResponse
    {
        $venue = $this->branch($request, $id);
        $data = $request->validate(['court_id' => ['required', 'integer']]);

        $contract = StandingContract::query()->where('venue_id', $venue->id)->findOrFail($contractId);
        $updated = $this->contractService->transferCourt($contract, (int) $data['court_id'], $request->user());

        return response()->json([
            'message' => 'Contract transferred to new court successfully.',
            'contract' => $this->formatContractDetail($updated),
        ]);
    }

    /**
     * POST /api/partner/venues/{id}/standing-contracts/{contractId}/attendance
     */
    public function attendance(Request $request, string $id, string $contractId): JsonResponse
    {
        $venue = $this->branch($request, $id);
        $data = $request->validate([
            'session_id' => ['required', 'integer'],
            'status' => ['required', 'string', 'in:present,absent,cancelled_customer,cancelled_venue,cancelled_rain'],
        ]);

        $contract = StandingContract::query()->where('venue_id', $venue->id)->findOrFail($contractId);

        $session = $this->contractService->recordAttendance(
            (int) $data['session_id'],
            (string) $data['status'],
            $request->user()
        );

        return response()->json([
            'message' => "Attendance recorded: {$data['status']}.",
            'session' => [
                'id' => $session->id,
                'session_date' => $session->session_date->toDateString(),
                'attendance_status' => $session->attendance_status,
                'check_in_time' => $session->check_in_time?->toIso8601String(),
            ],
            'contract' => $this->formatContractDetail($contract->fresh()),
        ]);
    }

    /**
     * POST /api/partner/venues/{id}/standing-contracts/{contractId}/payment
     */
    public function payment(Request $request, string $id, string $contractId): JsonResponse
    {
        $venue = $this->branch($request, $id);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_type' => ['required', 'string', 'in:deposit,session_advance,monthly_fee,penalty,refund'],
            'method' => ['required', 'string', 'in:cash,upi,card,online,wallet'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $contract = StandingContract::query()->where('venue_id', $venue->id)->findOrFail($contractId);

        $payment = $this->contractService->recordPayment(
            $contract,
            (float) $data['amount'],
            (string) $data['payment_type'],
            (string) $data['method'],
            $request->user(),
            $data['notes'] ?? null
        );

        return response()->json([
            'message' => 'Payment recorded successfully.',
            'payment' => [
                'id' => $payment->id,
                'amount' => (float) $payment->amount,
                'payment_type' => $payment->payment_type,
                'method' => $payment->method,
                'created_at' => $payment->created_at->toIso8601String(),
            ],
            'contract' => $this->formatContractDetail($contract->fresh()),
        ]);
    }

    private function formatContractSummary(StandingContract $c): array
    {
        $nextSession = $c->sessions()
            ->whereDate('session_date', '>=', today())
            ->where('attendance_status', 'scheduled')
            ->orderBy('session_date')
            ->first();

        return [
            'id' => $c->id,
            'customer_name' => $c->customer_name,
            'customer_phone' => $c->customer_phone,
            'court_id' => $c->venue_court_id,
            'court_name' => $c->court?->name ?? 'Court',
            'sport' => $c->sport,
            'day_of_week' => ucfirst($c->day_of_week),
            'start_time' => $c->start_time,
            'end_time' => $c->end_time,
            'price_per_session' => (float) $c->price_per_session,
            'monthly_value' => (float) ($c->monthly_package_price ?: ($c->price_per_session * 4.33)),
            'security_deposit' => (float) $c->security_deposit,
            'balance_due' => (float) $c->balance_due,
            'attendance_rate' => (float) $c->attendance_rate,
            'status' => $c->status,
            'is_at_risk' => $c->is_at_risk,
            'risk_reason' => $c->risk_reason,
            'active_from' => $c->active_from->toDateString(),
            'active_until' => $c->active_until?->toDateString(),
            'next_session' => $nextSession ? [
                'id' => $nextSession->id,
                'date' => $nextSession->session_date->toDateString(),
                'time' => "{$nextSession->start_time}-{$nextSession->end_time}",
            ] : null,
        ];
    }

    private function formatContractDetail(StandingContract $c): array
    {
        $summary = $this->formatContractSummary($c);

        $sessions = $c->sessions()
            ->with('court')
            ->orderByDesc('session_date')
            ->take(30)
            ->get()
            ->map(fn (StandingContractSession $s) => [
                'id' => $s->id,
                'booking_id' => $s->booking_id,
                'session_date' => $s->session_date->toDateString(),
                'start_time' => $s->start_time,
                'end_time' => $s->end_time,
                'court_name' => $s->court?->name ?? 'Court',
                'price' => (float) $s->price,
                'attendance_status' => $s->attendance_status,
                'payment_status' => $s->payment_status,
                'check_in_time' => $s->check_in_time?->toIso8601String(),
                'notes' => $s->notes,
            ]);

        $payments = $c->payments()
            ->with('collector:id,name')
            ->orderByDesc('created_at')
            ->take(20)
            ->get()
            ->map(fn (StandingContractPayment $p) => [
                'id' => $p->id,
                'amount' => (float) $p->amount,
                'payment_type' => $p->payment_type,
                'method' => $p->method,
                'collector_name' => $p->collector?->name ?? 'Desk',
                'notes' => $p->notes,
                'created_at' => $p->created_at->toIso8601String(),
            ]);

        $logs = $c->logs()
            ->with('actor:id,name')
            ->orderByDesc('created_at')
            ->take(20)
            ->get()
            ->map(fn (StandingContractLog $l) => [
                'id' => $l->id,
                'action' => $l->action,
                'details' => $l->details,
                'actor_name' => $l->actor?->name ?? 'System',
                'created_at' => $l->created_at->toIso8601String(),
            ]);

        return array_merge($summary, [
            'duration_minutes' => $c->duration_minutes,
            'auto_renew' => $c->auto_renew,
            'max_members' => $c->max_members,
            'notes' => $c->notes,
            'consecutive_missed_sessions' => $c->consecutive_missed_sessions,
            'total_sessions_count' => $c->total_sessions_count,
            'attended_sessions_count' => $c->attended_sessions_count,
            'sessions' => $sessions,
            'payments' => $payments,
            'logs' => $logs,
        ]);
    }
}
