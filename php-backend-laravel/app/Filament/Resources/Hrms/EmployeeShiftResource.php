<?php

declare(strict_types=1);

namespace App\Filament\Resources\Hrms;

use App\Filament\Resources\Hrms\Pages\CreateEmployeeShift;
use App\Filament\Resources\Hrms\Pages\EditEmployeeShift;
use App\Filament\Resources\Hrms\Pages\ListEmployeeShifts;
use App\Models\Hrms\EmployeeShift;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EmployeeShiftResource extends Resource
{
    protected static ?string $model = EmployeeShift::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static string|\UnitEnum|null $navigationGroup = 'Workforce & HR';

    protected static ?string $navigationLabel = 'Shift Definitions';

    protected static ?int $navigationSort = 4;

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'control'
            && (auth()->user()?->isSuperAdmin() || auth()->user()?->hasRoleEither(['OPS', 'FINANCE']));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('name')->required(),
            TextInput::make('code')->unique(ignoreRecord: true)->required(),
            TimePicker::make('start_time')->required(),
            TimePicker::make('end_time')->required(),
            TextInput::make('grace_period_minutes')->numeric()->default(15)->suffix('minutes'),
            Toggle::make('is_night_shift')->default(false),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->weight('bold')->searchable(),
                TextColumn::make('code')->fontFamily('mono'),
                TextColumn::make('formatted_timing')->label('Timings'),
                TextColumn::make('grace_period_minutes')->suffix('m grace'),
                IconColumn::make('is_night_shift')->boolean()->label('Night Shift'),
                IconColumn::make('is_active')->boolean(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployeeShifts::route('/'),
            'create' => CreateEmployeeShift::route('/create'),
            'edit' => EditEmployeeShift::route('/{record}/edit'),
        ];
    }
}
