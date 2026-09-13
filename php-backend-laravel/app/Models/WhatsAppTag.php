<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class WhatsAppTag extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_tags';

    protected $fillable = [
        'venue_id',
        'name',
        'color_hex',
    ];

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(WhatsAppConversation::class, 'whatsapp_conversation_tags', 'tag_id', 'conversation_id');
    }
}