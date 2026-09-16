<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One charge against a member subscription. `provider_payment_id` is UNIQUE — a redelivered
 * webhook can't record it twice.
 *
 * @property int $amount_paise
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $paid_at
 */
final class MemberPayment extends Model
{
    public const STATUS_CAPTURED = 'captured';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'subscription_id', 'user_id', 'provider_payment_id', 'provider_invoice_id',
        'amount_paise', 'currency', 'status', 'method', 'paid_at',
    ];

    protected $casts = [
        'amount_paise' => 'integer',
        'paid_at' => 'datetime',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(MemberSubscription::class, 'subscription_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
