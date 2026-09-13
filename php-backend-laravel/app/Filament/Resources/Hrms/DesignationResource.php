<?php

declare(strict_types=1);

namespace App\Filament\Resources\Hrms;

use App\Filament\Resources\Hrms\Pages\CreateDesignation;
use App\Filament\Resources\Hrms\Pages\EditDesignation;
use App\Filament\Resources\Hrms\Pages\ListDesignations;
use App\Models\Hrms\Department;
use App\Models\Hrms\Designation;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DesignationResource extends Resource
{
    protected static ?string $model = Designation::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static string|\UnitEnum|null $navigationGroup = 'Workforce & HR';

    protected static ?string $navigationLabel = 'Designations';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'control'
            && (auth()->user()?->isSuperAdmin() || auth()->user()?->hasRoleEither(['OPS', 'FINANCE']));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('department_id')
                ->label('Department')
                ->options(Department::pluck('name', 'id'))
                ->required(),
            TextInput::make('name')->required(),
            TextInput::make('code')->required(),
            TextInput::make('level')->numeric()->default(1),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->weight('bold')->searchable(),
                TextColumn::make('code')->fontFamily('mono'),
                TextColumn::make('department.name')->label('Department')->badge(),
                TextColumn::make('level')->sortable(),
                IconColumn::make('is_active')->boolean(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDesignations::route('/'),
            'create' => CreateDesignation::route('/create'),
            'edit' => EditDesignation::route('/{record}/edit'),
        ];
    }
}
