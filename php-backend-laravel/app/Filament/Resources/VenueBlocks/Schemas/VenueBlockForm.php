<?php

declare(strict_types=1);

namespace App\Filament\Resources\VenueBlocks\Schemas;

use App\Filament\Resources\Venues\VenueResource;
use App\Models\VenueBlock;
use App\Models\VenueCourt;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * The block editor.
 *
 * Two switches carry the whole model and are worth naming plainly, because the
 * underlying nulls are not obvious: leaving the court empty blocks **every**
 * court, and "all day" clears the time window rather than storing 00:00–23:59.
 * Both are stated in helper text rather than left for the partner to discover by
 * blocking their entire venue on a Saturday.
 */
class VenueBlockForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('What is being blocked')
                ->columns(2)
                ->schema([
                    Select::make('venue_id')
                        ->label('Venue')
                        ->options(fn (): array => VenueResource::getEloquentQuery()
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live()
                        // Changing venue invalidates any court already picked.
                        ->afterStateUpdated(fn (callable $set) => $set('venue_court_id', null)),

                    Select::make('venue_court_id')
                        ->label('Court')
                        ->options(fn (Get $get): array => filled($get('venue_id'))
                            ? VenueCourt::query()
                                ->where('venue_id', $get('venue_id'))
                                ->orderBy('sort_order')
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all()
                            : [])
                        ->searchable()
                        ->placeholder('Every court at this venue')
                        ->helperText('Leave empty to close the whole venue.'),

                    Select::make('kind')
                        ->label('Reason')
                        ->options(VenueBlock::KINDS)
                        ->default('maintenance')
                        ->native(false)
                        ->required()
                        ->live(),

                    TextInput::make('title')
                        ->label('Label')
                        ->maxLength(120)
                        ->placeholder(fn (Get $get): string => match ($get('kind')) {
                            'academy' => 'U-14 batch',
                            'tournament' => 'Corporate League · QF',
                            'holiday' => 'Independence Day',
                            'private' => 'Private hire',
                            default => 'Surface re-lay',
                        })
                        ->helperText('Shown on the calendar and in the message a customer sees when the slot is unavailable.'),
                ]),

            Section::make('When')
                ->columns(2)
                ->schema([
                    DatePicker::make('starts_on')
                        ->label('From')
                        ->required()
                        ->default(today())
                        ->live(),

                    DatePicker::make('ends_on')
                        ->label('Until')
                        ->required()
                        ->default(today())
                        ->afterOrEqual('starts_on')
                        ->helperText('Same day for a one-off.'),

                    Select::make('weekday')
                        ->label('Repeat on')
                        ->options([
                            0 => 'Sundays', 1 => 'Mondays', 2 => 'Tuesdays', 3 => 'Wednesdays',
                            4 => 'Thursdays', 5 => 'Fridays', 6 => 'Saturdays',
                        ])
                        ->native(false)
                        ->placeholder('Every day in the range')
                        ->helperText('For a weekly batch — pick the weekday and set a long date range.'),

                    Toggle::make('all_day')
                        ->label('All day')
                        ->default(false)
                        ->dehydrated(false)
                        ->live()
                        // Hydrate from the stored nulls so editing an all-day block
                        // shows the toggle already on.
                        ->afterStateHydrated(fn (Toggle $component, ?VenueBlock $record) => $component->state(
                            $record === null ? false : $record->isAllDay(),
                        ))
                        ->afterStateUpdated(function (bool $state, callable $set): void {
                            if ($state) {
                                $set('start_time', null);
                                $set('end_time', null);
                            }
                        })
                        ->helperText('Takes the court off sale for the whole day.'),

                    TimePicker::make('start_time')
                        ->label('From time')
                        ->seconds(false)
                        ->displayFormat('H:i')
                        ->format('H:i')
                        ->visible(fn (Get $get): bool => ! $get('all_day'))
                        ->requiredIf('all_day', false),

                    TimePicker::make('end_time')
                        ->label('Until time')
                        ->seconds(false)
                        ->displayFormat('H:i')
                        ->format('H:i')
                        ->visible(fn (Get $get): bool => ! $get('all_day'))
                        ->requiredIf('all_day', false)
                        ->after('start_time'),
                ]),
        ]);
    }
}
