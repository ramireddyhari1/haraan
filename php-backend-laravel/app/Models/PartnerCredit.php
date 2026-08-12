<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A prepaid top-up of conversations. Positive grants only — what's been used is
 * derived from the messaging ledger, so a credit is never decremented in place
 * and the history stays auditable.
 */
final class PartnerCredit extends Model
{
    protected $fillable = ['partner_id', 'conversations', 'source', 'reference', 'note'];

    protected $casts = ['conversations' => 'integer'];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_id');
    }
}
