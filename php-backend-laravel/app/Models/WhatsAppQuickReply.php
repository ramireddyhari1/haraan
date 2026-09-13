<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WhatsAppQuickReply extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_quick_replies';

    protected $fillable = [
        'venue_id',
        'shortcut',
        'category',
        'title',
        'body',
    ];

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /**
     * Interpolate dynamic tokens like {{customer_name}}, {{venue_name}}, {{court_name}}, {{slot_time}}, {{amount}}, {{payment_url}}
     * @param array<string, string> $tokens
     */
    public function render(array $tokens): string
    {
        $rendered = $this->body;
        foreach ($tokens as $key => $val) {
            $rendered = str_replace('{{' . $key . '}}', (string) $val, $rendered);
        }
        return $rendered;
    }
}