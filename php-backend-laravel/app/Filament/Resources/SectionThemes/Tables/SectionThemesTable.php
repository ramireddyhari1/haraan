<?php

declare(strict_types=1);

namespace App\Filament\Resources\SectionThemes\Tables;

use App\Models\SectionTheme;
use App\Support\SectionThemeJson;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SectionThemesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ColorColumn::make('accent_deep')
                    ->label('Header')
                    ->state(fn (SectionTheme $record): ?string => $record->accent_deep ?: $record->accent_primary),
                ColorColumn::make('accent_primary')->label('Accent'),
                TextColumn::make('campaign_name')
                    ->label('Campaign')
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('section')
                    ->label('Lane')
                    ->formatStateUsing(fn (string $state): string => SectionTheme::SECTIONS[$state] ?? $state)
                    ->badge(),
                TextColumn::make('decoration')
                    ->label('Decoration')
                    ->state(fn (SectionTheme $record): string => match (true) {
                        $record->decorationUrl() === null => 'None',
                        $record->decorationType() === 'lottie' => 'Lottie',
                        default => 'Image',
                    })
                    ->color('gray'),
                TextColumn::make('status')
                    ->state(fn (SectionTheme $record): string => match (true) {
                        ! $record->is_active => 'Unpublished',
                        $record->ends_at->isPast() => 'Ended',
                        $record->starts_at->isFuture() => 'Scheduled',
                        default => 'Live',
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Live' => 'success',
                        'Scheduled' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('starts_at')
                    ->label('Starts')
                    ->dateTime('d M Y, h:i A', 'Asia/Kolkata')
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->label('Ends')
                    ->dateTime('d M Y, h:i A', 'Asia/Kolkata')
                    ->sortable(),
                TextColumn::make('priority')->numeric()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('starts_at', 'desc')
            ->filters([
                SelectFilter::make('section')->label('Lane')->options(SectionTheme::SECTIONS),
            ])
            ->recordActions([
                EditAction::make(),
                self::exportAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No campaign themes yet')
            ->emptyStateDescription('With none live, Events and Pulse keep their normal look. Create one, or import a theme JSON file.');
    }

    /** Download the theme in the portable JSON format, ready to re-import or hand to a designer. */
    public static function exportAction(): Action
    {
        return Action::make('exportJson')
            ->label('Export JSON')
            ->icon('heroicon-m-arrow-down-tray')
            ->color('gray')
            ->action(fn (SectionTheme $record): StreamedResponse => response()->streamDownload(
                function () use ($record): void {
                    echo json_encode(SectionThemeJson::export($record), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                },
                'theme-'.$record->section.'-'.Str::slug($record->campaign_name).'.json',
                ['Content-Type' => 'application/json'],
            ));
    }
}
