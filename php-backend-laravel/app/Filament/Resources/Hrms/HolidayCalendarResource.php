<?php

declare(strict_types=1);

namespace App\Filament\Resources\Hrms;

use App\Filament\Resources\Hrms\Pages\CreateHolidayCalendar;
use App\Filament\Resources\Hrms\Pages\EditHolidayCalendar;
use App\Filament\Resources\Hrms\Pages\ListHolidayCalendars;
use App\Models\Hrms\HolidayCalendar;
use App\Models\Venue;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class HolidayCalendarResource extends Resource
{
    protected static ?string $model = HolidayCalendar::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-sun';

    protected static string|\UnitEnum|null $navigationGroup = 'Workforce & HR';

    protected static ?string $navigationLabel = 'Holiday Calendar';

    protected static ?int $navigationSort = 11;

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'control'
            && (auth()->user()?->isSuperAdmin() || auth()->user()?->hasRoleEither(['OPS', 'FINANCE']));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('title')->required(),
            DatePicker::make('date')->required(),
            Select::make('applicable_venue_id')
                ->label('Specific Venue / Branch (Empty for all)')
                ->options(Venue::pluck('name', 'id'))
                ->searchable(),
            Toggle::make('is_optional')->label('Optional / Restricted Holiday')->default(false),
            TextInput::make('description'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('date')->date('D, M d, Y')->weight('bold')->sortable(),
                TextColumn::make('title')->weight('bold')->searchable(),
                TextColumn::make('venue.name')->label('Branch')->placeholder('Company-wide'),
                IconColumn::make('is_optional')->boolean()->label('Optional'),
                TextColumn::make('description')->limit(40),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHolidayCalendars::route('/'),
            'create' => CreateHolidayCalendar::route('/create'),
            'edit' => EditHolidayCalendar::route('/{record}/edit'),
        ];
    }
}
