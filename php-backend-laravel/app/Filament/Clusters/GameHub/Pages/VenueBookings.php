<?php

declare(strict_types=1);

namespace App\Filament\Clusters\GameHub\Pages;

use App\Filament\Clusters\GameHub\GameHubCluster;
use App\Models\Booking;
use App\Models\Venue;
use App\Models\VenueBlock;
use App\Models\VenueBlockedDate;
use App\Services\BookingLedger;
use App\Services\BookingService;
use App\Support\BusinessClock;
use App\Support\VenueDayGrid;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;

/**
 * Day bookings — the web twin of the partner app's day screen.
 *
 * A date strip, the day in one card (how full, what it's worth, what's in hand,
 * what's still owed), and the courts × times grid. Tapping an open cell seats a
 * walk-in (and can take the money there and then); tapping a booking opens it to
 * check in, collect the balance or cancel.
 *
 * The grid comes from VenueDayGrid — the same code the app's
 * GET /api/partner/venues/{id}/day runs — so a cell drawn Open here is one the
 * booking engine will accept, and a player mid-payment shows as held, not free.
 */
class VenueBookings extends Page
{
    protected static ?string $cluster = GameHubCluster::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $title = 'Day bookings';

    protected static ?string $navigationLabel = 'Day bookings';

    protected static ?int $navigationSort = 99;

    protected static bool $shouldRegisterNavigation = false;

    /**
     * VenueBookingResource (same GameHub cluster) owns /game-hub/venue-bookings.
     * Sharing that URI let the resource's route silently replace this page's, so
     * route('…game-hub.pages.venue-bookings') stopped existing and the partner
     * dashboard's "Day bookings" shortcut 500'd the whole dashboard.
     */
    protected static ?string $slug = 'day-bookings';

    protected string $view = 'filament.clusters.game-hub.venue-bookings';

    /** Statuses that no longer hold a court or owe money. */
    private const DEAD = ['cancelled', 'canceled', 'refunded', 'expired'];

    public ?int $venueId = null;

    public string $date = '';

    /** 'all' | 'due' — the "still to collect" row flips the grid to the owed list. */
    public string $filter = 'all';

    /** Walk-in sheet. Untyped strings on purpose — Livewire unsets typed nulls. */
    public $seatSlotId = null;

    public $seatCourtId = null;

    public $guestName = '';

    public $guestPhone = '';

    public $hours = '1';

    /** cash | upi | later */
    public $payNow = 'cash';

    /** What the open-slot sheet does: seat a walk-in, or block the court. */
    public $sheetMode = 'walkin';

    /** Why the court is blocked (a VenueBlock kind). */
    public $blockKind = 'maintenance';

    public $blockNote = '';

    /** Block sheet (a blocked cell was tapped). */
    public $openBlockId = null;

    /** The reasons offered at the desk, in the words a venue uses. */
    public const BLOCK_REASONS = [
        'maintenance' => 'Maintenance',
        'private'     => 'Private hire',
        'academy'     => 'Coaching',
        'tournament'  => 'Event',
    ];

    /** Booking sheet. */
    public $openBookingId = null;

    /** The booking just made — its cell plays a one-off "landed" animation. */
    public $justBookedId = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return ($user?->canManage('gamehub') ?? false) && $user->hasPartnerPermission('bookings');
    }

    /** Live "booked today" count on the partner nav (scoped to the partner's venues). */
    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();
        if ($user === null) {
            return null;
        }

        $ids = $user->scopedVenueIds();
        $venueIds = Venue::query()
            ->where('partner_id', $user->effectivePartnerId())
            ->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))
            ->pluck('id');

        if ($venueIds->isEmpty()) {
            return null;
        }

        $count = Booking::query()
            ->where('booking_type', 'venue')
            ->whereIn('venue_id', $venueIds)
            ->whereDate('created_at', today())
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Booked today';
    }

    public function mount(): void
    {
        $this->date = BusinessClock::today();
        $this->venueId = $this->venueOptions()->keys()->first();
    }

    // ── Scope ─────────────────────────────────────────────────────────────────

    /** Venues this user may manage (own venues; super-admins see all). */
    protected function venuesQuery(): Builder
    {
        $query = Venue::query()->orderBy('name');
        $user = auth()->user();

        if ($user !== null && ! $user->isSuperAdmin()) {
            // effectivePartnerId so desk staff resolve to their owner's venues
            // (they own none themselves), then narrow to any assigned subset.
            $query->where('partner_id', $user->effectivePartnerId());

            if (($venueIds = $user->scopedVenueIds()) !== null) {
                $query->whereIn('id', $venueIds);
            }
        }

        return $query;
    }

    public function venueOptions(): Collection
    {
        return $this->venuesQuery()->pluck('name', 'id');
    }

    #[Computed]
    public function venue(): ?Venue
    {
        return $this->venueId === null ? null : $this->venuesQuery()->find($this->venueId);
    }

    /** A booking on the selected venue — never another venue's, whatever id is posted. */
    private function ownBooking(int $id): ?Booking
    {
        $venue = $this->venue();

        return $venue === null ? null : Booking::query()
            ->where('booking_type', 'venue')
            ->where('venue_id', $venue->id)
            ->find($id);
    }

    // ── Reads ─────────────────────────────────────────────────────────────────

    /** @return array<string, mixed>|null */
    #[Computed]
    public function grid(): ?array
    {
        $venue = $this->venue();

        return $venue === null ? null : VenueDayGrid::build($venue, $this->date);
    }

    public function isBlocked(): bool
    {
        return (bool) ($this->grid()['is_blocked'] ?? false);
    }

    /**
     * Two weeks around the selected day, each with its real booking count.
     *
     * @return list<array{date: string, dow: string, day: string, month: string, is_today: bool, count: int}>
     */
    public function days(): array
    {
        $today = BusinessClock::todayDate();
        $start = $today->copy()->subDays(2);
        $end = $today->copy()->addDays(11);

        $counts = $this->venue() === null ? collect() : Booking::query()
            ->where('booking_type', 'venue')
            ->where('venue_id', $this->venueId)
            ->whereNotIn(DB::raw('lower(status)'), self::DEAD)
            ->whereBetween('slot_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('date(slot_date) as d, COUNT(*) as n')
            ->groupBy('d')
            ->pluck('n', 'd');

        $days = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $days[] = [
                'date' => $d->toDateString(),
                'dow' => $d->isSameDay($today) ? 'Today' : $d->format('D'),
                'day' => $d->format('j'),
                'month' => $d->format('M'),
                'is_today' => $d->isSameDay($today),
                'count' => (int) ($counts[$d->toDateString()] ?? 0),
            ];
        }

        return $days;
    }

    /**
     * The day card, computed the way the app's DayBookingsRepository does: the grid
     * gives capacity and what's sold; the day's bookings give what's in hand and
     * what's owed.
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function stats(): array
    {
        $grid = $this->grid();
        $total = 0;
        $booked = 0;
        $open = 0;
        $expected = 0.0;

        foreach ($grid['slots'] ?? [] as $slot) {
            if (count($slot['courts']) > 0) {
                foreach ($slot['courts'] as $cell) {
                    // A court that can't be sold at this hour isn't capacity.
                    if (! $cell['allowed']) {
                        continue;
                    }
                    $total++;
                    if ($cell['is_booked']) {
                        $booked++;
                        $expected += $cell['price'];
                    } elseif (! $cell['is_held'] && ! ($grid['is_blocked'] ?? false)) {
                        $open++;
                    }
                }
            } else {
                $total += $slot['capacity'];
                $booked += $slot['booked'];
                $open += $slot['available'];
                $expected += $slot['booked'] * $slot['price'];
            }
        }

        $dayBookings = $this->venue() === null ? collect() : Booking::query()
            ->where('booking_type', 'venue')
            ->where('venue_id', $this->venueId)
            ->whereDate('slot_date', $this->date)
            ->whereNotIn(DB::raw('lower(status)'), self::DEAD)
            ->where(DB::raw('upper(status)'), '!=', 'PENDING')
            ->get(['id', 'total_amount', 'amount_paid', 'payment_status']);

        $collected = (float) $dayBookings->sum('amount_paid');
        $owing = $dayBookings->filter(fn (Booking $b) => (float) $b->total_amount - (float) $b->amount_paid > 0.5);
        $due = (float) $owing->sum(fn (Booking $b) => (float) $b->total_amount - (float) $b->amount_paid);

        $byMethod = $dayBookings->isEmpty() ? collect() : DB::table('booking_payments')
            ->whereIn('booking_id', $dayBookings->pluck('id'))
            ->selectRaw('lower(method) as m, SUM(amount) as total')
            ->groupBy('m')
            ->pluck('total', 'm')
            ->filter(fn ($v) => $v > 0);

        // A day of walk-ins priced by hand can be worth more than the grid's rates say.
        $expected = max($expected, $collected + $due);

        return [
            'total' => $total,
            'booked' => $booked,
            'open' => $open,
            'occupancy' => $total > 0 ? (int) round(min(1, $booked / $total) * 100) : 0,
            'expected' => $expected,
            'collected' => $collected,
            'collected_share' => $expected > 0 ? min(100, round($collected / $expected * 100, 1)) : 0,
            'due' => $due,
            'due_count' => $owing->count(),
            'owing_ids' => $owing->pluck('id')->all(),
            'split' => $byMethod->count() >= 2
                ? $byMethod->map(fn ($v, $m) => (strtolower((string) $m) === 'upi' ? 'UPI' : ucfirst((string) $m)).' ₹'.number_format((float) $v))->join(' · ')
                : null,
        ];
    }

    /**
     * Minutes-of-day "now", only when the selected date is today — drives the
     * now-line and dims rows that have already been played.
     */
    public function nowMinutes(): ?int
    {
        if ($this->date !== BusinessClock::today()) {
            return null;
        }
        $now = BusinessClock::now();

        return (int) $now->format('G') * 60 + (int) $now->format('i');
    }

    public function slotLength(): int
    {
        return $this->venue()?->slotLength() ?? 60;
    }

    /** "7:00 AM" whatever the row stores ("07:00:00", "7 AM", "7:00 AM"). */
    public static function clock(?string $time): string
    {
        $m = BookingService::timeToMinutes($time);

        return $m === null ? (string) $time : Carbon::today()->addMinutes($m)->format('g:i A');
    }

    /** @return array<string, mixed>|null */
    public function openBooking(): ?array
    {
        if ($this->openBookingId === null) {
            return null;
        }

        $b = $this->ownBooking((int) $this->openBookingId);
        if ($b === null) {
            return null;
        }

        return VenueDayGrid::booking($b->loadMissing('user:id,name')) + [
            'court' => $b->venueCourt?->name,
            'time' => trim(self::clock($b->start_time).($b->end_time ? ' – '.self::clock($b->end_time) : '')),
            'balance' => max(0, round((float) $b->total_amount - (float) $b->amount_paid, 2)),
            'is_hold' => strtoupper((string) $b->status) === 'PENDING',
        ];
    }

    public function canCheckIn(): bool
    {
        return auth()->user()?->hasPartnerPermission('checkin') ?? false;
    }

    // ── Day navigation ────────────────────────────────────────────────────────

    public function selectDate(string $date): void
    {
        if (strtotime($date)) {
            $this->date = date('Y-m-d', strtotime($date));
            $this->closeSheets();
        }
    }

    public function shiftDay(int $by): void
    {
        $this->selectDate(Carbon::parse($this->date)->addDays(max(-1, min(1, $by)))->toDateString());
    }

    public function updatedVenueId(): void
    {
        $this->closeSheets();
    }

    public function toggleDue(): void
    {
        $this->filter = $this->filter === 'due' ? 'all' : 'due';
    }

    public function toggleClosed(): void
    {
        $venue = $this->venue();
        if ($venue === null) {
            return;
        }

        if ($this->isBlocked()) {
            VenueBlockedDate::query()->where('venue_id', $venue->id)->whereDate('date', $this->date)->delete();
            Notification::make()->title('Day reopened')->success()->send();
        } else {
            VenueBlockedDate::query()->firstOrCreate(['venue_id' => $venue->id, 'date' => $this->date]);
            Notification::make()->title('Day closed')->body('Nothing can be booked for '.Carbon::parse($this->date)->format('l, j M').'.')->success()->send();
        }

        unset($this->grid, $this->stats);
    }

    // ── Sheets ────────────────────────────────────────────────────────────────

    public function openSeat(int $slotId, int $courtId): void
    {
        $this->closeSheets();
        $this->seatSlotId = $slotId;
        $this->seatCourtId = $courtId;
        $this->hours = '1';
        $this->payNow = 'cash';
        $this->sheetMode = 'walkin';
        $this->blockKind = 'maintenance';
        $this->blockNote = '';
    }

    public function showBlock(int $id): void
    {
        $this->closeSheets();
        $this->openBlockId = $id;
    }

    /**
     * How long the open sheet can run, in the venue's own slot length: 30-minute venues
     * offer 30 min · 1 hr · 1½ hr · 2 hr, hourly ones 1 · 2 · 3 hr. `units` is what
     * reserveVenue() counts (slots, not hours). An option that would run into a booked,
     * held, blocked or unsold slot on this court is offered but disabled.
     *
     * @return list<array{units: int, minutes: int, label: string, until: string, free: bool}>
     */
    public function durationOptions(): array
    {
        $grid = $this->grid();
        $slots = collect($grid['slots'] ?? [])->values();
        $at = $slots->search(fn ($sl) => (int) $sl['slot_id'] === (int) $this->seatSlotId);
        if ($at === false) {
            return [];
        }
        $len = $this->slotLength();
        $start = BookingService::timeToMinutes($slots[$at]['time']);
        $max = $len <= 30 ? 4 : 3;
        $out = [];
        $free = true;
        for ($n = 1; $n <= $max; $n++) {
            $row = $slots[$at + $n - 1] ?? null;
            $rowStart = $row ? BookingService::timeToMinutes($row['time']) : null;
            if ($row === null || $start === null || $rowStart !== $start + ($n - 1) * $len) {
                $free = false;
            } elseif ($this->seatCourtId) {
                $cell = collect($row['courts'])->firstWhere('court_id', (int) $this->seatCourtId);
                if (! $cell || $cell['is_booked'] || $cell['is_held'] || ! $cell['allowed']) {
                    $free = false;
                }
            } elseif (($row['available'] ?? 0) < 1) {
                $free = false;
            }
            $mins = $n * $len;
            $end = $start === null ? null : $start + $mins;
            $out[] = [
                'units' => $n,
                'minutes' => $mins,
                'label' => $mins < 60 ? $mins.' min' : ($mins === 60 ? '1 hr' : intdiv($mins, 60).($mins % 60 ? '½' : '').' hrs'),
                'until' => $end === null ? '' : Carbon::today()->addMinutes($end)->format('g:i A'),
                'free' => $free,
            ];
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    public function openBlock(): ?array
    {
        if ($this->openBlockId === null || $this->venue() === null) {
            return null;
        }
        $b = VenueBlock::query()->where('venue_id', $this->venue()->id)->find((int) $this->openBlockId);
        if ($b === null) {
            return null;
        }

        return VenueDayGrid::block($b) + [
            'court' => $b->court?->name ?? 'Every court',
            'by' => $b->created_by ? \App\Models\User::query()->whereKey($b->created_by)->value('name') : null,
            'time' => $b->isAllDay() ? 'All day' : self::clock($b->start_time).' – '.self::clock($b->end_time),
        ];
    }

    public function showBooking(int $id): void
    {
        $this->closeSheets();
        $this->openBookingId = $id;
    }

    public function closeSheets(): void
    {
        $this->seatSlotId = null;
        $this->seatCourtId = null;
        $this->openBookingId = null;
        $this->openBlockId = null;
        $this->blockNote = '';
        $this->guestName = '';
        $this->guestPhone = '';
        $this->resetValidation();
    }

    // ── Actions ───────────────────────────────────────────────────────────────

    public function seat(BookingService $bookings, BookingLedger $ledger): void
    {
        $venue = $this->venue();
        if ($venue === null || $this->seatSlotId === null) {
            return;
        }

        $this->validate([
            'guestName'  => ['required', 'string', 'max:120'],
            'guestPhone' => ['nullable', 'string', 'max:30'],
            'hours'      => ['required', 'integer', 'min:1', 'max:6'],
            'payNow'     => ['required', 'in:cash,upi,later'],
        ], [], ['guestName' => 'name', 'guestPhone' => 'phone']);

        try {
            $booking = $bookings->createOfflineVenueBooking(
                auth()->user(),
                (int) $venue->id,
                (int) $this->seatSlotId,
                $this->date,
                trim((string) $this->guestName),
                preg_replace('/\s+/', '', (string) $this->guestPhone) ?: null,
                $this->seatCourtId ? (int) $this->seatCourtId : null,
                (int) $this->hours,
            );

            if ($this->payNow !== 'later' && (float) $booking->total_amount > 0) {
                $ledger->collect($booking, (float) $booking->total_amount, (string) $this->payNow, auth()->user());
            }
        } catch (\Throwable $e) {
            // Most often the conflict guard: someone took the court a moment ago.
            Notification::make()->title('Couldn\'t book that')->body($e->getMessage())->danger()->send();
            unset($this->grid, $this->stats);

            return;
        }

        Notification::make()
            ->title(trim((string) $this->guestName).' is booked')
            ->body('₹'.number_format((float) $booking->total_amount).($this->payNow === 'later' ? ' · to collect' : ' · paid by '.strtoupper((string) $this->payNow)))
            ->success()
            ->send();

        $this->justBookedId = $booking->id;
        $this->closeSheets();
        unset($this->grid, $this->stats);
    }

    /**
     * Take the court out of sale for this time — maintenance, a private hire, coaching.
     * Written as a one-off VenueBlock, which reserveVenue() already refuses, so the app,
     * the web and WhatsApp can't sell it either.
     */
    public function blockSlot(): void
    {
        $venue = $this->venue();
        if ($venue === null || $this->seatSlotId === null) {
            return;
        }

        $this->validate([
            'blockKind' => ['required', 'in:'.implode(',', array_keys(self::BLOCK_REASONS))],
            'blockNote' => ['nullable', 'string', 'max:120'],
            'hours'     => ['required', 'integer', 'min:1', 'max:6'],
        ], [], ['blockNote' => 'note']);

        $opt = collect($this->durationOptions())->firstWhere('units', (int) $this->hours);
        if ($opt === null || ! $opt['free']) {
            Notification::make()->title('Couldn\'t block that')->body('Part of that time is already booked or blocked. Pick a shorter time.')->danger()->send();

            return;
        }

        $slot = collect($this->grid()['slots'] ?? [])->firstWhere('slot_id', (int) $this->seatSlotId);
        $start = BookingService::timeToMinutes($slot['time'] ?? null);
        if ($start === null) {
            return;
        }
        $end = min(24 * 60, $start + (int) $opt['minutes']);
        $hm = fn (int $m): string => $m >= 24 * 60 ? '24:00' : sprintf('%02d:%02d', intdiv($m, 60), $m % 60);

        // A court that isn't this venue's can't be blocked from here.
        $courtId = $this->seatCourtId ? (int) $this->seatCourtId : null;
        if ($courtId !== null && ! $venue->courts()->whereKey($courtId)->exists()) {
            return;
        }

        VenueBlock::query()->create([
            'venue_id' => $venue->id,
            'venue_court_id' => $courtId,
            'kind' => $this->blockKind,
            'title' => trim((string) $this->blockNote) ?: null,
            'starts_on' => $this->date,
            'ends_on' => $this->date,
            'weekday' => null,
            'start_time' => $hm($start),
            'end_time' => $hm($end),
            'created_by' => auth()->id(),
        ]);

        Notification::make()
            ->title('Court blocked')
            ->body((self::BLOCK_REASONS[$this->blockKind] ?? 'Blocked').' · '.self::clock($hm($start)).' – '.self::clock($hm($end)))
            ->success()
            ->send();

        $this->closeSheets();
        unset($this->grid, $this->stats);
    }

    /** Lift a one-off block set at the desk. Recurring and whole-venue blocks stay put. */
    public function unblock(int $id): void
    {
        $venue = $this->venue();
        $b = $venue ? VenueBlock::query()->where('venue_id', $venue->id)->find($id) : null;
        if ($b === null) {
            return;
        }
        if (! VenueDayGrid::block($b)['removable']) {
            Notification::make()->title('This block repeats or covers the whole venue')->body('Ask Haraan to change it.')->warning()->send();

            return;
        }
        $b->delete();
        Notification::make()->title('Court open again')->success()->send();
        $this->closeSheets();
        unset($this->grid, $this->stats);
    }

    public function checkIn(int $id): void
    {
        $booking = $this->ownBooking($id);
        if ($booking === null || ! $this->canCheckIn()) {
            return;
        }

        if ($booking->checked_in_at === null) {
            $booking->checked_in_count = max(1, (int) $booking->quantity);
            $booking->checked_in_at = now();
            $booking->save();
        }

        Notification::make()->title(($booking->guest_name ?: $booking->user?->name ?: 'Guest').' checked in')->success()->send();
        unset($this->grid, $this->stats);
    }

    /** The balance, taken at the desk — the same path as the app's "collect". */
    public function collect(int $id, string $method, BookingService $bookings, BookingLedger $ledger): void
    {
        $booking = $this->ownBooking($id);
        if ($booking === null || ! in_array($method, ['cash', 'upi', 'card'], true)) {
            return;
        }

        try {
            if (strtoupper((string) $booking->status) === 'CANCELLED') {
                throw new \RuntimeException('This booking was cancelled.');
            }
            // An online walk-in is still a hold: confirm it before taking money, so a
            // lapsed hold whose court was resold refuses here instead.
            if (in_array(strtoupper((string) $booking->status), ['PENDING', 'EXPIRED'], true)) {
                $booking = $bookings->confirmDeskHold($booking);
            }

            $due = round((float) $booking->total_amount - (float) $booking->amount_paid, 2);
            if ($due > 0) {
                $ledger->collect($booking, $due, $method, auth()->user());
            }
        } catch (\Throwable $e) {
            Notification::make()->title('Couldn\'t record that payment')->body($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title('₹'.number_format($due).' collected')->body('By '.strtoupper($method))->success()->send();
        unset($this->grid, $this->stats);
    }

    public function cancelBooking(int $id, BookingService $bookings): void
    {
        if ($this->ownBooking($id) === null) {
            return;
        }

        try {
            $bookings->cancelAsPartner(auth()->user(), (string) $id);
            Notification::make()->title('Booking cancelled')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Couldn\'t cancel')->body($e->getMessage())->danger()->send();

            return;
        }

        $this->closeSheets();
        unset($this->grid, $this->stats);
    }
}
