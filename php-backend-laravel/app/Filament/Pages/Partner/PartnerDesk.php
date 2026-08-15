<?php

declare(strict_types=1);

namespace App\Filament\Pages\Partner;

use App\Models\Booking;
use App\Models\Venue;
use App\Models\VenueCourt;
use App\Services\BookingService;
use App\Support\PartnerBranchContext;
use App\Support\PartnerLane;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Today's Desk — what's free right now, and how to fill it.
 *
 * Every other partner surface is a record of what happened. This is the one you
 * stand at. A café's dominant flow is a walk-in, not a reservation: someone is
 * at the counter asking for a table for four *now*, and the desk has seconds to
 * answer. So the page leads with the floor — every unit, its state, and a button
 * on the free ones — instead of leading with a bookings table you have to read.
 *
 * A desk belongs to ONE branch. If the switcher is on "All branches" the page
 * refuses to guess and asks which floor you are standing on, because seating
 * someone at the wrong outlet is worse than one extra click.
 *
 * Sports venues get it too — a turf desk has the same job — with courts where a
 * café has tables ({@see PartnerLane}).
 */
class PartnerDesk extends Page
{
    protected string $view = 'filament.pages.partner.desk';

    /** /partner/desk — the class name would otherwise give "partner-desk". */
    protected static ?string $slug = 'desk';

    protected static ?string $title = "Today's Desk";

    protected static ?string $navigationLabel = "Today's Desk";

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-bolt';

    protected static ?int $navigationSort = -2;

    /** Walk-in form state. Untyped on purpose — Livewire unsets typed props. */
    public $guestName = '';

    public $guestPhone = '';

    public $partySize = '';

    public $hours = '1';

    /** Which unit the walk-in sheet is open for. */
    public $seatingCourtId = null;

    public static function shouldRegisterNavigation(): bool
    {
        return self::canAccess();
    }

    public static function canAccess(): bool
    {
        if (Filament::getCurrentPanel()?->getId() !== 'partner') {
            return false;
        }

        $user = auth()->user();
        $lane = $user?->partnerLane();

        // Branch lanes only — an event host has no floor — and only for someone
        // allowed to take bookings at all.
        return $lane !== null
            && PartnerLane::isBranchLane($lane)
            && ($user?->hasPartnerPermission('bookings') ?? false);
    }

    public function lane(): string
    {
        return auth()->user()?->partnerLane() ?? PartnerLane::GAMEHUB;
    }

    /** "table" / "court" for this lane. */
    public function noun(bool $plural = false): string
    {
        return PartnerLane::resourceNoun($this->lane(), $plural);
    }

    /** Branches this user may open a desk at. */
    public function branches(): Collection
    {
        return PartnerBranchContext::branches();
    }

    /**
     * The branch whose floor is on screen.
     *
     * The switcher's selection when there is one; the only branch when there is
     * only one. Null means "ask" — never a guess.
     */
    public function branch(): ?Venue
    {
        $selected = PartnerBranchContext::current();

        if ($selected !== null) {
            return $selected;
        }

        $branches = $this->branches();

        return $branches->count() === 1 ? $branches->first() : null;
    }

    /**
     * The floor: every active unit with its state right now.
     *
     * "Busy" is decided by a booking whose window covers this minute, not by the
     * day having any booking at all — the desk needs to know what is free *now*,
     * and a table booked for 9pm is free at 6.
     *
     * @return array<int, array<string, mixed>>
     */
    public function floor(): array
    {
        $branch = $this->branch();

        if ($branch === null) {
            return [];
        }

        $now = Carbon::now();
        $today = $now->copy()->startOfDay();

        $units = VenueCourt::query()
            ->where('venue_id', $branch->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $bookings = Booking::query()
            ->where('booking_type', 'venue')
            ->where('venue_id', $branch->id)
            ->whereDate('slot_date', $today)
            ->whereIn(DB::raw('lower(status)'), ['confirmed', 'paid', 'completed', 'checked_in'])
            ->get(['id', 'venue_court_id', 'start_time', 'end_time', 'guest_name', 'guest_phone', 'total_amount'])
            ->groupBy('venue_court_id');

        return $units->map(function (VenueCourt $c) use ($bookings, $now, $branch): array {
            $mine = $bookings->get($c->id) ?? collect();

            $current = $mine->first(fn (Booking $b): bool => $this->covers($b, $now));
            $next = $mine
                ->filter(fn (Booking $b): bool => $this->startsAfter($b, $now))
                ->sortBy('start_time')
                ->first();

            return [
                'id' => $c->id,
                'name' => $c->name,
                'kind' => $c->kindLabel($this->lane()),
                'seats' => $c->seatsLabel(),
                'seat_count' => $c->seats,
                'rate' => $c->rateFor($now, $now->format('g:i A'), (int) ($branch->price ?? 0)),
                'busy' => $current !== null,
                'busy_until' => $current?->end_time,
                'guest' => $current?->guest_name,
                'next_at' => $next?->start_time,
                'today_count' => $mine->count(),
            ];
        })->all();
    }

    /** Reservations still to come today, across the whole branch. */
    public function upcoming(): array
    {
        $branch = $this->branch();

        if ($branch === null) {
            return [];
        }

        $now = Carbon::now();

        return Booking::query()
            ->where('booking_type', 'venue')
            ->where('venue_id', $branch->id)
            ->whereDate('slot_date', $now->copy()->startOfDay())
            ->whereIn(DB::raw('lower(status)'), ['confirmed', 'paid', 'completed', 'checked_in'])
            ->with('venueCourt:id,name')
            ->get()
            ->filter(fn (Booking $b): bool => $this->startsAfter($b, $now))
            ->sortBy('start_time')
            ->take(8)
            ->map(fn (Booking $b): array => [
                'id' => $b->id,
                'at' => $b->start_time,
                'who' => $b->guest_name ?: 'Walk-in',
                'phone' => $b->guest_phone,
                'where' => $b->venueCourt?->name,
                'amount' => (float) $b->total_amount,
                'paid' => strtolower((string) $b->payment_status) === 'paid',
            ])
            ->values()
            ->all();
    }

    /** Headline counts for the strip above the floor. */
    public function summary(): array
    {
        $floor = $this->floor();
        $free = count(array_filter($floor, fn (array $u): bool => ! $u['busy']));

        return [
            'total' => count($floor),
            'free' => $free,
            'busy' => count($floor) - $free,
            'upcoming' => count($this->upcoming()),
        ];
    }

    // ---------------------------------------------------------------------
    //  Seating a walk-in
    // ---------------------------------------------------------------------

    public function openSeat(int $courtId): void
    {
        $this->seatingCourtId = $courtId;
        $this->guestName = '';
        $this->guestPhone = '';
        $this->partySize = '';
        $this->hours = '1';
    }

    public function closeSeat(): void
    {
        $this->seatingCourtId = null;
    }

    /**
     * Seat the walk-in: a real booking, from now, on this unit.
     *
     * Goes through BookingService::createOfflineVenueBooking() rather than
     * writing a row here, so the desk obeys the same court+window conflict rule,
     * blocked-date guard and pricing as every other booking path. A desk that
     * could double-book by taking a shortcut would be worse than no desk.
     */
    public function seat(BookingService $bookings): void
    {
        $branch = $this->branch();
        $court = $this->seatingCourtId;

        if ($branch === null || $court === null) {
            return;
        }

        $unit = VenueCourt::query()
            ->where('venue_id', $branch->id)
            ->where('is_active', true)
            ->find($court);

        if ($unit === null) {
            Notification::make()->title('That '.$this->noun().' is no longer available.')->danger()->send();
            $this->closeSeat();

            return;
        }

        $party = ctype_digit((string) $this->partySize) ? (int) $this->partySize : null;

        if (! $unit->fitsParty($party)) {
            Notification::make()
                ->title("{$unit->name} seats {$unit->seats} — too small for a party of {$party}.")
                ->warning()
                ->send();

            return;
        }

        try {
            $booking = $bookings->createOfflineVenueBooking(
                auth()->user(),
                (int) $branch->id,
                null,
                Carbon::today()->toDateString(),
                trim((string) $this->guestName) ?: null,
                trim((string) $this->guestPhone) ?: null,
                (int) $unit->id,
                max(1, (int) ($this->hours ?: 1)),
            );
        } catch (\Throwable $e) {
            // Most often the conflict guard: someone is already on it.
            Notification::make()->title('Could not seat them')->body($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()
            ->title('Seated at '.$unit->name)
            ->body(trim((string) $this->guestName) !== ''
                ? $this->guestName.' · ₹'.number_format((float) $booking->total_amount)
                : '₹'.number_format((float) $booking->total_amount))
            ->success()
            ->send();

        $this->closeSeat();
    }

    /** Whether a booking's window covers the given moment. */
    private function covers(Booking $b, Carbon $at): bool
    {
        [$start, $end] = $this->window($b);

        return $start !== null && $end !== null && $at->between($start, $end);
    }

    private function startsAfter(Booking $b, Carbon $at): bool
    {
        [$start] = $this->window($b);

        return $start !== null && $start->gt($at);
    }

    /**
     * A booking's start/end as real times today.
     *
     * @return array{0:?Carbon, 1:?Carbon}
     */
    private function window(Booking $b): array
    {
        $day = Carbon::today();

        $parse = function (?string $t) use ($day): ?Carbon {
            if ($t === null || trim($t) === '') {
                return null;
            }
            $ts = strtotime($t);

            return $ts === false
                ? null
                : $day->copy()->setTime((int) date('G', $ts), (int) date('i', $ts));
        };

        return [$parse($b->start_time), $parse($b->end_time)];
    }
}
