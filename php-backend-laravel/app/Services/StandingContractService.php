<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use App\Models\StandingContract;
use App\Models\StandingContractLog;
use App\Models\StandingContractPayment;
use App\Models\StandingContractSession;
use App\Models\User;
use App\Models\VenueCourt;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * End-to-end lifecycle management for Standing Contracts and Recurring Bookings.
 */
class StandingContractService
{
    public function __construct(
        private readonly RecurringConflictEngine $conflictEngine,
    ) {
    }

    /**
     * Create a new Standing Contract and materialize future bookings 30 days ahead.
     */
    public function createContract(User $creator, array $data): StandingContract
    {
        return DB::transaction(function () use ($creator, $data): StandingContract {
            $venueId = (int) $data['venue_id'];
            $courtId = (int) $data['venue_court_id'];
            $dayOfWeek = strtolower(trim($data['day_of_week']));
            $startTime = trim($data['start_time']);
            $endTime = trim($data['end_time']);
            $startDate = trim($data['active_from']);
            $endDate = ! empty($data['active_until']) ? trim($data['active_until']) : null;
            $autoSkipConflicts = (bool) ($data['auto_skip_conflicts'] ?? true);

            // Run conflict analysis
            $conflictReport = $this->conflictEngine->checkConflicts(
                venueId: $venueId,
                courtId: $courtId,
                dayOfWeek: $dayOfWeek,
                startTime: $startTime,
                endTime: $endTime,
                startDate: $startDate,
                endDate: $endDate,
                weeksToCheck: 12
            );

            if (! $conflictReport['is_clear'] && ! $autoSkipConflicts) {
                throw new ConflictHttpException(
                    "Conflicts detected on " . count($conflictReport['conflicts']) . " session dates. Review schedule before creating."
                );
            }

            $conflictingDates = collect($conflictReport['conflicts'])->pluck('date')->all();

            $deposit = (float) ($data['security_deposit'] ?? 0.0);
            $advance = (float) ($data['advance_paid'] ?? 0.0);

            $contract = StandingContract::query()->create([
                'venue_id' => $venueId,
                'venue_court_id' => $courtId,
                'user_id' => isset($data['user_id']) ? (int) $data['user_id'] : null,
                'customer_name' => trim($data['customer_name']),
                'customer_phone' => trim($data['customer_phone']),
                'sport' => $data['sport'] ?? null,
                'day_of_week' => $dayOfWeek,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'duration_minutes' => (int) ($data['duration_minutes'] ?? 60),
                'price_per_session' => (float) $data['price_per_session'],
                'monthly_package_price' => isset($data['monthly_package_price']) ? (float) $data['monthly_package_price'] : null,
                'security_deposit' => $deposit,
                'advance_paid' => $advance,
                'balance_due' => max(0.0, (float) ($data['price_per_session'] ?? 0.0) - $advance),
                'active_from' => $startDate,
                'active_until' => $endDate,
                'status' => StandingContract::STATUS_ACTIVE,
                'auto_renew' => (bool) ($data['auto_renew'] ?? true),
                'max_members' => (int) ($data['max_members'] ?? 10),
                'notes' => $data['notes'] ?? null,
                'created_by' => $creator->id,
            ]);

            // Audit log
            StandingContractLog::query()->create([
                'standing_contract_id' => $contract->id,
                'user_id' => $creator->id,
                'action' => 'created',
                'details' => "Contract created for {$contract->customer_name} ({$contract->day_of_week} {$contract->start_time}-{$contract->end_time}).",
                'ip_address' => request()->ip(),
            ]);

            // If security deposit or advance was paid, record ledger row
            if ($deposit > 0) {
                StandingContractPayment::query()->create([
                    'standing_contract_id' => $contract->id,
                    'amount' => $deposit,
                    'payment_type' => 'deposit',
                    'method' => $data['payment_method'] ?? 'cash',
                    'collected_by' => $creator->id,
                    'notes' => 'Security deposit received on contract creation',
                ]);
            }
            if ($advance > 0) {
                StandingContractPayment::query()->create([
                    'standing_contract_id' => $contract->id,
                    'amount' => $advance,
                    'payment_type' => 'session_advance',
                    'method' => $data['payment_method'] ?? 'cash',
                    'collected_by' => $creator->id,
                    'notes' => 'Advance payment received on contract creation',
                ]);
            }

            // Materialize upcoming sessions 30 days ahead
            $this->materializeSessions($contract, 30, $conflictingDates);

            return $contract->fresh(['court', 'customer', 'sessions']);
        });
    }

    /**
     * Materialize sessions and corresponding child bookings for a contract up to N days ahead.
     *
     * @param  list<string>  $skipDates  Dates to automatically skip (e.g. conflicts)
     */
    public function materializeSessions(StandingContract $contract, int $daysAhead = 30, array $skipDates = []): int
    {
        if (! $contract->isActive()) {
            return 0;
        }

        $horizon = today()->addDays($daysAhead);
        $maxDate = $contract->active_until ? min($horizon, $contract->active_until) : $horizon;

        $targetWeekday = strtolower($contract->day_of_week);
        $current = max(today(), $contract->active_from->copy());

        $generatedCount = 0;

        while ($current->lte($maxDate)) {
            if (strtolower($current->format('l')) === $targetWeekday) {
                $dateStr = $current->toDateString();

                // Check if session already exists for this date
                $sessionExists = StandingContractSession::query()
                    ->where('standing_contract_id', $contract->id)
                    ->whereDate('session_date', $dateStr)
                    ->exists();

                if (! $sessionExists) {
                    $isAutoSkipped = in_array($dateStr, $skipDates, true);

                    $booking = null;
                    if (! $isAutoSkipped) {
                        // Create confirmed child booking on Day Grid
                        $booking = Booking::query()->create([
                            'quantity' => 1,
                            'total_amount' => $contract->price_per_session,
                            'amount_paid' => 0.00,
                            'status' => 'CONFIRMED',
                            'booking_type' => 'venue',
                            'user_id' => $contract->user_id ?? $contract->created_by ?? 1,
                            'venue_id' => $contract->venue_id,
                            'venue_court_id' => $contract->venue_court_id,
                            'slot_date' => $dateStr,
                            'start_time' => $contract->start_time,
                            'end_time' => $contract->end_time,
                            'channel' => 'offline',
                            'guest_name' => $contract->customer_name,
                            'guest_phone' => $contract->customer_phone,
                            'standing_contract_id' => $contract->id,
                        ]);
                    }

                    StandingContractSession::query()->create([
                        'standing_contract_id' => $contract->id,
                        'booking_id' => $booking?->id,
                        'session_date' => $dateStr,
                        'start_time' => $contract->start_time,
                        'end_time' => $contract->end_time,
                        'venue_court_id' => $contract->venue_court_id,
                        'price' => $contract->price_per_session,
                        'attendance_status' => $isAutoSkipped
                            ? StandingContractSession::ATTENDANCE_SKIPPED_MANUAL
                            : StandingContractSession::ATTENDANCE_SCHEDULED,
                        'payment_status' => 'unpaid',
                        'notes' => $isAutoSkipped ? 'Auto-skipped due to schedule conflict' : null,
                    ]);

                    $generatedCount++;
                }
            }

            $current->addDay();
        }

        return $generatedCount;
    }

    /**
     * Skip a specific session (due to holiday, weather, or customer request).
     * Automatically frees up the court on the Day Grid.
     */
    public function skipSession(
        StandingContract $contract,
        string $date,
        string $reason = 'Manual Skip',
        ?User $actor = null,
    ): StandingContractSession {
        return DB::transaction(function () use ($contract, $date, $reason, $actor): StandingContractSession {
            $session = StandingContractSession::query()
                ->where('standing_contract_id', $contract->id)
                ->whereDate('session_date', $date)
                ->firstOrFail();

            // Cancel and remove the court reservation on Day Grid
            if ($session->booking_id !== null) {
                $booking = Booking::query()->find($session->booking_id);
                if ($booking !== null) {
                    $booking->status = 'CANCELLED';
                    $booking->save();
                }
                $session->booking_id = null;
            }

            $status = str_contains(strtolower($reason), 'rain')
                ? StandingContractSession::ATTENDANCE_CANCELLED_RAIN
                : (str_contains(strtolower($reason), 'holiday')
                    ? StandingContractSession::ATTENDANCE_SKIPPED_HOLIDAY
                    : StandingContractSession::ATTENDANCE_SKIPPED_MANUAL);

            $session->attendance_status = $status;
            $session->notes = $reason;
            $session->save();

            StandingContractLog::query()->create([
                'standing_contract_id' => $contract->id,
                'user_id' => $actor?->id,
                'action' => 'session_skipped',
                'details' => "Session on {$date} skipped. Reason: {$reason}",
                'ip_address' => request()->ip(),
            ]);

            $contract->recalculateMetrics();

            return $session;
        });
    }

    /**
     * Pause a contract (e.g. customer traveling for 1 month).
     */
    public function pauseContract(StandingContract $contract, ?User $actor = null, ?string $reason = null): StandingContract
    {
        return DB::transaction(function () use ($contract, $actor, $reason): StandingContract {
            $contract->status = StandingContract::STATUS_PAUSED;
            $contract->save();

            // Cancel any unplayed upcoming bookings
            $futureSessions = $contract->sessions()
                ->whereDate('session_date', '>=', today())
                ->where('attendance_status', StandingContractSession::ATTENDANCE_SCHEDULED)
                ->get();

            foreach ($futureSessions as $s) {
                if ($s->booking_id !== null) {
                    $b = Booking::query()->find($s->booking_id);
                    if ($b !== null) {
                        $b->status = 'CANCELLED';
                        $b->save();
                    }
                    $s->booking_id = null;
                }
                $s->attendance_status = StandingContractSession::ATTENDANCE_SKIPPED_MANUAL;
                $s->notes = 'Contract paused';
                $s->save();
            }

            StandingContractLog::query()->create([
                'standing_contract_id' => $contract->id,
                'user_id' => $actor?->id,
                'action' => 'paused',
                'details' => "Contract paused. " . ($reason ? "Reason: {$reason}" : ''),
                'ip_address' => request()->ip(),
            ]);

            return $contract;
        });
    }

    /**
     * Resume a paused contract.
     */
    public function resumeContract(StandingContract $contract, ?User $actor = null): StandingContract
    {
        return DB::transaction(function () use ($contract, $actor): StandingContract {
            $contract->status = StandingContract::STATUS_ACTIVE;
            $contract->save();

            StandingContractLog::query()->create([
                'standing_contract_id' => $contract->id,
                'user_id' => $actor?->id,
                'action' => 'resumed',
                'details' => "Contract resumed. Materializing upcoming sessions.",
                'ip_address' => request()->ip(),
            ]);

            $this->materializeSessions($contract, 30);
            $contract->recalculateMetrics();

            return $contract;
        });
    }

    /**
     * Terminate contract permanently.
     */
    public function terminateContract(StandingContract $contract, ?User $actor = null, ?string $reason = null): StandingContract
    {
        return DB::transaction(function () use ($contract, $actor, $reason): StandingContract {
            $contract->status = StandingContract::STATUS_TERMINATED;
            $contract->save();

            // Cancel all future bookings
            $futureSessions = $contract->sessions()
                ->whereDate('session_date', '>=', today())
                ->where('attendance_status', StandingContractSession::ATTENDANCE_SCHEDULED)
                ->get();

            foreach ($futureSessions as $s) {
                if ($s->booking_id !== null) {
                    $b = Booking::query()->find($s->booking_id);
                    if ($b !== null) {
                        $b->status = 'CANCELLED';
                        $b->save();
                    }
                    $s->booking_id = null;
                }
                $s->attendance_status = StandingContractSession::ATTENDANCE_CANCELLED_VENUE;
                $s->notes = 'Contract terminated';
                $s->save();
            }

            StandingContractLog::query()->create([
                'standing_contract_id' => $contract->id,
                'user_id' => $actor?->id,
                'action' => 'terminated',
                'details' => "Contract terminated. " . ($reason ? "Reason: {$reason}" : ''),
                'ip_address' => request()->ip(),
            ]);

            return $contract;
        });
    }

    /**
     * Transfer recurring slot to another court.
     */
    public function transferCourt(StandingContract $contract, int $newCourtId, ?User $actor = null): StandingContract
    {
        return DB::transaction(function () use ($contract, $newCourtId, $actor): StandingContract {
            $newCourt = VenueCourt::query()->where('venue_id', $contract->venue_id)->findOrFail($newCourtId);

            $oldCourtId = $contract->venue_court_id;
            $contract->venue_court_id = $newCourt->id;
            $contract->save();

            // Update future sessions and bookings
            $futureSessions = $contract->sessions()
                ->whereDate('session_date', '>=', today())
                ->where('attendance_status', StandingContractSession::ATTENDANCE_SCHEDULED)
                ->get();

            foreach ($futureSessions as $s) {
                $s->venue_court_id = $newCourt->id;
                $s->save();

                if ($s->booking_id !== null) {
                    $b = Booking::query()->find($s->booking_id);
                    if ($b !== null) {
                        $b->venue_court_id = $newCourt->id;
                        $b->save();
                    }
                }
            }

            StandingContractLog::query()->create([
                'standing_contract_id' => $contract->id,
                'user_id' => $actor?->id,
                'action' => 'court_transferred',
                'details' => "Transferred from Court #{$oldCourtId} to Court #{$newCourt->id} ({$newCourt->name}).",
                'ip_address' => request()->ip(),
            ]);

            return $contract;
        });
    }

    /**
     * Record student/group attendance for a session.
     */
    public function recordAttendance(int $sessionId, string $status, ?User $actor = null): StandingContractSession
    {
        return DB::transaction(function () use ($sessionId, $status, $actor): StandingContractSession {
            $session = StandingContractSession::findOrFail($sessionId);
            $session->attendance_status = $status;
            if ($status === StandingContractSession::ATTENDANCE_PRESENT) {
                $session->check_in_time = now();
            }
            $session->save();

            $contract = $session->contract;
            $contract->recalculateMetrics();

            StandingContractLog::query()->create([
                'standing_contract_id' => $contract->id,
                'user_id' => $actor?->id,
                'action' => 'attendance_marked',
                'details' => "Session on {$session->session_date->toDateString()} marked as {$status}.",
                'ip_address' => request()->ip(),
            ]);

            return $session;
        });
    }

    /**
     * Record a payment (deposit, monthly fee, advance) against the standing contract.
     */
    public function recordPayment(
        StandingContract $contract,
        float $amount,
        string $paymentType,
        string $method,
        ?User $collector = null,
        ?string $notes = null,
    ): StandingContractPayment {
        return DB::transaction(function () use ($contract, $amount, $paymentType, $method, $collector, $notes): StandingContractPayment {
            $payment = StandingContractPayment::query()->create([
                'standing_contract_id' => $contract->id,
                'amount' => $amount,
                'payment_type' => $paymentType,
                'method' => $method,
                'collected_by' => $collector?->id,
                'notes' => $notes,
            ]);

            if ($paymentType === 'deposit') {
                $contract->security_deposit += $amount;
            } elseif ($paymentType === 'session_advance' || $paymentType === 'monthly_fee') {
                $contract->advance_paid += $amount;
                $contract->balance_due = max(0.0, $contract->balance_due - $amount);
            }
            $contract->save();

            StandingContractLog::query()->create([
                'standing_contract_id' => $contract->id,
                'user_id' => $collector?->id,
                'action' => 'payment_received',
                'details' => "Received ₹{$amount} ({$paymentType}) via {$method}.",
                'ip_address' => request()->ip(),
            ]);

            return $payment;
        });
    }
}
