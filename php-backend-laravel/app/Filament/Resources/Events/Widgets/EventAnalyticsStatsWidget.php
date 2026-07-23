<?php

declare(strict_types=1);

namespace App\Filament\Resources\Events\Widgets;

use App\Models\Booking;
use App\Models\Event;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Widgets\StatsOverviewWidget;

/**
 * Per-event host analytics. All figures are computed from real booking data
 * for the single event bound to the page; no placeholder numbers.
 *
 * Booking statuses are stored inconsistently (CONFIRMED / confirmed / PAID /
 * cancelled / REFUNDED …) so every filter matches case-insensitively.
 */
class EventAnalyticsStatsWidget extends StatsOverviewWidget implements HasActions
{
    use InteractsWithActions;

    /** Injected by the page via InteractsWithRecord::getWidgetData(). */
    public ?Event $record = null;

    protected int | string | array $columnSpan = 'full';

    /**
     * Custom view = the stock stats grid PLUS <x-filament-actions::modals/> so the
     * "Repeat Fans" cards can open the repeat-fan drill-down modal on click.
     *
     * @var view-string
     */
    protected string $view = 'filament.resources.events.widgets.event-analytics-stats';

    /** Statuses that represent money actually earned. */
    private const PAID = ['confirmed', 'paid', 'completed'];

    /** Statuses that represent lost / reversed revenue. */
    private const LOST = ['cancelled', 'refunded', 'failed'];

    protected function getColumns(): int
    {
        return 4;
    }

    /** The stock stats grid is replaced by getCards(); satisfy the parent contract. */
    protected function getStats(): array
    {
        return [];
    }

    /**
     * Structured card data for the custom analytics grid. Each number is sorted
     * into one of three visual treatments so the decoration matches the meaning:
     *
     *   - 'meter' — a 0–100% value with a real ceiling (fill bar tells the truth).
     *   - 'split' — two parts that add up to a whole (one stacked bar).
     *   - 'plain' — a count or fact with no natural max (value + status chip only).
     *
     * @return list<array<string, mixed>>
     */
    public function getCards(): array
    {
        $event = $this->record;

        if (! $event) {
            return [];
        }

        $paid = Booking::where('event_id', $event->id)
            ->whereIn(\DB::raw('lower(status)'), self::PAID);

        $revenue   = (float) (clone $paid)->sum('total_amount');
        $attendees = (int) (clone $paid)->sum('quantity');
        $orders    = (int) (clone $paid)->count();
        $checkedIn = (int) (clone $paid)->sum('checked_in_count');
        $discount  = (float) (clone $paid)->sum('discount');
        $coupons   = (int) (clone $paid)->whereNotNull('coupon_code')
            ->where('coupon_code', '!=', '')->count();

        $lost = Booking::where('event_id', $event->id)
            ->whereIn(\DB::raw('lower(status)'), self::LOST);
        $lostCount = (int) (clone $lost)->count();
        $lostValue = (float) (clone $lost)->sum('total_amount');

        $capacity  = max((int) $event->total_slots, 0);
        $available = max((int) $event->available_slots, 0);
        $sold      = max($capacity - $available, 0);
        $fill      = $capacity > 0 ? (int) round($sold / $capacity * 100) : 0;

        $avgPerAttendee = $attendees > 0 ? $revenue / $attendees : 0.0;

        $views      = max((int) $event->views, 0);
        $conversion = $views > 0 ? round($orders / $views * 100, 2) : 0.0;

        $showUp   = $attendees > 0 ? (int) round($checkedIn / $attendees * 100) : 0;
        $noShows  = max($attendees - $checkedIn, 0);

        [$repeatFans, $repeatPct, $repeatIds] = $this->repeatAttendees($event);

        // Split this event's revenue between returning fans and first-timers.
        $repeatRevenue = $repeatIds === [] ? 0.0 : (float) (clone $paid)
            ->whereIn('user_id', $repeatIds)
            ->sum('total_amount');
        $newRevenue = max($revenue - $repeatRevenue, 0.0);
        $repeatRevPct = $revenue > 0 ? (int) round($repeatRevenue / $revenue * 100) : 0;

        $clickRepeat = $this->repeatFanCardAttributes($repeatFans);

        return [
            [
                'type'  => 'plain',
                'label' => 'Event Views',
                'value' => number_format($views),
                'icon'  => 'heroicon-m-eye',
                'color' => 'info',
                'chip'  => 'Detail-page opens',
            ],
            [
                'type'  => 'meter',
                'label' => 'Conversion Rate',
                'value' => $conversion . '%',
                'icon'  => 'heroicon-m-arrow-trending-up',
                'color' => $conversion >= 5 ? 'success' : ($conversion > 0 ? 'warning' : 'gray'),
                'pct'   => min(100, (int) round($conversion)),
                'chip'  => 'Views → paid bookings',
            ],
            [
                'type'  => 'plain',
                'label' => 'Total Revenue',
                'value' => $this->money($revenue),
                'icon'  => 'heroicon-m-banknotes',
                'color' => 'success',
                'chip'  => "$orders paid " . str('order')->plural($orders),
            ],
            [
                'type'  => 'plain',
                'label' => 'Avg per Attendee',
                'value' => $this->money($avgPerAttendee),
                'icon'  => 'heroicon-m-user',
                'color' => 'primary',
                'chip'  => 'Revenue ÷ tickets sold',
            ],
            [
                'type'  => 'plain',
                'label' => 'Attendees',
                'value' => number_format($attendees),
                'icon'  => 'heroicon-m-user-group',
                'color' => 'info',
                'chip'  => 'Tickets across paid orders',
            ],
            [
                'type'  => 'meter',
                'label' => 'Checked In',
                'value' => number_format($checkedIn),
                'icon'  => 'heroicon-m-check-badge',
                'color' => $showUp >= 80 ? 'success' : ($showUp > 0 ? 'warning' : 'gray'),
                'pct'   => $showUp,
                'chip'  => $showUp . '% show-up · ' . number_format($checkedIn) . ' of ' . number_format($attendees),
            ],
            [
                'type'  => 'plain',
                'label' => 'No-shows',
                'value' => number_format($noShows),
                'icon'  => 'heroicon-m-user-minus',
                'color' => $noShows > 0 ? 'danger' : 'gray',
                'chip'  => 'Booked but not arrived',
            ],
            [
                'type'    => 'plain',
                'label'   => 'Repeat Fans',
                'value'   => number_format($repeatFans),
                'icon'    => 'heroicon-m-arrow-path',
                'color'   => $repeatPct >= 30 ? 'success' : ($repeatFans > 0 ? 'info' : 'gray'),
                'chip'    => $repeatPct . '% booked a past event',
                'attributes' => $clickRepeat,
            ],
            [
                'type'     => 'split',
                'label'    => 'Revenue Mix',
                'value'    => $this->money($revenue),
                'icon'     => 'heroicon-m-arrow-path-rounded-square',
                'color'    => 'info',
                'chip'     => 'Returning vs first-time fans',
                'segments' => [
                    [
                        'pct'   => $repeatRevPct,
                        'color' => 'success',
                        'label' => 'Returning ' . $repeatRevPct . '%',
                        'value' => $this->money($repeatRevenue),
                    ],
                    [
                        'pct'   => 100 - $repeatRevPct,
                        'color' => 'info',
                        'label' => 'First-timers ' . (100 - $repeatRevPct) . '%',
                        'value' => $this->money($newRevenue),
                    ],
                ],
                'attributes' => $clickRepeat,
            ],
            [
                'type'  => 'plain',
                'label' => 'Bookings',
                'value' => $sold . ' / ' . $capacity,
                'icon'  => 'heroicon-m-ticket',
                'color' => $fill >= 100 ? 'success' : ($fill >= 50 ? 'primary' : 'gray'),
                'chip'  => $fill . '% filled',
            ],
            [
                'type'  => 'meter',
                'label' => 'Sell-through',
                'value' => $fill . '%',
                'icon'  => 'heroicon-m-chart-pie',
                'color' => $fill >= 80 ? 'danger' : ($fill >= 50 ? 'warning' : 'info'),
                'pct'   => $fill,
                'chip'  => 'Seats sold vs capacity',
            ],
            [
                'type'  => 'plain',
                'label' => 'Discounts Given',
                'value' => $this->money($discount),
                'icon'  => 'heroicon-m-tag',
                'color' => $discount > 0 ? 'warning' : 'gray',
                'chip'  => $coupons . ' coupon ' . str('redemption')->plural($coupons),
            ],
            [
                'type'  => 'plain',
                'label' => 'Refunds / Cancelled',
                'value' => number_format($lostCount),
                'icon'  => 'heroicon-m-arrow-uturn-left',
                'color' => $lostCount > 0 ? 'danger' : 'gray',
                'chip'  => $this->money($lostValue) . ' reversed',
            ],
            [
                'type'  => 'plain',
                'label' => 'Days to Event',
                'value' => $this->daysToEvent($event),
                'icon'  => 'heroicon-m-calendar-days',
                'color' => 'gray',
                'chip'  => $event->date?->format('D, d M Y') ?? 'Date TBD',
            ],
        ];
    }

    /**
     * "Returning fans" for this event: of the unique attendees who paid for this
     * event, how many have also paid for a *different* event by the same host
     * before. Falls back to the owning organization when a host (partner) isn't
     * set. Returns [count, percentageOfThisEventsFanbase, repeatUserIds].
     *
     * @return array{0: int, 1: int, 2: list<int>}
     */
    private function repeatAttendees(Event $event): array
    {
        // The unique people who paid for THIS event.
        $fans = Booking::where('event_id', $event->id)
            ->whereIn(\DB::raw('lower(status)'), self::PAID)
            ->distinct()
            ->pluck('user_id')
            ->filter()
            ->all();

        if ($fans === []) {
            return [0, 0, []];
        }

        // This host's other events (by partner, else by organization).
        $otherEvents = Event::where('id', '!=', $event->id)
            ->when(
                $event->partner_id !== null,
                fn ($q) => $q->where('partner_id', $event->partner_id),
                fn ($q) => $event->organization_id !== null
                    ? $q->where('organization_id', $event->organization_id)
                    : $q->whereRaw('1 = 0'), // no host key → no cross-event history
            )
            ->pluck('id');

        if ($otherEvents->isEmpty()) {
            return [0, 0, []];
        }

        // Of this event's fans, which ones paid for one of the host's past events.
        $repeatIds = Booking::whereIn('event_id', $otherEvents)
            ->whereIn(\DB::raw('lower(status)'), self::PAID)
            ->whereIn('user_id', $fans)
            ->distinct()
            ->pluck('user_id')
            ->filter()
            ->values()
            ->all();

        $repeat = count($repeatIds);
        $pct = count($fans) > 0 ? (int) round($repeat / count($fans) * 100) : 0;

        return [$repeat, $pct, $repeatIds];
    }

    /**
     * Extra attributes for the two "repeat fan" cards: make them look and behave
     * clickable (open the drill-down modal) only when there actually are repeat
     * fans to show. With none, the card is inert.
     *
     * @return array<string, string>
     */
    private function repeatFanCardAttributes(int $repeatFans): array
    {
        if ($repeatFans < 1) {
            return [];
        }

        return [
            'wire:click' => "mountAction('repeatFans')",
            'class'      => 'cursor-pointer',
            'title'      => 'View repeat fans',
        ];
    }

    /**
     * Generic drill-down for every stat card. `mountAction('drill', {metric})`
     * opens a modal that lists the actual booking rows behind the number, so a
     * host can click any box and see the data that produced it.
     */
    public function drillAction(): Action
    {
        return Action::make('drill')
            ->modalHeading(fn (array $arguments): string => self::DRILL_TITLES[$arguments['metric'] ?? ''] ?? 'Details')
            ->modalContent(fn (array $arguments): \Illuminate\Contracts\View\View => view(
                'filament.resources.events.widgets.analytics-drill-modal',
                ['d' => $this->getDrillData($arguments['metric'] ?? '')],
            ))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close');
    }

    /** Modal heading per metric — cheap lookup so the title needs no query. */
    private const DRILL_TITLES = [
        'views'       => 'Event views',
        'conversion'  => 'Converted bookings',
        'revenue'     => 'Paid bookings — revenue',
        'avg'         => 'Spend per order',
        'attendees'   => 'Attendees',
        'checkedin'   => 'Checked-in attendees',
        'noshows'     => 'No-shows',
        'bookings'    => 'All bookings',
        'sellthrough' => 'Capacity',
        'discounts'   => 'Discounted bookings',
        'refunds'     => 'Refunds / cancellations',
        'days'        => 'Event date',
    ];

    /**
     * The dataset behind one card. Money/count metrics return a `rows` table of
     * real bookings; pure-summary metrics (views, capacity, date) return a
     * `summary` list of label/value pairs instead.
     *
     * @return array{subheading: string, summary: list<array{label: string, value: string}>, rows: list<array<string, mixed>>, extraLabel: ?string, empty: string}
     */
    public function getDrillData(string $metric): array
    {
        $event = $this->record;

        $blank = ['subheading' => '', 'summary' => [], 'rows' => [], 'extraLabel' => null, 'empty' => 'Nothing to show yet.', 'masked' => ! $this->canSeeContacts()];

        if (! $event) {
            return $blank;
        }

        $paid = fn () => Booking::where('event_id', $event->id)
            ->whereIn(\DB::raw('lower(status)'), self::PAID);
        $lost = fn () => Booking::where('event_id', $event->id)
            ->whereIn(\DB::raw('lower(status)'), self::LOST);

        switch ($metric) {
            case 'revenue':
            case 'avg':
            case 'conversion':
                return array_merge($blank, [
                    'subheading' => $metric === 'conversion'
                        ? 'Paid bookings — the conversions counted against detail-page views.'
                        : 'Every paid booking for this event, newest first.',
                    'rows'       => $this->rowsFrom($paid()->latest()),
                    'empty'      => 'No paid bookings yet.',
                ]);

            case 'attendees':
                return array_merge($blank, [
                    'subheading' => 'Paid orders and how many tickets each covers.',
                    'extraLabel' => 'Tickets',
                    'rows'       => $this->rowsFrom($paid()->latest(), fn (Booking $b) => (int) $b->quantity . ' ' . str('ticket')->plural((int) $b->quantity)),
                    'empty'      => 'No attendees yet.',
                ]);

            case 'checkedin':
                return array_merge($blank, [
                    'subheading' => 'Bookings with at least one attendee scanned in.',
                    'extraLabel' => 'Arrived',
                    'rows'       => $this->rowsFrom(
                        $paid()->where('checked_in_count', '>', 0)->latest(),
                        fn (Booking $b) => (int) $b->checked_in_count . ' / ' . (int) $b->quantity,
                    ),
                    'empty'      => 'Nobody has checked in yet.',
                ]);

            case 'noshows':
                return array_merge($blank, [
                    'subheading' => 'Paid bookings where not everyone has arrived.',
                    'extraLabel' => 'Missing',
                    'rows'       => $this->rowsFrom(
                        $paid()->where(function ($q) {
                            $q->whereNull('checked_in_count')
                                ->orWhereColumn('checked_in_count', '<', 'quantity');
                        })->latest(),
                        fn (Booking $b) => (max((int) $b->quantity - (int) $b->checked_in_count, 0)) . ' of ' . (int) $b->quantity,
                    ),
                    'empty'      => 'No no-shows — everyone arrived.',
                ]);

            case 'discounts':
                return array_merge($blank, [
                    'subheading' => 'Paid bookings that redeemed a coupon.',
                    'extraLabel' => 'Coupon',
                    'rows'       => $this->rowsFrom(
                        $paid()->where('discount', '>', 0)->latest(),
                        fn (Booking $b) => ($b->coupon_code ?: '—') . ' · −' . $this->money((float) $b->discount),
                    ),
                    'empty'      => 'No coupons redeemed.',
                ]);

            case 'refunds':
                return array_merge($blank, [
                    'subheading' => 'Bookings that were cancelled, refunded, or failed.',
                    'rows'       => $this->rowsFrom($lost()->latest()),
                    'empty'      => 'No refunds or cancellations.',
                ]);

            case 'bookings':
                return array_merge($blank, [
                    'subheading' => 'Every booking for this event, any status.',
                    'rows'       => $this->rowsFrom(
                        Booking::where('event_id', $event->id)->latest(),
                    ),
                    'empty'      => 'No bookings yet.',
                ]);

            case 'views':
                $views = max((int) $event->views, 0);
                $orders = (int) $paid()->count();
                return array_merge($blank, [
                    'subheading' => 'Views are a running counter of detail-page opens; individual visitors are not stored.',
                    'summary'    => [
                        ['label' => 'Detail-page opens', 'value' => number_format($views)],
                        ['label' => 'Paid bookings from them', 'value' => number_format($orders)],
                        ['label' => 'Conversion', 'value' => ($views > 0 ? round($orders / $views * 100, 2) : 0) . '%'],
                    ],
                ]);

            case 'sellthrough':
                $capacity  = max((int) $event->total_slots, 0);
                $available = max((int) $event->available_slots, 0);
                $sold      = max($capacity - $available, 0);
                return array_merge($blank, [
                    'subheading' => 'Seats sold against the event capacity.',
                    'summary'    => [
                        ['label' => 'Capacity', 'value' => number_format($capacity)],
                        ['label' => 'Sold', 'value' => number_format($sold)],
                        ['label' => 'Available', 'value' => number_format($available)],
                        ['label' => 'Sell-through', 'value' => ($capacity > 0 ? (int) round($sold / $capacity * 100) : 0) . '%'],
                    ],
                ]);

            case 'days':
                return array_merge($blank, [
                    'subheading' => 'When this event happens.',
                    'summary'    => [
                        ['label' => 'Date', 'value' => $event->date?->format('l, d M Y') ?? 'Not set'],
                        ['label' => 'Countdown', 'value' => $this->daysToEvent($event) . ($event->date && $event->date->isFuture() ? ' days to go' : '')],
                    ],
                ]);
        }

        return $blank;
    }

    /**
     * Turn a booking query into display rows for the drill-down table. $extra is
     * an optional per-row callback for a metric-specific trailing column.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  callable(Booking): string|null  $extra
     * @return list<array<string, mixed>>
     */
    private function rowsFrom($query, ?callable $extra = null): array
    {
        return $query->with('user:id,name,email,phone')
            ->limit(200)
            ->get()
            ->map(function (Booking $b) use ($extra): array {
                $name = trim((string) ($b->attendee_name ?: $b->guest_name ?: ($b->user->name ?? ''))) ?: 'Guest';
                $contact = trim((string) ($b->attendee_phone ?: $b->guest_phone ?: ($b->user->phone ?? '')));
                $email = trim((string) ($b->attendee_email ?: ($b->user->email ?? '')));

                return [
                    'name'    => $name,
                    'contact' => $this->maskPhone($contact),
                    'email'   => $this->maskEmail($email),
                    'qty'     => (int) $b->quantity,
                    'amount'  => $this->money((float) $b->total_amount),
                    'status'  => ucfirst(strtolower((string) $b->status)),
                    'date'    => $b->created_at?->format('d M, H:i') ?? '—',
                    'extra'   => $extra ? $extra($b) : null,
                ];
            })
            ->all();
    }

    /**
     * The drill-down modal listing each returning fan — name, contact, how many of
     * this host's past events they've attended, and what they spent on this one.
     */
    public function repeatFansAction(): Action
    {
        return Action::make('repeatFans')
            ->modalHeading('Repeat fans')
            ->modalDescription('People who booked a past event of yours and came back for this one.')
            ->modalContent(fn (): \Illuminate\Contracts\View\View => view(
                'filament.resources.events.widgets.repeat-fans-modal',
                ['fans' => $this->getRepeatFans()],
            ))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close');
    }

    /**
     * The returning fans for this event, richest first. For each: display name,
     * contact, count of the host's PAST events they paid for, and their spend +
     * ticket count on THIS event.
     *
     * @return list<array{name: string, email: string, phone: string, past: int, spent: float, tickets: int}>
     */
    public function getRepeatFans(): array
    {
        $event = $this->record;

        if (! $event) {
            return [];
        }

        [, , $repeatIds] = $this->repeatAttendees($event);

        if ($repeatIds === []) {
            return [];
        }

        // The host's other events (same host key as repeatAttendees()).
        $otherEvents = Event::where('id', '!=', $event->id)
            ->when(
                $event->partner_id !== null,
                fn ($q) => $q->where('partner_id', $event->partner_id),
                fn ($q) => $event->organization_id !== null
                    ? $q->where('organization_id', $event->organization_id)
                    : $q->whereRaw('1 = 0'),
            )
            ->pluck('id');

        // How many of the host's past events each fan paid for.
        $pastCounts = Booking::whereIn('event_id', $otherEvents)
            ->whereIn(\DB::raw('lower(status)'), self::PAID)
            ->whereIn('user_id', $repeatIds)
            ->select('user_id', \DB::raw('COUNT(DISTINCT event_id) as c'))
            ->groupBy('user_id')
            ->pluck('c', 'user_id');

        // Spend + tickets on THIS event, per fan.
        $here = Booking::where('event_id', $event->id)
            ->whereIn(\DB::raw('lower(status)'), self::PAID)
            ->whereIn('user_id', $repeatIds)
            ->select('user_id', \DB::raw('SUM(total_amount) as spent'), \DB::raw('SUM(quantity) as tickets'))
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $users = User::whereIn('id', $repeatIds)
            ->get(['id', 'name', 'email', 'phone'])
            ->keyBy('id');

        return collect($repeatIds)
            ->map(function ($uid) use ($users, $pastCounts, $here): array {
                $u = $users->get($uid);
                $row = $here->get($uid);

                return [
                    'name'    => trim((string) ($u->name ?? '')) ?: 'Guest',
                    'email'   => $this->maskEmail((string) ($u->email ?? '')),
                    'phone'   => $this->maskPhone((string) ($u->phone ?? '')),
                    'past'    => (int) ($pastCounts[$uid] ?? 0),
                    'spent'   => (float) ($row->spent ?? 0),
                    'tickets' => (int) ($row->tickets ?? 0),
                ];
            })
            ->sortByDesc(fn (array $r) => ($r['past'] * 1_000_000) + $r['spent'])
            ->values()
            ->all();
    }

    /**
     * Who may see attendee contact details (email / phone) in the drill-downs.
     * Super-admins plus the roles that legitimately act on customers — OPS
     * (check-in / operations) and FINANCE (refunds). Everyone else (e.g.
     * MARKETING) gets masked values.
     */
    private function canSeeContacts(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && ($user->isSuperAdmin() || $user->hasRoleEither(['OPS', 'FINANCE']));
    }

    /** Mask an email to `j•••@domain.com` unless the viewer is allowed full contacts. */
    private function maskEmail(string $email): string
    {
        $email = trim($email);

        if ($email === '' || $this->canSeeContacts()) {
            return $email;
        }

        if (! str_contains($email, '@')) {
            return '•••';
        }

        [$local, $domain] = explode('@', $email, 2);

        return mb_substr($local, 0, 1) . '•••@' . $domain;
    }

    /** Mask a phone to `•••• 1234` (last 4 kept) unless full contacts are allowed. */
    private function maskPhone(string $phone): string
    {
        $phone = trim($phone);

        if ($phone === '' || $this->canSeeContacts()) {
            return $phone;
        }

        $digits = preg_replace('/\D/', '', $phone);

        return strlen((string) $digits) >= 4 ? '•••• ' . substr((string) $digits, -4) : '••••';
    }

    private function money(float $amount): string
    {
        return '₹' . number_format($amount);
    }

    private function daysToEvent(Event $event): string
    {
        if (! $event->date) {
            return '—';
        }

        $days = now()->startOfDay()->diffInDays($event->date->copy()->startOfDay(), false);

        return match (true) {
            $days < 0  => abs((int) $days) . 'd ago',
            $days === 0 => 'Today',
            default    => (string) (int) $days,
        };
    }
}
