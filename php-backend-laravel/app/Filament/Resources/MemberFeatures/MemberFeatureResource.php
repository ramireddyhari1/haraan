<?php

declare(strict_types=1);

namespace App\Filament\Resources\MemberFeatures;

use App\Filament\Resources\MemberFeatures\Pages\ManageMemberFeatures;
use App\Models\MemberFeatureDefinition;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * How member features read in the app's plan comparison. Keys and types are defined in code
 * (they're what the server enforces), so rows here can be reworded, reordered or hidden but
 * never created or deleted.
 */
class MemberFeatureResource extends Resource
{
    protected static ?string $model = MemberFeatureDefinition::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-list-bullet';

    protected static ?string $cluster = \App\Filament\Clusters\Finance\FinanceCluster::class;

    protected static ?string $navigationLabel = 'Member features';

    protected static ?string $modelLabel = 'member feature';

    protected static ?int $navigationSort = 23;

    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('admin') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(80),
            TextInput::make('unit')->maxLength(30)->placeholder('reviews / month'),
            Textarea::make('description')->rows(2)->maxLength(255)->columnSpanFull(),
            TextInput::make('sort')->numeric()->default(100),
            Toggle::make('is_visible')->label('Shown in the app'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                TextColumn::make('name')->weight('bold')->description(fn (MemberFeatureDefinition $r): ?string => $r->description),
                TextColumn::make('key')->badge()->color('gray'),
                TextColumn::make('type')->badge()->color('info'),
                TextColumn::make('unit')->placeholder('—'),
                IconColumn::make('is_visible')->label('In app')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageMemberFeatures::route('/'),
        ];
    }
}
