<?php

declare(strict_types=1);

namespace App\Filament\Resources\MemberPlans;

use App\Filament\Resources\MemberPlans\Pages\CreateMemberPlan;
use App\Filament\Resources\MemberPlans\Pages\EditMemberPlan;
use App\Filament\Resources\MemberPlans\Pages\ListMemberPlans;
use App\Filament\Resources\MemberPlans\RelationManagers\PricesRelationManager;
use App\Filament\Resources\MemberPlans\Schemas\MemberPlanForm;
use App\Filament\Resources\MemberPlans\Tables\MemberPlansTable;
use App\Models\MemberPlan;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

/**
 * Member plans — Free, Pro, Hero. What each tier costs and what it unlocks. Super-admin only:
 * these rows are pricing. Not to be confused with partner plans (Platform → Plans), which
 * are a separate catalogue for partner automations.
 */
class MemberPlanResource extends Resource
{
    protected static ?string $model = MemberPlan::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $cluster = \App\Filament\Clusters\Finance\FinanceCluster::class;

    protected static ?string $navigationLabel = 'Member plans';

    protected static ?string $modelLabel = 'member plan';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 20;

    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('admin') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return MemberPlanForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MemberPlansTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [PricesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMemberPlans::route('/'),
            'create' => CreateMemberPlan::route('/create'),
            'edit' => EditMemberPlan::route('/{record}/edit'),
        ];
    }
}
