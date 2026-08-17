<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One student, present on one date. */
final class BatchAttendance extends Model
{
    protected $table = 'batch_attendance';

    protected $fillable = ['batch_enrollment_id', 'date'];

    protected $casts = ['date' => 'date'];

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(BatchEnrollment::class, 'batch_enrollment_id');
    }
}
