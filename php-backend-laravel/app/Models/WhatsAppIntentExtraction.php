<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $conversation_id
 * @property int|null $message_id
 * @property string $intent_type
 * @property string|null $detected_sport
 * @property \Carbon\Carbon|null $detected_date
 * @property string|null $detected_start_time
 * @property string|null $detected_end_time
 * @property int $detected_duration_minutes
 * @property string|null $detected_court_name
 * @property int|null $resolved_court_id
 * @property int|null $resolved_slot_id
 * @property float|null $calculated_rate
 * @property float $confidence_score
 * @property string $action_state suggested|hold_created|converted|dismissed
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
final class WhatsAppIntentExtraction extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_intent_extractions';

    protected $fillable = [
        'conversation_id',
        'message_id',
        'intent_type',
        'detected_sport',
        'detected_date',
        'detected_start_time',
        'detected_end_time',
        'detected_duration_minutes',
        'detected_court_name',
        'resolved_court_id',
        'resolved_slot_id',
        'calculated_rate',
        'confidence_score',
        'action_state',
    ];

    protected function casts(): array
    {
        return [
            'detected_date'             => 'date',
            'detected_duration_minutes' => 'integer',
            'calculated_rate'           => 'float',
            'confidence_score'          => 'float',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsAppConversation::class, 'conversation_id');
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(WhatsAppMessage::class, 'message_id');
    }

    public function resolvedCourt(): BelongsTo
    {
        return $this->belongsTo(VenueCourt::class, 'resolved_court_id');
    }

    public function resolvedSlot(): BelongsTo
    {
        return $this->belongsTo(VenueSlot::class, 'resolved_slot_id');
    }
}