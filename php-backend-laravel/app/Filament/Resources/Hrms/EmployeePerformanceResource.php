<?php

declare(strict_types=1);

namespace App\Filament\Resources\Hrms;

use App\Filament\Resources\Hrms\Pages\CreateEmployeePerformance;
use App\Filament\Resources\Hrms\Pages\EditEmployeePerformance;
use App\Filament\Resources\Hrms\Pages\ListEmployeePerformances;
use App\Models\Hrms\EmployeeKpi;
use App\Models\Hrms\EmployeeProfile;
use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EmployeePerformanceResource extends Resource
{
    protected static ?string $model = EmployeeKpi::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static string|\UnitEnum|null $navigationGroup = 'Workforce & HR';

    protected static ?string $navigationLabel = 'KPIs & Appraisals';

    protected static ?int $navigationSort = 10;

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
            TextInput::make('period')
                ->label('Appraisal Period (YYYY-MM)')
                ->default(now()->format('Y-m'))
                ->required(),
            Select::make('reviewer_id')
                ->label('Reviewer')
                ->options(User::pluck('name', 'id'))
                ->default(fn () => auth()->id())
                ->required(),
            TextInput::make('punctuality_rating')->numeric()->minValue(1)->maxValue(5)->default(5.0)->required(),
            TextInput::make('task_completion_rating')->numeric()->minValue(1)->maxValue(5)->default(5.0)->required(),
            TextInput::make('customer_service_rating')->numeric()->minValue(1)->maxValue(5)->default(5.0)->required(),
            TextInput::make('teamwork_rating')->numeric()->minValue(1)->maxValue(5)->default(5.0)->required(),
            TextInput::make('overall_score')->numeric()->minValue(1)->maxValue(5)->default(5.0)->required(),
            Textarea::make('manager_feedback')->rows(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('period')->weight('bold'),
                TextColumn::make('employee.full_name')->label('Employee')->searchable(),
                TextColumn::make('overall_score')
                    ->label('Score')
                    ->badge()
                    ->color(fn ($state) => (float)$state >= 4.0 ? 'success' : ((float)$state >= 3.0 ? 'warning' : 'danger'))
                    ->formatStateUsing(fn ($state) => number_format((float)$state, 1) . ' ★'),
                TextColumn::make('punctuality_rating')->label('Punctuality'),
                TextColumn::make('task_completion_rating')->label('Execution'),
                TextColumn::make('customer_service_rating')->label('Service'),
                TextColumn::make('teamwork_rating')->label('Teamwork'),
                TextColumn::make('reviewer.name')->label('Reviewer'),
            ])
            ->filters([
                SelectFilter::make('period')
                    ->options([
                        now()->format('Y-m') => now()->format('F Y'),
                        now()->subMonth()->format('Y-m') => now()->subMonth()->format('F Y'),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployeePerformances::route('/'),
            'create' => CreateEmployeePerformance::route('/create'),
            'edit' => EditEmployeePerformance::route('/{record}/edit'),
        ];
    }
}
