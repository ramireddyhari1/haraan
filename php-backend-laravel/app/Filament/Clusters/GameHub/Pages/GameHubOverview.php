<?php

declare(strict_types=1);

namespace App\Filament\Clusters\GameHub\Pages;

use App\Filament\Clusters\GameHub\Concerns\SummarisesVenues;
use App\Filament\Clusters\GameHub\GameHubCluster;
use App\Models\LiveMatch;
use App\Models\Venue;
use App\Models\VenueCourt;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The GameHub front page: today at the courts, this month against the same
 * days last month, and the venues themselves. Money on "today" is what the
 * payment ledger collected; the month compares paid bookings like for like
 * (1st to today vs 1st to the same date last month).
 *
 * Replaces a "command center" whose figures were mostly invented (₹48,750
 * collected today when nothing was, an 88–99 "health score", an "OpenCV
 * 99.8% uptime" pillar, a scripted live-operations feed).
 */
class GameHubOverview extends Page
{
    use SummarisesVenues;

    protected static ?string $cluster = GameHubCluster::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $title = 'GameHub overview';

    protected static ?string $navigationLabel = 'Overview';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.clusters.game-hub.summary-page';

    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('gamehub') ?? false;
    }

    public function getPanels(): array
    {
        return [$this->today(), $this->month(), $this->venues()];
    }

    private function today(): array
    {
        $today = Carbon::today();
        $sheet = fn () => static::venueBookings()->whereDate('slot_date', $today);
        $live = fn () => $sheet()->whereNotIn(DB::raw('lower(status)'), [...self::CANCELLED, 'expired']);

        $onSheet = $live()->count();
        $checkedIn = $live()->whereNotNull('checked_in_at')->count();
        $cancelled = $sheet()->whereIn(DB::raw('lower(status)'), self::CANCELLED)->count();

        $byMethod = DB::table('booking_payments')
            ->whereIn('booking_id', static::venueBookings()->select('id'))
            ->whereDate('collected_at', $today)
            ->selectRaw('lower(method) as m, SUM(amount) as total')
            ->groupBy('m')
            ->pluck('total', 'm');

        $next = $live()->whereNull('checked_in_at')
            ->where('start_time', '>=', now()->format('H:i'))
            ->with(['venue:id,name', 'user:id,name'])
            ->orderBy('start_time')
            ->limit(5)
            ->get();

        return [
            'title' => 'Today',
            'window' => $today->format('l, j M'),
            'stats' => [
                ['label' => 'Collected', 'value' => self::inr((float) $byMethod->sum()), 'sub' => 'from the payment ledger', 'tone' => $byMethod->sum() > 0 ? 'good' : null],
                ['label' => 'On the sheet', 'value' => number_format($onSheet), 'sub' => 'bookings for today'],
                ['label' => 'Checked in', 'value' => number_format($checkedIn), 'sub' => $onSheet > 0 ? self::pct($checkedIn, $onSheet) . ' of today' : null],
                ['label' => 'Cancelled', 'value' => number_format($cancelled)],
            ],
            'split' => [
                'label' => 'Collected today, by method',
                'parts' => self::parts(collect($byMethod)->mapWithKeys(fn ($v, $m) => [strtoupper((string) $m) === 'UPI' ? 'UPI' : ucfirst((string) $m) => (float) $v])->all()),
            ],
            'list' => [
                'title' => 'Still to come today',
                'rows' => $next->map(fn ($b): array => [
                    'primary' => $b->guest_name ?: ($b->user?->name ?? 'Guest'),
                    'secondary' => collect([$b->venue?->name, $b->slot_label])->filter()->join(' · '),
                    'trailing' => substr((string) $b->start_time, 0, 5),
                ])->all(),
                'empty' => 'Nobody else is booked in for today.',
            ],
        ];
    }

    private function month(): array
    {
        $start = now()->startOfMonth();
        $prevStart = now()->subMonthNoOverflow()->startOfMonth();
        $prevEnd = now()->subMonthNoOverflow(); // same point in last month

        $paid = fn () => static::venueBookings()->whereIn(DB::raw('lower(status)'), self::PAID);
        $rev = (float) $paid()->where('created_at', '>=', $start)->sum('total_amount');
        $prev = (float) $paid()->whereBetween('created_at', [$prevStart, $prevEnd])->sum('total_amount');
        $count = $paid()->where('created_at', '>=', $start)->count();
        $players = $paid()->where('created_at', '>=', $start)
            ->distinct()->count(DB::raw("coalesce(nullif(guest_phone, ''), user_id)"));

        $growth = $prev > 0 ? round(($rev - $prev) / $prev * 100, 1) : null;

        return [
            'title' => 'This month',
            'window' => $start->format('j M') . ' – ' . now()->format('j M'),
            'stats' => [
                ['label' => 'Booked value', 'value' => self::inr($rev), 'sub' => 'paid and confirmed bookings'],
                ['label' => 'vs last month', 'value' => $growth === null ? '—' : ($growth >= 0 ? '+' : '') . $growth . '%',
                    'sub' => $growth === null ? 'nothing to compare yet' : 'same days, ' . self::inr($prev),
                    'tone' => $growth === null ? null : ($growth >= 0 ? 'good' : 'warn')],
                ['label' => 'Bookings', 'value' => number_format($count)],
                ['label' => 'Players', 'value' => number_format($players)],
            ],
        ];
    }

    private function venues(): array
    {
        $venues = fn () => static::ownVenues(Venue::query(), 'id');
        $live = $venues()->where('is_active', true)->where('status', 'published')->count();
        $courts = static::ownVenues(VenueCourt::query()->where('is_active', true))->count();

        $stats = [
            ['label' => 'Live venues', 'value' => number_format($live), 'sub' => number_format($venues()->count()) . ' in all'],
            ['label' => 'Active courts', 'value' => number_format($courts)],
        ];

        // Matches store a venue name, not a venue id — so only the platform view
        // can count them without guessing which belong to whom.
        if (! static::inPartnerConsole()) {
            $stats[] = ['label' => 'Matches live now', 'value' => number_format(
                LiveMatch::whereIn(DB::raw('lower(status)'), ['live', 'in_progress'])->count()
            )];
        }

        return ['title' => 'Venues', 'stats' => $stats];
    }
}
