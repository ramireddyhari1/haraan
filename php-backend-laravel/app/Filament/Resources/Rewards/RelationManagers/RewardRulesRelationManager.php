<?php

declare(strict_types=1);

namespace App\Filament\Resources\Rewards\RelationManagers;

use App\Models\AdminAction;
use App\Models\MemberPlan;
use App\Models\RewardCodePool;
use App\Models\RewardRule;
use App\Models\RewardZone;
use App\Models\Venue;
use App\Support\Membership\MemberFeature;
use App\Support\Rewards\RewardConditions;
use App\Support\Rewards\RewardTypes;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * A program's rules. The form is typed — trigger, conditions and reward are picked from the
 * fixed registries (RewardConditions, RewardTypes) and stored as validated JSON; nothing an admin
 * types is ever evaluated as code. Each create/edit/delete is audited (field-level by the model,
 * plus a named entry here).
 */
class RewardRulesRelationManager extends RelationManager
{
    protected static string $relationship = 'rules';

    protected static ?string $title = 'Rules';

    public function form(Schema $schema): Schema
    {
        $type = fn (Get $get): string => (string) $get('reward_type');

        return $schema->components([
            Section::make('Rule')->columns(2)->schema([
                TextInput::make('name')->required()->maxLength(120),
                Select::make('trigger')->options(RewardRule::TRIGGERS)->required()->default(RewardRule::TRIGGER_COMPLETED)->native(false),
                Select::make('reward_type')->label('Reward')->options(RewardTypes::RULE_TYPES)->required()->live()->native(false)
                    ->helperText(fn (Get $get): string => RewardTypes::isMoneyValue((string) $get('reward_type'))
                        ? 'Money-value: always locked until the result is verified.'
                        : ''),
                Select::make('unlock_method')->options(RewardRule::UNLOCK_METHODS)->required()->default(RewardRule::UNLOCK_AUTO)->native(false)
                    ->helperText('A video unlock is never offered to members whose plan has no ads; plans with “Unlock rewards without ads” skip the video.'),
                Toggle::make('is_active')->label('Active')->default(true),
                TextInput::make('sort')->numeric()->default(100),
            ]),

            Section::make('Reward')->columns(3)->schema([
                TextInput::make('p_amount')->label('Bonus XP')->numeric()->minValue(1)->maxValue(10000)
                    ->visible(fn (Get $get) => $type($get) === RewardTypes::BONUS_XP)->required(fn (Get $get) => $type($get) === RewardTypes::BONUS_XP),

                Select::make('p_discount_type')->label('Discount')->options(['fixed' => '₹ off', 'percent' => '% off'])->default('fixed')->native(false)
                    ->visible(fn (Get $get) => $type($get) === RewardTypes::HARAAN_COUPON),
                TextInput::make('p_discount')->label('Amount')->numeric()->minValue(1)
                    ->visible(fn (Get $get) => $type($get) === RewardTypes::HARAAN_COUPON)->required(fn (Get $get) => $type($get) === RewardTypes::HARAAN_COUPON),
                TextInput::make('p_max_discount')->label('Max discount (₹)')->numeric()->minValue(1)
                    ->visible(fn (Get $get) => $type($get) === RewardTypes::HARAAN_COUPON),
                TextInput::make('p_min_order')->label('Minimum order (₹)')->numeric()->minValue(0)
                    ->visible(fn (Get $get) => $type($get) === RewardTypes::HARAAN_COUPON),
                Select::make('p_scope')->label('Works on')->options(['all' => 'Tickets and venue bookings', 'event' => 'Event tickets', 'venue' => 'Venue bookings'])->default('all')->native(false)->live()
                    ->visible(fn (Get $get) => $type($get) === RewardTypes::HARAAN_COUPON),
                Select::make('p_venue_id')->label('At this venue only')
                    ->options(fn (): array => Venue::query()->orderBy('name')->limit(200)->pluck('name', 'id')->all())
                    ->searchable()->native(false)->placeholder('Any venue')
                    ->visible(fn (Get $get) => $type($get) === RewardTypes::HARAAN_COUPON && $get('p_scope') === 'venue')
                    ->helperText('How a sponsor funds “₹100 off at our turf”. Enforced at checkout.'),
                TextInput::make('p_valid_days')->label('Valid for (days after claim)')->numeric()->minValue(1)->maxValue(365)->default(30)
                    ->visible(fn (Get $get) => $type($get) === RewardTypes::HARAAN_COUPON),

                Select::make('p_pool_id')->label('Code pool')
                    ->options(fn (): array => RewardCodePool::query()->orderBy('name')->pluck('name', 'id')->all())->native(false)
                    ->visible(fn (Get $get) => $type($get) === RewardTypes::SPONSOR_CODE)->required(fn (Get $get) => $type($get) === RewardTypes::SPONSOR_CODE),

                Select::make('p_plan_id')->label('Plan')
                    ->options(fn (): array => MemberPlan::query()->where('is_default', false)->orderBy('rank')->pluck('name', 'id')->all())->native(false)
                    ->visible(fn (Get $get) => $type($get) === RewardTypes::MEMBERSHIP_TRIAL)->required(fn (Get $get) => $type($get) === RewardTypes::MEMBERSHIP_TRIAL),
                TextInput::make('p_days')->label('Days')->numeric()->minValue(1)->maxValue(90)->default(7)
                    ->visible(fn (Get $get) => $type($get) === RewardTypes::MEMBERSHIP_TRIAL),

                TextInput::make('p_url')->label('Offer link (https)')->url()->maxLength(255)
                    ->visible(fn (Get $get) => $type($get) === RewardTypes::OFFER_LINK)->required(fn (Get $get) => $type($get) === RewardTypes::OFFER_LINK),
                TextInput::make('p_cta_text')->label('Button text')->maxLength(30)->default('Open offer')
                    ->visible(fn (Get $get) => $type($get) === RewardTypes::OFFER_LINK),
            ]),

            Section::make('Only when')->columns(3)->schema([
                Select::make('c_result')->label('Result')->options(RewardConditions::RESULTS)->default('any')->native(false),
                Toggle::make('c_player_of_match')->label('Player of the match only')
                    ->helperText('Only matches with a recorded player of the match.'),
                TextInput::make('c_min_registered_per_side')->label('Registered players per side (min)')->numeric()->minValue(0)->maxValue(11),
                TextInput::make('c_min_play_streak_weeks')->label('Weekly streak (min weeks)')->numeric()->minValue(0)->maxValue(520),
                Select::make('c_min_trust')->label('Result trust (min)')->options(RewardConditions::TRUST_LEVELS)->default('low')->native(false)
                    ->helperText('For “when the result is confirmed” rules.'),
                Select::make('c_member_feature')->label('Member feature required')
                    ->options(fn (): array => collect(MemberFeature::keys())->filter(fn (string $k) => MemberFeature::isBoolean($k))
                        ->mapWithKeys(fn (string $k) => [$k => MemberFeature::label($k)." ({$k})"])->all())
                    ->placeholder('Anyone')->native(false),
            ]),

            Section::make('Only where')
                ->description('Leave empty to use the program’s zones. Set them to make this one rule tighter — how a single campaign gives more the closer the match is to the sponsor.')
                ->columns(2)
                ->collapsed(fn (?RewardRule $record = null): bool => $record === null || $record->zones->isEmpty())
                ->schema([
                    Select::make('includeZones')->label('Only in these zones')
                        ->relationship('includeZones', 'name', fn ($query) => $query->where('is_active', true))
                        ->pivotData(['mode' => RewardZone::MODE_INCLUDE])
                        ->multiple()->searchable()->preload()->native(false),
                    Select::make('excludeZones')->label('Never in these zones')
                        ->relationship('excludeZones', 'name', fn ($query) => $query->where('is_active', true))
                        ->pivotData(['mode' => RewardZone::MODE_EXCLUDE])
                        ->multiple()->searchable()->preload()->native(false)
                        ->rule(fn (Get $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get): void {
                            if (array_intersect((array) $value, (array) $get('includeZones')) !== []) {
                                $fail('A zone can’t be both included and excluded.');
                            }
                        }),
                ]),

            Section::make('Limits')->columns(3)->schema([
                TextInput::make('per_user_daily_cap')->label('Per player per day')->numeric()->minValue(1)->placeholder('No limit'),
                TextInput::make('per_user_total_cap')->label('Per player in total')->numeric()->minValue(1)->placeholder('No limit'),
                TextInput::make('expires_after_days')->label('Expires after (days)')->numeric()->minValue(1)->maxValue(365)
                    ->placeholder('Platform default'),
            ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('name')->weight('bold'),
                TextColumn::make('trigger')->badge()->formatStateUsing(fn (string $state): string => RewardRule::TRIGGERS[$state] ?? $state),
                TextColumn::make('reward_type')->label('Reward')->formatStateUsing(fn (string $state): string => RewardTypes::RULE_TYPES[$state] ?? $state),
                TextColumn::make('unlock_method')->label('Unlock')->formatStateUsing(fn (string $state): string => $state === RewardRule::UNLOCK_AD ? 'Video' : 'Auto'),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->headerActions([
                CreateAction::make()->label('Add rule')
                    ->mutateDataUsing(fn (array $data): array => self::toRecord($data))
                    ->after(fn (RewardRule $record) => self::audit('reward_rule.created', $record)),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(fn (array $data): array => self::toForm($data))
                    ->mutateDataUsing(fn (array $data): array => self::toRecord($data))
                    ->after(fn (RewardRule $record) => self::audit('reward_rule.updated', $record)),
                DeleteAction::make()->after(fn (RewardRule $record) => self::audit('reward_rule.deleted', $record)),
            ]);
    }

    /** Flat form fields → validated payload + conditions. */
    public static function toRecord(array $data): array
    {
        $payload = [];
        $conditions = [];
        foreach ($data as $key => $value) {
            if (str_starts_with((string) $key, 'p_')) {
                $payload[substr($key, 2)] = $value;
                unset($data[$key]);
            } elseif (str_starts_with((string) $key, 'c_')) {
                $conditions[substr($key, 2)] = $value;
                unset($data[$key]);
            }
        }

        try {
            $data['payload'] = RewardTypes::normalizePayload((string) ($data['reward_type'] ?? ''), $payload);
            $data['conditions'] = RewardConditions::normalize($conditions);
        } catch (\InvalidArgumentException $e) {
            Notification::make()->title('Rule not saved')->body($e->getMessage())->danger()->send();
            throw new Halt;
        }

        return $data;
    }

    /** Stored payload + conditions → flat form fields. */
    public static function toForm(array $data): array
    {
        foreach ((array) ($data['payload'] ?? []) as $k => $v) {
            $data['p_'.$k] = $v;
        }
        foreach ((array) ($data['conditions'] ?? []) as $k => $v) {
            $data['c_'.$k] = $v;
        }

        return $data;
    }

    private static function audit(string $action, RewardRule $rule): void
    {
        AdminAction::log($action, [
            'rule_id' => $rule->id, 'program_id' => $rule->program_id, 'name' => $rule->name,
            'reward_type' => $rule->reward_type, 'payload' => $rule->payload, 'conditions' => $rule->conditions,
            'unlock_method' => $rule->unlock_method, 'is_active' => $rule->is_active,
        ], $rule);
    }
}
