<?php

namespace App\Filament\Resources\AutomationRules\Tables;

use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class AutomationRulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Same order the matcher uses, so the table reads as the running order.
            ->defaultSort('priority')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('trigger_type')
                    ->label('Trigger')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'comment' => 'Comment → DM',
                        'fallback' => 'Fallback',
                        default => 'Keyword',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'comment' => 'success',
                        'fallback' => 'warning',
                        default => 'info',
                    }),

                TextColumn::make('keywords')
                    ->label('Keywords')
                    ->formatStateUsing(fn ($state): string => is_array($state) ? implode(', ', $state) : (string) $state)
                    ->placeholder('—')
                    ->wrap()
                    ->limit(60),

                TextColumn::make('reply_body')
                    ->label('Reply')
                    ->limit(50)
                    ->tooltip(fn ($record): string => (string) $record->reply_body)
                    ->color('gray'),

                TextColumn::make('partner.name')
                    ->label('Applies to')
                    ->placeholder('All partners')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('priority')
                    ->sortable()
                    ->alignRight(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('trigger_type')
                    ->label('Trigger')
                    ->options(['keyword' => 'Keyword', 'fallback' => 'Fallback', 'comment' => 'Comment → DM']),

                TernaryFilter::make('is_active')->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No auto-replies yet')
            ->emptyStateDescription('Inbound messages are recorded either way — without a rule, nobody gets an automatic answer.');
    }
}
