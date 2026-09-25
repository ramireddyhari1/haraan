<?php

declare(strict_types=1);

namespace App\Filament\Resources\Venues\RelationManagers;

use App\Models\AdminAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Everything that has been done to this venue from a console.
 *
 * Rows arrive two ways and both are real: {@see \App\Models\Concerns\AuditsAdminChanges}
 * writes one automatically whenever an audited column moves — rate, booking fee,
 * on-sale switches, ownership, cancellation terms — and the actions on this page
 * write one explicitly for things that are not a column change, like blocking
 * time or notifying the owner.
 *
 * Audit entries are append-only at the model level, so there is nothing to edit
 * or delete here and no action offering to.
 */
class VenueAuditRelationManager extends RelationManager
{
    protected static string $relationship = 'auditTrail';

    protected static ?string $title = 'Audit log';

    protected static string | \BackedEnum | null $icon = 'heroicon-o-clipboard-document-list';

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $count = $ownerRecord->auditTrail()->count();

        return $count > 0 ? (string) $count : null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('user'))
            ->emptyStateHeading('Nothing logged yet')
            ->emptyStateDescription('Rate, fee, ownership and on-sale changes are recorded here the moment they are saved.')
            ->emptyStateIcon('heroicon-o-clipboard-document-list')
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('d M Y, H:i:s')
                    ->description(fn (AdminAction $record): string => $record->created_at?->diffForHumans() ?? '')
                    ->sortable(),

                TextColumn::make('action')
                    ->label('Action')
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        str_contains($state, 'deleted'), str_contains($state, 'deactivated'), str_contains($state, 'blocked') => 'danger',
                        str_contains($state, 'created'), str_contains($state, 'activated') => 'success',
                        str_contains($state, 'pricing'), str_contains($state, 'price'), str_contains($state, 'fee') => 'warning',
                        default => 'info',
                    })
                    ->searchable(),

                TextColumn::make('user.name')
                    ->label('Operator')
                    ->placeholder('System')
                    ->description(fn (AdminAction $record): ?string => $record->user?->email),

                TextColumn::make('meta')
                    ->label('Detail')
                    ->state(fn (AdminAction $record): string => $record->summary())
                    ->wrap()
                    ->placeholder('—'),

                TextColumn::make('ip')
                    ->label('IP')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->label('Action')
                    ->options(fn (): array => AdminAction::query()
                        ->where('subject_type', 'Venue')
                        ->where('subject_id', $this->getOwnerRecord()->getKey())
                        ->distinct()
                        ->orderBy('action')
                        ->pluck('action', 'action')
                        ->all()),
            ]);
    }
}
