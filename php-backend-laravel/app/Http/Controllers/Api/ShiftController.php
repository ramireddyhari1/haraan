<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BookingPayment;
use App\Models\ShiftDrop;
use App\Models\ShiftSession;
use App\Models\Venue;
use App\Services\ShiftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function __construct(private readonly ShiftService $shifts)
    {
    }

    private function branch(Request $request, string|int $id): Venue
    {
        return $request->user()->branches()->findOrFail($id);
    }

    /**
     * GET /api/partner/venues/{id}/shift/current
     * Returns the active open shift for this venue and staff member.
     */
    public function current(Request $request, string $id): JsonResponse
    {
        $venue = $this->branch($request, $id);
        $user = $request->user();

        // Check if current user has an open shift, or if any shift is open at this venue
        $shift = $this->shifts->find($user, (int) $venue->id)
            ?? ShiftSession::query()->open()->where('venue_id', $venue->id)->latest('opened_at')->first();

        if ($shift === null) {
            return response()->json([
                'has_open_shift'    => false,
                'venue_id'          => $venue->id,
                'venue_name'        => $venue->name,
                'unattributed_cash' => $this->shifts->unattributedCash((int) $venue->id),
            ]);
        }

        return response()->json([
            'has_open_shift' => true,
            'shift'          => $this->formatShift($shift, $venue),
        ]);
    }

    /**
     * POST /api/partner/venues/{id}/shift/open
     * Opens a new desk shift with an opening cash float.
     */
    public function open(Request $request, string $id): JsonResponse
    {
        $venue = $this->branch($request, $id);
        $data = $request->validate([
            'opening_float' => ['nullable', 'numeric', 'min:0'],
            'note'          => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $openingFloat = (float) ($data['opening_float'] ?? 0.0);

        $shift = $this->shifts->open($user, (int) $venue->id, $openingFloat, $user);

        if (! empty($data['note'])) {
            $shift->note = $data['note'];
            $shift->save();
        }

        return response()->json([
            'has_open_shift' => true,
            'message'        => 'Shift opened successfully.',
            'shift'          => $this->formatShift($shift, $venue),
        ]);
    }

    /**
     * POST /api/partner/venues/{id}/shift/drop
     * Records a cash expense or mid-shift cash withdrawal from the drawer.
     */
    public function drop(Request $request, string $id): JsonResponse
    {
        $venue = $this->branch($request, $id);
        $data = $request->validate([
            'amount'   => ['required', 'numeric', 'min:0.01'],
            'category' => ['required', 'string', 'max:50'],
            'reason'   => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $shift = $this->shifts->find($user, (int) $venue->id)
            ?? ShiftSession::query()->open()->where('venue_id', $venue->id)->latest('opened_at')->first();

        if ($shift === null) {
            return response()->json([
                'error' => 'No active shift is currently open. Please open a shift before recording a cash drop.',
            ], 422);
        }

        $drop = ShiftDrop::query()->create([
            'shift_session_id' => $shift->id,
            'user_id'          => $user->id,
            'amount'           => (float) $data['amount'],
            'category'         => $data['category'],
            'reason'           => $data['reason'] ?? null,
        ]);

        $shift->refresh();

        return response()->json([
            'message' => 'Cash drop recorded successfully.',
            'drop'    => [
                'id'       => $drop->id,
                'amount'   => (float) $drop->amount,
                'category' => $drop->category,
                'reason'   => $drop->reason,
                'time'     => $drop->created_at->format('H:i'),
            ],
            'shift' => $this->formatShift($shift, $venue),
        ]);
    }

    /**
     * POST /api/partner/venues/{id}/shift/close
     * Closes the active shift against a physical drawer count and logs variance.
     */
    public function close(Request $request, string $id): JsonResponse
    {
        $venue = $this->branch($request, $id);
        $data = $request->validate([
            'counted_cash'   => ['required', 'numeric', 'min:0'],
            'note'           => ['nullable', 'string', 'max:500'],
            'denominations'  => ['nullable', 'array'],
        ]);

        $user = $request->user();
        $shift = $this->shifts->find($user, (int) $venue->id)
            ?? ShiftSession::query()->open()->where('venue_id', $venue->id)->latest('opened_at')->first();

        if ($shift === null) {
            return response()->json([
                'error' => 'No open shift session to close for this venue.',
            ], 422);
        }

        $countedCash = (float) $data['counted_cash'];
        $note = $data['note'] ?? null;

        // If denomination counts provided, serialize into note for audit records
        if (! empty($data['denominations']) && is_array($data['denominations'])) {
            $denomSummary = collect($data['denominations'])
                ->filter(fn ($count) => (int) $count > 0)
                ->map(fn ($count, $val) => "₹{$val}×{$count}")
                ->join(', ');
            if ($denomSummary !== '') {
                $note = trim(($note ? "{$note} | " : '')."Count: {$denomSummary}");
            }
        }

        $closedShift = $this->shifts->close($shift, $countedCash, $user, $note);

        return response()->json([
            'message'        => 'Shift closed successfully.',
            'shift'          => $this->formatShift($closedShift, $venue),
            'variance'       => (float) $closedShift->variance,
            'variance_label' => $closedShift->varianceLabel(),
            'expected_cash'  => (float) $closedShift->expectedCash(),
            'counted_cash'   => (float) $closedShift->counted_cash,
        ]);
    }

    /**
     * GET /api/partner/venues/{id}/shifts
     * Returns historical closed shifts for auditing.
     */
    public function history(Request $request, string $id): JsonResponse
    {
        $venue = $this->branch($request, $id);

        $shifts = ShiftSession::query()
            ->where('venue_id', $venue->id)
            ->whereNotNull('closed_at')
            ->with(['staff:id,name', 'closer:id,name'])
            ->latest('closed_at')
            ->paginate(15);

        return response()->json([
            'data' => collect($shifts->items())->map(function (ShiftSession $s) {
                return [
                    'id'             => $s->id,
                    'staff_name'     => $s->staff?->name ?? 'Staff',
                    'closed_by'      => $s->closer?->name,
                    'opened_at'      => $s->opened_at->toIso8601String(),
                    'closed_at'      => $s->closed_at?->toIso8601String(),
                    'opening_float'  => (float) $s->opening_float,
                    'cash_collected' => (float) $s->cashMovement(),
                    'total_drops'    => (float) $s->totalDrops(),
                    'expected_cash'  => (float) $s->expectedCash(),
                    'counted_cash'   => $s->counted_cash !== null ? (float) $s->counted_cash : null,
                    'variance'       => $s->variance !== null ? (float) $s->variance : null,
                    'variance_label' => $s->varianceLabel(),
                    'note'           => $s->note,
                ];
            }),
            'current_page' => $shifts->currentPage(),
            'last_page'    => $shifts->lastPage(),
            'total'        => $shifts->total(),
        ]);
    }

    /**
     * GET /api/partner/venues/{id}/shifts/{shiftId}
     * Inspect a specific past shift with all payments and cash drops.
     */
    public function show(Request $request, string $id, string $shiftId): JsonResponse
    {
        $venue = $this->branch($request, $id);

        $shift = ShiftSession::query()
            ->where('venue_id', $venue->id)
            ->with(['staff:id,name', 'closer:id,name', 'payments.booking', 'drops.staff:id,name'])
            ->findOrFail($shiftId);

        return response()->json([
            'shift' => $this->formatShift($shift, $venue),
        ]);
    }

    private function formatShift(ShiftSession $shift, Venue $venue): array
    {
        $payments = $shift->payments()
            ->with(['booking.user'])
            ->latest('collected_at')
            ->take(50)
            ->get()
            ->map(function (BookingPayment $p): array {
                $b = $p->booking;
                $customerName = $b?->guest_name
                    ?: ($b?->attendee_name ?: ($b?->user?->name ?? 'Walk-in Guest'));

                return [
                    'id'            => $p->id,
                    'amount'        => (float) $p->amount,
                    'method'        => $p->method,
                    'customer_name' => $customerName,
                    'time'          => $p->collected_at?->format('H:i') ?? '',
                ];
            });

        $drops = $shift->drops()
            ->with('staff:id,name')
            ->latest()
            ->get()
            ->map(function (ShiftDrop $d): array {
                return [
                    'id'         => $d->id,
                    'amount'     => (float) $d->amount,
                    'category'   => $d->category,
                    'reason'     => $d->reason,
                    'staff_name' => $d->staff?->name ?? 'Staff',
                    'time'       => $d->created_at->format('H:i'),
                ];
            });

        return [
            'id'             => $shift->id,
            'is_open'        => $shift->isOpen(),
            'venue_id'       => $venue->id,
            'venue_name'     => $venue->name,
            'staff_id'       => $shift->user_id,
            'staff_name'     => $shift->staff?->name ?? 'Staff',
            'opened_at'      => $shift->opened_at->toIso8601String(),
            'closed_at'      => $shift->closed_at?->toIso8601String(),
            'opening_float'  => (float) $shift->opening_float,
            'cash_collected' => (float) $shift->cashMovement(),
            'upi_collected'  => (float) $shift->payments()->where('method', 'upi')->sum('amount'),
            'card_collected' => (float) $shift->payments()->where('method', 'card')->sum('amount'),
            'total_drops'    => (float) $shift->totalDrops(),
            'expected_cash'  => (float) $shift->expectedCash(),
            'counted_cash'   => $shift->counted_cash !== null ? (float) $shift->counted_cash : null,
            'variance'       => $shift->variance !== null ? (float) $shift->variance : null,
            'variance_label' => $shift->varianceLabel(),
            'note'           => $shift->note,
            'payments'       => $payments,
            'drops'          => $drops,
        ];
    }
}
