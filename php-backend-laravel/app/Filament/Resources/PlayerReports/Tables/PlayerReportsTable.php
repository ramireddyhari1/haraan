<?php

namespace App\Filament\Resources\PlayerReports\Tables;

use App\Filament\Support\AvatarColumn;
use App\Models\PlayerReport;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PlayerReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Oldest OPEN first: a queue sorted newest-first quietly buries the reports
            // that have been waiting longest, which is the opposite of a queue's job.
            ->defaultSort('created_at', 'asc')
            ->columns([
                AvatarColumn::make(
                    'avatar',
                    nameFor: fn (PlayerReport $r): string => (string) ($r->reported?->name ?: 'Player'),
                    avatarFor: fn (PlayerReport $r): ?string => $r->reported?->avatar,
                ),
                TextColumn::make('reported.name')
                    ->label('Reported player')
                    ->weight('bold')
                    ->description(fn (PlayerReport $r): string => (string) ($r->reported?->player_id ?: '—'))
                    ->searchable(),
                TextColumn::make('reason')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'harassment', 'inappropriate' => 'danger',
                        'cheating', 'fake_profile' => 'warning',
                        default => 'gray',
                    })
                    // Machine keys are stored; a human reads this column.
                    ->formatStateUsing(fn (string $state): string => ucfirst(str_replace('_', ' ', $state))),
                TextColumn::make('details')
                    ->label('What they said')
                    ->limit(60)
                    ->placeholder('No detail given')
                    ->wrap(),
                TextColumn::make('reporter.name')
                    ->label('Reported by')
                    ->description(fn (PlayerReport $r): string => (string) ($r->reporter?->player_id ?: '—'))
                    ->searchable(),
                // The number that turns one complaint into a pattern.
                TextColumn::make('total_on_player')
                    ->label('Total on player')
                    ->badge()
                    ->sortable()
                    ->color(fn ($state): string => ((int) $state) > 1 ? 'danger' : 'gray')
                    ->formatStateUsing(fn ($state): string => (string) ((int) $state)),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'open' => 'danger',
                        'actioned' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->label('Reported')
                    ->since()
                    ->sortable(),
                TextColumn::make('reviewer.name')
                    ->label('Decided by')
                    ->placeholder('—')
                    ->description(fn (PlayerReport $r): ?string => $r->reviewed_at?->diffForHumans())
                    ->toggleable(),
            ])
            ->filters([
                Filter::make('reported_id')
                    ->form([
                        TextInput::make('value')->label('Reported user ID')->numeric(),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->where('reported_id', $data['value'])
                        : $query),

                SelectFilter::make('status')
                    ->options([
                        'open' => 'Open',
                        'actioned' => 'Actioned',
                        'dismissed' => 'Dismissed',
                    ])
                    ->default('open'),
                SelectFilter::make('reason')->options(
                    collect(PlayerReport::REASONS)
                        ->mapWithKeys(fn (string $r): array => [$r => ucfirst(str_replace('_', ' ', $r))])
                        ->all()
                ),
            ])
            ->recordActions([
                // Both outcomes record WHO decided and WHEN. A queue that can be cleared
                // anonymously is a queue nobody can audit.
                Action::make('actioned')
                    ->label('Mark actioned')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (PlayerReport $r): bool => $r->status === 'open')
                    ->requiresConfirmation()
                    ->modalDescription('Record that you have acted on this report. This does not itself change the player\'s account.')
                    ->action(function (PlayerReport $r): void {
                        $r->update([
                            'status' => 'actioned',
                            'reviewed_at' => now(),
                            'reviewed_by' => auth()->id(),
                        ]);
                        Notification::make()->success()->title('Marked actioned')->send();
                    }),
                Action::make('dismiss')
                    ->label('Dismiss')
                    ->icon('heroicon-o-x-circle')
                    ->color('gray')
                    ->visible(fn (PlayerReport $r): bool => $r->status === 'open')
                    ->requiresConfirmation()
                    ->action(function (PlayerReport $r): void {
                        $r->update([
                            'status' => 'dismissed',
                            'reviewed_at' => now(),
                            'reviewed_by' => auth()->id(),
                        ]);
                        Notification::make()->success()->title('Dismissed')->send();
                    }),
                Action::make('reopen')
                    ->label('Reopen')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->visible(fn (PlayerReport $r): bool => $r->status !== 'open')
                    ->action(function (PlayerReport $r): void {
                        $r->update(['status' => 'open', 'reviewed_at' => null, 'reviewed_by' => null]);
                        Notification::make()->success()->title('Reopened')->send();
                    }),
            ])
            ->emptyStateHeading('Nothing reported')
            ->emptyStateDescription('Reports players file from the app land here.');
    }
}
