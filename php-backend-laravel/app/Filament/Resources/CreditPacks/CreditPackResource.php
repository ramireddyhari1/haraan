<?php

namespace App\Filament\Resources\CreditPacks;

use App\Filament\Resources\CreditPacks\Pages\CreateCreditPack;
use App\Filament\Resources\CreditPacks\Pages\EditCreditPack;
use App\Filament\Resources\CreditPacks\Pages\ListCreditPacks;
use App\Filament\Resources\CreditPacks\Schemas\CreditPackForm;
use App\Filament\Resources\CreditPacks\Tables\CreditPacksTable;
use App\Models\CreditPack;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

/**
 * Prepaid conversation packs. Sold as one-time orders rather than metered
 * recurring debits — see the billing migration for why that matters in India.
 */
class CreditPackResource extends Resource
{
    protected static ?string $model = CreditPack::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-battery-50';

    protected static ?string $navigationLabel = 'Credit packs';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 96;

    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('admin') ?? false;
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Platform';
    }

    public static function form(Schema $schema): Schema
    {
        return CreditPackForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CreditPacksTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCreditPacks::route('/'),
            'create' => CreateCreditPack::route('/create'),
            'edit' => EditCreditPack::route('/{record}/edit'),
        ];
    }
}
