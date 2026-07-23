<?php

declare(strict_types=1);

namespace App\Filament\Clusters\GameHub\Pages;

use App\Filament\Clusters\GameHub\GameHubCluster;
use App\Support\BookingReport;
use BackedEnum;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Download a booking report (partner's events + venues) as CSV for a date range.
 * The web twin of the app's Reports screen; both use App\Support\BookingReport.
 */
class Reports extends Page
{
    protected static ?string $cluster = GameHubCluster::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-arrow-down';

    protected static ?string $title = 'Reports';

    protected static ?string $navigationLabel = 'Reports';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.clusters.game-hub.reports';

    public string $from = '';
    public string $to = '';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return ($user?->canManage('gamehub') ?? false) && $user->hasPartnerPermission('reports');
    }

    public function mount(): void
    {
        $this->from = now()->subDays(30)->toDateString();
        $this->to = now()->toDateString();
    }

    public function rowCount(): int
    {
        return count(BookingReport::rows((int) auth()->id(), $this->normalisedFrom(), $this->normalisedTo()));
    }

    public function download(): StreamedResponse
    {
        $from = $this->normalisedFrom();
        $to = $this->normalisedTo();
        $csv = BookingReport::csv((int) auth()->id(), $from, $to);

        return response()->streamDownload(
            fn () => print ($csv),
            "bookings_{$from}_to_{$to}.csv",
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
