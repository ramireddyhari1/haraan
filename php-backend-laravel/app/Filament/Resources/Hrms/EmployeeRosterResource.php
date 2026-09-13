<?php

declare(strict_types=1);

namespace App\Filament\Resources\Hrms;

use App\Filament\Resources\Hrms\Pages\CreateEmployeeRoster;
use App\Filament\Resources\Hrms\Pages\EditEmployeeRoster;
use App\Filament\Resources\Hrms\Pages\ListEmployeeRosters;
use App\Models\Hrms\EmployeeProfile;
use App\Models\Hrms\EmployeeShift;
use App\Models\Hrms\EmployeeShiftRoster;
use App\Models\Venue;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EmployeeRosterResource extends Resource
{
    protected static ?string $model = EmployeeShiftRoster::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|\UnitEnum|null $navigationGroup = 'Workforce & HR';

    protected static ?string $navigationLabel = 'Shift Rostering';

    protected static ?int $navigationSort = 5;

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'control'
            && (auth()->user()?->isSuperAdmin() || auth()->user()?->hasRoleEither(['OPS', 'FINANCE']));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('employee_profile_id')
                ->label('Employee')
                ->options(EmployeeProfile::with('user')->get()->pluck('full_name', 'id'))
                ->searchable()
                ->required(),
            Select::make('employee_shift_id')
                ->label('Shift')
                ->options(EmployeeShift::pluck('name', 'id'))
                ->searchable()
                ->required(),
            Select::make('venue_id')
                ->label('Workplace / Venue')
                ->options(Venue::pluck('name', 'id'))
                ->searchable(),
            DatePicker::make('roster_date')
                ->default(now())
                ->required(),
            Select::make('status')
                ->options([
                    'scheduled' => 'Scheduled',
                    'completed' => 'Completed',
                    'swapped' => 'Swapped',
                    'dropped' => 'Dropped',
                ])
                ->default('scheduled')
                ->required(),
            TextInput::make('notes')->placeholder('Supervisor notes...'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('roster_date')->date('D, M d, Y')->weight('bold')->sortable(),
                TextColumn::make('employee.full_name')->label('Employee')->searchable(),
                TextColumn::make('shift.name')->label('Shift')->badge()->color('info'),
                TextColumn::make('shift.formatted_timing')->label('Timings'),
                TextColumn::make('venue.name')->label('Venue / Branch')->placeholder('HQ'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'scheduled' => 'primary',
                        'completed' => 'success',
                        'swapped' => 'warning',
                        default => 'danger',
                    }),
            ])
            ->filters([
                SelectFilter::make('employee_shift_id')
                    ->label('Shift')
                    ->options(EmployeeShift::pluck('name', 'id')),
                SelectFilter::make('status')
                    ->options([
                        'scheduled' => 'Scheduled',
                        'completed' => 'Completed',
                        'swapped' => 'Swapped',
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployeeRosters::route('/'),
            'create' => CreateEmployeeRoster::route('/create'),
            'edit' => EditEmployeeRoster::route('/{record}/edit'),
        ];
    }
}
