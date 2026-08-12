<?php

namespace App\Filament\Resources\PartnerSubscriptions;

use App\Filament\Resources\PartnerSubscriptions\Pages\CreatePartnerSubscription;
use App\Filament\Resources\PartnerSubscriptions\Pages\EditPartnerSubscription;
use App\Filament\Resources\PartnerSubscriptions\Pages\ListPartnerSubscriptions;
use App\Filament\Resources\PartnerSubscriptions\Schemas\PartnerSubscriptionForm;
use App\Filament\Resources\PartnerSubscriptions\Tables\PartnerSubscriptionsTable;
use App\Models\PartnerSubscription;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

/**
 * Who is on which plan. Until Razorpay subscriptions land (phase 2b) this is the
 * only way a partner gets a paid plan, so rows created here are marked
 * source=admin and are what the entitlement check reads.
 */
class PartnerSubscriptionResource extends Resource
{
    protected static ?string $model = PartnerSubscription::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationLabel = 'Subscriptions';

    protected static ?int $navigationSort = 94;

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
        return PartnerSubscriptionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PartnerSubscriptionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPartnerSubscriptions::route('/'),
            'create' => CreatePartnerSubscription::route('/create'),
            'edit' => EditPartnerSubscription::route('/{record}/edit'),
        ];
    }
}
