<?php

declare(strict_types=1);

namespace App\Filament\Resources\MemberEntitlementOverrides;

use App\Filament\Resources\MemberEntitlementOverrides\Pages\ManageMemberEntitlementOverrides;
use App\Models\AdminAction;
use App\Models\MemberEntitlementOverride;
use App\Models\MemberFeatureDefinition;
use App\Models\User;
use App\Support\Membership\MemberFeature;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Per-member exceptions to their plan for a single feature — "give this creator unlimited
 * delivery reviews until December", "turn ads back on for this test account". An override
 * replaces the plan's value for that one key while it's unexpired.
 */
class MemberEntitlementOverrideResource extends Resource
{
    protected static ?string $model = MemberEntitlementOverride::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-vertical';

    protected static ?string $cluster = \App\Filament\Clusters\Finance\FinanceCluster::class;

    protected static ?string $navigationLabel = 'Member overrides';

    protected static ?string $modelLabel = 'entitlement override';

    protected static ?int $navigationSort = 22;

    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('finance') ?? false;
    }

    /** @return array<string, string> */
    private static function featureOptions(): array
    {
        $names = MemberFeatureDefinition::query()->pluck('name', 'key')->all();
        $options = [];
        foreach (MemberFeature::keys() as $key) {
            $options[$key] = ($names[$key] ?? MemberFeature::label($key)) . ' (' . $key . ')';
        }

        return $options;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('user_id')
                ->label('Member')
                ->required()
                ->searchable()
                ->getSearchResultsUsing(fn (string $search): array => User::query()
                    ->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('player_id', 'like', "%{$search}%"))
                    ->limit(25)
                    ->get()
                    ->mapWithKeys(fn (User $u): array => [$u->id => trim($u->name . ' · ' . ($u->email ?: $u->phone ?: $u->player_id))])
                    ->all())
                ->getOptionLabelUsing(fn ($value): ?string => User::find($value)?->name),

            Select::make('feature_key')
                ->label('Feature')
                ->options(fn (): array => self::featureOptions())
                ->required()
                ->live()
                ->native(false),

            Toggle::make('enabled')
                ->label('Included')
                ->default(true),

            TextInput::make('limit_value')
                ->label('Limit')
                ->numeric()
                ->minValue(0)
                ->placeholder('Unlimited')
                ->visible(fn (Get $get): bool => MemberFeature::exists((string) $get('feature_key'))
                    && ! MemberFeature::isBoolean((string) $get('feature_key'))),

            DateTimePicker::make('expires_at')
                ->label('Expires')
                ->helperText('Leave empty for no expiry.'),

            Textarea::make('reason')
                ->required()
                ->rows(2)
                ->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['user', 'grantedBy']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('user.name')->label('Member')->weight('bold')->searchable(),
                TextColumn::make('feature_key')->label('Feature')->badge()->color('info'),
                IconColumn::make('enabled')->label('Included')->boolean(),
                TextColumn::make('limit_value')
                    ->label('Limit')
                    ->state(fn (MemberEntitlementOverride $r): string => MemberFeature::exists($r->feature_key) && MemberFeature::isBoolean($r->feature_key)
                        ? '—'
                        : ($r->limit_value === null ? 'Unlimited' : (string) $r->limit_value)),
                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime('d M Y')
                    ->placeholder('Never')
                    ->color(fn (MemberEntitlementOverride $r): string => $r->expires_at?->isPast() ? 'danger' : 'gray'),
                TextColumn::make('reason')->limit(40)->wrap(),
                TextColumn::make('grantedBy.name')->label('By')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('feature_key')->label('Feature')->options(fn (): array => self::featureOptions()),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateDataUsing(fn (array $data): array => ['granted_by' => auth()->id()] + $data)
                    ->after(fn (MemberEntitlementOverride $record) => AdminAction::log('member_override.updated', [
                        'override_id' => $record->id, 'user_id' => $record->user_id, 'feature' => $record->feature_key,
                        'enabled' => $record->enabled, 'limit' => $record->limit_value,
                    ])),
                DeleteAction::make()
                    ->after(fn (MemberEntitlementOverride $record) => AdminAction::log('member_override.deleted', [
                        'override_id' => $record->id, 'user_id' => $record->user_id, 'feature' => $record->feature_key,
                    ])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageMemberEntitlementOverrides::route('/'),
        ];
    }
}
