<?php

namespace App\Filament\Resources\Payouts;

use App\Filament\Resources\Payouts\Pages\CreatePayout;
use App\Filament\Resources\Payouts\Pages\EditPayout;
use App\Filament\Resources\Payouts\Pages\ListPayouts;
use App\Filament\Resources\Payouts\Schemas\PayoutForm;
use App\Filament\Resources\Payouts\Tables\PayoutsTable;
use App\Models\Payout;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PayoutResource extends Resource
{
    protected static ?string $model = Payout::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $cluster = \App\Filament\Clusters\Finance\FinanceCluster::class;

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('finance') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return PayoutForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PayoutsTable::configure($table);
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
            'index' => ListPayouts::route('/'),
            'create' => CreatePayout::route('/create'),
            'edit' => EditPayout::route('/{record}/edit'),
        ];
    }
}
