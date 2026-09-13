<?php

declare(strict_types=1);

namespace App\Models\Hrms;

use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WorkforceAuditLedger extends Model
{
    use HasFactory;

    protected $table = 'workforce_audit_ledger';

    protected $fillable = [
        'event_uuid',
        'occurred_at',
        'tenant_id',
        'venue_id',
        'actor_id',
        'actor_role',
        'impersonated_by_id',
        'entity_type',
        'entity_id',
        'event_name',
        'payload_before',
        'payload_after',
        'client_ip',
        'user_agent',
        'device_fingerprint',
        'geo_latitude',
        'geo_longitude',
        'telemetry_metadata',
        'previous_event_hash',
        'signature_hash',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'payload_before' => 'array',
        'payload_after' => 'array',
        'telemetry_metadata' => 'array',
        'geo_latitude' => 'decimal:8',
        'geo_longitude' => 'decimal:8',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function impersonatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonated_by_id');
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class, 'venue_id');
    }
}
