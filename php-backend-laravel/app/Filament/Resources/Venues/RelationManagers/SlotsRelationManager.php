<?php

namespace App\Filament\Resources\Venues\RelationManagers;

use App\Models\AdminAction;
use App\Models\VenueSlot;
use App\Support\SlotGenerator;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SlotsRelationManager extends RelationManager
{
    protected static string $relationship = 'slots';

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

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // The website and the app show a slot only on its day, so the day has to be
                // chosen here. This form used to ask for the time alone, and new rows got the
                // column default "Today", which matches no date: the slot never showed.
                Select::make('day')
                    ->options(array_combine(
                        [VenueSlot::EVERY_DAY, ...VenueSlot::WEEKDAYS],
                        [VenueSlot::EVERY_DAY, ...VenueSlot::WEEKDAYS],
                    ))
                    ->default(VenueSlot::EVERY_DAY)
                    ->required()
                    ->native(false)
                    ->helperText('Every-day slots run on every date. A weekday slot at the same time replaces the every-day one on that day.'),
                // Same rules as the partner app: a start time the booking engine can read,
                // and one row per day and time. Saved as "6:00 AM" whatever the spelling.
                TextInput::make('time')
                    ->label('Start time')
                    ->required()
                    ->maxLength(40)
                    ->placeholder('6:00 AM')
                    ->rules([
                        fn (?VenueSlot $record, $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($record, $get): void {
                            $start = VenueSlot::startMinutes((string) $value);
                            if ($start === null) {
                                $fail('Enter a start time, like 6:00 AM.');

                                return;
                            }
                            $day = VenueSlot::normaliseDay((string) $get('day'));
                            $clash = $this->getOwnerRecord()->slots()
                                ->when($record !== null, fn ($q) => $q->whereKeyNot($record->getKey()))
                                ->get()
                                ->contains(fn (VenueSlot $s): bool => VenueSlot::normaliseDay($s->day) === $day
                                    && VenueSlot::startMinutes($s->time) === $start);
                            if ($clash) {
                                $fail('This venue already has a '.VenueSlot::normaliseTime((string) $value).' slot on '.($day === VenueSlot::EVERY_DAY ? 'every day' : $day).'.');
                            }
                        },
                    ]),
                TextInput::make('price')
                    ->label('Price for all courts (₹)')
                    ->numeric()
                    ->minValue(0)
                    ->helperText('Leave empty to charge each court\'s own rate. A price here replaces the court rate and its peak price at this time.'),
                // Courts cost different amounts, so each can have its own price at this time.
                // It beats the all-courts price above. Same field the partner app edits.
                ...$this->getOwnerRecord()->courts()->where('is_active', true)->orderBy('sort_order')->get()
                    ->map(fn (\App\Models\VenueCourt $c) => TextInput::make('court_prices.'.$c->id)
                        ->label($c->name.' (₹)')
                        ->numeric()
                        ->minValue(0)
                        ->placeholder('₹'.number_format((float) ($c->price ?? $this->getOwnerRecord()->price ?? 0)).' court rate'))
                    ->all(),
                // The column existed (and the table showed it) but nothing in /control could set it.
                Select::make('sports')
                    ->label('Runs for')
                    ->multiple()
                    ->native(false)
                    ->options(fn (): array => $this->sportOptions())
                    ->placeholder('All sports')
                    ->helperText('Leave empty to sell every court at this time. Pick sports to sell only courts that host them — e.g. football evenings only.'),
                Toggle::make('is_available')
                    ->label('Open for booking')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('time')
            // Every day first, then Monday → Sunday, each in clock order — how the partner
            // app lists them — instead of insertion order.
            ->modifyQueryUsing(fn ($query) => $query
                ->orderByRaw("CASE day WHEN 'Every day' THEN 0 WHEN 'Monday' THEN 1 WHEN 'Tuesday' THEN 2 WHEN 'Wednesday' THEN 3 WHEN 'Thursday' THEN 4 WHEN 'Friday' THEN 5 WHEN 'Saturday' THEN 6 WHEN 'Sunday' THEN 7 ELSE 8 END")
                // Times are stored "6:00 AM" (VenueSlot::normaliseTime): AM before PM, then
                // the hour with 12 as 0, then the minutes.
                ->orderByRaw("CASE WHEN time LIKE '%PM' THEN 1 ELSE 0 END")
                ->orderByRaw("CAST(time AS INTEGER) % 12")
                ->orderByRaw("substr(time, instr(time, ':') + 1, 2)"))
            ->columns([
                TextColumn::make('day')
                    ->badge()
                    ->sortable(),
                TextColumn::make('time')
                    ->searchable(),
                // The same number the desk and checkout charge; blank = the court's rate.
                TextColumn::make('price')
                    ->label('Price')
                    ->formatStateUsing(fn ($state): string => (float) $state > 0 ? '₹'.number_format((float) $state) : 'Court rate')
                    ->placeholder('Court rate'),
                TextColumn::make('court_prices')
                    ->label('Per court')
                    ->state(function (VenueSlot $record): string {
                        $names = $this->getOwnerRecord()->courts()->pluck('name', 'id');
                        $parts = [];
                        foreach ($record->courtPriceList() as $id => $p) {
                            $parts[] = ($names[$id] ?? 'Court '.$id).' ₹'.number_format($p);
                        }

                        return implode(' · ', $parts);
                    })
                    ->placeholder('—'),
                TextColumn::make('sports')
                    ->label('Runs for')
                    ->badge()
                    ->placeholder('All sports'),
                IconColumn::make('is_available')
                    ->label('Open')
                    ->boolean(),
            ])
            ->description('Start times shared by every court. To see or set what each court charges at these times, use the Courts & slots tab.')
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('day')
                    ->options(array_combine(
                        [VenueSlot::EVERY_DAY, ...VenueSlot::WEEKDAYS],
                        [VenueSlot::EVERY_DAY, ...VenueSlot::WEEKDAYS],
                    )),
            ])
            // Slots belong to this venue. Associate/Dissociate used to sit here: dissociating
            // left a slot with no venue, and associating could pull another venue's slot in.
            ->headerActions([
                $this->generateAction(),
                CreateAction::make()->label('Add slot'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No slots yet')
            ->emptyStateDescription('Nothing can be booked until the venue has start times. Use Generate slots to make a full day in one go.');
    }

    /** Sports a slot can be limited to: the venue's own plus every sport its courts host. */
    private function sportOptions(): array
    {
        $venue = $this->getOwnerRecord();
        $list = is_array($venue->sports) ? $venue->sports : [];
        foreach ($venue->courts()->get() as $c) {
            $list = [...$list, ...$c->sportsList()];
        }
        $list = array_values(array_unique(array_filter(array_map('trim', $list))));
        sort($list);

        return array_combine($list, $list) ?: [];
    }

    /**
     * One click instead of one row at a time: opening and closing time, 30- or 60-minute
     * slots, which days, an optional price, and whether to add to the current slots or
     * replace them. Same builder as the partner app ({@see SlotGenerator}).
     */
    private function generateAction(): Action
    {
        return Action::make('generateSlots')
            ->label('Generate slots')
            ->icon('heroicon-m-sparkles')
            ->color('primary')
            ->modalHeading('Generate slots')
            ->modalDescription('Creates every slot between opening and closing time. Bookings already taken are never touched.')
            ->modalSubmitActionLabel('Generate')
            ->schema([
                ToggleButtons::make('step')
                    ->label('Slot length')
                    ->options([30 => '30 minutes', 60 => '1 hour'])
                    ->default(fn (): int => (int) ($this->getOwnerRecord()->slot_minutes ?: 60))
                    ->inline()
                    ->required(),
                TextInput::make('open')->label('Opens at')->placeholder('6:00 AM')->default('6:00 AM')->required(),
                TextInput::make('close')->label('Closes at')->placeholder('11:00 PM')->default('11:00 PM')
                    ->helperText('12:00 AM means midnight. The last slot is the one that still ends by closing time.')
                    ->required(),
                Radio::make('days_mode')
                    ->label('Days')
                    ->options(['every' => 'Every day', 'pick' => 'Only some days'])
                    ->default('every')
                    ->live()
                    ->required(),
                CheckboxList::make('days')
                    ->options(array_combine(VenueSlot::WEEKDAYS, VenueSlot::WEEKDAYS))
                    ->columns(4)
                    ->visible(fn ($get): bool => $get('days_mode') === 'pick')
                    ->required(fn ($get): bool => $get('days_mode') === 'pick'),
                TextInput::make('price')
                    ->label('Price per slot (₹)')
                    ->numeric()->minValue(0)
                    ->helperText('Leave empty to charge the court\'s own rate.'),
                Radio::make('mode')
                    ->label('Existing slots')
                    ->options([
                        'add' => 'Keep them, and add only the missing times',
                        'replace' => 'Replace all of this venue\'s slots',
                    ])
                    ->default('add')
                    ->required(),
            ])
            ->action(function (array $data): void {
                $venue = $this->getOwnerRecord();
                $days = ($data['days_mode'] ?? 'every') === 'pick' ? array_values($data['days'] ?? []) : [VenueSlot::EVERY_DAY];

                try {
                    $result = SlotGenerator::generate(
                        $venue,
                        (string) $data['open'],
                        (string) $data['close'],
                        (int) $data['step'],
                        $days,
                        isset($data['price']) && $data['price'] !== '' ? (float) $data['price'] : null,
                        1,
                        (string) ($data['mode'] ?? 'add'),
                    );
                } catch (\InvalidArgumentException $e) {
                    Notification::make()->title('Slots not generated')->body($e->getMessage())->danger()->send();

                    return;
                }

                AdminAction::log('venue.slots_generated', [
                    'step' => (int) $data['step'], 'open' => $data['open'], 'close' => $data['close'],
                    'days' => $days, 'mode' => $data['mode'] ?? 'add',
                    'created' => $result['created'], 'kept' => $result['kept'], 'removed' => $result['removed'],
                ], $venue);

                Notification::make()
                    ->title($result['created'].' slots created')
                    ->body(($result['removed'] > 0 ? $result['removed'].' old slots removed. ' : '')
                        .($result['kept'] > 0 ? $result['kept'].' already existed and were kept. ' : '')
                        .'Slot length is now '.((int) $data['step'] === 30 ? '30 minutes.' : '1 hour.'))
                    ->success()
                    ->send();
            });
    }
}
