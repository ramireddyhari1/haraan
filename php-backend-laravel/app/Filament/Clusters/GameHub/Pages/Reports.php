<?php

declare(strict_types=1);

namespace App\Filament\Clusters\GameHub\Pages;

use App\Filament\Clusters\GameHub\Concerns\SummarisesVenues;
use App\Filament\Clusters\GameHub\GameHubCluster;
use App\Support\BookingReport;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pick a date range, see what happened in it, download the bookings as CSV.
 *
 * There is one export — the bookings sheet (BookingReport, the same file the
 * partner app downloads). The page used to offer four "reports" and a
 * "schedule for 06:00" button; all four downloaded this same file and nothing
 * was ever scheduled, so the choice and the button are gone.
 */
class Reports extends Page
{
    use SummarisesVenues;

    protected static ?string $cluster = GameHubCluster::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $title = 'Reports';

    protected static ?string $navigationLabel = 'Reports';

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.clusters.game-hub.reports';

    public string $from = '';

    public string $to = '';

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (! ($user?->canManage('gamehub') ?? false)) {
            return false;
        }

        if (static::inPartnerConsole()) {
            return $user->hasPartnerPermission('reports');
        }

        return true;
    }

    public function mount(): void
    {
        $this->from = now()->subDays(30)->toDateString();
        $this->to = now()->toDateString();
    }

    public function getPanels(): array
    {
        [$from, $to] = [$this->normalisedFrom() . ' 00:00:00', $this->normalisedTo() . ' 23:59:59'];
        $inRange = fn () => static::venueBookings()->whereBetween('created_at', [$from, $to]);

        $total = $inRange()->count();
        $paid = $inRange()->whereIn(DB::raw('lower(status)'), self::PAID);
        $value = (float) (clone $paid)->sum('total_amount');
        $paidCount = (clone $paid)->count();
        $cancelled = $inRange()->whereIn(DB::raw('lower(status)'), self::CANCELLED)->count();
        $collected = (float) DB::table('booking_payments')
            ->whereIn('booking_id', static::venueBookings()->select('id'))
            ->whereBetween('collected_at', [$from, $to])
            ->sum('amount');
        $players = (clone $paid)->distinct()->count(DB::raw("coalesce(nullif(guest_phone, ''), user_id)"));

        return [[
            'title' => 'In this range',
            'window' => date('j M Y', strtotime($from)) . ' – ' . date('j M Y', strtotime($to)),
            'stats' => [
                ['label' => 'Bookings made', 'value' => number_format($total), 'sub' => number_format($paidCount) . ' paid or confirmed'],
                ['label' => 'Booked value', 'value' => self::inr($value)],
                ['label' => 'Collected', 'value' => self::inr($collected), 'sub' => 'payment ledger, net of refunds'],
                ['label' => 'Cancelled', 'value' => self::pct($cancelled, $total), 'sub' => number_format($cancelled) . ' of ' . number_format($total)],
                ['label' => 'Players', 'value' => number_format($players)],
            ],
        ]];
    }

    /** Rows the CSV will contain for the chosen range. */
    public function rowCount(): int
    {
        return count(BookingReport::rows(static::partnerId(), $this->normalisedFrom(), $this->normalisedTo()));
    }

    public function download(): StreamedResponse
    {
        $from = $this->normalisedFrom();
        $to = $this->normalisedTo();
        // Partner console: the owner's bookings (staff resolve to their owner).
        // /control: every venue booking.
        $csv = BookingReport::csv(static::partnerId(), $from, $to);

        return response()->streamDownload(
            fn () => print ($csv),
            "haraan_bookings_{$from}_to_{$to}.csv",
            ['Content-Type' => 'text/csv'],
        );
    }

    private function normalisedFrom(): string
    {
        return $this->from !== '' ? date('Y-m-d', strtotime($this->from)) : now()->subDays(30)->toDateString();
    }

    private function normalisedTo(): string
    {
        return $this->to !== '' ? date('Y-m-d', strtotime($this->to)) : now()->toDateString();
    }
}
