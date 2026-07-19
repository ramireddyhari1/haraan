<?php

declare(strict_types=1);

namespace App\Filament\Clusters\Events\Widgets;

use App\Filament\Resources\Events\Pages\EventAnalytics;
use App\Models\Event;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * "What's coming up" — the next few published events, so the Events overview
 * leads with the calendar an operator is actually working toward rather than a
 * pair of navigation links. Poster, when/where, tickets-sold meter, status;
 * each row deep-links to that event's analytics. (Presentation mirrors the
 * Events list table intentionally.)
 */
class UpcomingEventsWidget extends TableWidget
{
    use \App\Filament\Concerns\RefreshesOnContentUpdate;
    use \App\Filament\Concerns\ScopesToPartnerEvents;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getTableHeading(): ?string
    {
        return 'Upcoming events';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                $this->scopedEventQuery()
                    ->whereRaw("lower(status) = 'published'")
                    ->whereDate('date', '>=', now()->toDateString())
                    ->orderBy('date')
                    ->limit(6)
            )
            ->paginated(false)
            ->emptyStateHeading('No upcoming events')
            ->emptyStateDescription('Published events with a future date will show here.')
            ->emptyStateIcon('heroicon-o-calendar-days')
            ->recordUrl(fn (Event $r): string => EventAnalytics::getUrl(['record' => $r]))
            ->columns([
                ImageColumn::make('poster')
                    ->label('')
                    ->height(40)
                    ->extraImgAttributes(['style' => 'width:58px;object-fit:cover;border-radius:8px;'])
                    ->getStateUsing(fn (Event $r): string => $r->heroImageUrl() ?? self::posterPlaceholder()),

                TextColumn::make('title')
                    ->weight('bold')
                    ->description(fn (Event $r): ?string => self::whenWhere($r))
                    ->wrap(),

                TextColumn::make('sold')
                    ->label('Tickets')
                    ->badge()
                    ->state(fn (Event $r): string => self::ticketsLabel($r))
                    ->color(fn (Event $r): string => self::ticketsColor($r)),

                TextColumn::make('date')
                    ->date('d M Y')
                    ->sortable(),
            ]);
    }

    private static function whenWhere(Event $r): ?string
    {
        $bits = [];
        if ($r->date !== null) {
            $bits[] = $r->date->format('D, d M · g:i A');
        }
        $place = trim((string) ($r->venue ?: $r->location));
        if ($place !== '') {
            $bits[] = $place;
        }

        return $bits === [] ? null : implode(' · ', $bits);
    }

    private static function ticketsLabel(Event $r): string
    {
        $total = max(0, (int) $r->total_slots);
        $sold = max(0, $total - max(0, (int) $r->available_slots));

        return $total > 0 ? "{$sold} / {$total}" : (string) $sold;
    }

    private static function ticketsColor(Event $r): string
    {
        $total = max(0, (int) $r->total_slots);
        if ($total === 0) {
            return 'gray';
        }
        $ratio = 1 - (max(0, (int) $r->available_slots) / $total);

        return match (true) {
            $ratio >= 1.0 => 'danger',
            $ratio >= 0.85 => 'warning',
            default => 'success',
        };
    }

    private static function posterPlaceholder(): string
    {
        $svg = "<svg xmlns='http://www.w3.org/2000/svg' width='58' height='40'>"
            . "<rect width='58' height='40' rx='8' fill='#e8ecf3'/>"
            . "<path d='M18 27l6-7 5 5 4-4 7 6z' fill='#b6c0d0'/>"
            . "<circle cx='21' cy='16' r='3' fill='#b6c0d0'/></svg>";

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
