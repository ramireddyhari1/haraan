<?php

declare(strict_types=1);

namespace App\Filament\Resources\Hrms;

use App\Filament\Resources\Hrms\Pages\CreateEmployee;
use App\Filament\Resources\Hrms\Pages\EditEmployee;
use App\Filament\Resources\Hrms\Pages\ListEmployees;
use App\Models\Hrms\Department;
use App\Models\Hrms\Designation;
use App\Models\Hrms\EmployeeProfile;
use App\Models\User;
use App\Models\Venue;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EmployeeResource extends Resource
{
    protected static ?string $model = EmployeeProfile::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|\UnitEnum|null $navigationGroup = 'Workforce & HR';

    protected static ?string $navigationLabel = 'Employees & Staff';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'control'
            && (auth()->user()?->isSuperAdmin() || auth()->user()?->hasRoleEither(['OPS', 'FINANCE']));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('user_id')
                ->label('User Account')
                ->options(User::pluck('name', 'id'))
                ->searchable()
                ->required(),
            TextInput::make('employee_code')
                ->label('Employee Code')
                ->default(fn () => 'HRN-EMP-' . rand(1000, 9999))
                ->required(),
            Select::make('department_id')
                ->label('Department')
                ->options(Department::pluck('name', 'id'))
                ->searchable(),
            Select::make('designation_id')
                ->label('Designation')
                ->options(Designation::pluck('name', 'id'))
                ->searchable(),
            Select::make('venue_id')
                ->label('Primary Branch / Venue')
                ->options(Venue::pluck('name', 'id'))
                ->searchable(),
            Select::make('employment_status')
                ->options([
                    'active' => 'Active',
                    'on_leave' => 'On Leave',
                    'probation' => 'Probation',
                    'terminated' => 'Terminated',
                ])
                ->default('active')
                ->required(),
            TextInput::make('base_salary')
                ->numeric()
                ->prefix('₹')
                ->default(35000),
            TextInput::make('geofence_radius_meters')
                ->numeric()
                ->suffix('meters')
                ->default(200),
            DatePicker::make('joining_date')
                ->default(now()),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee_code')->fontFamily('mono')->weight('bold')->searchable(),
                TextColumn::make('user.name')->label('Name')->searchable()->sortable(),
                TextColumn::make('department.name')->label('Department')->badge()->color('gray'),
                TextColumn::make('designation.name')->label('Designation'),
                TextColumn::make('venue.name')->label('Branch / Venue')->placeholder('HQ'),
                TextColumn::make('employment_status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'on_leave' => 'warning',
                        default => 'danger',
                    }),
                TextColumn::make('base_salary')->money('INR')->sortable(),
                TextColumn::make('joining_date')->date(),
            ])
            ->filters([
                SelectFilter::make('employment_status')
                    ->options([
                        'active' => 'Active',
                        'on_leave' => 'On Leave',
                        'probation' => 'Probation',
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployees::route('/'),
            'create' => CreateEmployee::route('/create'),
            'edit' => EditEmployee::route('/{record}/edit'),
        ];
    }
}
