<?php

namespace App\Filament\Resources\Events\Tables;

use App\Filament\Resources\Events\Pages\CreateEvent;
use App\Filament\Resources\Events\Pages\EditEvent;
use App\Filament\Resources\Events\Pages\EventAnalytics;
use App\Models\Event;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The events list, rebuilt as a BookMyShow-style card grid rather than a raw
 * column dump. Each event is one card: portrait poster on the left, a stack of
 * title → when/where → status·demand·category chips → price·tickets on the
 * right. Renders as a single scannable column on phones and two-up on wide
 * desktops (contentGrid), with no horizontal scroll. Setup fields that used to
 * be toggle columns don't belong on a card, so they live on the edit form.
 */
class EventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            // One card per event; single column on phones/tablets, two across on
            // large screens. This is what turns the table into a card grid.
            ->contentGrid(['default' => 1, 'xl' => 2])
            ->columns([
                Split::make([
                    ImageColumn::make('poster')
                        ->label('')
                        ->height(78)
                        ->extraImgAttributes(['style' => 'width:58px;height:78px;object-fit:cover;border-radius:12px;box-shadow:0 6px 16px -8px rgba(11,18,32,.45);'])
                        ->getStateUsing(fn (Event $r): string => $r->heroImageUrl() ?? self::posterPlaceholder())
                        ->grow(false),

                    Stack::make([
                        TextColumn::make('title')
                            ->weight('bold')
                            ->size('lg')
                            ->searchable(['title', 'venue', 'location'])
                            ->wrap(),

                        TextColumn::make('whenwhere')
                            ->label('Date')
                            ->state(fn (Event $r): ?string => self::whenWhere($r))
                            ->color('gray')
                            ->size('sm')
                            ->wrap()
                            ->sortable(['date']),

                        // Chip row: live status + demand tag + category.
                        Split::make([
                            TextColumn::make('status')
                                ->badge()
                                ->formatStateUsing(fn (?string $state): string => match (strtolower((string) $state)) {
                                    'published' => 'Live',
                                    default => $state ? ucfirst(strtolower($state)) : '—',
                                })
                                ->color(fn (?string $state): string => match (strtolower((string) $state)) {
                                    'published' => 'success',
                                    'draft' => 'gray',
                                    'cancelled', 'ended' => 'danger',
                                    default => 'warning',
                                }),

                            TextColumn::make('demand')
                                ->badge()
                                ->state(fn (Event $r): ?string => self::demandLabel($r))
                                ->color(fn (Event $r): string => self::demandColor($r)),

                            TextColumn::make('category')
                                ->badge()
                                ->color('info'),
                        ])->grow(false),

                        // Price + tickets-gone, the two numbers an operator watches.
                        Split::make([
                            TextColumn::make('price')
                                ->money('INR')
                                ->weight('bold')
                                ->color('primary')
                                ->sortable(),

                            TextColumn::make('sold')
                                ->label('Tickets sold')
                                ->badge()
                                ->state(fn (Event $r): string => self::ticketsLabel($r))
                                ->color(fn (Event $r): string => self::ticketsColor($r))
                                ->tooltip(fn (Event $r): string => $r->available_slots . ' of ' . max(0, (int) $r->total_slots) . ' left')
                                // Sort by tickets actually gone (capacity − remaining), most-sold first.
                                ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw(
                                    '(COALESCE(total_slots, 0) - COALESCE(available_slots, 0)) ' . ($direction === 'asc' ? 'asc' : 'desc')
                                )),
                        ])->grow(false),
                    ])->space(2),
                ]),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'published' => 'Published',
                        'draft' => 'Draft',
                        'cancelled' => 'Cancelled',
                        'ended' => 'Ended',
                    ])
                    // status casing is mixed in the DB — match case-insensitively.
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereRaw('lower(status) = ?', [strtolower($data['value'])])
                        : $query),
                SelectFilter::make('category')
                    ->options(fn (): array => Event::query()
                        ->whereNotNull('category')
                        ->distinct()
                        ->orderBy('category')
                        ->pluck('category', 'category')
                        ->all()),
            ])
            // Warm first-run state instead of Filament's bare "No records" — a new
            // partner lands here with zero events, so point them straight at Create.
            ->emptyStateIcon('heroicon-o-ticket')
            ->emptyStateHeading('No events yet')
            ->emptyStateDescription('Publish your first event to start taking bookings and checking guests in at the gate.')
            ->emptyStateActions([
                Action::make('createFirstEvent')
                    ->label('Create your first event')
                    ->icon('heroicon-m-plus')
                    ->button()
                    ->url(fn (): string => CreateEvent::getUrl()),
            ])
            // Tapping the card opens the read-only analytics dashboard, not the
            // edit form. Editing stays a deliberate, explicit action.
            ->recordUrl(fn ($record): string => EventAnalytics::getUrl(['record' => $record]))
            ->recordActions([
                Action::make('analytics')
                    ->label('Analytics')
                    ->icon('heroicon-m-chart-bar')
                    ->color('gray')
                    ->url(fn ($record): string => EventAnalytics::getUrl(['record' => $record])),
                Action::make('duplicate')
                    ->label('Duplicate')
                    ->icon('heroicon-m-document-duplicate')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Duplicate this event?')
                    ->modalDescription('Creates an editable draft copy with the same details and ticket tiers. Bookings, sales and ratings are not carried over — you get a clean event to re-date and publish.')
                    ->modalSubmitActionLabel('Duplicate')
                    ->action(fn (Event $record) => self::duplicate($record)),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /** "12 Jul 2026 · Quake Arena, Hyderabad" — the when/where sub-line under the title. */
    private static function whenWhere(Event $r): ?string
    {
        $bits = [];
        if ($r->date !== null) {
            $bits[] = $r->date->format('d M Y');
        }
        $place = trim((string) ($r->venue ?: $r->location));
        if ($place !== '') {
            $bits[] = $place;
        }

        return $bits === [] ? null : implode(' · ', $bits);
    }

    /** "382 / 500" sold vs capacity, or just the sold count when capacity is open. */
    private static function ticketsLabel(Event $r): string
    {
        $total = max(0, (int) $r->total_slots);
        $sold = max(0, $total - max(0, (int) $r->available_slots));

        return $total > 0 ? "{$sold} / {$total}" : (string) $sold;
    }

    /** Green when selling well, amber as it fills, red once sold out. */
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

    /** BMS-style demand tag driven by real sell-through; null (no badge) when nothing's sold. */
    private static function demandLabel(Event $r): ?string
    {
        $total = max(0, (int) $r->total_slots);
        if ($total === 0) {
            return null;
        }
        $sold = max(0, $total - max(0, (int) $r->available_slots));
        if ($sold <= 0) {
            return null;
        }
        $ratio = $sold / $total;

        return match (true) {
            $ratio >= 1.0 => 'Sold out',
            $ratio >= 0.85 => 'Almost full',
            $ratio >= 0.60 => 'Filling fast',
            default => 'Selling',
        };
    }

    /** Colour for the demand tag, matching the sell-through urgency. */
    private static function demandColor(Event $r): string
    {
        return match (self::demandLabel($r)) {
            'Sold out' => 'danger',
            'Almost full' => 'warning',
            'Filling fast' => 'warning',
            'Selling' => 'info',
            default => 'gray',
        };
    }

    /**
     * Clone an event into a fresh editable draft: same details + ticket tiers,
     * but a clean slate on everything that belongs to the original's own run —
     * status resets to draft, capacity is freed, sales/ratings are dropped, and
     * (in the partner console) ownership is stamped to the current partner. Drops
     * the host straight on the copy's Edit page to re-date and publish.
     */
    private static function duplicate(Event $record): \Illuminate\Http\RedirectResponse
    {
        $copy = $record->replicate(['created_at', 'updated_at']);
        $copy->title = mb_substr((string) $record->title, 0, 240) . ' (Copy)';
        $copy->status = 'draft';
        $copy->available_slots = max(0, (int) $record->total_slots);
        $copy->rating = null;
        $copy->ratings_count = 0;

        if (Filament::getCurrentPanel()?->getId() === 'partner') {
            $copy->partner_id = auth()->user()?->effectivePartnerId();
        }

        $copy->save();

        // Carry the ticket tiers so pricing is ready to go on the copy.
        foreach ($record->ticketTypes as $tier) {
            $clone = $tier->replicate(['created_at', 'updated_at']);
            $clone->event_id = $copy->id;
            $clone->save();
        }

        Notification::make()
            ->success()
            ->title('Event duplicated')
            ->body('An editable draft copy was created — set a new date and publish when ready.')
            ->send();

        return redirect(EditEvent::getUrl(['record' => $copy]));
    }

    /** A neutral rounded placeholder for events without a poster (self-contained). */
    private static function posterPlaceholder(): string
    {
        $svg = "<svg xmlns='http://www.w3.org/2000/svg' width='58' height='78'>"
            . "<rect width='58' height='78' rx='12' fill='#e8ecf3'/>"
            . "<path d='M16 54l10-12 8 8 6-6 12 10z' fill='#b6c0d0'/>"
            . "<circle cx='22' cy='30' r='4' fill='#b6c0d0'/></svg>";

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
