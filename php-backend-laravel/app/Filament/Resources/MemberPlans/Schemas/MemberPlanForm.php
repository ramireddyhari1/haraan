<?php

declare(strict_types=1);

namespace App\Filament\Resources\MemberPlans\Schemas;

use App\Models\MemberFeatureDefinition;
use App\Support\Membership\MemberFeature;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class MemberPlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Plan')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(60),

                        TextInput::make('code')
                            ->required()
                            ->maxLength(40)
                            ->alphaDash()
                            ->unique(ignoreRecord: true)
                            // Subscriptions, the app and Razorpay notes all carry this code.
                            ->disabledOn('edit')
                            ->helperText('Stable identifier. Can\'t be changed once the plan exists.'),

                        TextInput::make('tagline')
                            ->maxLength(120)
                            ->columnSpanFull()
                            ->helperText('One line under the plan name in the app.'),

                        Textarea::make('description')
                            ->rows(2)
                            ->maxLength(1000)
                            ->columnSpanFull(),

                        TextInput::make('rank')
                            ->numeric()
                            ->minValue(0)
                            ->required()
                            ->default(10)
                            ->helperText('Higher = more value. Moving to a higher rank is an upgrade (immediate); lower is a downgrade (at period end).'),

                        TextInput::make('sort')
                            ->numeric()
                            ->default(100),

                        Toggle::make('is_active')
                            ->label('Shown in the app')
                            ->default(true),

                        Toggle::make('is_default')
                            ->label('Default plan')
                            ->helperText('What every member has without a paid plan. Exactly one plan is the default.'),
                    ]),

                Section::make('Entitlements')
                    ->description('Enforced server-side for every member on this plan. Blank limit = unlimited; 0 = none.')
                    ->schema([
                        Repeater::make('entitlements')
                            ->relationship()
                            ->hiddenLabel()
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->columns(['default' => 1, 'md' => 3])
                            ->itemLabel(fn (array $state): string => self::featureName((string) ($state['feature_key'] ?? '')))
                            ->default(fn (): array => array_map(
                                fn (string $key): array => [
                                    'feature_key' => $key,
                                    'enabled' => false,
                                    'limit_value' => MemberFeature::isBoolean($key) ? null : 0,
                                ],
                                MemberFeature::keys(),
                            ))
                            ->schema([
                                Hidden::make('feature_key')->required(),

                                Toggle::make('enabled')
                                    ->label('Included')
                                    ->inline(false),

                                TextInput::make('limit_value')
                                    ->label(fn (Get $get): string => MemberFeature::exists((string) $get('feature_key'))
                                        && MemberFeature::type((string) $get('feature_key')) === MemberFeature::TYPE_QUOTA
                                            ? 'Per month'
                                            : 'Limit')
                                    ->numeric()
                                    ->minValue(0)
                                    ->placeholder('Unlimited')
                                    ->visible(fn (Get $get): bool => MemberFeature::exists((string) $get('feature_key'))
                                        && ! MemberFeature::isBoolean((string) $get('feature_key'))),
                            ]),
                    ]),
            ]);
    }

    private static function featureName(string $key): string
    {
        static $names = null;
        $names ??= MemberFeatureDefinition::query()->pluck('name', 'key')->all();

        return ($names[$key] ?? MemberFeature::label($key)) . '  ·  ' . $key;
    }
}
