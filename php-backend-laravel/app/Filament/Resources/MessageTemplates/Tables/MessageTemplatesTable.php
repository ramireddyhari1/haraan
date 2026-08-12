<?php

namespace App\Filament\Resources\MessageTemplates\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MessageTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->weight('bold')->searchable(),

                TextColumn::make('key')->color('gray')->searchable(),

                TextColumn::make('category')->badge()->color('gray'),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'submitted' => 'warning',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),

                // The whole point of the screen: approved without a SID still can't send.
                TextColumn::make('provider_template_id')
                    ->label('Meta template name')
                    ->placeholder('not set')
                    ->color(fn ($record): string => filled($record->provider_template_id) ? 'gray' : 'danger')
                    ->limit(20),

                IconColumn::make('sendable')
                    ->label('Can send cold')
                    ->state(fn ($record): bool => $record->status === 'approved' && filled($record->provider_template_id))
                    ->boolean()
                    ->tooltip('Whether this can reach someone outside the 24-hour window.'),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'draft' => 'Draft', 'submitted' => 'Submitted',
                    'approved' => 'Approved', 'rejected' => 'Rejected',
                ]),
            ])
            ->recordActions([EditAction::make()])
            ->emptyStateHeading('No templates registered')
            ->emptyStateDescription('Run the MessageTemplateSeeder to register the templates the code sends.');
    }
}
