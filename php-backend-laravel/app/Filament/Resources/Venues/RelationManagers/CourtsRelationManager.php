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
    private const SPORTS = ['Cricket', 'Football', 'Badminton', 'Pickleball', 'Basketball', 'Tennis', 'Volleyball'];

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
                            ->content('This venue has no slots yet. Create them in the Slots tab (Generate slots), then set this court\'s prices here.'),
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

        return [
            'courts' => $courts,
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
