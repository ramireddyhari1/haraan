<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A 24-hour messaging window — the unit WhatsApp actually bills. Every message
 * to the same recipient, in the same category, inside the same window rides one
 * charge; the next one after it expires opens (and costs) a new conversation.
 */
final class MessageConversation extends Model
{
    protected $fillable = [
        'partner_id', 'channel', 'recipient', 'category',
        'opened_at', 'expires_at', 'message_count',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'expires_at' => 'datetime',
        'message_count' => 'integer',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MessageLog::class, 'conversation_id');
    }

    public function isLive(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isFuture();
    }
}
