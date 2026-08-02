<?php

declare(strict_types=1);

namespace App\Filament\Resources\Shifts\Tables;

use App\Models\ShiftSession;
use App\Services\ShiftService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The close-out sheet.
 *
 * Deliberately does NOT pre-fill the counted amount with the expected figure —
 * that would turn counting the drawer into pressing Save, which is the entire
 * control. The expected number is shown only *after* a count is entered, in the
 * confirmation, so the person counting isn't anchored to it.
 */
class ShiftSessionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('opened_at', 'desc')
            ->columns([
                TextColumn::make('staff.name')
                    ->label('On duty')
                    ->description(fn (ShiftSession $record): ?string => $record->venue?->name)
                    ->searchable()
                    ->weight('medium'),

                TextColumn::make('opened_at')
                    ->label('Shift')
                    ->state(fn (ShiftSession $record): string => sprintf(
                        '%s → %s',
                        $record->opened_at?->format('d M, g:i A'),
                        $record->closed_at?->format('g:i A') ?? 'now',
                    ))
                    ->sortable(),

                TextColumn::make('opening_float')
                    ->label('Float')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => self::inr((float) $state))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('cash')
                    ->label('Cash taken')
                    ->alignEnd()
                    ->state(fn (ShiftSession $record): string => self::inr($record->cashMovement())),

                TextColumn::make('digital')
                    ->label('UPI / card')
                    ->alignEnd()
                    ->state(fn (ShiftSession $record): string => self::inr($record->digitalMovement()))
                    ->tooltip('Lands in your account and on the PDQ statement — never counted in the drawer.'),

                TextColumn::make('expected')
                    ->label('Expected in drawer')
                    ->alignEnd()
                    ->weight('medium')
                    ->state(fn (ShiftSession $record): string => self::inr($record->expectedCash())),

                TextColumn::make('counted_cash')
                    ->label('Counted')
                    ->alignEnd()
                    ->placeholder('—')
                    ->formatStateUsing(fn ($state): string => self::inr((float) $state)),

                TextColumn::make('variance')
                    ->label('Variance')
                    ->badge()
                    ->alignEnd()
                    ->state(function (ShiftSession $record): string {
                        $variance = $record->currentVariance();

                        if ($variance === null) {
                            return $record->isOpen() ? 'Open' : 'Not counted';
                        }

                        return abs($variance) < 0.01
                            ? 'Square'
                            : sprintf('%s %s', self::inr(abs($variance)), $variance < 0 ? 'short' : 'over');
                    })
                    ->color(function (ShiftSession $record): string {
                        $variance = $record->currentVariance();

                        return match (true) {
                            $variance === null => 'gray',
                            abs($variance) < 0.01 => 'success',
                            $variance < 0 => 'danger',
                            default => 'warning',
                        };
                    }),
            ])
            ->filters([
                Filter::make('open')
                    ->label('Still open')
                    ->query(fn (Builder $query): Builder => $query->whereNull('closed_at')),

                Filter::make('short')
                    ->label('Came up short')
                    ->query(fn (Builder $query): Builder => $query->where('variance', '<', 0)),
            ])
            ->recordActions([
                Action::make('close')
                    ->label('Close shift')
                    ->icon('heroicon-m-lock-closed')
                    ->color('primary')
                    ->visible(fn (ShiftSession $record): bool => $record->isOpen() && self::mayClose($record))
                    ->modalHeading(fn (ShiftSession $record): string => 'Close ' . ($record->staff?->name ?? 'shift'))
                    ->modalDescription('Count the drawer, then enter what is physically there. Do not adjust it to match the system — the difference is the point.')
                    ->modalSubmitActionLabel('Close and record')
                    ->schema([
                        TextInput::make('counted_cash')
                            ->label('Cash counted in the drawer')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->prefix('₹')
                            // No default on purpose — see the class docblock.
                            ->helperText('Notes and coins actually in the drawer, including the opening float.'),
                        Textarea::make('note')
                            ->label('Anything worth noting')
                            ->rows(2)
                            ->maxLength(200)
                            ->placeholder('e.g. ₹200 paid out for water refill, receipt in the till'),
                    ])
                    ->action(function (ShiftSession $record, array $data, ShiftService $shifts): void {
                        $expected = $record->expectedCash();
                        $counted = (float) $data['counted_cash'];

                        $shifts->close($record, $counted, auth()->user(), $data['note'] ?? null);

                        $variance = round($counted - $expected, 2);

                        if (abs($variance) < 0.01) {
                            Notification::make()
                                ->success()
                                ->title('Shift closed · drawer is square')
                                ->body(self::inr($counted) . ' counted, ' . self::inr($expected) . ' expected.')
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->color($variance < 0 ? 'danger' : 'warning')
                            ->title(sprintf(
                                'Shift closed · %s %s',
                                self::inr(abs($variance)),
                                $variance < 0 ? 'short' : 'over',
                            ))
                            ->body(self::inr($counted) . ' counted against ' . self::inr($expected) . ' expected.')
                            ->persistent()
                            ->send();
                    }),

                Action::make('payments')
                    ->label('Cash trail')
                    ->icon('heroicon-m-list-bullet')
                    ->color('gray')
                    ->modalHeading('Every payment in this shift')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->visible(fn (ShiftSession $record): bool => $record->payments()->exists())
                    ->modalContent(fn (ShiftSession $record) => view('filament.venue.shift-trail', [
                        'shift' => $record,
                        'payments' => $record->payments()->with('booking')->orderBy('collected_at')->get(),
                    ])),
            ])
            ->emptyStateHeading('No shifts yet')
            ->emptyStateDescription('A shift opens by itself the first time someone takes cash at the desk. Close it at the end of the day to reconcile the drawer.')
            ->emptyStateIcon('heroicon-o-inbox-stack');
    }

    /**
     * Who may close a shift: the person on duty (their own drawer), or anyone with
     * the reports capability — an owner or manager closing up after someone left.
     */
    private static function mayClose(ShiftSession $record): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        return $record->user_id === $user->id || $user->hasPartnerPermission('reports');
    }

    private static function inr(float $n): string
    {
        $n = (int) round($n);
        $str = (string) abs($n);

        if (strlen($str) <= 3) {
            return '₹' . $str;
        }

        $last3 = substr($str, -3);
        $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', substr($str, 0, -3));

        return '₹' . $rest . ',' . $last3;
    }
}
