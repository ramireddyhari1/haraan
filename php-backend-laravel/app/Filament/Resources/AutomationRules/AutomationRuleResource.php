<?php

namespace App\Filament\Resources\AutomationRules;

use App\Filament\Resources\AutomationRules\Pages\CreateAutomationRule;
use App\Filament\Resources\AutomationRules\Pages\EditAutomationRule;
use App\Filament\Resources\AutomationRules\Pages\ListAutomationRules;
use App\Filament\Resources\AutomationRules\Schemas\AutomationRuleForm;
use App\Filament\Resources\AutomationRules\Tables\AutomationRulesTable;
use App\Models\AutomationRule;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

/**
 * Auto-reply rules for inbound WhatsApp. Super-admins only for now: rules go out
 * over the SHARED sender, so a badly worded one is Haraan's reputation, not just
 * one partner's. Partner-authored rules come with their own WABA.
 */
class AutomationRuleResource extends Resource
{
    protected static ?string $model = AutomationRule::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-bottom-center-text';

    protected static ?string $navigationLabel = 'Auto-replies';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 92;

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
        return AutomationRuleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AutomationRulesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAutomationRules::route('/'),
            'create' => CreateAutomationRule::route('/create'),
            'edit' => EditAutomationRule::route('/{record}/edit'),
        ];
    }
}
