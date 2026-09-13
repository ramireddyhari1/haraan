<?php

declare(strict_types=1);

namespace App\Filament\Resources\Hrms;

use App\Filament\Resources\Hrms\Pages\CreateEmployeeTask;
use App\Filament\Resources\Hrms\Pages\EditEmployeeTask;
use App\Filament\Resources\Hrms\Pages\ListEmployeeTasks;
use App\Models\Hrms\EmployeeProfile;
use App\Models\Hrms\EmployeeTask;
use App\Models\User;
use App\Models\Venue;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EmployeeTaskResource extends Resource
{
    protected static ?string $model = EmployeeTask::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static string|\UnitEnum|null $navigationGroup = 'Workforce & HR';

    protected static ?string $navigationLabel = 'Tasks & Assignments';

    protected static ?int $navigationSort = 9;

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'control'
            && (auth()->user()?->isSuperAdmin() || auth()->user()?->hasRoleEither(['OPS', 'FINANCE']));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('title')->required(),
            Select::make('employee_profile_id')
                ->label('Assigned Employee')
                ->options(EmployeeProfile::with('user')->get()->pluck('full_name', 'id'))
                ->searchable()
                ->required(),
            Select::make('assigned_by')
                ->label('Assigned By')
                ->options(User::pluck('name', 'id'))
                ->default(fn () => auth()->id())
                ->required(),
            Select::make('venue_id')
                ->label('Venue / Branch')
                ->options(Venue::pluck('name', 'id')),
            Select::make('priority')
                ->options([
                    'low' => 'Low',
                    'medium' => 'Medium',
                    'high' => 'High',
                    'urgent' => 'Urgent',
                ])
                ->default('medium')
                ->required(),
            Select::make('status')
                ->options([
                    'todo' => 'To Do',
                    'in_progress' => 'In Progress',
                    'completed' => 'Completed',
                    'cancelled' => 'Cancelled',
                ])
                ->default('todo')
                ->required(),
            DatePicker::make('due_date')->default(now()->addDays(2)),
            Textarea::make('description')->rows(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->weight('bold')->searchable(),
                TextColumn::make('employee.full_name')->label('Assignee')->searchable(),
                TextColumn::make('priority')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'urgent' => 'danger',
                        'high' => 'warning',
                        'medium' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'in_progress' => 'primary',
                        default => 'gray',
                    }),
                TextColumn::make('due_date')->date('M d, Y'),
                TextColumn::make('assigner.name')->label('Assigned By'),
            ])
            ->filters([
                SelectFilter::make('priority')
                    ->options(['urgent' => 'Urgent', 'high' => 'High', 'medium' => 'Medium', 'low' => 'Low']),
                SelectFilter::make('status')
                    ->options(['todo' => 'To Do', 'in_progress' => 'In Progress', 'completed' => 'Completed']),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployeeTasks::route('/'),
            'create' => CreateEmployeeTask::route('/create'),
            'edit' => EditEmployeeTask::route('/{record}/edit'),
        ];
    }
}
