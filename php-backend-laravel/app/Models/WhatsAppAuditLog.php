<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class WhatsAppAuditLog extends Model
{
    public $timestamps = false;

    protected $table = 'whatsapp_audit_logs';

    protected $fillable = [
        'venue_id',
        'conversation_id',
        'actor_id',
        'actor_name',
        'action',
        'details',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'details'    => 'array',
            'created_at' => 'datetime',
        ];
    }

    public static function log(int $venueId, string $action, ?int $conversationId = null, ?int $actorId = null, string $actorName = 'System', ?array $details = null): self
    {
        return self::create([
            'venue_id'        => $venueId,
            'conversation_id' => $conversationId,
            'actor_id'        => $actorId,
            'actor_name'      => $actorName,
            'action'          => $action,
            'details'         => $details,
            'created_at'      => now(),
        ]);
    }
}