<?php

declare(strict_types=1);

namespace App\Filament\Resources\FeatureFlags;

use App\Filament\Forms\OrganizationSelect;
use App\Filament\Resources\FeatureFlags\Pages\CreateFeatureFlag;
use App\Filament\Resources\FeatureFlags\Pages\EditFeatureFlag;
use App\Filament\Resources\FeatureFlags\Pages\ListFeatureFlags;
use App\Models\FeatureFlag;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class FeatureFlagResource extends Resource
{
    protected static ?string $model = FeatureFlag::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-flag';

    protected static string|\UnitEnum|null $navigationGroup = 'Platform';

    protected static ?string $recordTitleAttribute = 'name';

    /** Runtime flags are platform-wide config — super-admins only. */
    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('key')
                ->required()
                ->unique(ignoreRecord: true)
                ->helperText('Machine name the app checks, e.g. local_league_creation')
                ->disabledOn('edit'),
            TextInput::make('name')->required(),
            Textarea::make('description')->columnSpanFull(),
            Toggle::make('enabled')
                ->helperText('Master switch — off means the flag is disabled for everyone.'),
            TextInput::make('rollout_percentage')
                ->numeric()->minValue(0)->maxValue(100)->default(100)->suffix('%')
                ->helperText('Deterministic per-user rollout. 100 = everyone.'),
            Select::make('organization_ids')
                ->label('Target organizations')
                ->multiple()
                ->options(fn (): array => OrganizationSelect::options())
                ->searchable()
                ->helperText('Leave empty to target all districts. Selecting a unit includes its whole subtree.'),
            TextInput::make('min_app_version')
                ->placeholder('e.g. 1.4.0')
                ->helperText('Optional — hide the feature from older app builds.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')->searchable()->copyable(),
                TextColumn::make('name')->searchable(),
                ToggleColumn::make('enabled'),
                TextColumn::make('rollout_percentage')->label('Rollout')->suffix('%')->sortable(),
                IconColumn::make('organization_ids')
                    ->label('Targeted')
                    ->state(fn (FeatureFlag $r): bool => ! empty($r->organization_ids))
                    ->boolean(),
                TextColumn::make('min_app_version')->label('Min ver')->placeholder('—'),
                TextColumn::make('updated_at')->dateTime()->sortable()->since(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFeatureFlags::route('/'),
            'create' => CreateFeatureFlag::route('/create'),
            'edit' => EditFeatureFlag::route('/{record}/edit'),
        ];
    }
}
