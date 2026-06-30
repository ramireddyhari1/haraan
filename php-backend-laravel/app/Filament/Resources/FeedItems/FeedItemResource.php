<?php

namespace App\Filament\Resources\FeedItems;

use App\Filament\Resources\FeedItems\Pages\CreateFeedItem;
use App\Filament\Resources\FeedItems\Pages\EditFeedItem;
use App\Filament\Resources\FeedItems\Pages\ListFeedItems;
use App\Filament\Resources\FeedItems\Schemas\FeedItemForm;
use App\Filament\Resources\FeedItems\Tables\FeedItemsTable;
use App\Models\FeedItem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FeedItemResource extends Resource
{
    protected static ?string $model = FeedItem::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $cluster = \App\Filament\Clusters\Marketing\MarketingCluster::class;

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('marketing') ?? false;
    }

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return FeedItemForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FeedItemsTable::configure($table);
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
            'index' => ListFeedItems::route('/'),
            'create' => CreateFeedItem::route('/create'),
            'edit' => EditFeedItem::route('/{record}/edit'),
        ];
    }
}
