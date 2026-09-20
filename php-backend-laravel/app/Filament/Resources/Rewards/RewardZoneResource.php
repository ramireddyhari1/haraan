<?php

declare(strict_types=1);

namespace App\Filament\Resources\Rewards;

use App\Filament\Resources\Rewards\Pages\ManageRewardZones;
use App\Models\AdminAction;
use App\Models\Event;
use App\Models\MatchGround;
use App\Models\RewardGrant;
use App\Models\RewardZone;
use App\Models\Venue;
use App\Support\PlatformRules;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\ViewField;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Rewards → Zones. The geography a program can be limited to, drawn once and reused.
 *
 * A zone only ever narrows WHO is eligible — it can't change what a reward is worth, and it
 * can't unlock anything. Money-value rewards still wait for a verified result, and a
 * location-targeted one waits for a stricter result than usual
 * (`rewards.geo_min_trust_for_zone_money`). See docs/location-rewards-design.md.
 *
 * Zones do nothing at all until `rewards.geo_enabled` is turned on in Platform rules.
 */
class RewardZoneResource extends Resource
{
    protected static ?string $model = RewardZone::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';

    protected static string|\UnitEnum|null $navigationGroup = 'Rewards';

    protected static ?string $navigationLabel = 'Zones';

    protected static ?string $modelLabel = 'zone';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('marketing') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        $isCircle = fn (Get $get): bool => $get('kind') === RewardZone::KIND_CIRCLE;
        $isPin = fn (Get $get): bool => $isCircle($get) && $get('anchor_type') === RewardZone::ANCHOR_POINT;

        return $schema->components([
            Section::make('Zone')->columns(2)->schema([
                TextInput::make('name')->required()->maxLength(120)
                    ->placeholder('Kadapa city — 15 km')
                    ->helperText('What a marketer picks from a list. Say where and how wide.'),
                Select::make('kind')->label('Shape')->options(RewardZone::KINDS)->required()
                    ->default(RewardZone::KIND_CIRCLE)->live()->native(false),
                Toggle::make('is_active')->label('Active')->default(true)
                    ->helperText('Off takes this zone out of every program using it, without deleting the history.'),
            ]),

            Section::make('Where')->columns(2)
                ->visible($isCircle)
                ->description('A match is inside this zone when its own location fix is within the radius.')
                ->schema([
                    Select::make('anchor_type')->label('Centre')->options(RewardZone::ANCHORS)
                        ->default(RewardZone::ANCHOR_POINT)->required()->live()->native(false)
                        ->helperText('Anchoring to a venue, ground or event means the zone follows it if it ever moves.'),
                    Select::make('anchor_id')->label('Which one')
                        ->visible(fn (Get $get): bool => $isCircle($get) && $get('anchor_type') !== RewardZone::ANCHOR_POINT)
                        ->required(fn (Get $get): bool => $isCircle($get) && $get('anchor_type') !== RewardZone::ANCHOR_POINT)
                        ->searchable()->native(false)
                        ->options(fn (Get $get): array => match ($get('anchor_type')) {
                            'venue' => Venue::query()->orderBy('name')->limit(200)->pluck('name', 'id')->all(),
                            'ground' => MatchGround::query()->orderBy('name')->limit(200)->pluck('name', 'id')->all(),
                            'event' => Event::query()->orderByDesc('id')->limit(200)->pluck('title', 'id')->all(),
                            default => [],
                        }),
                    TextInput::make('radius_m')->label('Radius (metres)')->numeric()->required($isCircle)
                        ->default(fn (): int => (int) round(PlatformRules::float('rewards.geo_default_radius_km') * 1000))
                        ->minValue(100)
                        ->maxValue(fn (): int => (int) round(PlatformRules::float('rewards.geo_max_zone_radius_km') * 1000))
                        ->helperText(fn (): string => 'Up to '.PlatformRules::float('rewards.geo_max_zone_radius_km').' km — raise the ceiling in Platform rules if you really need more.'),
                    ViewField::make('map_picker')->hiddenLabel()->view('filament.reward-zone-picker')
                        ->dehydrated(false)->columnSpanFull()
                        ->visible($isPin),
                    Hidden::make('place_id'),
                    TextInput::make('latitude')->numeric()->step('0.0000001')->minValue(-90)->maxValue(90)
                        ->required($isPin)->visible($isPin)->live()
                        ->helperText('Set by the pin above — or right-click the spot in Google Maps for "lat, lng".'),
                    TextInput::make('longitude')->numeric()->step('0.0000001')->minValue(-180)->maxValue(180)
                        ->required($isPin)->visible($isPin)->live(),
                ]),

            Section::make('Which places')->columns(3)
                ->visible(fn (Get $get): bool => $get('kind') === RewardZone::KIND_AREA)
                ->description('Matched against the match’s own locality, district and state. Every field you fill must match, so fill only what you mean. An area with nothing filled matches nothing.')
                ->schema([
                    TextInput::make('locality')->maxLength(120)->placeholder('Madhapur'),
                    TextInput::make('district')->maxLength(120)->placeholder('Kadapa'),
                    TextInput::make('state')->maxLength(120)->placeholder('Andhra Pradesh'),
                ]),

            Section::make('Notes')->schema([
                Textarea::make('notes')->hiddenLabel()->rows(2)->maxLength(500)
                    ->placeholder('Why this zone exists — the sponsor, the campaign, who asked for it.'),
            ])->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->description('Zones do nothing until “Target rewards by location” is on in Platform rules → Post-match rewards.')
            ->modifyQueryUsing(fn ($query) => $query->withCount([
                'programs',
                // Every grant ever traced to this zone, so a marketer can see which geography
                // is actually producing rewards rather than guessing.
            ]))
            ->columns([
                TextColumn::make('name')->weight('bold')->searchable()
                    ->description(fn (RewardZone $z): ?string => $z->notes),
                TextColumn::make('kind')->badge()
                    ->formatStateUsing(fn (string $state): string => $state === RewardZone::KIND_CIRCLE ? 'Circle' : 'Area')
                    ->color(fn (string $state): string => $state === RewardZone::KIND_CIRCLE ? 'info' : 'gray'),
                TextColumn::make('coverage')->label('Covers')
                    ->state(fn (RewardZone $z): string => $z->isCircle()
                        ? (($z->radiusKm() ?? 0).' km'.($z->anchor_type === RewardZone::ANCHOR_POINT ? '' : ' around the '.$z->anchor_type))
                        : trim(implode(', ', array_filter([$z->locality, $z->district, $z->state])) ?: '— nothing set —')),
                TextColumn::make('programs_count')->label('Programs'),
                TextColumn::make('grants')->label('Rewards given')
                    ->state(fn (RewardZone $z): int => RewardGrant::query()->where('zone_id', $z->id)->count()),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('Programs using this zone go back to targeting everywhere. Rewards already given keep their record of where they came from.')
                    ->after(fn (RewardZone $record) => AdminAction::log('reward_zone.deleted', ['zone_id' => $record->id, 'name' => $record->name])),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageRewardZones::route('/')];
    }
}
