<?php

declare(strict_types=1);

namespace App\Filament\Resources\Rewards;

use App\Filament\Resources\Rewards\Pages\CreateRewardProgram;
use App\Filament\Resources\Rewards\Pages\EditRewardProgram;
use App\Filament\Resources\Rewards\Pages\ListRewardPrograms;
use App\Filament\Resources\Rewards\RelationManagers\RewardRulesRelationManager;
use App\Models\AdminAction;
use App\Models\RewardGrant;
use App\Models\RewardProgram;
use App\Models\RewardSponsor;
use App\Models\RewardZone;
use App\Support\PlatformRules;
use App\Support\SportRules;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Rewards → Programs. A program is a campaign — Haraan's own or a sponsor's — with targeting,
 * schedule, budget and the card the player sees; its rules (below the form) say what is won and
 * when. Money-value rewards always wait for a verified result; that is not a setting.
 *
 * Every change is audited field by field (AuditsAdminChanges); going live, pausing and ending are
 * also logged as their own actions.
 */
class RewardProgramResource extends Resource
{
    protected static ?string $model = RewardProgram::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-gift';

    protected static string|\UnitEnum|null $navigationGroup = 'Rewards';

    protected static ?string $navigationLabel = 'Programs & rules';

    protected static ?string $modelLabel = 'reward program';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('marketing') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        $sports = array_combine(SportRules::SUPPORTED, array_map(fn (string $s) => ucwords(str_replace('_', ' ', $s)), SportRules::SUPPORTED));

        return $schema->components([
            Section::make('Program')->columns(2)->schema([
                TextInput::make('name')->required()->maxLength(120),
                Select::make('kind')->options(RewardProgram::KINDS)->required()->default(RewardProgram::KIND_HARAAN)->live()->native(false),
                Select::make('sponsor_id')->label('Sponsor')
                    ->options(fn (): array => RewardSponsor::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                    ->required(fn (Get $get): bool => $get('kind') === RewardProgram::KIND_SPONSORED)
                    ->visible(fn (Get $get): bool => $get('kind') === RewardProgram::KIND_SPONSORED)
                    ->native(false),
                Select::make('status')->options(RewardProgram::STATUSES)->required()->default('draft')->native(false)
                    ->helperText('Only Live programs inside their dates give rewards.'),
                DateTimePicker::make('starts_at')->label('Starts'),
                DateTimePicker::make('ends_at')->label('Ends')->after('starts_at'),
                TextInput::make('priority')->numeric()->default(100)->minValue(0)->maxValue(10000)
                    ->helperText('Lower runs first. When a player hits a cap, earlier programs win.'),
            ]),
            Section::make('Who can win')->columns(2)->schema([
                CheckboxList::make('sports')->options($sports)->columns(4)->helperText('None ticked = every sport.')->columnSpanFull(),
                CheckboxList::make('match_types')->options(['casual' => 'Casual', 'league' => 'League', 'tournament' => 'Tournament'])
                    ->columns(3)->helperText('None ticked = every match type.')->columnSpanFull(),
                Toggle::make('members_only')->label('Members only')
                    ->helperText('Only plans with “Member-only rewards” (rewards.member_programs) can win.'),
            ]),
            Section::make('Where')
                ->description(fn (): string => PlatformRules::bool('rewards.geo_enabled')
                    ? 'Matched against where the match itself was played — never where a player says they are. Leave both empty to reward every location, as this program does today.'
                    : 'Location targeting is OFF in Platform rules → Post-match rewards, so these zones are ignored and this program reaches every location. Set them up now; turn it on when you’re ready.')
                ->columns(2)
                ->schema([
                    Select::make('includeZones')->label('Only in these zones')
                        ->relationship('includeZones', 'name', fn ($query) => $query->where('is_active', true))
                        ->pivotData(['mode' => RewardZone::MODE_INCLUDE])
                        ->multiple()->searchable()->preload()->native(false)
                        ->helperText('Empty = everywhere. A match qualifies when it falls in any one of these.'),
                    Select::make('excludeZones')->label('Never in these zones')
                        ->relationship('excludeZones', 'name', fn ($query) => $query->where('is_active', true))
                        ->pivotData(['mode' => RewardZone::MODE_EXCLUDE])
                        ->multiple()->searchable()->preload()->native(false)
                        // A zone can only be on one list: the pivot is unique per (program, zone),
                        // and "include it but also never" is a question with no answer.
                        ->rule(fn (Get $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get): void {
                            $clash = array_intersect((array) $value, (array) $get('includeZones'));
                            if ($clash !== []) {
                                $fail('A zone can’t be both included and excluded. Remove it from one of the two lists.');
                            }
                        })
                        ->helperText('Checked first — a match in one of these wins nothing from this program, whatever else it matches.'),
                ]),
            Section::make('Budget')->columns(2)->schema([
                TextInput::make('budget_total')->label('Most rewards in total')->numeric()->minValue(1)->placeholder('No limit'),
                TextInput::make('budget_daily')->label('Most rewards per day')->numeric()->minValue(1)->placeholder('No limit'),
            ]),
            Section::make('What the player sees')->columns(2)->schema([
                TextInput::make('headline')->maxLength(120)->placeholder('₹50 cashback on your next order'),
                ColorPicker::make('brand_color'),
                Textarea::make('description')->maxLength(500)->rows(2)->columnSpanFull(),
                TextInput::make('disclosure')->maxLength(300)
                    ->required(fn (Get $get): bool => $get('kind') === RewardProgram::KIND_SPONSORED)
                    ->helperText('Shown on every sponsored card, e.g. “Sponsored by PayFast”. Required for sponsored programs.'),
                TextInput::make('terms_url')->label('Terms link')->url()->rule('starts_with:https://')->maxLength(255),
                FileUpload::make('card_image')->image()->disk('public')->directory('rewards/programs')->maxSize(2048)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('priority')
            ->modifyQueryUsing(fn ($query) => $query->with('sponsor')->withCount([
                'grants as claimed_count' => fn ($q) => $q->whereIn('status', [RewardGrant::CLAIMED, RewardGrant::REDEEMED]),
                'grants as redeemed_count' => fn ($q) => $q->where('status', RewardGrant::REDEEMED),
            ]))
            ->columns([
                TextColumn::make('name')->weight('bold')->searchable()->description(fn (RewardProgram $r): ?string => $r->sponsor?->name),
                TextColumn::make('kind')->badge()->formatStateUsing(fn (string $state): string => RewardProgram::KINDS[$state] ?? $state)
                    ->color(fn (string $state): string => $state === RewardProgram::KIND_SPONSORED ? 'warning' : 'info'),
                TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'live' => 'success', 'paused' => 'warning', 'ended' => 'gray', default => 'gray'
                    }),
                TextColumn::make('grants_count')->label('Given')
                    ->formatStateUsing(fn (RewardProgram $r): string => $r->grants_count.($r->budget_total ? ' / '.$r->budget_total : '')),
                TextColumn::make('claimed_count')->label('Claimed'),
                TextColumn::make('redeemed_count')->label('Used'),
                TextColumn::make('ends_at')->label('Ends')->dateTime('d M Y')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->options(RewardProgram::STATUSES),
                SelectFilter::make('kind')->options(RewardProgram::KINDS),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('goLive')->label('Go live')->icon('heroicon-m-play')->color('success')
                    ->visible(fn (RewardProgram $r): bool => $r->status !== 'live')
                    ->requiresConfirmation()
                    ->modalDescription('Players start winning this program’s rewards from the next finished match.')
                    ->action(fn (RewardProgram $r) => self::setStatus($r, 'live')),
                Action::make('pause')->label('Pause')->icon('heroicon-m-pause')->color('warning')
                    ->visible(fn (RewardProgram $r): bool => $r->status === 'live')
                    ->requiresConfirmation()
                    ->modalDescription('No new rewards from this program. Rewards already given stay with their players.')
                    ->action(fn (RewardProgram $r) => self::setStatus($r, 'paused')),
            ]);
    }

    public static function setStatus(RewardProgram $program, string $status): void
    {
        $from = $program->status;
        $program->forceFill(['status' => $status])->save();
        AdminAction::log('reward_program.'.($status === 'live' ? 'published' : $status), [
            'program_id' => $program->id, 'name' => $program->name, 'from' => $from, 'to' => $status,
        ], $program);
        Notification::make()->title('Program '.RewardProgram::STATUSES[$status])->success()->send();
    }

    public static function getRelations(): array
    {
        return [RewardRulesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRewardPrograms::route('/'),
            'create' => CreateRewardProgram::route('/create'),
            'edit' => EditRewardProgram::route('/{record}/edit'),
        ];
    }
}
