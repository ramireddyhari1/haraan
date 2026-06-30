<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

final class AdminAction extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'action', 'meta', 'ip'];

    protected $casts = [
        'meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Record an admin action for the audit trail. Captures the acting user and IP
     * automatically. e.g. AdminAction::log('booking.confirmed', ['booking_id' => 7]).
     */
    public static function log(string $action, array $meta = []): void
    {
        static::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'meta' => $meta,
            'ip' => Request::ip(),
        ]);
    }
}
