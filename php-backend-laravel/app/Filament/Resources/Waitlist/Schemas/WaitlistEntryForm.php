<?php

declare(strict_types=1);

namespace App\Filament\Resources\Waitlist\Schemas;

use App\Filament\Resources\Venues\VenueResource;
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
 * Adding someone to the waitlist — typically while they are still on the phone.
 *
 * Both "any court" and "any time" default ON, because a caller who wanted 7pm
 * Saturday will almost always take 8pm on the other turf, and an entry that only
 * matches one exact court-hour will hardly ever fire.
 */
class WaitlistEntryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Who')
                ->columns(2)
                ->schema([
                    TextInput::make('guest_name')
                        ->label('Name')
                        ->required()
                        ->maxLength(120),
                    TextInput::make('guest_phone')
                        ->label('Phone')
                        ->tel()
                        ->required()
                        ->maxLength(20)
                        ->helperText('How you will reach them when the slot frees up.'),
                ]),

            Section::make('What they want')
                ->columns(2)
                ->schema([
                    Select::make('venue_id')
                        ->label('Venue')
                        ->options(fn (): array => VenueResource::getEloquentQuery()
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->required()
                        ->native(false)
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(fn (callable $set) => $set('venue_court_id', null)),

                    DatePicker::make('wanted_on')
                        ->label('Date')
                        ->required()
                        ->minDate(today())
                        ->default(today()),

                    Toggle::make('any_court')
                        ->label('Any court')
                        ->default(true)
                        ->dehydrated(false)
                        ->live()
                        ->afterStateHydrated(fn (Toggle $c, $record) => $c->state(
                            $record === null ? true : $record->venue_court_id === null,
                        ))
                        ->afterStateUpdated(function (bool $state, callable $set): void {
                            if ($state) {
                                $set('venue_court_id', null);
                            }
                        })
                        ->helperText('Most callers will take whichever court is free.'),

                    Select::make('venue_court_id')
                        ->label('Court')
                        ->options(fn (Get $get): array => filled($get('venue_id'))
                            ? VenueCourt::query()
                                ->where('venue_id', $get('venue_id'))
                                ->orderBy('sort_order')
                                ->pluck('name', 'id')
                                ->all()
                            : [])
                        ->native(false)
                        ->visible(fn (Get $get): bool => ! $get('any_court'))
                        ->requiredIf('any_court', false),

                    Toggle::make('any_time')
                        ->label('Any time that day')
                        ->default(false)
                        ->dehydrated(false)
                        ->live()
                        ->afterStateHydrated(fn (Toggle $c, $record) => $c->state(
                            $record === null ? false : $record->start_time === null,
                        ))
                        ->afterStateUpdated(function (bool $state, callable $set): void {
                            if ($state) {
                                $set('start_time', null);
                                $set('end_time', null);
                            }
                        }),

                    TimePicker::make('start_time')
                        ->label('From')
                        ->seconds(false)
                        ->format('H:i')
                        ->displayFormat('H:i')
                        ->visible(fn (Get $get): bool => ! $get('any_time'))
                        ->requiredIf('any_time', false),

                    TimePicker::make('end_time')
                        ->label('Until')
                        ->seconds(false)
                        ->format('H:i')
                        ->displayFormat('H:i')
                        ->visible(fn (Get $get): bool => ! $get('any_time'))
                        ->after('start_time'),

                    TextInput::make('note')
                        ->label('Note')
                        ->maxLength(200)
                        ->columnSpanFull()
                        ->placeholder('e.g. group of 10, will pay on UPI'),
                ]),
        ]);
    }
}
