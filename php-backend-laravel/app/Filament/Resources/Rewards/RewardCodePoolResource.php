<?php

declare(strict_types=1);

namespace App\Filament\Resources\Rewards;

use App\Filament\Resources\Rewards\Pages\CreateRewardCodePool;
use App\Filament\Resources\Rewards\Pages\EditRewardCodePool;
use App\Filament\Resources\Rewards\Pages\ListRewardCodePools;
use App\Filament\Resources\Rewards\RelationManagers\RewardCodesRelationManager;
use App\Models\RewardCodePool;
use App\Models\RewardSponsor;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Rewards → Code pools. Unique codes a sponsor supplies (one per winner). Imported in bulk,
 * encrypted at rest, shown masked. Revealing one is a super-admin action and is audited.
 */
class RewardCodePoolResource extends Resource
{
    protected static ?string $model = RewardCodePool::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-ticket';

    protected static string|\UnitEnum|null $navigationGroup = 'Rewards';

    protected static ?string $navigationLabel = 'Code pools';

    protected static ?string $modelLabel = 'code pool';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 4;

    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('marketing') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(120),
            Select::make('sponsor_id')->label('Sponsor')
                ->options(fn (): array => RewardSponsor::query()->orderBy('name')->pluck('name', 'id')->all())->native(false),
            TextInput::make('instructions')->label('How to use (shown to the winner)')->maxLength(300)
                ->placeholder('Apply at checkout in the PayFast app'),
            TextInput::make('low_stock_threshold')->label('Warn when fewer than')->numeric()->minValue(0)->default(20),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('sponsor')
                ->withCount(['codes', 'codes as remaining_count' => fn ($q) => $q->whereNull('grant_id')]))
            ->columns([
                TextColumn::make('name')->weight('bold')->searchable()->description(fn (RewardCodePool $r): ?string => $r->sponsor?->name),
                TextColumn::make('codes_count')->label('Codes'),
                TextColumn::make('remaining_count')->label('Left')
                    ->color(fn (RewardCodePool $r): string => (int) $r->remaining_count <= (int) $r->low_stock_threshold ? 'danger' : 'success'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [RewardCodesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRewardCodePools::route('/'),
            'create' => CreateRewardCodePool::route('/create'),
            'edit' => EditRewardCodePool::route('/{record}/edit'),
        ];
    }
}
