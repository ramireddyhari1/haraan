<?php

declare(strict_types=1);

namespace App\Filament\Resources\PayoutBatches\Schemas;

use App\Models\PartnerPayoutAccount;
use App\Models\PayoutBatch;
use App\Models\User;
use App\Services\PartnerSettlement;
use App\Services\SettlementTax;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

/**
 * Creating a settlement is a decision, so the form leads with the two facts the
 * decision needs: how much this partner is owed, and where the money will land.
 * Picking a partner fills the amount with their available balance and stamps
 * the method from their settlement account — both still editable.
 */
class PayoutBatchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Who')
                    ->schema([
                        Select::make('partner_id')
                            ->label('Partner')
                            ->options(fn (): array => User::query()
                                ->where('role', 'PARTNER')
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                                if (blank($state)) {
                                    return;
                                }

                                $partnerId = (int) $state;
                                $available = round(app(PartnerSettlement::class)->available($partnerId), 2);
                                $rates = app(SettlementTax::class)->defaultRates($partnerId);
                                $set('gross_amount', $available);
                                $set('commission_percent', 0);
                                $set('commission_gst_percent', $rates['commission_gst_percent']);
                                $set('tds_percent', $rates['tds_percent']);
                                $set('tcs_percent', $rates['tcs_percent']);
                                static::recalculate($get, $set);
                                $set('method', PartnerPayoutAccount::query()
                                    ->where('partner_id', $partnerId)
                                    ->value('method'));
                            })
                            ->belowContent(fn (Get $get): ?string => static::balanceLine($get('partner_id')))
                            ->columnSpanFull(),
                    ]),

                Section::make('Deductions & payout')
                    ->description(fn (Get $get): ?string => static::ratesNote($get('partner_id')))
                    ->schema([
                        TextInput::make('gross_amount')
                            ->label('Settlement amount before deductions (₹)')
                            ->numeric()
                            ->minValue(1)
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set) => static::recalculate($get, $set))
                            ->disabled(fn (?PayoutBatch $record): bool => (bool) $record?->isPaid())
                            ->helperText('Defaults to the full available balance. Lower it to settle only part of it.')
                            ->columnSpanFull(),
                        static::rate('commission_percent', 'Haraan commission (%)', 99.99, 'Haraan keeps this share of the settlement amount.'),
                        static::rate('commission_gst_percent', 'GST on commission (%)', 28, 'Charged on the commission. Zero while Haraan has no GSTIN.'),
                        static::rate('tds_percent', 'TDS, section 194-O (%)', 20, 'Deducted from the settlement amount.'),
                        static::rate('tcs_percent', 'GST TCS, section 52 (%)', 5, 'Only for partners with a GSTIN.'),
                        Placeholder::make('breakdown')
                            ->label('What the partner receives')
                            ->content(fn (Get $get): HtmlString => static::breakdownHtml($get))
                            ->columnSpanFull(),
                        // The net is re-derived on the server; this copy only carries it through the form.
                        Hidden::make('amount'),
                    ])
                    ->columns(2),

                Section::make('Status & period')
                    ->schema([
                        Select::make('status')
                            ->options([
                                'processing' => 'Processing',
                                'paid' => 'Paid',
                                'failed' => 'Failed',
                            ])
                            ->default('processing')
                            ->native(false)
                            ->required()
                            ->belowContent('Leave on Processing until the transfer actually lands.'),
                        DatePicker::make('period_start')
                            ->label('Period from')
                            ->default(now()->subWeek()->startOfWeek()),
                        DatePicker::make('period_end')
                            ->label('Period to')
                            ->default(now()->subWeek()->endOfWeek())
                            ->afterOrEqual('period_start'),
                    ])
                    ->columns(2),

                Section::make('Transfer')
                    ->schema([
                        Select::make('method')
                            ->label('Paid via')
                            ->options(['upi' => 'UPI', 'bank' => 'Bank transfer'])
                            ->native(false)
                            ->belowContent(fn (Get $get): string => static::destinationLine($get('partner_id'))),
                        TextInput::make('reference')
                            ->label('Reference / UTR')
                            ->maxLength(120)
                            ->belowContent('The bank reference, so the partner can trace it.'),
                        Textarea::make('note')
                            ->label('Internal note')
                            ->rows(2)
                            ->maxLength(500)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    /** "Available ₹12,400 · collected ₹40,000 · settled ₹27,600 · in batches ₹0" */
    public static function balanceLine(mixed $partnerId): ?string
    {
        if (blank($partnerId)) {
            return 'Pick a partner to see what they are owed.';
        }

        $s = app(PartnerSettlement::class)->summary((int) $partnerId);

        return 'Available ' . PartnerSettlement::inr($s['available'])
            . ' · collected ' . PartnerSettlement::inr($s['collected'])
            . ' · already settled ' . PartnerSettlement::inr($s['settled'])
            . ' · in open batches ' . PartnerSettlement::inr($s['inFlight']);
    }

    /** Where the money goes, in full (finance sends it) — or a warning if there's nowhere to send it. */
    public static function destinationLine(mixed $partnerId): string
    {
        if (blank($partnerId)) {
            return '—';
        }

        $account = PartnerPayoutAccount::query()->where('partner_id', (int) $partnerId)->first();

        if ($account === null) {
            return '⚠ No settlement account on file — the partner must add one in /partner → Payouts.';
        }

        return $account->fullLine()
            . ' · ' . ($account->account_holder ?: 'no name on file')
            . ' · ' . ($account->isVerified() ? 'verified' : '⚠ not verified yet');
    }

    /** One percentage input: live, clamped, and frozen once the money has gone. */
    private static function rate(string $name, string $label, float $max, string $help): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->numeric()
            ->minValue(0)
            ->maxValue($max)
            ->default(0)
            ->required()
            ->suffix('%')
            ->live(onBlur: true)
            ->afterStateUpdated(fn (Get $get, Set $set) => static::recalculate($get, $set))
            ->disabled(fn (?PayoutBatch $record): bool => (bool) $record?->isPaid())
            ->helperText($help);
    }

    /** The form's numbers, run through the same arithmetic the server saves with. */
    public static function figures(Get $get): ?array
    {
        if (blank($get('partner_id'))) {
            return null;
        }

        return app(SettlementTax::class)->compute(
            (int) $get('partner_id'),
            (float) ($get('gross_amount') ?? 0),
            (float) ($get('commission_percent') ?? 0),
            (float) ($get('commission_gst_percent') ?? 0),
            (float) ($get('tds_percent') ?? 0),
            (float) ($get('tcs_percent') ?? 0),
        );
    }

    public static function recalculate(Get $get, Set $set): void
    {
        $set('amount', static::figures($get)['amount'] ?? 0);
    }

    /** Why a rate differs from the platform default for this partner (no PAN, no GSTIN...). */
    public static function ratesNote(mixed $partnerId): ?string
    {
        if (blank($partnerId)) {
            return null;
        }

        $notes = app(SettlementTax::class)->defaultRates((int) $partnerId)['notes'];

        return $notes === [] ? 'Rates come from Platform rules → Partner invoices & tax deductions.' : implode(' ', $notes);
    }

    /** A receipt-style breakdown, so finance reads exactly what the partner's statement will say. */
    public static function breakdownHtml(Get $get): HtmlString
    {
        $f = static::figures($get);
        if ($f === null) {
            return new HtmlString('<span style="color:#64748b">Pick a partner first.</span>');
        }

        $money = fn (float $v): string => '₹' . number_format($v, 2);
        $pct = fn (float $v): string => rtrim(rtrim(number_format($v, 2), '0'), '.') . '%';
        $row = fn (string $label, string $value, bool $strong = false): string => '<div style="display:flex;justify-content:space-between;gap:16px;padding:4px 0;'
            . ($strong ? 'border-top:1px solid rgba(15,23,42,.12);margin-top:4px;padding-top:8px;font-weight:700;font-size:15px' : '')
            . '"><span>' . e($label) . '</span><span style="font-variant-numeric:tabular-nums">' . e($value) . '</span></div>';

        return new HtmlString('<div style="max-width:440px">'
            . $row('Settlement amount', $money($f['gross_amount']))
            . $row('− Commission (' . $pct($f['commission_percent']) . ')', $money($f['commission_amount']))
            . $row('− GST on commission (' . $pct($f['commission_gst_percent']) . ')', $money($f['commission_gst_amount']))
            . $row('− TDS 194-O (' . $pct($f['tds_percent']) . ')', $money($f['tds_amount']))
            . $row('− GST TCS (' . $pct($f['tcs_percent']) . ')', $money($f['tcs_amount']))
            . $row('Sent to the partner', $money($f['amount']), true)
            . '</div>');
    }
}
