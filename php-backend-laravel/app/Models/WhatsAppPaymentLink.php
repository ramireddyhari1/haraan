<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WhatsAppPaymentLink extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_payment_links';

    protected $fillable = [
        'conversation_id',
        'booking_id',
        'venue_id',
        'razorpay_payment_link_id',
        'short_url',
        'amount',
        'status',
        'expires_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount'     => 'float',
            'expires_at' => 'datetime',
            'paid_at'    => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsAppConversation::class, 'conversation_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class, 'venue_id');
    }
}