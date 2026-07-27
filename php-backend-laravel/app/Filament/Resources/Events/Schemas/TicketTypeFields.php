<?php

declare(strict_types=1);

namespace App\Filament\Resources\Events\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

/**
 * The fields for one priced ticket tier (General / VIP / Early Bird / …), shared
 * by both places a tier is edited so they never drift apart:
 *   - the "Ticket Types" relation manager on the Edit Event page, and
 *   - the inline "Ticket tiers" repeater in the create wizard's Tickets step.
 *
 * Redesigned into a District/BookMyShow-style ticket card: Name, an optional
 * Description, "Total Seats" (-1 = unlimited), a Free toggle, Price, a Visible
 * toggle and Enable Bulk Booking. The power-user knobs (kind, donation floor,
 * dynamic phases, timed sales, display order) live in a collapsed Advanced
 * section so the common case stays a clean card.
 */
class TicketTypeFields
{
    /** @return array<int, \Filament\Schemas\Components\Component> */
    public static function components(): array
    {
        return [
            // Preset picker — prefills the fields for a common ticket model so a
            // host never has to reason about the raw columns. Not persisted.
            Select::make('preset')
                ->label('Start from a preset')
                ->options([
                    'general'    => 'General Admission',
                    'free'       => 'Free Pass',
                    'tier'       => 'Tier / Category (Gold, VIP…)',
                    'early_bird' => 'Early Bird (timed)',
                    'dynamic'    => 'Dynamic Pricing (price rises as it sells)',
                    'bundle'     => 'Bundle (Couple / Group / Family)',
                    'addon'      => 'Add-on (parking, food…)',
                    'donation'   => 'Donation (pay what you want)',
                ])
                ->live()
                ->dehydrated(false)
                ->afterStateUpdated(function ($state, Set $set): void {
                    match ($state) {
                        'free'       => self::applyPreset($set, 'Free Pass', 'standard', 0, free: true),
                        'general'    => self::applyPreset($set, 'General Admission', 'standard', 0, free: true),
                        'tier'       => self::applyPreset($set, 'Gold', 'standard', 499),
                        'early_bird' => self::applyPreset($set, 'Early Bird', 'standard', 350),
                        'dynamic'    => self::applyDynamicPreset($set),
                        'bundle'     => self::applyPreset($set, 'Couple Pass', 'standard', 999, admits: 2),
                        'addon'      => self::applyPreset($set, 'Parking', 'addon', 100),
                        'donation'   => self::applyPreset($set, 'Donation', 'donation', 0, minPrice: 0),
                        default      => null,
                    };
                })
                ->helperText('Optional — pick the closest model, then tweak below.')
                ->columnSpanFull(),

            // Which session this tier is sold for. Only meaningful once the event is
            // in "Customize per slot" mode AND has saved sessions to choose from —
            // so it's hidden on the create wizard (no slot ids yet) and on unified
            // events, matching the "fine-tune per session after saving" flow.
            Select::make('event_slot_id')
                ->label('Session')
                ->options(fn (Get $get, $livewire = null): array => self::slotOptions($get, $livewire))
                ->visible(fn (Get $get, $livewire = null): bool => self::perSlot($get, $livewire) && self::slotOptions($get, $livewire) !== [])
                ->native(false)
                ->placeholder('All sessions')
                ->helperText('Leave blank to sell this tier across every session.')
                ->columnSpanFull(),

            TextInput::make('name')
                ->label('Ticket Name')
                ->required()
                ->maxLength(255)
                ->placeholder('e.g. General Admission')
                ->columnSpanFull(),

            Textarea::make('description')
                ->label('Description (optional)')
                ->rows(2)
                ->maxLength(500)
                ->placeholder('e.g. Includes entry and welcome drink')
                ->columnSpanFull(),

            TextInput::make('capacity')
                ->label('Total Seats')
                ->numeric()
                ->minValue(-1)
                ->default(-1)
                // null capacity (unlimited) shows as -1; -1/blank saves back to null.
                ->formatStateUsing(fn ($state) => $state === null ? -1 : (int) $state)
                ->dehydrateStateUsing(fn ($state) => ($state === null || $state === '' || (int) $state < 0) ? null : (int) $state)
                ->helperText('-1 = unlimited (bounded by the event capacity).'),

            // Free is a UI convenience over price = 0. Not a column: it zeroes the
            // price when on, and hydrates on from an existing zero-price tier.
            Toggle::make('free')
                ->label('Free ticket')
                ->dehydrated(false)
                ->live()
                ->default(true)
                ->afterStateHydrated(fn (Set $set, Get $get) => $set('free', (float) ($get('price') ?? 0) <= 0))
                ->afterStateUpdated(function ($state, Set $set): void {
                    if ($state) {
                        $set('price', 0);
                        $set('bulk_booking', false);
                    }
                }),

            TextInput::make('price')
                ->label('Ticket Price')
                ->numeric()
                ->minValue(0)
                ->prefix('₹')
                ->default(0)
                ->visible(fn (Get $get): bool => ! $get('free'))
                ->required(fn (Get $get): bool => ! $get('free'))
                ->helperText(fn (Get $get) => $get('kind') === 'donation'
                    ? 'Suggested amount — buyers can pay more.'
                    : null),

            Toggle::make('visible')
                ->label('Visible to buyers')
                ->default(true)
                ->helperText('Turn off to hide this tier from buyers without deleting it.'),

            // Bulk booking — buy many at once, bounded by min/max. Not for free tiers.
            Toggle::make('bulk_booking')
                ->label('Enable Bulk Booking')
                ->live()
                ->default(false)
                ->visible(fn (Get $get): bool => ! $get('free'))
                ->helperText('Allow buying several of this ticket in one order.'),
            TextInput::make('min_per_order')
                ->label('Min per order')
                ->numeric()
                ->minValue(1)
                ->default(1)
                ->visible(fn (Get $get): bool => ! $get('free') && (bool) $get('bulk_booking')),
            TextInput::make('max_per_order')
                ->label('Max per order')
                ->numeric()
                ->minValue(1)
                ->visible(fn (Get $get): bool => ! $get('free') && (bool) $get('bulk_booking'))
                ->helperText('Leave blank for no upper limit.'),

            // Everything below is optional power-user stuff (tier kind, donation
            // floor, timed early-bird windows, demand-based dynamic pricing, display
            // order). Collapsed so the common case stays a clean card.
            Section::make('Advanced — kind, timed sales & dynamic pricing')
                ->description('Optional. Add-ons/donations, early-bird windows, prices that rise as spots sell, and display order.')
                ->collapsed()
                ->columns(2)
                ->schema([
                    Select::make('kind')
                        ->label('Ticket kind')
                        ->options([
                            'standard' => 'Standard entry',
                            'addon'    => 'Add-on (extra, not entry)',
                            'donation' => 'Donation (pay what you want)',
                        ])
                        ->default('standard')
                        ->required()
                        ->live(),
                    TextInput::make('admits')
                        ->numeric()
                        ->minValue(1)
                        ->default(1)
                        ->helperText('People admitted per ticket. Use 2+ for Couple/Group/Family bundles.'),
                    TextInput::make('min_price')
                        ->label('Minimum amount')
                        ->numeric()
                        ->minValue(0)
                        ->prefix('₹')
                        ->visible(fn ($get) => $get('kind') === 'donation')
                        ->helperText('Floor for pay-what-you-want donations.'),
                    Repeater::make('pricing_phases')
                        ->label('Dynamic price phases')
                        ->schema([
                            TextInput::make('label')
                                ->placeholder('Early bird')
                                ->required()
                                ->maxLength(40),
                            TextInput::make('price')
                                ->numeric()
                                ->minValue(1)
                                ->prefix('₹')
                                ->required(),
                            TextInput::make('capacity')
                                ->label('Spots at this price')
                                ->numeric()
                                ->minValue(1)
                                ->required(),
                        ])
                        ->columns(3)
                        ->addActionLabel('Add phase')
                        ->reorderable()
                        ->collapsible()
                        ->visible(fn ($get) => $get('kind') === 'standard')
                        ->columnSpanFull()
                        ->helperText('Price climbs as spots sell — the first phase fills, then the next. '
                            . 'The live price is always the earliest phase with room left. Leave empty for a flat price.'),
                    DateTimePicker::make('sales_start')
                        ->label('On sale from')
                        ->helperText('Leave blank to sell immediately (used for Early Bird).'),
                    DateTimePicker::make('sales_end')
                        ->label('On sale until')
                        ->helperText('Leave blank for no end (used for Early Bird).'),
                    TextInput::make('sort')
                        ->numeric()
                        ->default(0)
                        ->columnSpanFull()
                        ->helperText('Lower numbers show first.'),
                ]),
        ];
    }

    /**
     * Whether the surrounding event is in "Customize per slot" mode. Reads the
     * sibling toggle in the create wizard's form state, or the owning event's
     * column when this schema is hosted by the Ticket Types relation manager.
     */
    private static function perSlot(Get $get, $livewire = null): bool
    {
        $inline = $get('../../tickets_per_slot');
        if ($inline !== null) {
            return (bool) $inline;
        }

        $event = self::ownerEvent($livewire);

        return (bool) ($event?->tickets_per_slot);
    }

    /**
     * The session options for the "Session" select: the sibling slots in the
     * create-wizard form state, else the owning event's saved sessions.
     *
     * @return array<int|string, string>
     */
    private static function slotOptions(Get $get, $livewire = null): array
    {
        $slots = $get('../../slots');
        if (is_array($slots) && $slots !== []) {
            $opts = [];
            foreach ($slots as $key => $s) {
                $id = $s['id'] ?? null;
                if ($id === null) {
                    continue; // unsaved slot — no id to bind a tier to yet
                }
                $label = trim((string) ($s['label'] ?? ''));
                $opts[$id] = $label !== '' ? $label : 'Session';
            }

            if ($opts !== []) {
                return $opts;
            }
        }

        $event = self::ownerEvent($livewire);
        if ($event !== null) {
            return $event->slots->mapWithKeys(fn ($s) => [$s->id => $s->displayLabel()])->all();
        }

        return [];
    }

    /** The Event that owns the schema when it's hosted by a relation manager, else null. */
    private static function ownerEvent($livewire): ?\App\Models\Event
    {
        if ($livewire !== null && method_exists($livewire, 'getOwnerRecord')) {
            $record = $livewire->getOwnerRecord();

            return $record instanceof \App\Models\Event ? $record : null;
        }

        return null;
    }

    /** Prefill the tier fields from a chosen preset. */
    private static function applyPreset(
        Set $set,
        string $name,
        string $kind,
        float $price,
        int $admits = 1,
        ?float $minPrice = null,
        bool $free = false,
    ): void {
        $set('name', $name);
        $set('kind', $kind);
        $set('price', $price);
        $set('free', $free || $price <= 0);
        $set('admits', $admits);
        $set('min_price', $minPrice);
    }

    /** Prefill a demand-based dynamic-pricing tier with a sensible 3-phase ladder. */
    private static function applyDynamicPreset(Set $set): void
    {
        $set('name', 'Regular Ticket');
        $set('kind', 'standard');
        $set('price', 499);
        $set('free', false);
        $set('admits', 1);
        $set('min_price', null);
        $set('pricing_phases', [
            ['label' => 'Early bird', 'price' => 350, 'capacity' => 20],
            ['label' => 'Phase 1', 'price' => 450, 'capacity' => 180],
            ['label' => 'Phase 2', 'price' => 499, 'capacity' => 100],
        ]);
    }
}
