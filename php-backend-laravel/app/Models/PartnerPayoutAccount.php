<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A partner's settlement destination — bank or UPI. One per partner owner
 * (User::effectivePartnerId()). The partner enters and edits their own details;
 * `verified_at` is set by admin once the destination is confirmed payable.
 */
final class PartnerPayoutAccount extends Model
{
    protected $fillable = [
        'partner_id', 'method', 'account_holder',
        'bank_name', 'account_number', 'ifsc_code', 'upi_vpa', 'verified_at',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_id');
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    /** A single human line describing the destination, with numbers masked. */
    public function summaryLine(): string
    {
        if ($this->method === 'upi') {
            return $this->maskUpi((string) $this->upi_vpa);
        }

        $tail = $this->maskTail((string) $this->account_number);
        $bank = $this->bank_name ? $this->bank_name . ' · ' : '';

        return $bank . $tail;
    }

    /** •••• 4321 — only the last four survive. */
    public function maskTail(?string $num): string
    {
        $num = preg_replace('/\s+/', '', (string) $num);
        if ($num === '' || $num === null) {
            return '—';
        }

        return '•••• ' . substr($num, -4);
    }

    /** na••@okhdfc — keep the handle head and the bank suffix. */
    public function maskUpi(?string $vpa): string
    {
        $vpa = (string) $vpa;
        if (! str_contains($vpa, '@')) {
            return $vpa !== '' ? $vpa : '—';
        }
        [$head, $bank] = explode('@', $vpa, 2);
        $shown = mb_substr($head, 0, 2);

        return $shown . str_repeat('•', max(2, mb_strlen($head) - 2)) . '@' . $bank;
    }
}
