<?php

declare(strict_types=1);

namespace App\Filament\Resources\HomeBlocks;

use App\Filament\Forms\OrganizationSelect;
use App\Filament\Resources\HomeBlocks\Pages\CreateHomeBlock;
use App\Filament\Resources\HomeBlocks\Pages\EditHomeBlock;
use App\Filament\Resources\HomeBlocks\Pages\ListHomeBlocks;
use App\Models\FeatureFlag;
use App\Models\HomeBlock;
use BackedEnum;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class HomeBlockResource extends Resource
{
    protected static ?string $model = HomeBlock::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $cluster = \App\Filament\Clusters\Marketing\MarketingCluster::class;

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $navigationLabel = 'Home layout';

    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('marketing') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('type')
                ->options(HomeBlock::TYPES)
                ->required()
                ->native(false)
                ->helperText('Which widget the app renders for this block.'),
            TextInput::make('title')
                ->helperText('Optional section header shown in the app.'),
            KeyValue::make('config')
                ->keyLabel('Param')->valueLabel('Value')
                ->helperText('Type-specific params, e.g. section=for_you, placement=home.')
                ->columnSpanFull(),
            Select::make('feature_flag_key')
                ->label('Gate behind feature flag')
                ->options(fn (): array => FeatureFlag::orderBy('key')->pluck('key', 'key')->all())
                ->searchable()
                ->placeholder('Always show')
                ->helperText('If set, the block only appears when this flag is on for the viewer.'),
            Select::make('organization_ids')
                ->label('Target organizations')
                ->multiple()
                ->options(fn (): array => OrganizationSelect::options())
                ->searchable()
                ->helperText('Leave empty for all districts.'),
            DateTimePicker::make('starts_at')->helperText('Optional — schedule the block to appear.'),
            DateTimePicker::make('ends_at')->helperText('Optional — schedule the block to disappear.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => HomeBlock::TYPES[$state] ?? $state),
                TextColumn::make('title')->placeholder('—'),
                ToggleColumn::make('is_active'),
                TextColumn::make('feature_flag_key')->label('Flag')->placeholder('—'),
                IconColumn::make('organization_ids')
                    ->label('Targeted')
                    ->state(fn (HomeBlock $r): bool => ! empty($r->organization_ids))
                    ->boolean(),
                TextColumn::make('starts_at')->dateTime()->placeholder('—')->toggleable(),
                TextColumn::make('ends_at')->dateTime()->placeholder('—')->toggleable(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHomeBlocks::route('/'),
            'create' => CreateHomeBlock::route('/create'),
            'edit' => EditHomeBlock::route('/{record}/edit'),
        ];
    }
}
