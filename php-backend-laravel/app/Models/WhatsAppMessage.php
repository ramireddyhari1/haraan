<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $conversation_id
 * @property string $direction inbound|outbound
 * @property string $sender_type customer|partner|system|bot
 * @property int|null $sender_id
 * @property string $message_type text|image|document|audio|video|location|template|payment_link|ticket_card
 * @property string $body
 * @property string|null $media_url
 * @property string|null $provider_message_id
 * @property string $delivery_status pending|sent|delivered|read|failed
 * @property array|null $raw_payload
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
final class WhatsAppMessage extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'conversation_id',
        'direction',
        'sender_type',
        'sender_id',
        'message_type',
        'body',
        'media_url',
        'provider_message_id',
        'delivery_status',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsAppConversation::class, 'conversation_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}