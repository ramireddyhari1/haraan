<?php

declare(strict_types=1);

namespace App\Filament\Resources\Shifts\Pages;

use App\Filament\Resources\Shifts\ShiftSessionResource;
use App\Filament\Resources\Venues\VenueResource;
use App\Services\ShiftService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListShiftSessions extends ListRecords
{
    protected static string $resource = ShiftSessionResource::class;

    /**
     * Opening a shift by hand is the exception, not the rule — a shift opens
     * itself on the first cash taken. This exists for the one case that matters:
     * starting the day with a known float in the drawer, so the close-out has
     * something honest to reconcile against.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('openShift')
                ->label('Open a shift')
                ->icon('heroicon-m-lock-open')
                ->modalHeading('Open a shift with a starting float')
                ->modalDescription('Only needed if you are putting change in the drawer before trading. Otherwise the shift opens itself when the first cash is taken.')
                ->modalSubmitActionLabel('Open shift')
                ->schema([
                    Select::make('venue_id')
                        ->label('Venue')
                        ->options(fn (): array => VenueResource::getEloquentQuery()
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->required()
                        ->native(false)
                        ->searchable(),
                    TextInput::make('opening_float')
                        ->label('Cash going into the drawer')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->prefix('₹')
                        ->required(),
                ])
                ->action(function (array $data, ShiftService $shifts): void {
                    $shift = $shifts->open(
                        auth()->user(),
                        (int) $data['venue_id'],
                        (float) $data['opening_float'],
                        auth()->user(),
                    );

                    Notification::make()
                        ->success()
                        ->title($shift->wasRecentlyCreated ? 'Shift opened' : 'You already had a shift open')
                        ->body($shift->wasRecentlyCreated
                            ? 'Cash you take from now on is counted against this drawer.'
                            : 'Reusing it rather than opening a second drawer.')
                        ->send();
                }),
        ];
    }
}
