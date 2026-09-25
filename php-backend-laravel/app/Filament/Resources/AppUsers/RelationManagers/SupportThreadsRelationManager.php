<?php

declare(strict_types=1);

namespace App\Filament\Resources\AppUsers\RelationManagers;

use App\Models\SupportThread;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SupportThreadsRelationManager extends RelationManager
{
    protected static string $relationship = 'supportThreads';

    protected static ?string $title = 'Support Threads';

    protected static string | \BackedEnum | null $icon = 'heroicon-o-chat-bubble-left-right';

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function getBadge(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): ?string
    {
        /** @var \App\Models\User $ownerRecord */
        $count = $ownerRecord->supportThreads()->count();

        return $count > 0 ? (string) $count : null;
    }

    public function table(Table $table): Table
    {
        /** @var User $user */
        $user = $this->getOwnerRecord();

        return $table
            ->defaultSort('last_message_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['category', 'assignee']))
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),

                TextColumn::make('subject')
                    ->label('Subject / Topic')
                    ->weight('bold')
                    ->placeholder('General inquiry')
                    ->searchable(),

                TextColumn::make('category.label')
                    ->label('Category')
                    ->badge()
                    ->color('info')
                    ->placeholder('Unsorted'),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'open' => 'danger',
                        'pending' => 'warning',
                        'closed' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('admin_unread_count')
                    ->label('Unread')
                    ->badge()
                    ->color(fn (?int $state): string => ($state ?? 0) > 0 ? 'danger' : 'gray'),

                TextColumn::make('assignee.name')
                    ->label('Assignee')
                    ->placeholder('Unassigned'),

                TextColumn::make('last_message_at')
                    ->label('Last activity')
                    ->since()
                    ->sortable(),
            ])
            ->headerActions([
                Action::make('openInSupport')
                    ->label('View in Support desk')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (): string => route('filament.control.resources.support-threads.index', [
                        'tableFilters' => ['user_id' => ['value' => $user->id]],
                    ]))
                    ->openUrlInNewTab(),
            ]);
    }
}
