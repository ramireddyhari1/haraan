<?php

declare(strict_types=1);

namespace App\Filament\Resources\Hrms;

use App\Filament\Resources\Hrms\Pages\CreateDepartment;
use App\Filament\Resources\Hrms\Pages\EditDepartment;
use App\Filament\Resources\Hrms\Pages\ListDepartments;
use App\Models\Hrms\Department;
use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DepartmentResource extends Resource
{
    protected static ?string $model = Department::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static string|\UnitEnum|null $navigationGroup = 'Workforce & HR';

    protected static ?string $navigationLabel = 'Departments';

    protected static ?int $navigationSort = 2;

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
            Select::make('manager_id')
                ->label('Department Head')
                ->options(User::pluck('name', 'id'))
                ->searchable(),
            Select::make('parent_id')
                ->label('Parent Department')
                ->options(Department::pluck('name', 'id'))
                ->searchable(),
            Textarea::make('description')->rows(2),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->weight('bold')->searchable(),
                TextColumn::make('code')->fontFamily('mono'),
                TextColumn::make('manager.name')->label('Head'),
                TextColumn::make('parent.name')->label('Parent Department')->placeholder('—'),
                TextColumn::make('employees_count')->counts('employees')->label('Staff Count'),
                IconColumn::make('is_active')->boolean(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDepartments::route('/'),
            'create' => CreateDepartment::route('/create'),
            'edit' => EditDepartment::route('/{record}/edit'),
        ];
    }
}
