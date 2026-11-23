<?php

namespace App\Filament\Resources\Venues\RelationManagers;

use App\Models\AdminAction;
use App\Models\Booking;
use App\Models\Venue;
use App\Models\VenueCourt;
use App\Models\VenueSlot;
use App\Support\BusinessClock;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Courts are the physical bookable units inside a venue. Each court lists the sports it can
 * host, so ONE ground shared by football and cricket is a SINGLE court with both sports —
 * booking it for one sport blocks the other for that time. Three separate playing areas are
 * three courts. Per-court price overrides the venue base price when set.
 *
 * Slots are the venue's start times and are shared by every court, but what a court charges
 * at each of them is the court's own business. The board above the table puts the two
 * together: every court with the slots it is sold in and the price it charges in each, as
 * the desk and checkout would compute it.
 */
class CourtsRelationManager extends RelationManager
{
    protected static string $relationship = 'courts';

    protected static ?string $title = 'Courts & slots';

    /** Sports a court can host — the same set the venue form offers. */
    private const SPORTS = ['Cricket', 'Cricket Nets', 'Football', 'Badminton', 'Pickleball', 'Basketball', 'Tennis', 'Volleyball'];

    /** The weekday the board shows ("Monday"…). Empty = today's. */
    public string $boardDay = '';

    /**
     * Filament makes relation managers read-only on a resource View page by
     * default. The Venue 360 IS the view page, and these are the controls it
     * exists to offer — so the default is declined here deliberately. Authority
     * still comes from the resource and the capability checks on each action.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function setBoardDay(string $day): void
    {
        $this->boardDay = in_array($day, VenueSlot::WEEKDAYS, true) ? $day : '';
    }

    public function form(Schema $schema): Schema
    {
        // The standard list plus anything the venue already uses, without "Cricket"/"cricket" twins.
        $sports = self::SPORTS;
        foreach ($this->venueSports() as $v) {
            if (! in_array(strtolower($v), array_map('strtolower', $sports), true)) {
                $sports[] = ucfirst($v);
            }
        }

        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Court 1, Pitch A, Table 04, PS5 Station…')
                    ->helperText('The physical unit. If one ground is used for several sports, make ONE court and tick every sport below.'),
                Select::make('kind')
                    ->label('What is it')
                    ->options(VenueCourt::KINDS)
                    ->default('court')
                    ->native(false)
                    ->helperText('Drives the wording on the desk. A café books tables and stations; a turf books courts.'),
                TextInput::make('seats')
                    ->label('Seats')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(99)
                    ->placeholder('Leave blank if capacity is not a thing here')
                    ->helperText('Party size this unit holds. Set for tables and rooms; leave blank for a pool table or a pitch — the desk then never blocks a booking on party size.'),
                Select::make('sports')
                    ->label('Sports this court hosts')
                    ->multiple()
                    ->options(array_combine($sports, $sports))
                    ->helperText('Ticking two sports means the SAME court hosts both — a booking for one blocks the other at that time. Leave empty to allow every sport the venue offers. A sport the venue does not list yet is added to it on save.'),
                TextInput::make('price')
                    ->label('Base price per hour')
                    ->numeric()
                    ->minValue(0)
                    ->prefix('₹')
                    ->placeholder('Leave blank to use the venue price')
                    ->helperText('Off-peak / normal hourly rate. A price set on a slot (Slot prices) wins over this at that time.'),
                TextInput::make('peak_price')
                    ->label('Peak price per hour')
                    ->numeric()
                    ->minValue(1)
                    ->prefix('₹')
                    ->placeholder('Leave blank for no peak pricing')
                    ->helperText('Higher rate for busy times. Only applies when you set the days and/or window below.'),
                Select::make('peak_days')
                    ->label('Peak days')
                    ->multiple()
                    ->options([
                        'Mon' => 'Monday', 'Tue' => 'Tuesday', 'Wed' => 'Wednesday',
                        'Thu' => 'Thursday', 'Fri' => 'Friday', 'Sat' => 'Saturday', 'Sun' => 'Sunday',
                    ])
                    ->helperText('Leave empty to apply peak pricing every day (within the window below).'),
                TimePicker::make('peak_start')
                    ->label('Peak from')
                    ->seconds(false)
                    ->format('H:i')
                    ->displayFormat('h:i A')
                    ->helperText('e.g. 6:00 PM. Leave both blank to apply all day on the peak days.'),
                TimePicker::make('peak_end')
                    ->label('Peak until')
                    ->seconds(false)
                    ->format('H:i')
                    ->displayFormat('h:i A'),
                TextInput::make('sort_order')
                    ->numeric()
                    ->default(0),
                Toggle::make('is_active')
                    ->label('Open for booking')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('sort_order')
            ->header(fn () => view('filament.venue.court-board', $this->courtBoard()))
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->weight('semibold')
                    ->description(fn (VenueCourt $r): string => VenueCourt::KINDS[$r->kind ?? 'court'] ?? 'Court'),
                TextColumn::make('sports')
                    ->label('Sports')
                    ->badge()
                    ->placeholder('All venue sports'),
                TextColumn::make('price')
                    ->label('Base ₹/hr')
                    ->formatStateUsing(fn ($state): string => '₹'.number_format((float) $state))
                    ->placeholder('Venue price'),
                TextColumn::make('peak_price')
                    ->label('Peak ₹/hr')
                    ->formatStateUsing(fn ($state): string => (float) $state > 0 ? '₹'.number_format((float) $state) : '—')
                    ->placeholder('—'),
                TextColumn::make('slot_prices')
                    ->label('Own slot prices')
                    ->state(fn (VenueCourt $r): string => (string) $this->ownSlotPriceCount($r))
                    ->formatStateUsing(fn (string $state): string => $state === '0' ? '—' : $state.' '.((int) $state === 1 ? 'slot' : 'slots'))
                    ->color('gray'),
                IconColumn::make('is_active')
                    ->label('Open')
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add court')
                    ->after(fn (VenueCourt $record) => $this->afterCourtSaved($record, 'venue.court_created')),
            ])
            ->recordActions([
                $this->slotPricesAction(),
                EditAction::make()
                    ->after(fn (VenueCourt $record) => $this->afterCourtSaved($record, 'venue.court_updated')),
                DeleteAction::make()
                    ->before(function (VenueCourt $record, DeleteAction $action): void {
                        $upcoming = $this->upcomingBookings($record);
                        if ($upcoming > 0) {
                            Notification::make()
                                ->title('This court has '.$upcoming.' upcoming '.($upcoming === 1 ? 'booking' : 'bookings'))
                                ->body('Deleting it would hide them from the desk. Turn the court off instead (Edit → Open for booking), or move or cancel those bookings first.')
                                ->danger()
                                ->persistent()
                                ->send();
                            $action->cancel();
                        }
                    })
                    ->after(fn (VenueCourt $record) => AdminAction::log('venue.court_deleted', ['court' => $record->name], $this->getOwnerRecord())),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(function ($records, DeleteBulkAction $action): void {
                            $busy = collect($records)->filter(fn (VenueCourt $c): bool => $this->upcomingBookings($c) > 0);
                            if ($busy->isNotEmpty()) {
                                Notification::make()
                                    ->title('Not deleted')
                                    ->body($busy->pluck('name')->implode(', ').' still '.($busy->count() === 1 ? 'has' : 'have').' upcoming bookings. Turn '.($busy->count() === 1 ? 'it' : 'them').' off instead.')
                                    ->danger()
                                    ->send();
                                $action->cancel();
                            }
                        }),
                ]),
            ])
            ->emptyStateHeading('No courts yet')
            ->emptyStateDescription('Add the pitches, courts, tables or lanes people book here. Each one gets its own row on the desk.');
    }

    /**
     * One court's price at every slot, in one form. Blank leaves the slot to the court's
     * normal rate (or the slot's all-courts price). Saves into the same
     * `venue_slots.court_prices` the partner app and the Slots tab edit.
     */
    private function slotPricesAction(): Action
    {
        return Action::make('slotPrices')
            ->label('Slot prices')
            ->icon('heroicon-m-currency-rupee')
            ->color('primary')
            ->modalHeading(fn (VenueCourt $record): string => $record->name.' — price at each slot')
            ->modalDescription(fn (VenueCourt $record): string => 'Leave a box empty to charge '.($record->price !== null ? '₹'.number_format((float) $record->price).' (this court\'s rate'.($record->peakRate() ? ', peak ₹'.number_format($record->peakRate()).' in its window' : '').')' : 'the venue rate').'. A slot\'s all-courts price shows as the placeholder where one is set.')
            ->modalSubmitActionLabel('Save prices')
            ->fillForm(function (VenueCourt $record): array {
                $out = [];
                foreach ($this->getOwnerRecord()->slots()->get() as $slot) {
                    $own = $slot->courtPriceList()[(int) $record->id] ?? null;
                    $out['p'][$slot->id] = $own !== null ? (string) (int) $own : null;
                }

                return $out;
            })
            ->schema(function (VenueCourt $record): array {
                $slots = $this->getOwnerRecord()->slots()->get();
                $order = array_flip([VenueSlot::EVERY_DAY, ...VenueSlot::WEEKDAYS]);
                $groups = $slots->groupBy(fn (VenueSlot $s): string => VenueSlot::normaliseDay($s->day))
                    ->sortBy(fn ($rows, string $day): int => $order[$day] ?? 99);

                if ($groups->isEmpty()) {
                    return [
                        \Filament\Forms\Components\Placeholder::make('none')
                            ->hiddenLabel()
                            ->content('This venue has no slots yet. Set its opening hours (or switch on Open 24 hours) and save, then set this court\'s prices here.'),
                    ];
                }

                return $groups->map(fn ($rows, string $day) => Section::make($day === VenueSlot::EVERY_DAY ? 'Every day' : $day)
                    ->description($day === VenueSlot::EVERY_DAY ? 'Runs on every date unless a weekday row has the same time.' : 'Replaces the every-day slot at the same time on '.$day.'s.')
                    ->collapsible()
                    // Open the day the board is showing (and every-day rows); fold the rest.
                    ->collapsed($groups->count() > 1 && $day !== VenueSlot::EVERY_DAY
                        && $day !== ($this->boardDay !== '' ? $this->boardDay : BusinessClock::now()->format('l')))
                    ->schema([
                        Grid::make(['default' => 2, 'md' => 4])->schema(
                            $rows->sortBy(fn (VenueSlot $s): int => VenueSlot::startMinutes($s->time) ?? PHP_INT_MAX)
                                ->map(fn (VenueSlot $s) => TextInput::make('p.'.$s->id)
                                    ->label($s->time.($s->is_available ? '' : ' (closed)').($s->allowsCourt($record) ? '' : ' (other sport)'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->prefix('₹')
                                    ->placeholder((float) $s->price > 0 ? number_format((float) $s->price).' slot' : 'court rate'))
                                ->values()
                                ->all()
                        ),
                    ]))->values()->all();
            })
            ->action(function (VenueCourt $record, array $data): void {
                $changed = 0;
                DB::transaction(function () use ($record, $data, &$changed): void {
                    foreach ($this->getOwnerRecord()->slots()->lockForUpdate()->get() as $slot) {
                        $raw = $data['p'][$slot->id] ?? null;
                        $new = $raw !== null && $raw !== '' && (float) $raw > 0 ? (float) $raw : null;
                        $prices = is_array($slot->court_prices) ? $slot->court_prices : [];
                        $old = isset($prices[$record->id]) ? (float) $prices[$record->id] : null;
                        if ($old === $new) {
                            continue;
                        }
                        if ($new === null) {
                            unset($prices[$record->id], $prices[(string) $record->id]);
                        } else {
                            $prices[$record->id] = $new;
                        }
                        $slot->court_prices = $prices === [] ? null : $prices;
                        $slot->save();
                        $changed++;
                    }
                });

                AdminAction::log('venue.court_slot_prices', ['court' => $record->name, 'changed' => $changed], $this->getOwnerRecord());

                Notification::make()
                    ->title($changed === 0 ? 'Nothing changed' : $changed.' slot '.($changed === 1 ? 'price' : 'prices').' saved for '.$record->name)
                    ->success()
                    ->send();
            });
    }

    /**
     * Tap a slot on the board to change it: its all-courts price, each court's price, which
     * sports it runs for, and whether it is sold. This replaced the separate Slots tab, whose
     * own "Generate slots" made times that disagreed with the opening hours. The times
     * themselves now come only from the hours (Venue::regenerateSlotsFromHours).
     */
    public function editSlotAction(): Action
    {
        $slotFor = fn (array $arguments): ?VenueSlot => $this->getOwnerRecord()->slots()->find($arguments['slot'] ?? 0);

        return Action::make('editSlot')
            ->modalHeading(function (array $arguments) use ($slotFor): string {
                $s = $slotFor($arguments);

                return $s ? $s->time.' · '.(VenueSlot::normaliseDay($s->day) === VenueSlot::EVERY_DAY ? 'every day' : $s->day) : 'Slot';
            })
            ->modalDescription('Blank prices fall back to each court\'s own rate (and its peak price).')
            ->modalSubmitActionLabel('Save slot')
            ->modalWidth('lg')
            ->fillForm(function (array $arguments) use ($slotFor): array {
                $s = $slotFor($arguments);
                if (! $s) {
                    return [];
                }
                $court = [];
                foreach ($s->courtPriceList() as $id => $p) {
                    $court[$id] = (string) (int) $p;
                }

                return [
                    'is_available' => (bool) $s->is_available,
                    'price' => (float) $s->price > 0 ? (string) (int) $s->price : null,
                    'court_prices' => $court,
                    'sports' => $s->sportsList(),
                    'every_day' => false,
                ];
            })
            ->schema(function (array $arguments) use ($slotFor): array {
                $s = $slotFor($arguments);
                $venue = $this->getOwnerRecord();

                return [
                    \Filament\Forms\Components\Placeholder::make('ladder')
                        ->label('What each court charges now, and why')
                        ->content(fn (): \Illuminate\Support\HtmlString => new \Illuminate\Support\HtmlString(
                            $s ? $this->priceLadderHtml($s) : ''
                        )),
                    Toggle::make('is_available')->label('Sold at this time'),
                    TextInput::make('price')
                        ->label('Price for all courts')
                        ->numeric()->minValue(0)->prefix('₹')
                        ->placeholder('Each court\'s own rate')
                        ->helperText('Replaces the court rate and its peak price at this time.'),
                    Grid::make(['default' => 2])->schema(
                        $venue->courts()->where('is_active', true)->orderBy('sort_order')->get()
                            ->map(fn (VenueCourt $c) => TextInput::make('court_prices.'.$c->id)
                                ->label($c->name)
                                ->numeric()->minValue(0)->prefix('₹')
                                ->placeholder('₹'.number_format((float) ($c->price ?? $venue->price ?? 0)).' rate'))
                            ->all()
                    ),
                    Select::make('sports')
                        ->label('Runs for')
                        ->multiple()
                        ->native(false)
                        ->options($this->slotSportOptions())
                        ->placeholder('All sports'),
                    Toggle::make('every_day')
                        ->label('Apply to '.($s?->time ?? 'this time').' on every day')
                        ->helperText('Off: only this day changes.'),
                ];
            })
            ->action(function (array $arguments, array $data) use ($slotFor): void {
                $slot = $slotFor($arguments);
                if (! $slot) {
                    return;
                }
                $venue = $this->getOwnerRecord();
                $courtIds = $venue->courts()->pluck('id')->map(fn ($id) => (int) $id)->all();
                $prices = [];
                foreach ((array) ($data['court_prices'] ?? []) as $id => $p) {
                    if (in_array((int) $id, $courtIds, true) && $p !== null && $p !== '' && (float) $p > 0) {
                        $prices[(int) $id] = (float) $p;
                    }
                }
                $sports = array_values(array_filter((array) ($data['sports'] ?? [])));
                $start = VenueSlot::startMinutes((string) $slot->time);
                $targets = ! empty($data['every_day'])
                    ? $venue->slots()->get()->filter(fn (VenueSlot $s): bool => VenueSlot::startMinutes((string) $s->time) === $start)
                    : collect([$slot]);

                DB::transaction(function () use ($targets, $data, $prices, $sports): void {
                    foreach ($targets as $t) {
                        $t->is_available = (bool) ($data['is_available'] ?? true);
                        $t->price = isset($data['price']) && $data['price'] !== '' ? (float) $data['price'] : 0;
                        $t->court_prices = $prices === [] ? null : $prices;
                        $t->sports = $sports === [] ? null : $sports;
                        $t->save();
                    }
                });

                AdminAction::log('venue.slot_updated', ['time' => $slot->time, 'day' => $slot->day, 'rows' => $targets->count()], $venue);
                Notification::make()
                    ->title($slot->time.' saved'.($targets->count() > 1 ? ' on '.$targets->count().' days' : ''))
                    ->success()
                    ->send();
            });
    }

    /**
     * Rebuild the slot times from the opening hours. Keeps every price, sport limit and
     * closed switch on times the hours still cover. Fixes a venue whose slots were made by
     * the old generator and no longer match its hours.
     */
    public function syncSlotsAction(): Action
    {
        return Action::make('syncSlots')
            ->requiresConfirmation()
            ->modalHeading('Match slots to opening hours')
            ->modalDescription(fn (): string => 'Creates a slot every '.$this->getOwnerRecord()->slotLength().' min inside the opening hours ('.$this->getOwnerRecord()->displayHours().') and removes times outside them. Prices on times that stay are kept. Bookings are never touched.')
            ->modalSubmitActionLabel('Match slots')
            ->action(function (): void {
                $venue = $this->getOwnerRecord();
                $before = $venue->slots()->count();
                $venue->regenerateSlotsFromHours();
                $after = $venue->slots()->count();
                AdminAction::log('venue.slots_synced', ['before' => $before, 'after' => $after], $venue);
                Notification::make()->title('Slots now match the opening hours')->body($after.' slots across the week.')->success()->send();
            });
    }

    /** The date the board is showing: the next date on the chosen weekday (today when it matches). */
    private function boardDate(): \Illuminate\Support\Carbon
    {
        $date = BusinessClock::now()->startOfDay();
        $day = $this->boardDay !== '' ? $this->boardDay : $date->format('l');
        for ($i = 0; $i < 7 && $date->format('l') !== $day; $i++) {
            $date->addDay();
        }

        return Carbon::parse($date->toDateString());
    }

    /**
     * For each court, the price steps in the order VenueCourt::rateFor() tries them, with the
     * one that wins marked. Price can come from five places (pricing matrix, this court's
     * slot price, the all-courts slot price, the court's peak, the court's base / venue
     * price); this shows which one is in charge and where to change it.
     */
    private function priceLadderHtml(VenueSlot $slot): string
    {
        $venue = $this->getOwnerRecord();
        $date = $this->boardDate();
        $venuePrice = (int) ($venue->price ?? 0);
        $e = fn ($v): string => e((string) $v);
        $inr = fn ($n): string => '₹'.number_format((float) $n);
        $out = '';

        foreach ($venue->courts()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get() as $c) {
            $own = $slot->courtPriceList()[(int) $c->id] ?? null;
            $all = (float) $slot->price > 0 ? (float) $slot->price : null;
            $slotPrice = $slot->priceForCourt($c);
            $rate = $c->rateFor($date, $slot->time, $venuePrice, $slotPrice);
            $base = $slotPrice ?? (float) ($c->price ?? $venuePrice);

            $rule = \App\Models\PricingRule::where('venue_id', $venue->id)
                ->where(fn ($q) => $q->where('venue_court_id', $c->id)->orWhereNull('venue_court_id'))
                ->where('is_active', true)
                ->orderBy('priority', 'desc')
                ->orderByRaw('CASE WHEN venue_court_id IS NOT NULL THEN 0 ELSE 1 END')
                ->orderBy('id', 'desc')
                ->get()
                ->first(fn ($r) => $r->matches($date, $slot->time));
            $peak = $slotPrice === null && $c->peakRate() !== null && $c->isPeak($date, $slot->time);

            $winner = match (true) {
                $rule !== null => 'rule',
                $own !== null => 'own',
                $all !== null => 'all',
                $peak => 'peak',
                $c->price !== null => 'court',
                default => 'venue',
            };
            $steps = [
                'rule' => ['Pricing matrix', $rule ? $e($rule->name ?: 'Rule').' (on '.$inr($base).')' : 'no rule at this time', 'Pricing matrix tab'],
                'own' => ['This court at this slot', $own !== null ? $inr($own) : 'not set', 'box below'],
                'all' => ['All courts at this slot', $all !== null ? $inr($all) : 'not set', 'box below'],
                'peak' => ['Court peak price', $c->peakRate() ? $inr($c->peakRate()).($peak ? '' : ' (not in window)') : 'not set', 'court → Edit'],
                'court' => ['Court base price', $c->price !== null ? $inr($c->price) : 'not set', 'court → Edit'],
                'venue' => ['Venue price', $inr($venuePrice), 'form → Pricing'],
            ];

            $rows = '';
            foreach ($steps as $k => [$label, $value, $where]) {
                $win = $k === $winner;
                $rows .= '<div style="display:flex;justify-content:space-between;gap:8px;padding:3px 8px;border-radius:6px;'
                    .($win ? 'background:rgba(37,99,235,.10);font-weight:650' : 'opacity:.62').'">'
                    .'<span>'.($win ? '✓ ' : '').$label.' <span style="opacity:.7;font-weight:400">· '.$where.'</span></span>'
                    .'<span>'.$value.'</span></div>';
            }
            $out .= '<div style="border:1px solid rgba(127,127,127,.25);border-radius:10px;padding:8px;margin-bottom:8px;font-size:12.5px">'
                .'<div style="display:flex;justify-content:space-between;font-weight:700;margin-bottom:4px"><span>'.$e($c->name).'</span><span>'.$inr($rate).'/hr</span></div>'
                .$rows.'</div>';
        }

        return $out !== ''
            ? $out.'<div style="font-size:11.5px;opacity:.7">Checked top to bottom; the first one that applies wins. Shown for '.$e($date->format('l j M')).'.</div>'
            : '<div style="font-size:12.5px;opacity:.7">No active courts.</div>';
    }

    /** Sports a slot can be limited to: the venue's own plus every sport its courts host. */
    private function slotSportOptions(): array
    {
        $venue = $this->getOwnerRecord();
        $list = is_array($venue->sports) ? $venue->sports : [];
        foreach ($venue->courts()->get() as $c) {
            $list = [...$list, ...$c->sportsList()];
        }
        $byLower = [];
        foreach (array_filter(array_map(fn ($x) => trim((string) $x), $list)) as $sp) {
            $byLower[strtolower($sp)] ??= $sp;
        }
        $list = array_values($byLower);
        sort($list);

        return array_combine($list, $list) ?: [];
    }

    /** Keep the venue's sports list covering every sport its courts host, and log the change. */
    private function afterCourtSaved(VenueCourt $court, string $event): void
    {
        $venue = $this->getOwnerRecord();
        $have = is_array($venue->sports) ? $venue->sports : [];
        // Case-blind: older venues store "cricket", the court form offers "Cricket".
        $haveLower = array_map(fn ($x) => strtolower(trim((string) $x)), $have);
        $missing = array_values(array_filter($court->sportsList(), fn (string $sp): bool => ! in_array(strtolower($sp), $haveLower, true)));
        if ($missing !== []) {
            $venue->sports = array_values(array_unique([...$have, ...$missing]));
            $venue->save();
            Notification::make()
                ->title(implode(', ', $missing).' added to the venue\'s sports')
                ->body('So players filtering by '.(count($missing) === 1 ? 'that sport' : 'those sports').' can find this venue.')
                ->info()
                ->send();
        }

        AdminAction::log($event, ['court' => $court->name], $venue);
    }

    private function venueSports(): array
    {
        $s = $this->getOwnerRecord()->sports;

        return is_array($s) ? array_values(array_filter($s)) : [];
    }

    private function upcomingBookings(VenueCourt $court): int
    {
        return Booking::query()
            ->where('venue_court_id', $court->id)
            ->whereDate('slot_date', '>=', BusinessClock::today())
            ->whereNotIn(DB::raw('lower(status)'), ['cancelled', 'canceled', 'refunded', 'failed', 'expired'])
            ->count();
    }

    private function ownSlotPriceCount(VenueCourt $court): int
    {
        return $this->getOwnerRecord()->slots()->get()
            ->filter(fn (VenueSlot $s): bool => isset($s->courtPriceList()[(int) $court->id]))
            ->count();
    }

    /**
     * Everything the court board draws: each court with the slots it is sold in on the
     * chosen weekday and the rate it charges in each — computed by the same
     * {@see VenueCourt::rateFor()} the desk, app and checkout use, so the board can't
     * disagree with what a customer pays.
     *
     * @return array<string, mixed>
     */
    public function courtBoard(): array
    {
        /** @var Venue $venue */
        $venue = $this->getOwnerRecord();
        $today = BusinessClock::now()->startOfDay();
        $day = $this->boardDay !== '' ? $this->boardDay : $today->format('l');

        // The next date that falls on that weekday (today when it matches), so peak days
        // and pricing rules resolve exactly as they will for a real booking.
        $date = $today->copy();
        for ($i = 0; $i < 7 && $date->format('l') !== $day; $i++) {
            $date->addDay();
        }

        $allSlots = $venue->slots()->get();
        $rows = VenueSlot::forDate($allSlots, $date);
        $venuePrice = (int) ($venue->price ?? 0);

        $courts = $venue->courts()->orderBy('sort_order')->orderBy('id')->get()->map(function (VenueCourt $c) use ($rows, $date, $venuePrice): array {
            $chips = $rows->map(function (VenueSlot $s) use ($c, $date, $venuePrice): array {
                $own = $s->courtPriceList()[(int) $c->id] ?? null;
                $slotAll = (float) $s->price > 0 ? (float) $s->price : null;
                $allowed = $s->allowsCourt($c);
                $rate = $c->rateFor(Carbon::parse($date->toDateString()), $s->time, $venuePrice, $s->priceForCourt($c));
                $source = match (true) {
                    $own !== null => 'own',
                    $slotAll !== null => 'slot',
                    $c->peakRate() !== null && $c->isPeak(Carbon::parse($date->toDateString()), $s->time) => 'peak',
                    default => 'base',
                };

                return [
                    'id' => (int) $s->id,
                    'time' => $s->time,
                    'rate' => $rate,
                    'source' => $source,
                    'open' => (bool) $s->is_available && $allowed,
                    'why' => ! $s->is_available ? 'Slot is closed' : (! $allowed ? 'Slot runs for '.implode(', ', $s->sportsList()).' only' : null),
                    'day' => VenueSlot::normaliseDay($s->day),
                ];
            })->values()->all();

            $sold = collect($chips)->where('open', true);

            return [
                'id' => (int) $c->id,
                'name' => $c->name,
                'kind' => $c->kind ?? 'court',
                'sport' => $c->sportsList()[0] ?? null,
                'sports' => $c->sportsList(),
                'active' => (bool) $c->is_active,
                'base' => $c->price !== null ? (int) $c->price : $venuePrice,
                'baseFromVenue' => $c->price === null,
                'peak' => $c->peakRate(),
                'peakWhen' => $c->peakRate() ? trim(implode(' ', array_filter([
                    $c->peakDaysList() !== [] ? implode(', ', $c->peakDaysList()) : 'Daily',
                    $c->peak_start && $c->peak_end ? Carbon::parse($c->peak_start)->format('g:i A').'–'.Carbon::parse($c->peak_end)->format('g:i A') : null,
                ]))) : null,
                'chips' => $chips,
                'openCount' => $sold->count(),
                'min' => $sold->min('rate'),
                'max' => $sold->max('rate'),
            ];
        })->all();

        // Do the slot rows still match the opening hours? The old Slots tab generator made
        // "Every day" rows and its own times, so a venue could sell hours it never opens.
        $hasHours = is_array($venue->hours_json) && $venue->hours_json !== [];
        $inSync = true;
        if ($hasHours) {
            $step = $venue->slotLength();
            $want = [];
            $full = ['Mon' => 'Monday', 'Tue' => 'Tuesday', 'Wed' => 'Wednesday', 'Thu' => 'Thursday', 'Fri' => 'Friday', 'Sat' => 'Saturday', 'Sun' => 'Sunday'];
            foreach ($full as $key => $name) {
                foreach ($venue->windowsForWeekday($key) as [$o, $c]) {
                    for ($m = $o; $m + $step <= $c; $m += $step) {
                        $want[$name.'|'.$m] = true;
                    }
                }
            }
            $have = $allSlots->mapWithKeys(fn (VenueSlot $s): array => [$s->day.'|'.VenueSlot::startMinutes((string) $s->time) => true])->all();
            $inSync = $have == $want;
        }

        return [
            'courts' => $courts,
            'hasHours' => $hasHours,
            'hoursUrl' => \App\Filament\Resources\Venues\VenueResource::getUrl('edit', ['record' => $venue]),
            'hoursText' => $hasHours ? $venue->displayHours() : '',
            'inSync' => $inSync,
            'day' => $day,
            'date' => $date,
            'isToday' => $date->isSameDay($today),
            'todayName' => $today->format('l'),
            'slotCount' => $rows->count(),
            'hasSlots' => $allSlots->isNotEmpty(),
            'slotMinutes' => (int) ($venue->slot_minutes ?: 60),
        ];
    }
}
