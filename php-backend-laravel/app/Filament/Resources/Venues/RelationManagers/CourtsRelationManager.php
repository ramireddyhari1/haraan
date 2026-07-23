<?php

namespace App\Filament\Resources\Venues\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Courts are the physical bookable units inside a venue. Each court lists the sports it can
 * host, so ONE ground shared by football and cricket is a SINGLE court with both sports —
 * booking it for one sport blocks the other for that time. Three separate playing areas are
 * three courts. Per-court price overrides the venue base price when set.
 */
class CourtsRelationManager extends RelationManager
{
    protected static string $relationship = 'courts';

    protected static ?string $title = 'Courts / pitches / lanes';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Court 1, Pitch A, Turf East…')
                    ->helperText('The physical unit. If one ground is used for several sports, make ONE court and tick every sport below.'),
                Select::make('sports')
                    ->label('Sports this court hosts')
                    ->multiple()
                    ->options([
                        'Cricket' => 'Cricket',
                        'Football' => 'Football',
                        'Badminton' => 'Badminton',
                        'Basketball' => 'Basketball',
                        'Tennis' => 'Tennis',
                        'Volleyball' => 'Volleyball',
                    ])
                    ->helperText('Ticking two sports means the SAME court hosts both — a booking for one blocks the other at that time. Leave empty to allow every sport the venue offers.'),
                TextInput::make('price')
                    ->label('Base price per hour')
                    ->numeric()
                    ->prefix('₹')
                    ->placeholder('Leave blank to use the venue price')
                    ->helperText('Off-peak / normal hourly rate (e.g. a cricket pitch may cost more than a badminton court).'),
                TextInput::make('peak_price')
                    ->label('Peak price per hour')
                    ->numeric()
                    ->prefix('₹')
                    ->placeholder('Leave blank for no peak pricing')
                    ->helperText('Higher rate for busy times. Only applies when you set the days and/or window below.'),
                Select::make('peak_days')
                    ->label('Peak days')
                    ->multiple()
                    ->options([
                        'Mon' => 'Monday', 'Tue' => 'Tuesday', 'Wed' => 'Wednesday',
                        'Thu' => 'Thursday', 'Fri' => 'Friday', 'Sat' => 'Saturday', 'Sun' => 'Sunday',
                    ])
                    ->helperText('Leave empty to apply peak pricing every day (within the window below).'),
                TimePicker::make('peak_start')
                    ->label('Peak from')
                    ->seconds(false)
                    ->format('H:i')
                    ->displayFormat('h:i A')
                    ->helperText('e.g. 6:00 PM. Leave both blank to apply all day on the peak days.'),
                TimePicker::make('peak_end')
                    ->label('Peak until')
                    ->seconds(false)
                    ->format('H:i')
                    ->displayFormat('h:i A'),
                TextInput::make('sort_order')
                    ->numeric()
                    ->default(0),
                Toggle::make('is_active')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('sports')
                    ->label('Sports')
                    ->badge()
                    ->placeholder('All venue sports'),
                TextColumn::make('price')
                    ->label('₹/hr')
                    ->placeholder('Venue price')
                    ->money('inr', divideBy: 1),
                TextColumn::make('peak_price')
                    ->label('Peak ₹/hr')
                    ->placeholder('—')
                    ->money('inr', divideBy: 1),
                IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
