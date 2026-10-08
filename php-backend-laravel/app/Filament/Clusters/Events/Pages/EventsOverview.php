<?php

declare(strict_types=1);

namespace App\Filament\Clusters\Events\Pages;

use App\Filament\Clusters\Events\EventsCluster;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Events\EventResource;
use App\Models\Booking;
use App\Models\Event;
use App\Support\BusinessClock;
use App\Support\Rupees;
use App\Support\TakingsSeries;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

/**
 * Events at a glance: ticket takings, what is on sale and how full it is, where tickets
 * sell, and the latest orders. Every figure is read from bookings and events at render
 * time; an empty platform shows empty states, never sample numbers.
 */
class EventsOverview extends Page
{
    protected static ?string $cluster = EventsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $title = 'Events Overview';

    protected static ?string $navigationLabel = 'Overview';

    protected static ?int $navigationSort = -10;

    protected string $view = 'filament.clusters.events.events-overview';

    private const PAID = ['confirmed', 'paid', 'completed', 'checked_in'];

    public string $range = '30d';

    /** @var array<string,mixed> */
    public array $hero = [];

    /** @var array<string,mixed> */
    public array $series = [];

    /** @var array<int,array<string,mixed>> */
    public array $onSale = [];

    /** @var array<int,array<string,mixed>> */
    public array $categories = [];

    /** @var array<int,array<string,mixed>> */
    public array $cities = [];

    /** @var array<int,array<string,mixed>> */
    public array $places = [];

    /** @var array<int,array<string,mixed>> */
    public array $orders = [];

    /** @var array<int,array<string,mixed>> */
    public array $watch = [];

    public function mount(): void
    {
        $this->build();
    }

    public function setRange(string $range): void
    {
        $this->range = in_array($range, ['today', '7d', '30d', '90d', 'all'], true) ? $range : '30d';
        $this->build();
    }

    #[On('haraan-content-updated')]
    public function build(): void
    {
        $since = $this->since();

        $this->hero = $this->buildHero($since);
        $this->series = TakingsSeries::build($this->range, $this->paid());
        $this->onSale = $this->buildOnSale();
        $this->categories = $this->groupBy("coalesce(nullif(trim(events.category), ''), 'Uncategorised')", $since, 5);
        $this->cities = $this->groupBy("coalesce(nullif(trim(events.city), ''), 'Unknown')", $since, 5);
        $this->places = $this->groupBy("coalesce(nullif(trim(events.venue), ''), nullif(trim(events.location), ''), 'Not set')", $since, 5);
        $this->orders = $this->buildOrders();
        $this->watch = $this->buildWatch();
    }

    private function since(): ?Carbon
    {
        return match ($this->range) {
            'today' => BusinessClock::now()->startOfDay()->setTimezone(config('app.timezone')),
            '7d' => now()->subDays(7),
            '90d' => now()->subDays(90),
            'all' => null,
            default => now()->subDays(30),
        };
    }

    /** Paid event bookings. */
    private function paid()
    {
        return Booking::query()
            ->whereIn(DB::raw('lower(bookings.status)'), self::PAID)
            ->whereNotNull('bookings.event_id');
    }

    private function windowed(?Carbon $since)
    {
        return $this->paid()->when($since, fn ($q) => $q->where('bookings.created_at', '>=', $since));
    }

    private function buildHero(?Carbon $since): array
    {
        $revenue = (float) $this->windowed($since)->sum('total_amount');
        $orders = (int) $this->windowed($since)->count();
        $tickets = (int) $this->windowed($since)->sum('quantity');

        // Show-up rate only over events that have already happened, or it reads as a no-show.
        $held = $this->windowed($since)
            ->join('events', 'events.id', '=', 'bookings.event_id')
            ->whereDate('events.date', '<', BusinessClock::todayDate());
        $heldTickets = (int) (clone $held)->sum('bookings.quantity');
        $heldIn = (int) (clone $held)->sum('bookings.checked_in_count');

        return [
            'revenue' => Rupees::format($revenue),
            'orders' => $orders,
            'tickets' => $tickets,
            'avgTicket' => $tickets > 0 ? Rupees::format($revenue / $tickets) : '—',
            'showUp' => $heldTickets > 0 ? (int) round(min($heldIn, $heldTickets) / $heldTickets * 100).'%' : '—',
            'onSaleCount' => $this->upcoming()->count(),
            'showUpNote' => $heldTickets > 0 ? number_format($heldIn).' of '.number_format($heldTickets).' tickets scanned' : 'No finished events in this window',
            'rangeLabel' => match ($this->range) {
                'today' => 'Today so far',
                '7d' => 'Last 7 days',
                '90d' => 'Last 90 days',
                'all' => 'All time',
                default => 'Last 30 days',
            },
        ];
    }

    /** Upcoming, customer-visible events, soonest first, with how full each is. */
    private function buildOnSale(): array
    {
        return $this->upcoming()
            ->orderBy('date')->orderBy('time')
            ->limit(6)
            ->get(['id', 'title', 'date', 'time', 'city', 'venue', 'total_slots', 'available_slots', 'is_sold_out'])
            ->map(function (Event $e): array {
                $cap = max(0, (int) $e->total_slots);
                $sold = $cap > 0 ? max(0, $cap - max(0, (int) $e->available_slots)) : 0;
                $date = $e->date ? Carbon::parse($e->date) : null;

                return [
                    'title' => $e->title,
                    'when' => $date ? $date->format('D j M') : 'Date not set',
                    'days' => $date ? (int) BusinessClock::todayDate()->diffInDays($date, false) : null,
                    'where' => $e->venue ?: $e->city,
                    'sold' => $sold,
                    'cap' => $cap,
                    'pct' => $cap > 0 ? (int) round($sold / $cap * 100) : null,
                    'soldOut' => (bool) $e->is_sold_out || ($cap > 0 && $sold >= $cap),
                    'url' => EventResource::getUrl('edit', ['record' => $e]),
                ];
            })
            ->all();
    }

    private function upcoming()
    {
        return Event::query()
            ->whereDate('date', '>=', BusinessClock::todayDate())
            ->whereRaw("lower(coalesce(status, '')) not in ('draft', 'cancelled', 'canceled')");
    }

    /** Paid ticket takings grouped by an events expression, biggest first. */
    private function groupBy(string $expr, ?Carbon $since, int $limit): array
    {
        $rows = $this->windowed($since)
            ->join('events', 'events.id', '=', 'bookings.event_id')
            ->selectRaw("{$expr} as k, sum(bookings.total_amount) as v, sum(bookings.quantity) as t")
            ->groupBy('k')
            ->orderByDesc('v')
            ->limit($limit)
            ->toBase()
            ->get();

        $total = (float) $this->windowed($since)->sum('total_amount');
        $top = (float) ($rows->max('v') ?: 0);

        return $rows->map(fn ($r): array => [
            'label' => (string) $r->k,
            'value' => Rupees::format((float) $r->v),
            'tickets' => (int) $r->t,
            'share' => $total > 0 ? round((float) $r->v / $total * 100, 1) : 0,
            'bar' => $top > 0 ? (int) round((float) $r->v / $top * 100) : 0,
        ])->all();
    }

    private function buildOrders(): array
    {
        return Booking::query()
            ->whereNotNull('event_id')
            ->with(['user:id,name', 'event:id,title'])
            ->latest('id')
            ->limit(7)
            ->get()
            ->map(function (Booking $b): array {
                $s = strtolower((string) $b->status);

                return [
                    'who' => $b->user?->name ?: ($b->attendee_name ?: ($b->guest_name ?: 'Guest')),
                    'event' => $b->event?->title ?? 'Event #'.$b->event_id,
                    'qty' => (int) $b->quantity,
                    'amount' => Rupees::format((float) $b->total_amount),
                    'tone' => in_array($s, self::PAID, true) ? 'ok' : (in_array($s, ['pending', 'reserved'], true) ? 'warn' : (in_array($s, ['failed', 'refunded'], true) ? 'down' : 'idle')),
                    'status' => ucwords(str_replace('_', ' ', $s)),
                    'ago' => $b->created_at?->diffForHumans(short: true) ?? '',
                    'url' => BookingResource::getUrl('edit', ['record' => $b]),
                ];
            })
            ->all();
    }

    /** Things an events manager should look at, each a count with a link. */
    private function buildWatch(): array
    {
        $today = BusinessClock::todayDate();
        $week = $today->copy()->addDays(7);
        $list = EventResource::getUrl('index');

        $soonAndThin = $this->upcoming()
            ->whereDate('date', '<=', $week)
            ->where('total_slots', '>', 0)
            ->whereRaw('(total_slots - available_slots) < total_slots * 0.3')
            ->count();

        $soldOut = $this->upcoming()
            ->where(fn ($q) => $q->where('is_sold_out', true)->orWhere(fn ($w) => $w->where('total_slots', '>', 0)->where('available_slots', '<=', 0)))
            ->count();

        $drafts = Event::query()->whereRaw("lower(coalesce(status, '')) = 'draft'")->whereDate('date', '>=', $today)->count();

        return [
            ['title' => 'Under 30% sold, this week', 'sub' => 'Starts within 7 days', 'count' => $soonAndThin, 'icon' => 'heroicon-o-clock', 'url' => $list],
            ['title' => 'Sold out', 'sub' => 'Upcoming, no seats left', 'count' => $soldOut, 'icon' => 'heroicon-o-check-badge', 'url' => $list, 'good' => true],
            ['title' => 'Drafts with a future date', 'sub' => 'Not visible to customers yet', 'count' => $drafts, 'icon' => 'heroicon-o-pencil-square', 'url' => $list],
        ];
    }
}
