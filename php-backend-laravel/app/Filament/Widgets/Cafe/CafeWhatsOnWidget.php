<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Cafe;

use App\Models\Booking;
use App\Models\Event;
use App\Support\PartnerLane;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\DB;

/**
 * What's on at the café — the nights, not the bookings.
 *
 * This is the one widget the sports console has no equivalent of, and it is the
 * reason the café lane exists at all. A café's calendar isn't "who booked court
 * 3 at 7pm"; it's the open mic, the quiz night, the pottery workshop — each one
 * a thing people decide to come to, with tickets that either move or don't.
 *
 * Deliberately shows tickets sold and revenue per event rather than a plain
 * list: an owner looking at Thursday's quiz with four tickets gone still has
 * time to post about it, which is the whole point of looking.
 *
 * Events carry a `partner_id` and no venue, so this is a BUSINESS-level widget —
 * it does not narrow with the branch switcher, because there is nothing in the
 * schema that says which outlet an event belongs to. When `events.venue_id`
 * lands (P2), this should start honouring the selection.
 */
class CafeWhatsOnWidget extends TableWidget
{
    protected static ?int $sort = 3;

    /** Custom v4 widgets render blank unless this is false. */
    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = 'full';

    /** Café lane only — a turf has no open mic, and an event host has its own console. */
    public static function canView(): bool
    {
        if (Filament::getCurrentPanel()?->getId() !== 'partner') {
            return false;
        }

        return auth()->user()?->partnerLane() === PartnerLane::CAFE;
    }

    public function table(Table $table): Table
    {
        $partnerId = (int) (auth()->user()?->effectivePartnerId() ?? 0);

        return $table
            ->heading("What's on")
            ->description('Your upcoming nights, and how they are selling')
            ->query(
                Event::query()
                    ->where('partner_id', $partnerId)
                    ->notFinished()
                    ->orderBy('date')
                    ->limit(6)
            )
            ->paginated(false)
            ->emptyStateHeading('Nothing on the calendar')
            ->emptyStateDescription('Quiz nights, open mics and workshops you publish will show up here.')
            ->columns([
                TextColumn::make('title')
                    ->label('Event')
                    ->weight('bold')
                    ->wrap()
                    ->description(fn (Event $r): ?string => $r->category),

                TextColumn::make('date')
                    ->label('When')
                    ->date('D, d M')
                    ->description(fn (Event $r): ?string => $r->time),

                TextColumn::make('sold')
                    ->label('Tickets')
                    ->state(fn (Event $r): string => (string) $this->soldFor($r))
                    ->badge()
                    ->color(fn (Event $r): string => $this->soldFor($r) > 0 ? 'success' : 'gray'),

                TextColumn::make('revenue')
                    ->label('Revenue')
                    ->state(fn (Event $r): string => '₹'.number_format($this->revenueFor($r)))
                    ->alignEnd(),
            ]);
    }

    /**
     * Tickets sold for one event.
     *
     * Memoised per request: the table renders six rows and asks each row for both
     * a count and a sum, so an un-cached lookup is twelve queries for six events.
     *
     * @var array<int, array{sold:int, revenue:float}>
     */
    private array $memo = [];

    private function totals(Event $event): array
    {
        return $this->memo[$event->id] ??= (function () use ($event): array {
            $row = Booking::query()
                ->where('event_id', $event->id)
                // Status casing is mixed across this table — always lower both sides.
                ->whereIn(DB::raw('lower(status)'), ['confirmed', 'paid', 'completed', 'checked_in'])
                ->selectRaw('COALESCE(SUM(quantity), 0) as sold, COALESCE(SUM(total_amount), 0) as revenue')
                ->first();

            return [
                'sold' => (int) ($row->sold ?? 0),
                'revenue' => (float) ($row->revenue ?? 0),
            ];
        })();
    }

    private function soldFor(Event $event): int
    {
        return $this->totals($event)['sold'];
    }

    private function revenueFor(Event $event): float
    {
        return $this->totals($event)['revenue'];
    }
}
