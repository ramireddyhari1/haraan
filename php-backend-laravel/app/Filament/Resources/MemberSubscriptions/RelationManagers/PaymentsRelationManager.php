<?php

declare(strict_types=1);

namespace App\Filament\Resources\MemberSubscriptions\RelationManagers;

use App\Models\MemberPayment;
use App\Models\MemberSubscription;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Charges Razorpay reported against this subscription. Read-only; refunds happen in Razorpay. */
class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Payments';

    public static function canViewForRecord(mixed $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof MemberSubscription && $ownerRecord->provider === MemberSubscription::PROVIDER_RAZORPAY;
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('paid_at', 'desc')
            ->columns([
                TextColumn::make('paid_at')->label('Paid')->dateTime('d M Y, g:i A'),
                TextColumn::make('amount_paise')
                    ->label('Amount')
                    ->formatStateUsing(fn (MemberPayment $record): string => '₹' . number_format($record->amount_paise / 100, 2))
                    ->alignRight(),
                TextColumn::make('status')->badge()->color(fn (string $state): string => $state === MemberPayment::STATUS_CAPTURED ? 'success' : 'danger'),
                TextColumn::make('method')->placeholder('—'),
                TextColumn::make('provider_payment_id')->label('Payment id')->copyable(),
                TextColumn::make('provider_invoice_id')->label('Invoice')->placeholder('—')->copyable(),
            ]);
    }
}
