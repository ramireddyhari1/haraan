<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One person's request to have their Haraan account erased.
 *
 * @see \App\Services\AccountEraser  performs the erasure this row asks for
 */
final class AccountDeletionRequest extends Model
{
    protected $fillable = [
        'user_id', 'email', 'source', 'status', 'reason', 'ip_address',
        'verify_token', 'verified_at', 'completed_at', 'processed_by', 'admin_note',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Emails are matched case-insensitively everywhere else in this codebase
     * (see AuthController::passwordLogin), so normalise on write rather than
     * hoping every read remembers to lower() it.
     */
    public function setEmailAttribute(string $value): void
    {
        $this->attributes['email'] = Str::lower(trim($value));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function freshToken(): string
    {
        return Str::random(64);
    }

    /**
     * A web request only becomes actionable once the person proved they own the
     * address. In-app requests arrive already authenticated, so they skip this.
     */
    public function isActionable(): bool
    {
        return $this->status === 'pending'
            && ($this->source === 'in_app' || $this->verified_at !== null);
    }
}
