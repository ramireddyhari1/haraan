<?php

declare(strict_types=1);

namespace App\Filament\Clusters\Finance\Pages;

use App\Filament\Clusters\Finance\FinanceCluster;
use App\Models\AdminAction;
use App\Models\AppIconPlanRule;
use App\Models\AppIconSlot;
use App\Models\AppSetting;
use App\Models\MemberPlan;
use App\Services\Membership\AppIconCatalogue;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Image;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Finance → App icons. The home-screen icons baked into the Android app, and for each one
 * which plans may pick it and which plans get it put on automatically. Saved changes reach
 * phones the next time the app opens; no app update needed.
 *
 * New icon ART still needs an app release (Android can't download launcher icons), which is
 * why the list of icons is fixed here and can't be added to.
 */
class AppIconsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $cluster = FinanceCluster::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static ?string $navigationLabel = 'App icons';

    protected static ?string $title = 'App icons';

    protected static ?string $slug = 'app-icons';

    protected static ?int $navigationSort = 22;

    protected string $view = 'filament.pages.app-icons';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('admin') ?? false;
    }

    /** @return \Illuminate\Support\Collection<int, MemberPlan> */
    private function plans()
    {
        return MemberPlan::query()->orderBy('rank')->orderBy('id')->get();
    }

    /** @return \Illuminate\Support\Collection<int, AppIconSlot> */
    private function slots()
    {
        return AppIconSlot::query()->orderBy('sort')->orderBy('id')->get();
    }

    public function mount(): void
    {
        $this->form->fill($this->stored());
    }

    /** The saved state, in the form's shape. */
    private function stored(): array
    {
        $rules = AppIconPlanRule::query()->get()->groupBy('slot_key');
        $slots = [];
        foreach ($this->slots() as $slot) {
            $r = $rules->get($slot->key, collect());
            $slots[$slot->key] = [
                'name' => $slot->name,
                'is_active' => $slot->is_active,
                'starts_at' => $slot->starts_at?->toDateTimeString(),
                'ends_at' => $slot->ends_at?->toDateTimeString(),
                'pick' => $r->where('can_pick', true)->pluck('plan_id')->map(fn ($id) => (string) $id)->values()->all(),
                'auto' => $r->where('is_auto', true)->pluck('plan_id')->map(fn ($id) => (string) $id)->values()->all(),
            ];
        }

        return ['min_hours' => AppIconCatalogue::minHoursBetweenAuto(), 'slots' => $slots];
    }

    public function form(Schema $schema): Schema
    {
        $plans = $this->plans()->mapWithKeys(fn (MemberPlan $p) => [(string) $p->id => $p->name])->all();

        $sections = [
            Section::make('How it works')
                ->description('Each icon below is built into the Android app. Tick which plans can pick it on their Membership screen, and which plans get it put on their home screen automatically. Changes reach phones the next time the app is opened. New icon designs need an app update; everything else here doesn’t.')
                ->schema([
                    TextInput::make('min_hours')
                        ->label('Fewest hours between automatic icon changes on one phone')
                        ->helperText('Some phones move or drop the home-screen icon when it changes, so automatic changes are spaced out. A plan ending always puts the usual icon back straight away.')
                        ->numeric()->integer()->minValue(0)->maxValue(720)->required(),
                ]),
        ];

        foreach ($this->slots() as $slot) {
            $key = $slot->key;
            $isDefault = $slot->isDefault();
            $sections[] = Section::make($slot->name)
                ->description($isDefault
                    ? 'Everyone always has this one, so a member can always go back to it.'
                    : 'Key: ' . $key)
                ->collapsible()
                ->schema([
                    Grid::make(['default' => 1, 'md' => 6])->schema([
                        Image::make($slot->previewUrl(), $slot->name . ' icon')->imageSize(72)->columnSpan(1),
                        Grid::make(['default' => 1, 'md' => 2])->columnSpan(5)->schema([
                            TextInput::make("slots.{$key}.name")->label('Name in the app')->required()->maxLength(40),
                            Toggle::make("slots.{$key}.is_active")->label('Offered')
                                ->helperText($isDefault ? 'Always offered.' : 'Off hides it, and phones wearing it go back to their usual icon.')
                                ->disabled($isDefault)->dehydrated(! $isDefault),
                            DateTimePicker::make("slots.{$key}.starts_at")->label('From (optional)')->seconds(false)
                                ->visible(! $isDefault),
                            DateTimePicker::make("slots.{$key}.ends_at")->label('Until (optional)')->seconds(false)
                                ->helperText('For a festival or season. While it runs, a plan’s automatic seasonal icon wins over its usual one.')
                                ->after("slots.{$key}.starts_at")
                                ->visible(! $isDefault),
                            CheckboxList::make("slots.{$key}.pick")->label('Plans that can pick it')
                                ->options($plans)->columns(3)
                                ->disabled($isDefault)->dehydrated(! $isDefault)
                                ->helperText($isDefault ? 'Always every plan.' : null),
                            CheckboxList::make("slots.{$key}.auto")->label('Put it on automatically for')
                                ->options($plans)->columns(3)
                                ->helperText('Applied once when a member joins the plan or you change this. They can still pick another icon after.'),
                        ]),
                    ]),
                ]);
        }

        return $schema->components($sections)->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [$this->saveAction()];
    }

    public function saveAction(): Action
    {
        return Action::make('save')
            ->label('Save changes')
            ->icon('heroicon-m-check')
            ->requiresConfirmation(fn (): bool => $this->changesEveryone())
            ->modalHeading('Change the icon for every free member?')
            ->modalDescription('You’re changing which icon goes on automatically for the default (free) plan. That changes the home-screen icon of every member without a paid plan, and of signed-out users, the next time they open the app.')
            ->modalSubmitActionLabel('Yes, change it')
            ->action(fn () => $this->save());
    }

    /** Whether this save changes the default plan's automatic icon. */
    private function changesEveryone(): bool
    {
        $default = MemberPlan::query()->where('is_default', true)->value('id');
        if ($default === null) {
            return false;
        }
        $id = (string) $default;
        $stored = $this->stored()['slots'];
        foreach ($this->data['slots'] ?? [] as $key => $row) {
            $before = in_array($id, $stored[$key]['auto'] ?? [], true);
            $after = in_array($id, array_map('strval', (array) ($row['auto'] ?? [])), true);
            if ($before !== $after) {
                return true;
            }
        }

        return false;
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $before = $this->stored();
        $planIds = $this->plans()->pluck('id')->map(fn ($id) => (int) $id)->all();

        DB::transaction(function () use ($state, $planIds): void {
            AppSetting::set(AppIconCatalogue::MIN_HOURS_KEY, (string) (int) $state['min_hours'], 'membership');

            foreach ($this->slots() as $slot) {
                $row = $state['slots'][$slot->key] ?? [];
                $isDefault = $slot->isDefault();
                $slot->fill([
                    'name' => trim((string) ($row['name'] ?? $slot->name)),
                    'is_active' => $isDefault ? true : (bool) ($row['is_active'] ?? false),
                    'starts_at' => $isDefault ? null : ($row['starts_at'] ?? null ?: null),
                    'ends_at' => $isDefault ? null : ($row['ends_at'] ?? null ?: null),
                ])->save();

                $pick = $isDefault ? $planIds : array_map('intval', (array) ($row['pick'] ?? []));
                $auto = array_map('intval', (array) ($row['auto'] ?? []));
                foreach ($planIds as $planId) {
                    // Putting an icon on automatically implies the plan may wear it.
                    $isAuto = in_array($planId, $auto, true);
                    AppIconPlanRule::query()->updateOrCreate(
                        ['slot_key' => $slot->key, 'plan_id' => $planId],
                        ['can_pick' => $isAuto || in_array($planId, $pick, true), 'is_auto' => $isAuto],
                    );
                }
            }
        });

        AppIconCatalogue::syncLegacyFeatures();
        $after = $this->stored();
        AdminAction::log('app_icons.updated', ['before' => $before, 'after' => $after]);
        $this->form->fill($after);

        Notification::make()->title('App icons saved')->body('Phones pick this up the next time the app opens.')->success()->send();
    }
}
