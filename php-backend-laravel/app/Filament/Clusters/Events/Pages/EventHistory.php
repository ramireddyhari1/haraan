<?php

declare(strict_types=1);

namespace App\Filament\Clusters\Events\Pages;

use App\Filament\Clusters\Events\EventsCluster;
use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Events\Pages\EventAnalytics;
use App\Models\Booking;
use App\Models\Event;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * "History" — every event this partner has already run (date in the past), so
 * they can recall what they hosted and how it did. Reuses
 * EventResource::getEloquentQuery() so the partner-scoping (own events only,
 * desk-staff event limits) is identical to the live Events list; this page just
 * narrows it to past dates and presents a recall-focused table.
 */
class EventHistory extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $cluster = EventsCluster::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-clock';

    protected static ?string $title = 'Event History';

    protected static ?string $navigationLabel = 'History';

    /** After Check-in (5) — the last item in the Events group. */
    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.clusters.events.event-history';

    /** Statuses that represent money actually earned. */
    private const PAID = ['confirmed', 'paid', 'completed', 'checked_in'];

    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('events') ?? false;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_history')
                ->label('Export Historical CSV')
                ->icon('heroicon-m-arrow-down-tray')
                ->color('gray')
                ->action(fn (): \Symfony\Component\HttpFoundation\StreamedResponse => $this->exportHistoryCsv()),
        ];
    }

    private function exportHistoryCsv(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        if (class_exists(\App\Models\AdminAction::class)) {
            \App\Models\AdminAction::log('event_history.exported');
        }

        $headers = ['ID', 'Title', 'City', 'Held On', 'Sold Slots', 'Total Slots', 'Status'];

        return response()->streamDownload(function () use ($headers): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            EventResource::getEloquentQuery()
                ->whereNotNull('date')
                ->whereDate('date', '<', now()->toDateString())
                ->chunk(200, function ($rows) use ($out): void {
                    foreach ($rows as $r) {
                        $cap = max((int) $r->total_slots, 0);
                        $sold = $cap > 0 ? max($cap - max((int) $r->available_slots, 0), 0) : 0;
                        fputcsv($out, [
                            $r->id,
                            $r->title,
                            $r->city,
                            $r->date?->format('Y-m-d'),
                            $sold,
                            $cap,
                            $r->status,
                        ]);
                    }
                });
            fclose($out);
        }, 'event-history-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv']);
    }

    /** Events whose date has passed, scoped like the table (a partner sees only their own). */
    private function pastEvents(): Builder
    {
        return EventResource::getEloquentQuery()
            ->whereNotNull('date')
            ->whereDate('date', '<', \App\Support\BusinessClock::todayDate());
    }

    /** Paid bookings on past events, joined to the event for its date and city. */
    private function pastPaid()
    {
        return Booking::query()
            ->join('events', 'events.id', '=', 'bookings.event_id')
            ->whereIn('bookings.event_id', $this->pastEvents()->select('events.id'))
            ->whereIn(DB::raw('lower(bookings.status)'), self::PAID);
    }

    /** @return array<string,mixed> */
    public function getHistorySummary(): array
    {
        $count = $this->pastEvents()->count();
        $capacity = (int) $this->pastEvents()->sum('total_slots');
        $sold = (int) $this->pastEvents()->selectRaw('coalesce(sum(total_slots - available_slots), 0) as s')->value('s');
        $revenue = (float) $this->pastPaid()->sum('bookings.total_amount');
        $tickets = (int) $this->pastPaid()->sum('bookings.quantity');
        $scanned = (int) $this->pastPaid()->sum('bookings.checked_in_count');

        return [
            'events' => $count,
            'revenue' => \App\Support\Rupees::format($revenue),
            'tickets' => $tickets,
            'fill' => $capacity > 0 ? (int) round($sold / $capacity * 100).'%' : '—',
            'fillNote' => $capacity > 0 ? number_format($sold).' of '.number_format($capacity).' seats' : 'No seat limits set',
            'showUp' => $tickets > 0 ? (int) round(min($scanned, $tickets) / $tickets * 100).'%' : '—',
            'showUpNote' => $tickets > 0 ? number_format($scanned).' scanned at the gate' : 'No tickets sold yet',
        ];
    }

    /**
     * Takings per quarter of the event date, last six quarters, oldest first.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getQuarterlyTakings(): array
    {
        $now = \App\Support\BusinessClock::now();
        $quarters = [];
        for ($i = 5; $i >= 0; $i--) {
            $q = $now->copy()->firstOfQuarter()->subQuarters($i);
            $quarters[$q->format('Y').'-Q'.$q->quarter] = ['label' => 'Q'.$q->quarter.' '.$q->format('y'), 'value' => 0.0, 'events' => []];
        }

        $rows = $this->pastPaid()
            ->where('events.date', '>=', $now->copy()->firstOfQuarter()->subQuarters(5)->toDateString())
            ->get(['events.date as event_date', 'bookings.total_amount', 'bookings.event_id']);

        foreach ($rows as $r) {
            $d = \Illuminate\Support\Carbon::parse($r->event_date);
            $key = $d->format('Y').'-Q'.$d->quarter;
            if (isset($quarters[$key])) {
                $quarters[$key]['value'] += (float) $r->total_amount;
                $quarters[$key]['events'][$r->event_id] = true;
            }
        }

        $max = max(array_column($quarters, 'value') ?: [0]);

        return array_values(array_map(fn (array $q): array => [
            'label' => $q['label'],
            'value' => \App\Support\Rupees::format($q['value']),
            'short' => \App\Support\Rupees::short($q['value']),
            'events' => count($q['events']),
            'pct' => $max > 0 ? (int) round($q['value'] / $max * 100) : 0,
        ], $quarters));
    }

    /**
     * Past events by city: takings, how many events, and how full they got.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getCityHistory(): array
    {
        $cityExpr = "coalesce(nullif(trim(city), ''), 'Unknown')";

        $events = $this->pastEvents()
            ->selectRaw("{$cityExpr} as c, count(*) as n, coalesce(sum(total_slots), 0) as cap, coalesce(sum(total_slots - available_slots), 0) as sold")
            ->groupBy('c')
            ->toBase()
            ->get()
            ->keyBy('c');

        $money = $this->pastPaid()
            ->selectRaw("coalesce(nullif(trim(events.city), ''), 'Unknown') as c, sum(bookings.total_amount) as v")
            ->groupBy('c')
            ->toBase()
            ->pluck('v', 'c');

        return $events->map(fn ($e, $c): array => [
            'city' => (string) $c,
            'raw' => (float) ($money[$c] ?? 0),
            'revenue' => \App\Support\Rupees::format((float) ($money[$c] ?? 0)),
            'events' => (int) $e->n,
            'fill' => (int) $e->cap > 0 ? (int) round((int) $e->sold / (int) $e->cap * 100) : null,
        ])->sortByDesc('raw')->take(6)->values()->all();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => EventResource::getEloquentQuery()
                ->whereNotNull('date')
                ->whereDate('date', '<', now()->toDateString()))
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label('Event')
                    ->weight('bold')
                    ->description(fn (Event $r): ?string => $r->city)
                    ->searchable(['title', 'venue', 'location'])
                    ->wrap(),
                TextColumn::make('date')
                    ->label('Held on')
                    ->dateTime('D, d M Y')
                    ->sortable(),
                TextColumn::make('ago')
                    ->label('When')
                    ->state(fn (Event $r): string => $r->date ? $r->date->diffForHumans() : '—')
                    ->color('gray'),
                TextColumn::make('sold')
                    ->label('Sold')
                    ->badge()
                    ->color('info')
                    ->state(function (Event $r): string {
                        $cap  = max((int) $r->total_slots, 0);
                        $sold = $cap > 0 ? max($cap - max((int) $r->available_slots, 0), 0) : 0;
                        return $cap > 0 ? "{$sold} / {$cap}" : (string) $sold;
                    }),
                TextColumn::make('revenue')
                    ->label('Revenue')
                    ->weight('bold')
                    ->color('success')
                    ->state(fn (Event $r): string => '₹' . number_format((float) Booking::query()
                        ->where('event_id', $r->id)
                        ->whereIn(DB::raw('lower(status)'), self::PAID)
                        ->sum('total_amount'))),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => ucfirst(strtolower((string) $state)))
                    ->color(fn (?string $state): string => strtolower((string) $state) === 'published' ? 'success' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'published' => 'Published',
                        'draft' => 'Draft',
                        'cancelled' => 'Cancelled',
                    ])
                    ->query(fn (Builder $q, array $data): Builder => filled($data['value'] ?? null)
                        ? $q->whereRaw('lower(status) = ?', [strtolower((string) $data['value'])])
                        : $q),
            ], layout: FiltersLayout::AboveContent)
            ->recordActions([
                \App\Filament\Resources\Events\Actions\ManageGalleryAction::make(),
                Action::make('analytics')
                    ->label('View report')
                    ->icon('heroicon-m-chart-bar')
                    ->url(fn (Event $r): string => EventAnalytics::getUrl(['record' => $r]))
                    ->visible(fn (): bool => (auth()->user()?->hasPartnerPermission('reports') ?? false)),
            ])
            ->emptyStateIcon('heroicon-o-clock')
            ->emptyStateHeading('No past events yet')
            ->emptyStateDescription('Events you have hosted will appear here once their date has passed.');
    }
}
