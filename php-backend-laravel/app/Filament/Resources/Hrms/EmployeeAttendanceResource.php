<?php

declare(strict_types=1);

namespace App\Filament\Resources\Hrms;

use App\Filament\Resources\Hrms\Pages\EditEmployeeAttendance;
use App\Filament\Resources\Hrms\Pages\ListEmployeeAttendances;
use App\Models\Hrms\EmployeeAttendance;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EmployeeAttendanceResource extends Resource
{
    protected static ?string $model = EmployeeAttendance::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-check-badge';

    protected static string|\UnitEnum|null $navigationGroup = 'Workforce & HR';

    protected static ?string $navigationLabel = 'Attendance Logs';

    protected static ?int $navigationSort = 6;

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'control'
            && (auth()->user()?->isSuperAdmin() || auth()->user()?->hasRoleEither(['OPS', 'FINANCE']));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            DatePicker::make('date')->required(),
            DateTimePicker::make('clock_in_at'),
            DateTimePicker::make('clock_out_at'),
            TextInput::make('total_work_minutes')->numeric(),
            TextInput::make('total_break_minutes')->numeric(),
            Select::make('status')
                ->options([
                    'present' => 'Present',
                    'late' => 'Late',
                    'half_day' => 'Half Day',
                    'on_leave' => 'On Leave',
                    'absent' => 'Absent',
                ])
                ->required(),
            Select::make('clock_in_geofence_status')
                ->options([
                    'inside' => 'Inside Geofence',
                    'outside' => 'Outside Geofence',
                    'exempt' => 'Exempt',
                ]),
            TextInput::make('admin_notes'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('date')->date('D, M d, Y')->weight('bold')->sortable(),
                TextColumn::make('employee.full_name')->label('Employee')->searchable(),
                TextColumn::make('shift.name')->label('Shift')->placeholder('Standard'),
                TextColumn::make('clock_in_at')->dateTime('h:i A')->fontFamily('mono'),
                TextColumn::make('clock_out_at')->dateTime('h:i A')->fontFamily('mono')->placeholder('On Duty'),
                TextColumn::make('formatted_work_duration')->label('Worked'),
                TextColumn::make('clock_in_geofence_status')
                    ->label('Geofence')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'inside' => 'success',
                        'outside' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'present' => 'success',
                        'late' => 'warning',
                        'half_day' => 'info',
                        'on_leave' => 'purple',
                        default => 'danger',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'present' => 'Present',
                        'late' => 'Late',
                        'half_day' => 'Half Day',
                        'on_leave' => 'On Leave',
                        'absent' => 'Absent',
                    ]),
                SelectFilter::make('clock_in_geofence_status')
                    ->options([
                        'inside' => 'Inside',
                        'outside' => 'Outside',
                        'exempt' => 'Exempt',
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployeeAttendances::route('/'),
            'edit' => EditEmployeeAttendance::route('/{record}/edit'),
        ];
    }
}
