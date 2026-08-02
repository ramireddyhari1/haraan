<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ShiftSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Opening and closing a desk shift.
 *
 * The design rule that makes this survive contact with a real venue: a shift
 * **opens itself** on the first cash taken. Requiring staff to remember to press
 * "start shift" means the day they forget, the money is unattributed and the whole
 * control is worthless. The only act that has to be deliberate is the close-out,
 * because that is where someone counts the drawer.
 *
 * @see \App\Services\BookingLedger  which calls current() when stamping payments
 */
class ShiftService
{
    /**
     * The staff member's open shift at this venue, opening one if there isn't one.
     *
     * @param  float  $openingFloat  only used when a shift is actually opened
     */
    public function current(User $staff, int $venueId, float $openingFloat = 0.0): ShiftSession
    {
        return $this->find($staff, $venueId) ?? $this->open($staff, $venueId, $openingFloat);
    }

    /** The open shift, or null. Never creates one. */
    public function find(User $staff, int $venueId): ?ShiftSession
    {
        return ShiftSession::query()
            ->open()
            ->where('venue_id', $venueId)
            ->where('user_id', $staff->id)
            ->latest('opened_at')
            ->first();
    }

    /**
     * Open a shift. Idempotent per (staff, venue): if one is already open it is
     * returned untouched rather than a second drawer appearing.
     */
    public function open(User $staff, int $venueId, float $openingFloat = 0.0, ?User $openedBy = null): ShiftSession
    {
        return DB::transaction(function () use ($staff, $venueId, $openingFloat, $openedBy): ShiftSession {
            $existing = $this->find($staff, $venueId);

            if ($existing !== null) {
                return $existing;
            }

            return ShiftSession::query()->create([
                'venue_id' => $venueId,
                'user_id' => $staff->id,
                'opened_by' => ($openedBy ?? $staff)->id,
                'opened_at' => now(),
                'opening_float' => max(0.0, $openingFloat),
            ]);
        });
    }

    /**
     * Close a shift against a physical count.
     *
     * The variance is computed and **stored** here rather than derived on read, so
     * a later correction to the ledger cannot retroactively make a short shift look
     * square. What was counted, and what was expected at the moment of counting,
     * is the record.
     */
    public function close(ShiftSession $shift, float $countedCash, ?User $closedBy = null, ?string $note = null): ShiftSession
    {
        return DB::transaction(function () use ($shift, $countedCash, $closedBy, $note): ShiftSession {
            $expected = $shift->expectedCash();

            $shift->forceFill([
                'counted_cash' => round($countedCash, 2),
                'variance' => round($countedCash - $expected, 2),
                'closed_at' => now(),
                'closed_by' => $closedBy?->id,
                'note' => $note,
            ])->save();

            return $shift;
        });
    }

    /**
     * Cash taken at a venue with no shift open — money nobody has claimed
     * responsibility for. Worth surfacing rather than silently absorbing.
     */
    public function unattributedCash(int $venueId): float
    {
        return (float) DB::table('booking_payments')
            ->join('bookings', 'bookings.id', '=', 'booking_payments.booking_id')
            ->where('bookings.venue_id', $venueId)
            ->whereNull('booking_payments.shift_session_id')
            ->whereIn('booking_payments.method', ShiftSession::DRAWER_METHODS)
            ->sum('booking_payments.amount');
    }
}
