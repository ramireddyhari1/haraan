<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Booking;
use App\Models\Venue;
use App\Models\VenueBlockedDate;
use App\Models\VenueCourt;
use App\Models\VenueSlot;
use App\Services\BookingService;
use Illuminate\Support\Carbon;

/**
 * One venue's day as the desk sees it: every time row for that date, every
 * active court as a column, and in each cell whether it's sold, held by someone
 * mid-payment, not sellable, or open — with the price it would actually charge.
 *
 * The partner app (GET /api/partner/venues/{id}/day) and the web "Day bookings"
 * page both read this, so the two can't drift: a cell the web shows as Open is
 * one reserveVenue() will accept, exactly as in the app.
 */
final class VenueDayGrid
{
    /**
     * @return array{date: string, venue: array{id: int, name: string}, is_blocked: bool,
     *     courts: list<array{id: int, name: string, sports: array}>, slots: list<array<string, mixed>>}
     */
    public static function build(Venue $venue, string $date): array
    {
        $isBlocked = VenueBlockedDate::query()
            ->where('venue_id', $venue->id)->whereDate('date', $date)->exists();

        // One date, one day's template rows. Without this the grid stacked all
        // seven weekdays under whichever date was selected.
        $slots = VenueSlot::forDate(
            VenueSlot::query()->where('venue_id', $venue->id)->get(),
            Carbon::parse($date),
        );
        $courts = VenueCourt::query()->where('venue_id', $venue->id)
            ->where('is_active', true)->orderBy('sort_order')->get();

        // Confirmed sales AND live holds. A hold is a player part-way through paying in
        // the app: the conflict engine already refuses to sell that court-hour twice, so
        // a grid showing only confirmed rows drew the cell Open and then answered the
        // desk's tap with "already booked for this time" — an error about a booking the
        // desk could not see. They stay in separate buckets below: a hold blocks the
        // cell, but it is not a sale and must never be counted as one.
        $all = Booking::query()
            ->where('booking_type', 'venue')->where('venue_id', $venue->id)
            ->whereDate('slot_date', $date)
            // The conflict engine's own list (confirmed in any casing, paid, completed,
            // checked in, live holds).
            ->where(fn ($q) => BookingService::occupyingStatuses($q))
            ->with('user:id,name')->get();

        $isHold = static fn (Booking $b): bool => strtoupper((string) $b->status) === 'PENDING';
        $bookings = $all->reject($isHold);
        $holds = $all->filter($isHold);

        // Place every booking on the cells it covers — by its slot, or by its own hours
        // when it has none (app, web and WhatsApp bookings), and on every court when it
        // names none. Keyed "court-slot"; court 0 is a venue with no courts.
        $courtIds = $courts->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $length = $venue->slotLength();
        $place = static function ($list) use ($slots, $courtIds, $length): array {
            $bySlot = [];
            $byCell = [];
            foreach ($list as $b) {
                foreach (CourtOccupancy::slotIdsFor($b, $slots, $length) as $slotId) {
                    $bySlot[$slotId][] = $b;
                    foreach (CourtOccupancy::courtIdsFor($b, $courtIds) as $courtId) {
                        $byCell[$courtId.'-'.$slotId][] = $b;
                    }
                }
            }

            return [
                collect($bySlot)->map(fn ($l) => collect($l)),
                collect($byCell)->map(fn ($l) => collect($l)),
            ];
        };
        [$bySlot, $byCell] = $place($bookings);
        [$holdsBySlot, $holdsByCell] = $place($holds);

        $day = Carbon::parse($date);

        $rows = $slots->map(function (VenueSlot $s) use ($bySlot, $byCell, $holdsBySlot, $holdsByCell, $courts, $day, $venue): array {
            $b = $bySlot->get($s->id) ?? collect();
            $sHeld = $holdsBySlot->get($s->id) ?? collect();
            $cells = $courts->map(function (VenueCourt $c) use ($s, $byCell, $holdsByCell, $day, $venue): array {
                $cb = $byCell->get($c->id.'-'.$s->id) ?? collect();
                $ch = $holdsByCell->get($c->id.'-'.$s->id) ?? collect();
                // The rate this cell would actually CHARGE — peak included. Showing the
                // base rate here while reserveVenue() bills the peak one would have the
                // desk quoting a price the customer is never charged.
                $rate = $c->rateFor($day->copy(), $s->time, (int) ($venue->price ?? 0));

                return [
                    'court_id'  => $c->id,
                    'booked'    => $cb->count(),
                    'is_booked' => $cb->isNotEmpty(),
                    // Someone is at the payment screen for this court-hour right now.
                    'held'      => $ch->count(),
                    'is_held'   => $cb->isEmpty() && $ch->isNotEmpty(),
                    'price'     => (float) $rate,
                    'is_peak'   => $c->isPeak($day->copy(), $s->time),
                    // Whether this court may be sold at all at this time — decided here
                    // so it matches what reserveVenue() will accept. False cells are
                    // unsellable, not merely busy.
                    'allowed'   => $s->allowsCourt($c),
                    'bookings'  => $cb->map(fn (Booking $x): array => self::booking($x))->values(),
                ];
            })->values();

            return [
                'slot_id'   => $s->id,
                'label'     => trim(($s->day ?? '').' · '.($s->time ?? ''), " ·\t"),
                'time'      => $s->time,
                'price'     => (float) $s->price,
                'capacity'  => (int) $s->capacity,
                // Which sports this time runs for; empty = all of them.
                'sports'    => $s->sportsList(),
                'booked'    => $b->count(),
                // Held court-hours are out of stock without being sold, so they come
                // off `available` while staying out of `booked`.
                'held'      => $sHeld->count(),
                'available' => max((int) $s->capacity - $b->count() - $sHeld->count(), 0),
                'is_open'   => (bool) $s->is_available,
                'bookings'  => $b->map(fn (Booking $x): array => self::booking($x))->values(),
                'courts'    => $cells,
            ];
        });

        return [
            'date'       => $date,
            'venue'      => ['id' => $venue->id, 'name' => $venue->name],
            'is_blocked' => $isBlocked,
            'courts'     => $courts->map(fn (VenueCourt $c): array => [
                'id'     => $c->id,
                'name'   => $c->name,
                'sports' => $c->sportsList(),
            ])->values(),
            'slots'      => $rows,
        ];
    }

    /**
     * One booking as the desk needs it.
     *
     * @return array<string, mixed>
     */
    public static function booking(Booking $x): array
    {
        return [
            'id'          => $x->id,
            'customer'    => $x->guest_name ?: ($x->user?->name ?? 'Guest'),
            'phone'       => $x->guest_phone,
            'channel'     => $x->channel ?? 'online',
            'status'      => $x->status,
            'checked_in'  => (int) $x->checked_in_count,
            'ticket_code' => $x->ticket_code,
            'amount'      => round((float) $x->total_amount, 2),
            'amount_paid' => round((float) $x->amount_paid, 2),
            // unpaid | part | paid — drives the desk's "who still owes" chase list.
            'payment_status' => $x->payment_status,
        ];
    }
}
