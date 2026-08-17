<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A student on a batch, paid up to a date. Keyed on phone like the CRM. */
final class BatchEnrollment extends Model
{
    protected $fillable = [
        'venue_batch_id', 'partner_id', 'student_name', 'student_phone',
        'amount_paid', 'paid_until', 'is_active',
    ];

    protected $casts = [
        'amount_paid' => 'integer',
        'paid_until' => 'date',
        'is_active' => 'boolean',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(VenueBatch::class, 'venue_batch_id');
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(BatchAttendance::class);
    }

    /** Fees lapsed — the desk's "who to chase" signal. */
    public function isOverdue(): bool
    {
        return $this->paid_until !== null && $this->paid_until->isPast();
    }
}
