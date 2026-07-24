<?php

namespace App\Filament\Resources\PartnerPlans;

use App\Filament\Resources\PartnerPlans\Pages\CreatePartnerPlan;
use App\Filament\Resources\PartnerPlans\Pages\EditPartnerPlan;
use App\Filament\Resources\PartnerPlans\Pages\ListPartnerPlans;
use App\Filament\Resources\PartnerPlans\Schemas\PartnerPlanForm;
use App\Filament\Resources\PartnerPlans\Tables\PartnerPlansTable;
use App\Models\PartnerPlan;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

/**
 * The plan catalogue: what each tier costs, how many conversations it includes,
 * and which automations it unlocks. Super-admins only — these rows are pricing.
 */
class PartnerPlanResource extends Resource
{
    protected static ?string $model = PartnerPlan::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-squares-plus';

    protected static ?string $navigationLabel = 'Plans';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 93;

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
        return PartnerPlanForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PartnerPlansTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPartnerPlans::route('/'),
            'create' => CreatePartnerPlan::route('/create'),
            'edit' => EditPartnerPlan::route('/{record}/edit'),
        ];
    }
}
