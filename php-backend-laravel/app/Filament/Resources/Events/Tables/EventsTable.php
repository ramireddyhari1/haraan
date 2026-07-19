<?php

namespace App\Filament\Resources\Events\Tables;

use App\Filament\Resources\Events\Pages\EventAnalytics;
use App\Models\Event;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The events list, rebuilt for operators rather than as a raw column dump. A tight
 * default set — poster, what/when/where, whether it's live, price, and how many
 * tickets have gone — with the setup fields (visibility, seat map, access code…)
 * tucked behind the column toggle so the day-to-day view stays scannable.
 */
class EventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                ImageColumn::make('poster')
                    ->label('')
                    ->height(44)
                    ->extraImgAttributes(['style' => 'width:64px;object-fit:cover;border-radius:8px;'])
                    ->getStateUsing(fn (Event $r): string => $r->heroImageUrl() ?? self::posterPlaceholder()),

                TextColumn::make('title')
                    ->weight('bold')
                    ->description(fn (Event $r): ?string => self::whenWhere($r))
                    ->searchable(['title', 'venue', 'location'])
                    ->wrap(),

                TextColumn::make('category')
                    ->badge()
                    ->color('info')
                    ->placeholder('—'),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state ? ucfirst(strtolower($state)) : '—')
                    ->color(fn (?string $state): string => match (strtolower((string) $state)) {
                        'published' => 'success',
                        'draft' => 'gray',
                        'cancelled', 'ended' => 'danger',
                        default => 'warning',
                    }),

                TextColumn::make('date')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('price')
                    ->money('INR')
                    ->sortable(),

                // Tickets gone vs capacity — the number an operator actually watches.
                TextColumn::make('sold')
                    ->label('Tickets')
                    ->badge()
                    ->state(fn (Event $r): string => self::ticketsLabel($r))
                    ->color(fn (Event $r): string => self::ticketsColor($r))
                    ->tooltip(fn (Event $r): string => $r->available_slots . ' of ' . max(0, (int) $r->total_slots) . ' left'),

                TextColumn::make('partner.name')
                    ->label('Host')
                    ->placeholder('—')
                    ->toggleable(),

                // --- Setup detail, hidden by default (toggle on when needed) ---
                TextColumn::make('booking_format')->label('Format')->badge()->color('gray')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('visibility')->badge()->color('gray')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('access_code')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('time')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('total_slots')->numeric()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('available_slots')->numeric()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
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
            // Clicking anywhere on the row (i.e. the event name) opens the read-only analytics
            // dashboard, not the edit form. Editing stays a deliberate, explicit action.
            ->recordUrl(fn ($record): string => EventAnalytics::getUrl(['record' => $record]))
            ->recordActions([
                Action::make('analytics')
                    ->label('Analytics')
                    ->icon('heroicon-m-chart-bar')
                    ->color('gray')
                    ->url(fn ($record): string => EventAnalytics::getUrl(['record' => $record])),
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

    /** A neutral rounded placeholder for events without a poster (self-contained). */
    private static function posterPlaceholder(): string
    {
        $svg = "<svg xmlns='http://www.w3.org/2000/svg' width='64' height='44'>"
            . "<rect width='64' height='44' rx='8' fill='#e8ecf3'/>"
            . "<path d='M20 30l7-8 6 6 4-4 7 8z' fill='#b6c0d0'/>"
            . "<circle cx='24' cy='17' r='3' fill='#b6c0d0'/></svg>";

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
