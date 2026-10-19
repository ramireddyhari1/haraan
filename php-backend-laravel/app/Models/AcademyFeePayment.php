<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One academy fee collected at the desk. A row per payment, never a running
 * total: a parent who says "I paid in March" is answered by a row with a date,
 * a method and the name of whoever took it.
 *
 * `method = opening` is the balance carried over from before the ledger existed;
 * it belongs to no shift and is never counted as today's money.
 */
final class AcademyFeePayment extends Model
{
    public const DESK_METHODS = ['cash', 'upi', 'card'];

    protected $fillable = [
        'batch_enrollment_id', 'partner_id', 'venue_id', 'amount', 'method', 'months',
        'paid_until', 'collected_by', 'shift_session_id', 'collected_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'months' => 'integer',
        'paid_until' => 'date',
        'collected_at' => 'datetime',
    ];

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(BatchEnrollment::class, 'batch_enrollment_id');
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }
}
