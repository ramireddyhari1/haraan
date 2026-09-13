<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $venue_id
 * @property string $alert_type
 * @property string $severity low|medium|high|critical
 * @property string $title
 * @property string $description
 * @property array|null $metrics_payload
 * @property bool $is_resolved
 * @property \Carbon\Carbon|null $resolved_at
 * @property int|null $resolved_by
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
final class VenueOperationsAlert extends Model
{
    use HasFactory;

    protected $table = 'venue_operations_alerts';

    protected $fillable = [
        'venue_id',
        'alert_type',
        'severity',
        'title',
        'description',
        'metrics_payload',
        'is_resolved',
        'resolved_at',
        'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'metrics_payload' => 'array',
            'is_resolved'     => 'boolean',
            'resolved_at'     => 'datetime',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}