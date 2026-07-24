<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The template catalogue. Outside a live 24h window WhatsApp only accepts
 * pre-approved templates, so this is what partners will pick from — platform
 * rows (partner_id null) on the shared sender, partner-authored rows only once
 * a partner brings their own WABA.
 *
 * Empty in Phase 0: nothing reads it yet, it exists so the messages we already
 * send can be given keys and registered as they're formalised.
 */
final class MessageTemplate extends Model
{
    protected $fillable = [
        'key', 'partner_id', 'name', 'channel', 'category', 'locale', 'body',
        'variables', 'provider_template_id', 'status', 'is_active',
    ];

    protected $casts = [
        'variables' => 'array',
        'is_active' => 'boolean',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_id');
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}
