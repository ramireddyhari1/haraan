<?php

declare(strict_types=1);

namespace App\Filament\Resources\Rewards;

use App\Filament\Resources\Rewards\Pages\ManageBadgeDefinitions;
use App\Models\BadgeDefinition;
use App\Models\PlayerBadge;
use App\Support\Rewards\BadgeMetrics;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Rewards → Badges. What players can unlock on their profile. A badge unlocks once and stays;
 * switching one off hides it from profiles but never takes it from anyone who earned it. Keys are
 * permanent (the app and past unlocks point at them), so there is no delete.
 */
class BadgeDefinitionResource extends Resource
{
    protected static ?string $model = BadgeDefinition::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-trophy';

    protected static string|\UnitEnum|null $navigationGroup = 'Rewards';

    protected static ?string $navigationLabel = 'Badges';

    protected static ?string $modelLabel = 'badge';

    protected static ?int $navigationSort = 5;

    /** Glyphs the app draws; anything else falls back to the trophy. */
    public const ICONS = [
        'EmojiEvents' => 'Trophy', 'Star' => 'Star', 'WorkspacePremium' => 'Rosette', 'MilitaryTech' => 'Medal',
        'Whatshot' => 'Flame', 'Shield' => 'Shield', 'TrendingUp' => 'Trending up', 'SportsCricket' => 'Cricket',
    ];

    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('marketing') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('key')->required()->maxLength(40)->alphaDash()->unique(ignoreRecord: true)
                ->disabledOn('edit')->helperText('Permanent — it can’t change once players can earn it.'),
            TextInput::make('name')->required()->maxLength(80),
            TextInput::make('description')->maxLength(200),
            Select::make('icon')->options(self::ICONS)->required()->default('EmojiEvents')->native(false),
            Select::make('tier')->options(BadgeDefinition::TIERS)->required()->default('bronze')->native(false),
            Select::make('metric')->options(BadgeMetrics::METRICS)->required()->native(false),
            TextInput::make('threshold')->numeric()->required()->minValue(1)->maxValue(1000000)
                ->helperText('For district rank: unlocks at this rank or better.'),
            TextInput::make('bonus_xp')->label('Bonus XP when unlocked')->numeric()->minValue(0)->maxValue(10000)->default(0),
            Toggle::make('show_progress')->label('Show progress while locked')->default(true),
            Toggle::make('is_active')->label('Active')->default(true),
            TextInput::make('sort')->numeric()->default(100),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                TextColumn::make('name')->weight('bold')->description(fn (BadgeDefinition $r): string => $r->key),
                TextColumn::make('tier')->badge()->color(fn (string $state): string => match ($state) {
                    'gold' => 'warning', 'silver' => 'gray', default => 'info'
                }),
                TextColumn::make('metric')->formatStateUsing(fn (string $state, BadgeDefinition $r): string => (BadgeMetrics::METRICS[$state] ?? $state).' · '.$r->threshold),
                TextColumn::make('bonus_xp')->label('Bonus XP'),
                TextColumn::make('holders')->label('Unlocked by')
                    ->state(fn (BadgeDefinition $r): int => PlayerBadge::query()->where('badge_key', $r->key)->count()),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageBadgeDefinitions::route('/')];
    }
}
