<?php

namespace App\Filament\Resources\Venues\RelationManagers;

use App\Models\AdminAction;
use App\Models\VenueSlot;
use App\Support\SlotGenerator;
use Filament\Actions\Action;
use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
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
                TextInput::make('time')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('6:00 AM'),
                Toggle::make('is_available')
                    ->label('Open for booking')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('time')
            ->columns([
                TextColumn::make('day')
                    ->badge()
                    ->sortable(),
                TextColumn::make('time')
                    ->searchable(),
                IconColumn::make('is_available')
                    ->label('Open')
                    ->boolean(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                $this->generateAction(),
                CreateAction::make(),
                AssociateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DissociateAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DissociateBulkAction::make(),
                    DeleteBulkAction::make(),
                ]),
            ]);
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
