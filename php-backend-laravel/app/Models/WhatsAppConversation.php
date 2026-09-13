<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $partner_id
 * @property int $venue_id
 * @property string $phone_number
 * @property string|null $customer_name
 * @property string $status active|needs_action|hold_active|converted|archived
 * @property int|null $assigned_staff_id
 * @property \Carbon\Carbon|null $last_message_at
 * @property string|null $last_message_preview
 * @property string $last_message_sender customer|partner|system
 * @property int $unread_count
 * @property \Carbon\Carbon|null $window_expires_at
 * @property int|null $active_booking_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
final class WhatsAppConversation extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_conversations';

    protected $fillable = [
        'partner_id',
        'venue_id',
        'phone_number',
        'customer_name',
        'status',
        'assigned_staff_id',
        'last_message_at',
        'last_message_preview',
        'last_message_sender',
        'unread_count',
        'window_expires_at',
        'active_booking_id',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at'   => 'datetime',
            'window_expires_at' => 'datetime',
            'unread_count'      => 'integer',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_id');
    }

    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_staff_id');
    }

    public function activeBooking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'active_booking_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class, 'conversation_id')->orderBy('created_at', 'asc');
    }

    public function latestIntent(): HasOne
    {
        return $this->hasOne(WhatsAppIntentExtraction::class, 'conversation_id')->latestOfMany();
    }

    public function intentExtractions(): HasMany
    {
        return $this->hasMany(WhatsAppIntentExtraction::class, 'conversation_id')->orderBy('created_at', 'desc');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(WhatsAppInternalNote::class, 'conversation_id')->orderBy('created_at', 'desc');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(WhatsAppTag::class, 'whatsapp_conversation_tags', 'conversation_id', 'tag_id')->withTimestamps();
    }

    public function paymentLinks(): HasMany
    {
        return $this->hasMany(WhatsAppPaymentLink::class, 'conversation_id')->orderBy('created_at', 'desc');
    }

    public function isWindowActive(): bool
    {
        return $this->window_expires_at !== null && $this->window_expires_at->isFuture();
    }

    public function secondsRemainingInWindow(): int
    {
        if (! $this->isWindowActive()) {
            return 0;
        }

        return max(0, (int) now()->diffInSeconds($this->window_expires_at, false));
    }
}