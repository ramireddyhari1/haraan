<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Payout extends Model
{
    use HasFactory;

    protected $fillable = ['booking_id', 'amount', 'status', 'method', 'reference', 'note', 'processed_at'];

    public const STATUSES = ['pending' => 'Pending', 'processed' => 'Processed', 'failed' => 'Failed'];

    public const METHODS = ['upi' => 'UPI', 'bank' => 'Bank transfer', 'cash' => 'Cash'];

    protected $casts = [
        'amount' => 'float',
        'processed_at' => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
