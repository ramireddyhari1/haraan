<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single settlement transfer to a partner, covering a period's worth of
 * collected bookings — the partner-facing "payout" object. Created and marked
 * paid by admin (in /control) for the MVP; partners read it in their Payouts
 * page. See {@see PartnerPayoutAccount} for where the money lands.
 */
final class PayoutBatch extends Model
{
    protected $fillable = [
        'partner_id', 'gross_amount', 'amount', 'commission_percent', 'commission_amount',
        'commission_gst_percent', 'commission_gst_amount', 'tds_percent', 'tds_amount', 'tcs_percent', 'tcs_amount',
        'status', 'method', 'reference', 'invoice_number', 'tax_snapshot',
        'period_start', 'period_end', 'note', 'processed_at',
    ];

    protected $casts = [
        'amount' => 'float',
        'commission_percent' => 'float',
        'commission_amount' => 'float',
        'gross_amount' => 'float',
        'commission_gst_percent' => 'float',
        'commission_gst_amount' => 'float',
        'tds_percent' => 'float',
        'tds_amount' => 'float',
        'tcs_percent' => 'float',
        'tcs_amount' => 'float',
        'tax_snapshot' => 'array',
        'period_start' => 'date',
        'period_end' => 'date',
        'processed_at' => 'datetime',
    ];

    /** Statuses that mean the money has actually landed. */
    public const PAID = ['paid', 'processed', 'completed'];

    /**
     * Every settlement is stamped with the billing details it was issued under, and with
     * an invoice number when Haraan kept a commission — whoever creates it.
     */
    protected static function booted(): void
    {
        static::creating(function (PayoutBatch $b): void {
            if ($b->gross_amount === null) {
                $b->gross_amount = round((float) $b->amount + (float) $b->commission_amount
                    + (float) $b->commission_gst_amount + (float) $b->tds_amount + (float) $b->tcs_amount, 2);
            }
            app(\App\Services\SettlementTax::class)->stamp($b);
        });
    }

    public function isFailed(): bool
    {
        return strtolower((string) $this->status) === 'failed';
    }

    /** Gross for rows the backfill missed (never null after the 2026-10-17 migration). */
    public function gross(): float
    {
        return (float) ($this->gross_amount ?? ((float) $this->amount + (float) $this->commission_amount));
    }

    public function totalDeductions(): float
    {
        return round((float) $this->commission_amount + (float) $this->commission_gst_amount
            + (float) $this->tds_amount + (float) $this->tcs_amount, 2);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_id');
    }

    public function isPaid(): bool
    {
        return in_array(strtolower((string) $this->status), self::PAID, true);
    }
}
