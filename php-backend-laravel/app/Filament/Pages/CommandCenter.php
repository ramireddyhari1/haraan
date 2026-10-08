<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\MemberSubscriptions\MemberSubscriptionResource;
use App\Filament\Resources\Payouts\PayoutResource;
use App\Filament\Resources\SupportThreads\SupportThreadResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\Venues\VenueResource;
use App\Models\AdminAction;
use App\Models\Booking;
use App\Models\Event;
use App\Models\Payout;
use App\Models\PayoutBatch;
use App\Models\SupportThread;
use App\Models\User;
use App\Models\Venue;
use App\Services\PartnerSettlement;
use App\Support\BusinessClock;
use App\Support\Rupees;
use App\Support\TakingsSeries;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\On;

/**
 * The /control landing page: what came in, where it went, and what needs a person.
 *
 * Every figure on it is read from the database at render time. An earlier version
 * padded empty tables with invented numbers, sample bookings and "AI" cards, so an
 * admin could not tell the platform's real state from decoration. When a table is
 * empty the page now says so instead.
 */
class CommandCenter extends Page
{
    protected string $view = 'filament.pages.command-center';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-command-line';

    protected static ?string $title = 'Command Center';

    protected static ?string $navigationLabel = 'Command Center';

    protected static string|\UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = -100;

    /** Booking statuses that represent money actually collected (case-insensitive). */
    private const PAID = ['confirmed', 'paid', 'completed', 'checked_in'];

    /** Hours shown on the "courts today" strip, in the business zone. */
    private const COURT_DAY_START = 6;

    private const COURT_DAY_END = 23;

    public ?string $range = '30d';

    public string $searchQuery = '';

    /** @var array<string,mixed> */
    public array $hero = [];

    /** @var array<string,mixed> */
    public array $series = [];

    /** @var array<int,array<string,mixed>> */
    public array $split = [];

    /** @var array<string,mixed> */
    public array $events = [];

    /** @var array<string,mixed> */
    public array $venues = [];

    /** @var array<string,mixed> */
    public array $members = [];

    /** @var array<int,array<string,mixed>> */
    public array $radar = [];

    /** @var array<int,array<string,mixed>> */
    public array $feed = [];

    /** @var array<int,array<string,mixed>> */
    public array $cities = [];

    /** @var array<string,mixed> */
    public array $ledger = [];

    /** @var array<int,array<string,mixed>> */
    public array $systems = [];

    /** @var array<int,array<string,mixed>> */
    public array $audit = [];

    /** @var array<int,array<string,mixed>> */
    public array $searchResults = [];

    public static function canAccess(): bool
    {
        $u = auth()->user();

        return (bool) ($u?->isSuperAdmin() || $u?->canManage('finance') || $u?->canManage('events'));
    }

    public function mount(): void
    {
        $this->build();
    }

    /** Re-assemble on poll / Reverb signal / range change. */
    public function build(): void
    {
        [$since, $prevFrom, $prevTo] = $this->window();

        $this->hero = $this->buildHero($since, $prevFrom, $prevTo);
        $this->series = TakingsSeries::build((string) $this->range, $this->paid());
        $this->split = $this->buildSplit($since);
        $this->events = $this->buildEvents($since);
        $this->venues = $this->buildVenues($since);
        $this->members = $this->buildMembers($since);
        $this->radar = $this->buildRadar();
        $this->feed = $this->buildFeed();
        $this->cities = $this->buildCities($since);
        $this->ledger = $this->buildLedger();
        $this->systems = $this->buildSystems();
        $this->audit = $this->buildAudit();

        $this->filterSearch();
    }

    public function setRange(string $range): void
    {
        $this->range = in_array($range, ['today', '7d', '30d', '90d', 'all'], true) ? $range : '30d';
        $this->build();
    }

    public function updatedSearchQuery(): void
    {
        $this->filterSearch();
    }

    /** Reverb push: a content.updated broadcast (via the panel realtime bridge) rebuilds live. */
    #[On('haraan-content-updated')]
    public function onContentUpdated(): void
    {
        $this->build();
    }

    // ---------------------------------------------------------------------
    // Window
    // ---------------------------------------------------------------------

    /**
     * The selected window and the equal-length window before it, both in the app zone so
     * they compare correctly against created_at. "Today" is the business day (IST), not UTC.
     *
     * @return array{0:?Carbon,1:?Carbon,2:?Carbon}
     */
    private function window(): array
    {
        $appZone = config('app.timezone');
        $now = now();

        $since = match ($this->range) {
            'today' => BusinessClock::now()->startOfDay()->setTimezone($appZone),
            '7d' => $now->copy()->subDays(7),
            '90d' => $now->copy()->subDays(90),
            'all' => null,
            default => $now->copy()->subDays(30),
        };

        if (! $since) {
            return [null, null, null];
        }

        // Today compares with yesterday up to this same clock time; the others with the
        // equal-length window that ended where this one starts.
        if ($this->range === 'today') {
            return [$since, $since->copy()->subDay(), $now->copy()->subDay()];
        }

        return [$since, $since->copy()->subSeconds((int) $since->diffInSeconds($now)), $since];
    }

    private function paid()
    {
        return Booking::query()->whereIn(DB::raw('lower(status)'), self::PAID);
    }

    private function inWindow($query, ?Carbon $since)
    {
        return $since ? $query->where('created_at', '>=', $since) : $query;
    }

    // ---------------------------------------------------------------------
    // Money
    // ---------------------------------------------------------------------

    private function buildHero(?Carbon $since, ?Carbon $prevFrom, ?Carbon $prevTo): array
    {
        $gmv = (float) $this->inWindow($this->paid(), $since)->sum('total_amount');
        $orders = (int) $this->inWindow($this->paid(), $since)->count();
        $refunds = (float) $this->inWindow(Booking::query()->whereRaw('lower(status) = ?', ['refunded']), $since)->sum('total_amount');

        $prev = ($prevFrom && $prevTo)
            ? (float) $this->paid()->whereBetween('created_at', [$prevFrom, $prevTo])->sum('total_amount')
            : 0.0;

        if (! $since) {
            $delta = null;
        } elseif ($prev <= 0) {
            $delta = $gmv > 0 ? ['label' => 'First sales in this window', 'dir' => 'flat'] : null;
        } else {
            $pct = (int) round(($gmv - $prev) / $prev * 100);
            $delta = [
                'label' => ($pct > 0 ? '+' : '').$pct.'% vs the '.$this->previousLabel().' ('.$this->inr($prev).')',
                'dir' => $pct > 0 ? 'up' : ($pct < 0 ? 'down' : 'flat'),
            ];
        }

        $todaySince = BusinessClock::now()->startOfDay()->setTimezone(config('app.timezone'));

        return [
            'gmv' => $this->inr($gmv),
            'gmvRaw' => $gmv,
            'orders' => $orders,
            'avg' => $orders > 0 ? $this->inr($gmv / $orders) : '—',
            'refunds' => $this->inr($refunds),
            'refundsRaw' => $refunds,
            'delta' => $delta,
            'today' => $this->inr((float) $this->paid()->where('created_at', '>=', $todaySince)->sum('total_amount')),
            'todayOrders' => (int) $this->paid()->where('created_at', '>=', $todaySince)->count(),
            'online' => Schema::hasColumn('users', 'last_seen_at')
                ? (int) User::query()->where('last_seen_at', '>=', now()->subMinutes(5))->count()
                : null,
            'rangeLabel' => $this->rangeLabel(),
        ];
    }

    /**
     * Where the window's paid takings went: Haraan's fees, tax held, the partners' share,
     * plus refunds shown beside (they left the platform, so they are not part of the bar).
     */
    private function buildSplit(?Carbon $since): array
    {
        $gmv = (float) ($this->hero['gmvRaw'] ?? 0);
        if ($gmv <= 0) {
            return [];
        }

        $fees = $this->platformRevenue($since);
        $tax = (float) $this->inWindow($this->paid(), $since)->sum('tax_amount');
        $partners = max(0.0, $gmv - $fees - $tax);

        $parts = [
            ['key' => 'partners', 'label' => 'Partners', 'note' => 'hosts and venues', 'value' => $partners],
            ['key' => 'fees', 'label' => 'Haraan fees', 'note' => 'platform, gateway, commission', 'value' => $fees],
            ['key' => 'tax', 'label' => 'Tax held', 'note' => 'owed to the tax authority', 'value' => $tax],
        ];

        return array_values(array_map(fn (array $p): array => $p + [
            'fmt' => $this->inr($p['value']),
            'pct' => round($p['value'] / $gmv * 100, 1),
        ], array_filter($parts, fn (array $p): bool => $p['value'] > 0)));
    }

    /**
     * What Haraan itself earned on paid bookings in the window: customer-paid platform and
     * gateway fees, the event fee lines (hosts are settled on the ticket subtotal only), and
     * `host_deduction` (host-paid fees + Pulse commission). All set in /control → Platform rules.
     */
    private function platformRevenue(?Carbon $since): float
    {
        return (float) $this->inWindow($this->paid(), $since)->sum(DB::raw(
            "platform_fee + gateway_fee + host_deduction + CASE WHEN booking_type = 'venue' THEN 0 ELSE convenience_fee END"
        ));
    }

    /** Money still owed to partners and what moved today, all time. */
    private function buildLedger(): array
    {
        $allPaid = $this->paid()->where(fn ($q) => $q->whereNotNull('event_id')->orWhereNotNull('venue_id'));
        $earned = (float) (clone $allPaid)->sum('total_amount') - (float) (clone $allPaid)->sum('host_deduction');
        $settled = (float) Payout::query()->whereIn(DB::raw('lower(status)'), PartnerSettlement::SETTLED_PAYOUT)->sum('amount')
            + (float) PayoutBatch::query()->whereIn(DB::raw('lower(status)'), PayoutBatch::PAID)->sum('amount');
        $inFlight = (float) PayoutBatch::query()->whereIn(DB::raw('lower(status)'), ['processing', 'pending'])->sum('amount');
        $paidToday = (float) PayoutBatch::query()
            ->whereIn(DB::raw('lower(status)'), PayoutBatch::PAID)
            ->where('updated_at', '>=', BusinessClock::now()->startOfDay()->setTimezone(config('app.timezone')))
            ->sum('amount');

        return [
            'owed' => $this->inr(max(0.0, $earned - $settled - $inFlight)),
            'owedRaw' => max(0.0, $earned - $settled - $inFlight),
            'inFlight' => $this->inr($inFlight),
            'paidToday' => $this->inr($paidToday),
            'url' => $this->resourceUrl(PayoutResource::class, 'control/finance/payouts'),
        ];
    }

    // ---------------------------------------------------------------------
    // Lines of business
    // ---------------------------------------------------------------------

    private function buildEvents(?Carbon $since): array
    {
        $q = fn () => $this->inWindow($this->paid()->where(fn ($w) => $w->where('booking_type', 'event')->orWhereNotNull('event_id')), $since);

        $upcoming = $this->upcomingEvents();

        $next = (clone $upcoming)->orderBy('date')->orderBy('time')->first(['id', 'title', 'date', 'time', 'total_slots', 'available_slots']);
        $nextSold = null;
        if ($next && (int) $next->total_slots > 0) {
            $nextSold = max(0, (int) $next->total_slots - (int) $next->available_slots);
        }

        return [
            'gmv' => $this->inr((float) $q()->sum('total_amount')),
            'tickets' => (int) $q()->sum('quantity'),
            'orders' => (int) $q()->count(),
            'upcoming' => (clone $upcoming)->count(),
            'next' => $next ? [
                'title' => $next->title,
                'when' => $next->date ? Carbon::parse($next->date)->format('D j M') : '',
                'sold' => $nextSold,
                'total' => (int) $next->total_slots,
                'url' => $this->resourceUrl(EventResource::class, 'control/events/events', 'edit', $next),
            ] : null,
            'url' => $this->resourceUrl(EventResource::class, 'control/events/events'),
        ];
    }

    /** Upcoming events a customer can see: not drafts, not cancelled. */
    private function upcomingEvents()
    {
        return Event::query()
            ->whereDate('date', '>=', BusinessClock::todayDate())
            ->where(fn ($w) => $w->whereNull('status')->orWhereNotIn(DB::raw('lower(status)'), ['draft', 'cancelled', 'canceled']));
    }

    /**
     * Venue takings plus today's court-hours by clock hour across every venue. The strip on the
     * page is drawn from `hours`: each cell is how many courts are booked in that hour.
     */
    private function buildVenues(?Carbon $since): array
    {
        $q = fn () => $this->inWindow($this->paid()->where(fn ($w) => $w->where('booking_type', 'venue')->orWhereNotNull('venue_id')), $since);

        $courts = Schema::hasTable('venue_courts')
            ? (int) DB::table('venue_courts')->where('is_active', true)->count()
            : 0;

        $hours = array_fill_keys(range(self::COURT_DAY_START, self::COURT_DAY_END - 1), 0);
        $rows = Booking::query()
            ->whereNotNull('venue_id')
            ->whereDate('slot_date', BusinessClock::today())
            ->whereNotIn(DB::raw('lower(status)'), ['cancelled', 'canceled', 'refunded', 'failed', 'expired'])
            ->get(['start_time', 'end_time']);

        $bookedHours = 0;
        foreach ($rows as $row) {
            $start = $this->hourOf($row->start_time);
            $end = $this->hourOf($row->end_time, true);
            if ($start === null) {
                continue;
            }
            $end = $end !== null && $end > $start ? $end : $start + 1;
            for ($h = $start; $h < $end; $h++) {
                if (array_key_exists($h, $hours)) {
                    $hours[$h]++;
                    $bookedHours++;
                }
            }
        }

        $nowHour = BusinessClock::now()->hour;

        return [
            'gmv' => $this->inr((float) $q()->sum('total_amount')),
            'bookings' => (int) $q()->count(),
            'venues' => (int) Venue::query()->where('is_active', true)->count(),
            'courts' => $courts,
            'hours' => $hours,
            'bookedHours' => $bookedHours,
            'openHours' => max(0, $courts * count($hours) - $bookedHours),
            'nowHour' => $nowHour,
            'url' => $this->resourceUrl(VenueResource::class, 'control/game-hub/venues'),
        ];
    }

    private function hourOf(?string $time, bool $roundUp = false): ?int
    {
        if (! $time || ! preg_match('/^(\d{1,2}):(\d{2})/', $time, $m)) {
            return null;
        }
        $h = (int) $m[1];

        return $roundUp && (int) $m[2] > 0 ? $h + 1 : $h;
    }

    /** Active paid memberships by plan, and what members paid in the window. */
    private function buildMembers(?Carbon $since): array
    {
        if (! Schema::hasTable('member_subscriptions') || ! Schema::hasTable('member_plans')) {
            return ['plans' => [], 'active' => 0, 'revenue' => $this->inr(0), 'url' => null];
        }

        $plans = DB::table('member_plans')
            ->leftJoin('member_subscriptions', function ($j) {
                $j->on('member_subscriptions.plan_id', '=', 'member_plans.id')
                    ->where('member_subscriptions.status', 'active');
            })
            ->where('member_plans.is_active', true)
            ->groupBy('member_plans.id', 'member_plans.name', 'member_plans.rank', 'member_plans.is_default')
            ->orderBy('member_plans.rank')
            ->get([
                'member_plans.name',
                'member_plans.rank',
                'member_plans.is_default',
                DB::raw('count(member_subscriptions.id) as active'),
            ])
            ->reject(fn ($p) => (bool) $p->is_default)
            ->map(fn ($p) => ['name' => $p->name, 'active' => (int) $p->active])
            ->values()
            ->all();

        $revenue = 0.0;
        if (Schema::hasTable('member_payments')) {
            $pay = DB::table('member_payments')->where('status', 'captured');
            if ($since) {
                $pay->where(fn ($w) => $w->where('paid_at', '>=', $since)->orWhere(fn ($x) => $x->whereNull('paid_at')->where('created_at', '>=', $since)));
            }
            $revenue = (int) $pay->sum('amount_paise') / 100;
        }

        return [
            'plans' => array_slice($plans, 0, 3),
            'active' => array_sum(array_column($plans, 'active')),
            'revenue' => $this->inr($revenue),
            'url' => $this->resourceUrl(MemberSubscriptionResource::class, 'control/finance/member-subscriptions'),
        ];
    }

    /** GMV by city: an event's city, else the venue's city. Top six. */
    private function buildCities(?Carbon $since): array
    {
        $q = DB::table('bookings')
            ->leftJoin('events', 'events.id', '=', 'bookings.event_id')
            ->leftJoin('venues', 'venues.id', '=', 'bookings.venue_id')
            ->whereIn(DB::raw('lower(bookings.status)'), self::PAID)
            ->when($since, fn ($w) => $w->where('bookings.created_at', '>=', $since))
            ->selectRaw("coalesce(nullif(trim(events.city), ''), nullif(trim(venues.city), ''), 'Unknown') as city_name, sum(bookings.total_amount) as gmv, count(*) as orders")
            ->groupBy('city_name')
            ->orderByDesc('gmv')
            ->limit(6);

        $rows = $q->get();
        $top = (float) ($rows->max('gmv') ?: 0);

        return $rows->map(fn ($r) => [
            'city' => ucwords(strtolower((string) $r->city_name)),
            'gmv' => $this->inr((float) $r->gmv),
            'orders' => (int) $r->orders,
            'share' => $top > 0 ? round((float) $r->gmv / $top * 100) : 0,
        ])->all();
    }

    // ---------------------------------------------------------------------
    // Needs a person
    // ---------------------------------------------------------------------

    /** @return array<int,array<string,mixed>> */
    private function buildRadar(): array
    {
        $items = [];

        $nearSoldOut = $this->upcomingEvents()
            ->whereNotNull('total_slots')->where('total_slots', '>', 0)
            ->whereColumn('available_slots', '<=', DB::raw('total_slots * 0.15'))
            ->where('available_slots', '>', 0)
            ->count();
        $items[] = $this->r('Almost sold out', $nearSoldOut, 'Upcoming events with 15% or fewer seats left', 'heroicon-o-fire', $this->resourceUrl(EventResource::class, 'control/events/events'));

        $paidEventIds = $this->paid()->whereNotNull('event_id')->distinct()->pluck('event_id');
        $zeroSales = $this->upcomingEvents()->whereNotIn('id', $paidEventIds)->count();
        $items[] = $this->r('No sales yet', $zeroSales, 'Upcoming events nobody has booked', 'heroicon-o-megaphone', $this->resourceUrl(EventResource::class, 'control/events/events'));

        $pendingPayouts = (int) Payout::whereRaw('lower(status) = ?', ['pending'])->count();
        $items[] = $this->r('Payouts to send', $pendingPayouts, 'Partners waiting on a settlement', 'heroicon-o-banknotes', $this->resourceUrl(PayoutResource::class, 'control/finance/payouts'));

        $openSupport = (int) SupportThread::query()->where('status', '!=', 'closed')->count();
        $items[] = $this->r('Support waiting', $openSupport, 'Conversations that need a reply', 'heroicon-o-chat-bubble-left-right', $this->resourceUrl(SupportThreadResource::class, 'control/support-threads'));

        $failed = (int) Booking::query()->whereRaw('lower(status) = ?', ['failed'])
            ->where('created_at', '>=', now()->subDays(7))->count();
        $items[] = $this->r('Failed payments', $failed, 'Declined in the last 7 days', 'heroicon-o-exclamation-triangle', $this->resourceUrl(BookingResource::class, 'control/events/bookings'));

        return $items;
    }

    /** @return array<string,mixed> */
    private function r(string $title, int $count, string $sub, string $icon, string $url): array
    {
        return compact('title', 'count', 'sub', 'icon', 'url');
    }

    /** The eight most recent bookings, any status. */
    private function buildFeed(): array
    {
        $zone = BusinessClock::zone();

        return Booking::query()
            ->with(['user:id,name', 'event:id,title', 'venue:id,name', 'venueCourt:id,name'])
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(function (Booking $b) use ($zone): array {
                $isVenue = $b->venue_id !== null || $b->booking_type === 'venue';
                $what = $isVenue
                    ? trim(($b->venue?->name ?? 'Venue').($b->venueCourt?->name ? ' · '.$b->venueCourt->name : ''))
                    : ($b->event?->title ?? 'Event');
                $detail = $isVenue
                    ? trim(($b->slot_date ? Carbon::parse($b->slot_date)->format('D j M') : '').($b->start_time ? ', '.$this->clock($b->start_time) : ''), ', ')
                    : ((int) $b->quantity).' '.((int) $b->quantity === 1 ? 'ticket' : 'tickets');

                return [
                    'who' => $b->user?->name ?: ($b->guest_name ?: ($b->attendee_name ?: 'Walk-in')),
                    'what' => $what,
                    'detail' => $detail,
                    'kind' => $isVenue ? 'venue' : 'event',
                    'amount' => $this->inr((float) $b->total_amount),
                    'status' => $this->statusTone((string) $b->status),
                    'statusLabel' => ucwords(str_replace('_', ' ', strtolower((string) $b->status))),
                    'ago' => $b->created_at?->diffForHumans(short: true) ?? '',
                    'at' => $b->created_at?->copy()->setTimezone($zone)->format('j M, g:i A') ?? '',
                    'url' => $this->resourceUrl(BookingResource::class, 'control/events/bookings', 'edit', $b),
                ];
            })
            ->all();
    }

    private function clock(string $time): string
    {
        try {
            return Carbon::createFromFormat('H:i', substr($time, 0, 5))->format('g:i A');
        } catch (\Throwable) {
            return $time;
        }
    }

    private function statusTone(string $status): string
    {
        $s = strtolower($status);

        return match (true) {
            in_array($s, self::PAID, true) => 'ok',
            in_array($s, ['pending', 'reserved', 'hold', 'on_hold'], true) => 'warn',
            in_array($s, ['failed', 'refunded'], true) => 'down',
            default => 'idle',
        };
    }

    // ---------------------------------------------------------------------
    // Systems & audit
    // ---------------------------------------------------------------------

    /**
     * Checks this request can actually make. Nothing here is a vendor SLA or a made-up
     * latency: database and cache are timed now, the queue is counted, keys are checked
     * for presence only.
     */
    private function buildSystems(): array
    {
        $out = [];

        $t = microtime(true);
        try {
            DB::select('select 1');
            $out[] = ['name' => 'Database', 'ok' => true, 'detail' => $this->ms($t)];
        } catch (\Throwable) {
            $out[] = ['name' => 'Database', 'ok' => false, 'detail' => 'Not answering'];
        }

        $t = microtime(true);
        try {
            Cache::put('cc:ping', 1, 10);
            $ok = Cache::get('cc:ping') === 1;
            $out[] = ['name' => 'Cache', 'ok' => $ok, 'detail' => $ok ? $this->ms($t) : 'Read-back failed'];
        } catch (\Throwable) {
            $out[] = ['name' => 'Cache', 'ok' => false, 'detail' => 'Not answering'];
        }

        if (Schema::hasTable('jobs')) {
            $waiting = (int) DB::table('jobs')->count();
            $failed = Schema::hasTable('failed_jobs')
                ? (int) DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count()
                : 0;
            $out[] = [
                'name' => 'Queue',
                'ok' => $failed === 0 && $waiting < 100,
                'detail' => $waiting.' waiting'.($failed > 0 ? ', '.$failed.' failed today' : ''),
            ];
        }

        $out[] = [
            'name' => 'Razorpay keys',
            'ok' => filled(config('services.razorpay.key')) && filled(config('services.razorpay.secret')),
            'detail' => filled(config('services.razorpay.key')) ? (str_starts_with((string) config('services.razorpay.key'), 'rzp_live') ? 'Live' : 'Test mode') : 'Missing',
        ];

        $broadcast = (string) config('broadcasting.default');
        $out[] = [
            'name' => 'Live updates',
            'ok' => $broadcast === 'reverb' || $broadcast === 'pusher',
            'detail' => $broadcast === 'reverb' || $broadcast === 'pusher' ? ucfirst($broadcast) : 'Off (page refreshes every 30s)',
        ];

        return $out;
    }

    private function ms(float $startedAt): string
    {
        $ms = (microtime(true) - $startedAt) * 1000;

        return $ms < 1 ? 'under 1 ms' : round($ms, 1).' ms';
    }

    private function buildAudit(): array
    {
        $zone = BusinessClock::zone();

        return AdminAction::query()
            ->with('user:id,name')
            ->latest('id')
            ->limit(6)
            ->get()
            ->map(fn (AdminAction $a): array => [
                'time' => $a->created_at?->copy()->setTimezone($zone)->format('g:i A') ?? '',
                'day' => $a->created_at?->copy()->setTimezone($zone)->isSameDay(BusinessClock::now()) ? 'Today' : ($a->created_at?->copy()->setTimezone($zone)->format('j M') ?? ''),
                'what' => ucfirst(str_replace(['_', '.'], [' ', ' '], (string) $a->action)),
                'subject' => $a->subject_type ? class_basename((string) $a->subject_type).' #'.$a->subject_id : null,
                'who' => $a->user?->name ?? 'System',
            ])
            ->all();
    }

    // ---------------------------------------------------------------------
    // Search (⌘K)
    // ---------------------------------------------------------------------

    private function filterSearch(): void
    {
        $q = trim($this->searchQuery);
        if (mb_strlen($q) < 2) {
            $this->searchResults = [];

            return;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';
        $out = [];

        foreach (Event::query()->where('title', 'like', $like)->latest('date')->limit(4)->get(['id', 'title', 'date', 'city']) as $e) {
            $out[] = [
                'group' => 'Events',
                'title' => $e->title,
                'meta' => trim(($e->date ? Carbon::parse($e->date)->format('j M Y') : '').($e->city ? ' · '.$e->city : ''), ' ·'),
                'url' => $this->resourceUrl(EventResource::class, 'control/events/events', 'edit', $e),
            ];
        }

        foreach (Venue::query()->where('name', 'like', $like)->limit(4)->get(['id', 'name', 'city', 'location']) as $v) {
            $out[] = [
                'group' => 'Venues',
                'title' => $v->name,
                'meta' => $v->city ?: (string) $v->location,
                'url' => $this->resourceUrl(VenueResource::class, 'control/game-hub/venues', 'view', $v),
            ];
        }

        $users = User::query()
            ->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like))
            ->limit(4)
            ->get(['id', 'name', 'email', 'phone', 'role']);
        foreach ($users as $u) {
            $out[] = [
                'group' => 'People',
                'title' => $u->name ?: ($u->email ?: 'User #'.$u->id),
                'meta' => trim(ucfirst(strtolower((string) $u->role)).' · '.($u->phone ?: $u->email), ' ·'),
                'url' => $this->resourceUrl(UserResource::class, 'control/people/users', 'view', $u),
            ];
        }

        $bookings = Booking::query()
            ->with(['event:id,title', 'venue:id,name'])
            ->where(function ($w) use ($q, $like) {
                $w->where('ticket_code', 'like', $like)->orWhere('razorpay_payment_id', 'like', $like);
                if (ctype_digit(ltrim($q, '#'))) {
                    $w->orWhere('id', (int) ltrim($q, '#'));
                }
            })
            ->latest('id')
            ->limit(4)
            ->get();
        foreach ($bookings as $b) {
            $out[] = [
                'group' => 'Bookings',
                'title' => 'Booking #'.$b->id.' — '.($b->event?->title ?? $b->venue?->name ?? 'Booking'),
                'meta' => $this->inr((float) $b->total_amount).' · '.ucwords(strtolower((string) $b->status)),
                'url' => $this->resourceUrl(BookingResource::class, 'control/events/bookings', 'edit', $b),
            ];
        }

        $this->searchResults = $out;
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    /** A resource page URL, falling back to a plain path if the resource or page is missing. */
    private function resourceUrl(string $resource, string $fallback, string $page = 'index', $record = null): string
    {
        try {
            if (class_exists($resource) && $resource::hasPage($page)) {
                return $resource::getUrl($page, $record ? ['record' => $record] : []);
            }
            if (class_exists($resource) && $resource::hasPage('index')) {
                return $resource::getUrl('index');
            }
        } catch (\Throwable) {
            // fall through
        }

        return url($fallback);
    }

    private function inr(float $amount): string
    {
        return Rupees::format($amount);
    }

    private function rangeLabel(): string
    {
        return match ($this->range) {
            'today' => 'Today so far',
            '7d' => 'Last 7 days',
            '90d' => 'Last 90 days',
            'all' => 'All time',
            default => 'Last 30 days',
        };
    }

    private function previousLabel(): string
    {
        return match ($this->range) {
            'today' => 'same time yesterday',
            '7d' => 'previous 7 days',
            '90d' => 'previous 90 days',
            default => 'previous 30 days',
        };
    }
}
