<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * An SMTP sending account (e.g. a Gmail address + app password) used to deliver OTP emails.
 * A pool of these is managed from the /control admin panel; {@see \App\Services\EmailOtpService}
 * rotates through the active, under-limit accounts so no single account gets throttled.
 */
class EmailSender extends Model
{
    protected $fillable = [
        'label', 'host', 'port', 'encryption', 'username', 'app_password',
        'from_name', 'is_active', 'daily_limit', 'sent_today', 'sent_date',
        'healthy', 'last_error', 'last_used_at',
    ];

    protected $casts = [
        'app_password' => 'encrypted',   // never stored in plaintext
        'is_active' => 'boolean',
        'healthy' => 'boolean',
        'port' => 'integer',
        'daily_limit' => 'integer',
        'sent_today' => 'integer',
        'sent_date' => 'date',
        'last_used_at' => 'datetime',
    ];

    /** Accounts eligible to send right now: active and under today's limit. */
    public function scopeSendable(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(function (Builder $q): void {
                // Under limit today, or the counter is from a previous day (so it resets).
                $q->whereColumn('sent_today', '<', 'daily_limit')
                    ->orWhere('sent_date', '!=', now()->toDateString())
                    ->orWhereNull('sent_date');
            });
    }

    /** Remaining sends before this account hits its daily cap (accounting for day rollover). */
    public function remainingToday(): int
    {
        $used = $this->sent_date?->isToday() ? $this->sent_today : 0;

        return max(0, $this->daily_limit - $used);
    }

    /** Record a successful send: bump today's counter (resetting on a new day) and mark healthy. */
    public function markSent(): void
    {
        $today = now()->toDateString();
        if ($this->sent_date?->toDateString() !== $today) {
            $this->sent_today = 0;
            $this->sent_date = $today;
        }
        $this->sent_today++;
        $this->healthy = true;
        $this->last_error = null;
        $this->last_used_at = now();
        $this->save();
    }

    /** Record a failed send so the admin sees why and rotation skips a dead account. */
    public function markFailed(string $error): void
    {
        $this->healthy = false;
        $this->last_error = mb_substr($error, 0, 500);
        $this->last_used_at = now();
        $this->save();
    }
}
