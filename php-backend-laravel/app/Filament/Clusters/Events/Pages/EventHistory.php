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
