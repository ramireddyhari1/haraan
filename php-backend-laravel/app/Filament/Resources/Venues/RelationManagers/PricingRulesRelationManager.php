<?php

declare(strict_types=1);

namespace App\Filament\Resources\Venues\RelationManagers;

use App\Models\AdminAction;
use App\Models\PricingRule;
use App\Models\Venue;
use App\Models\VenueCourt;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The pricing matrix.
 *
 * `pricing_rules` has been read on every quote since {@see VenueCourt::rateFor()}
 * shipped — it is consulted before peak pricing and before the base rate — but
 * nothing in any console could write a row, so the engine has been running on an
 * empty table. This is that missing control.
 *
 * Two things are deliberate here. Every rule shows what it would actually charge
 * on this venue's default rate, computed through {@see PricingRule::applyTo()}
 * rather than re-derived, so the table cannot drift from the engine. And rule
 * writes are audit-logged, because changing a rate is the same class of act as
 * changing the venue's base price, which is already audited.
 */
class PricingRulesRelationManager extends RelationManager
{
    protected static string $relationship = 'pricingRules';

    protected static ?string $title = 'Pricing matrix';

    protected static string | \BackedEnum | null $icon = 'heroicon-o-currency-rupee';

    protected static ?string $modelLabel = 'pricing rule';

    /** Short weekday keys, stored lowercase — {@see PricingRule::matches()} lowers before comparing. */
    private const WEEKDAYS = [
        'mon' => 'Mon', 'tue' => 'Tue', 'wed' => 'Wed', 'thu' => 'Thu',
        'fri' => 'Fri', 'sat' => 'Sat', 'sun' => 'Sun',
    ];

    private const RULE_TYPES = [
        'time_of_day'         => 'Time of day',
        'day_of_week'         => 'Day of week',
        'seasonal_date_range' => 'Season / date range',
        'occupancy_surge'     => 'Occupancy surge',
        'last_minute'         => 'Last minute',
    ];

    private const PRICING_MODES = [
        'absolute'   => 'Set an exact rate',
        'delta'      => 'Add or subtract rupees',
        'percentage' => 'Add or subtract a percentage',
    ];

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

    /** Changing a rate is a pricing act, same as editing the venue's base price. */
    public function canCreate(): bool
    {
        return auth()->user()?->hasPartnerPermission('pricing') ?? false;
    }

    public function canEdit(Model $record): bool
    {
        return $this->canCreate();
    }

    public function canDelete(Model $record): bool
    {
        return $this->canCreate();
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $active = $ownerRecord->pricingRules()->where('is_active', true)->count();

        return $active > 0 ? (string) $active : null;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('What the rule is')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Rule name')
                        ->required()
                        ->maxLength(120)
                        ->placeholder('Prime evening surge')
                        ->helperText('Operators read this in the audit log and on the rate breakdown — name the intent, not the number.'),

                    Select::make('rule_type')
                        ->label('Kind')
                        ->options(self::RULE_TYPES)
                        ->default('time_of_day')
                        ->native(false)
                        ->required()
                        ->helperText('Labelling only — the window fields below are what actually decide whether a rule fires.'),

                    Select::make('venue_court_id')
                        ->label('Applies to')
                        ->options(fn (): array => VenueCourt::query()
                            ->where('venue_id', $this->getOwnerRecord()->getKey())
                            ->orderBy('sort_order')
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable()
                        ->placeholder('Every court at this venue')
                        ->helperText('A court-specific rule outranks a venue-wide rule of the same priority.'),

                    TextInput::make('priority')
                        ->label('Priority')
                        ->numeric()
                        ->default(0)
                        ->required()
                        ->helperText('Higher wins. The first matching rule sets the rate and the rest are skipped.'),
                ]),

            Section::make('When it fires')
                ->description('Leave a window empty to leave it unconstrained. A rule with nothing set applies to every hour of every day.')
                ->columns(2)
                ->schema([
                    CheckboxList::make('weekdays')
                        ->label('Weekdays')
                        ->options(self::WEEKDAYS)
                        ->columns(4)
                        ->columnSpanFull()
                        ->helperText('None ticked means every day.'),

                    TimePicker::make('start_time')
                        ->label('From time')
                        ->seconds(false)
                        ->displayFormat('H:i')
                        ->format('H:i')
                        ->helperText('A window that ends before it starts is read as crossing midnight.'),

                    TimePicker::make('end_time')
                        ->label('Until time')
                        ->seconds(false)
                        ->displayFormat('H:i')
                        ->format('H:i'),

                    DatePicker::make('date_from')
                        ->label('Active from')
                        ->native(false)
                        ->placeholder('No start bound'),

                    DatePicker::make('date_to')
                        ->label('Active until')
                        ->native(false)
                        ->placeholder('No end bound')
                        ->afterOrEqual('date_from'),
                ]),

            Section::make('What it charges')
                ->columns(2)
                ->schema([
                    Select::make('pricing_mode')
                        ->label('Mode')
                        ->options(self::PRICING_MODES)
                        ->default('percentage')
                        ->native(false)
                        ->required()
                        ->live(),

                    TextInput::make('amount')
                        ->label(fn (Get $get): string => match ($get('pricing_mode')) {
                            'absolute' => 'Exact rate (₹/hr)',
                            'delta'    => 'Rupees to add (negative to discount)',
                            default    => 'Percent to add (negative to discount)',
                        })
                        ->numeric()
                        ->required()
                        ->live(onBlur: true)
                        ->helperText(function (Get $get): string {
                            /** @var Venue $venue */
                            $venue = $this->getOwnerRecord();
                            $base = (float) $venue->price;

                            $preview = (new PricingRule([
                                'pricing_mode' => $get('pricing_mode') ?: 'percentage',
                                'amount'       => (float) $get('amount'),
                                'min_price'    => $get('min_price') !== null && $get('min_price') !== '' ? (float) $get('min_price') : null,
                                'max_price'    => $get('max_price') !== null && $get('max_price') !== '' ? (float) $get('max_price') : null,
                            ]))->applyTo($base);

                            return 'On this venue’s default rate of ₹' . number_format($base)
                                . '/hr, this rule charges ₹' . number_format($preview, 2) . '/hr.';
                        }),

                    TextInput::make('min_price')
                        ->label('Never charge below (₹)')
                        ->numeric()
                        ->placeholder('No floor')
                        ->live(onBlur: true),

                    TextInput::make('max_price')
                        ->label('Never charge above (₹)')
                        ->numeric()
                        ->placeholder('No ceiling')
                        ->live(onBlur: true)
                        ->gte('min_price'),

                    Toggle::make('is_active')
                        ->label('Rule is live')
                        ->default(true)
                        ->helperText('Switched off, the rule is kept but never consulted.')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('priority', 'desc')
            ->emptyStateHeading('No pricing rules')
            ->emptyStateDescription('Without a rule, every hour is quoted at the court rate, or the venue default when the court has none.')
            ->emptyStateIcon('heroicon-o-currency-rupee')
            ->columns([
                TextColumn::make('priority')
                    ->label('Pri.')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Rule')
                    ->weight('bold')
                    ->description(fn (PricingRule $record): string => self::RULE_TYPES[$record->rule_type] ?? (string) $record->rule_type)
                    ->searchable(),

                TextColumn::make('venueCourt.name')
                    ->label('Applies to')
                    ->placeholder('Whole venue')
                    ->badge()
                    ->color(fn (PricingRule $record): string => $record->venue_court_id ? 'info' : 'gray'),

                TextColumn::make('window')
                    ->label('When')
                    ->state(fn (PricingRule $record): string => self::windowSummary($record))
                    ->wrap(),

                TextColumn::make('effect')
                    ->label('Charges')
                    ->state(fn (PricingRule $record): string => self::effectSummary($record))
                    ->badge()
                    ->color('warning'),

                TextColumn::make('preview')
                    ->label('On default rate')
                    ->state(function (PricingRule $record): string {
                        /** @var Venue $venue */
                        $venue = $this->getOwnerRecord();

                        return '₹' . number_format($record->applyTo((float) $venue->price), 2) . '/hr';
                    })
                    ->tooltip('Computed through the same applyTo() the booking engine calls.'),

                IconColumn::make('is_active')
                    ->label('Live')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Live'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add rule')
                    ->mutateDataUsing(function (array $data): array {
                        $data['venue_id'] = $this->getOwnerRecord()->getKey();

                        return $data;
                    })
                    ->after(fn (PricingRule $record) => AdminAction::log('venue.pricing_rule_created', [
                        'venue_id' => $record->venue_id,
                        'rule'     => $record->name,
                        'mode'     => $record->pricing_mode,
                        'amount'   => $record->amount,
                    ], $this->getOwnerRecord())),
            ])
            ->recordActions([
                EditAction::make()
                    ->after(fn (PricingRule $record) => AdminAction::log('venue.pricing_rule_updated', [
                        'venue_id' => $record->venue_id,
                        'rule'     => $record->name,
                        'mode'     => $record->pricing_mode,
                        'amount'   => $record->amount,
                        'is_active' => $record->is_active,
                    ], $this->getOwnerRecord())),

                DeleteAction::make()
                    ->before(fn (PricingRule $record) => AdminAction::log('venue.pricing_rule_deleted', [
                        'venue_id' => $record->venue_id,
                        'rule'     => $record->name,
                    ], $this->getOwnerRecord())),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /** "Mon–Fri · 18:00–23:00 · until 31 Mar 2027", or "Always". */
    private static function windowSummary(PricingRule $record): string
    {
        $parts = [];

        $days = is_array($record->weekdays) ? $record->weekdays : [];
        if ($days !== []) {
            $parts[] = implode(', ', array_map(
                static fn (string $d): string => self::WEEKDAYS[strtolower($d)] ?? ucfirst($d),
                $days,
            ));
        }

        if ($record->start_time && $record->end_time) {
            $parts[] = $record->start_time . '–' . $record->end_time;
        }

        if ($record->date_from || $record->date_to) {
            $parts[] = implode(' → ', array_filter([
                $record->date_from?->format('d M Y'),
                $record->date_to?->format('d M Y'),
            ]));
        }

        return $parts === [] ? 'Always' : implode(' · ', $parts);
    }

    /** How the rule moves the rate, in its own terms. */
    private static function effectSummary(PricingRule $record): string
    {
        $amount = (float) $record->amount;

        $body = match ($record->pricing_mode) {
            'absolute'   => '₹' . number_format($amount) . '/hr flat',
            'delta'      => ($amount >= 0 ? '+' : '−') . '₹' . number_format(abs($amount)),
            'percentage' => ($amount >= 0 ? '+' : '−') . number_format(abs($amount), 1) . '%',
            default      => '—',
        };

        $guards = array_filter([
            $record->min_price !== null ? 'min ₹' . number_format((float) $record->min_price) : null,
            $record->max_price !== null ? 'max ₹' . number_format((float) $record->max_price) : null,
        ]);

        return $guards === [] ? $body : $body . ' (' . implode(', ', $guards) . ')';
    }
}
