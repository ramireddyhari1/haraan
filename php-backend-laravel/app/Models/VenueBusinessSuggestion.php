<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $venue_id
 * @property string $category pricing|occupancy|retention|leakage
 * @property string $title
 * @property string $rationale
 * @property float $projected_revenue_impact
 * @property array|null $action_payload
 * @property string $status pending|applied|dismissed
 * @property \Carbon\Carbon|null $applied_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
final class VenueBusinessSuggestion extends Model
{
    use HasFactory;

    protected $table = 'venue_business_suggestions';

    protected $fillable = [
        'venue_id',
        'category',
        'title',
        'rationale',
        'projected_revenue_impact',
        'action_payload',
        'status',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'projected_revenue_impact' => 'float',
            'action_payload'           => 'array',
            'applied_at'               => 'datetime',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }
}