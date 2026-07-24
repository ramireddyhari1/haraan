<?php

declare(strict_types=1);

namespace App\Filament\Resources\PayoutBatches\Schemas;

use App\Models\PartnerPayoutAccount;
use App\Models\User;
use App\Services\PartnerSettlement;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

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
                            ->afterStateUpdated(function (?string $state, Set $set): void {
                                if (blank($state)) {
                                    return;
                                }

                                $partnerId = (int) $state;
                                $set('amount', round(app(PartnerSettlement::class)->available($partnerId), 2));
                                $set('method', PartnerPayoutAccount::query()
                                    ->where('partner_id', $partnerId)
                                    ->value('method'));
                            })
                            ->belowContent(fn (Get $get): ?string => static::balanceLine($get('partner_id')))
                            ->columnSpanFull(),
                    ]),

                Section::make('Amount & period')
                    ->schema([
                        TextInput::make('amount')
                            ->label('Amount (₹)')
                            ->numeric()
                            ->minValue(1)
                            ->required()
                            ->belowContent('Defaults to everything currently available. Lower it to settle part of the balance.'),
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

    /** Where the money goes, masked — or a warning if there's nowhere to send it. */
    public static function destinationLine(mixed $partnerId): string
    {
        if (blank($partnerId)) {
            return '—';
        }

        $account = PartnerPayoutAccount::query()->where('partner_id', (int) $partnerId)->first();

        if ($account === null) {
            return '⚠ No settlement account on file — the partner must add one in /partner → Payouts.';
        }

        return $account->summaryLine()
            . ' · ' . ($account->account_holder ?: 'no name on file')
            . ' · ' . ($account->isVerified() ? 'verified' : '⚠ not verified yet');
    }
}
