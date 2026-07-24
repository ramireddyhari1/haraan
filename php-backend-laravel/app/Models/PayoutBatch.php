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
        'partner_id', 'amount', 'status', 'method',
        'reference', 'period_start', 'period_end', 'note', 'processed_at',
    ];

    protected $casts = [
        'amount' => 'float',
        'period_start' => 'date',
        'period_end' => 'date',
        'processed_at' => 'datetime',
    ];

    /** Statuses that mean the money has actually landed. */
    public const PAID = ['paid', 'processed', 'completed'];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_id');
    }

    public function isPaid(): bool
    {
        return in_array(strtolower((string) $this->status), self::PAID, true);
    }
}
