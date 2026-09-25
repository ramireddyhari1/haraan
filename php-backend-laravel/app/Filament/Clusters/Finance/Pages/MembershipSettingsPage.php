<?php

declare(strict_types=1);

namespace App\Filament\Clusters\Finance\Pages;

use App\Filament\Clusters\Finance\FinanceCluster;
use App\Models\AdminAction;
use App\Models\AppSetting;
use App\Models\FeatureFlag;
use App\Support\Membership\MembershipSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Finance → Membership settings. Everything about member plans that isn't a plan, a price or
 * an entitlement (those have their own resources): checkout and renewal timings, booking-perk
 * ceilings, the default venue booking window, the insights cooldown, whether the app may sell
 * plans itself, and the copy members read. Saved values take effect immediately.
 */
class MembershipSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $cluster = FinanceCluster::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationLabel = 'Membership settings';

    protected static ?string $title = 'Membership settings';

    protected static ?string $slug = 'membership-settings';

    protected static ?int $navigationSort = 21;

    protected string $view = 'filament.pages.membership-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('admin') ?? false;
    }

    public function mount(): void
    {
        $values = [];
        foreach (array_keys(MembershipSettings::NUMBERS) as $key) {
            $values[$key] = MembershipSettings::int($key);
        }
        foreach (array_keys(MembershipSettings::TEXTS) as $key) {
            $values[$key] = MembershipSettings::text($key);
        }
        $values['in_app_checkout'] = (bool) FeatureFlag::query()->where('key', MembershipSettings::IN_APP_CHECKOUT_FLAG)->value('enabled');

        $this->form->fill($values);
    }

    public function form(Schema $schema): Schema
    {
        $number = function (string $key): TextInput {
            [, , $min, $max, $label, $help] = MembershipSettings::NUMBERS[$key];

            return TextInput::make($key)->label($label)->numeric()->integer()->required()
                ->minValue($min)->maxValue($max)->helperText($help !== '' ? $help : null);
        };
        $text = function (string $key): TextInput {
            [, $max, $label, $help] = MembershipSettings::TEXTS[$key];

            return TextInput::make($key)->label($label)->required()->maxLength($max)->helperText($help);
        };

        return $schema
            ->components([
                Section::make('Selling plans')
                    ->description('Plans are always sold on haraan.app/membership.')
                    ->schema([
                        Toggle::make('in_app_checkout')
                            ->label('Let the Android app sell plans too')
                            ->helperText('Off keeps the app compliant with Google Play billing rules: it shows plans and the note below instead of a buy button. Turn on only if your Play setup allows it. Rollout and minimum app version are under Platform → Feature flags.'),
                        $text('app_store_note'),
                        $text('web_headline'),
                        $text('web_lede'),
                    ]),
                Section::make('Checkout & renewals')
                    ->columns(2)
                    ->schema([
                        $number('checkout_ttl_minutes'),
                        $number('grace_hours'),
                        $number('total_count_month'),
                        $number('total_count_quarter'),
                        $number('total_count_half_year'),
                        $number('total_count_year'),
                    ]),
                Section::make('Booking perks')
                    ->description('How much each plan gets is set per plan under Member plans. These are the platform-wide limits.')
                    ->columns(2)
                    ->schema([
                        $number('early_access_max_hours'),
                        $number('priority_booking_max_days'),
                        $number('venue_booking_window_days'),
                    ]),
                Section::make('Advanced insights')
                    ->schema([$number('insight_sport_cooldown_days')]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        foreach (array_merge(array_keys(MembershipSettings::NUMBERS), array_keys(MembershipSettings::TEXTS)) as $key) {
            AppSetting::set(MembershipSettings::storageKey($key), isset($state[$key]) ? trim((string) $state[$key]) : null, MembershipSettings::GROUP);
        }

        FeatureFlag::query()->updateOrCreate(
            ['key' => MembershipSettings::IN_APP_CHECKOUT_FLAG],
            ['enabled' => (bool) ($state['in_app_checkout'] ?? false)] + (FeatureFlag::query()->where('key', MembershipSettings::IN_APP_CHECKOUT_FLAG)->exists() ? [] : [
                'name' => 'Membership: in-app checkout',
                'description' => 'Lets the Android app sell Pro and Hero directly. Off = the app shows plans only.',
                'rollout_percentage' => 100,
            ]),
        );

        AdminAction::log('membership_settings.updated', ['values' => $state]);
        Notification::make()->title('Membership settings saved')->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')->label('Save changes')->icon('heroicon-m-check')->action('save'),
        ];
    }
}
