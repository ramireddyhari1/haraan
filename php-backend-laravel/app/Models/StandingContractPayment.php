<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Payment ledger for standing contract deposits, session advances, or monthly billing.
 *
 * @property int $id
 * @property int $standing_contract_id
 * @property int|null $booking_payment_id
 * @property float $amount
 * @property string $payment_type
 * @property string $method
 * @property int|null $collected_by
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon $created_at
 */
class StandingContractPayment extends Model
{
    use HasFactory;

    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = null;

    protected $fillable = [
        'standing_contract_id',
        'booking_payment_id',
        'amount',
        'payment_type',
        'method',
        'collected_by',
        'notes',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(StandingContract::class, 'standing_contract_id');
    }

    public function bookingPayment(): BelongsTo
    {
        return $this->belongsTo(BookingPayment::class, 'booking_payment_id');
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }
}
