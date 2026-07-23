<?php

namespace App\Filament\Resources\Ads;

use App\Filament\Resources\Ads\Pages\CreateAd;
use App\Filament\Resources\Ads\Pages\EditAd;
use App\Filament\Resources\Ads\Pages\ListAds;
use App\Filament\Resources\Ads\Schemas\AdForm;
use App\Filament\Resources\Ads\Tables\AdsTable;
use App\Models\Ad;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AdResource extends Resource
{
    protected static ?string $model = Ad::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $cluster = \App\Filament\Clusters\Marketing\MarketingCluster::class;

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('marketing') ?? false;
    }

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return AdForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AdsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAds::route('/'),
            'create' => CreateAd::route('/create'),
            'edit' => EditAd::route('/{record}/edit'),
        ];
    }
}
