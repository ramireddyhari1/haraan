<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BroadcastsContentChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A bookable physical unit inside a venue — "Court 1", "Pitch A", "Lane 3".
 *
 * A court can host several sports ({@see $sports}); a booking locks it across all of them
 * for its time window, so the same ground shared by football and cricket never double-books.
 * {@see $price} is the court's own hourly rate, falling back to the venue price when null.
 */
final class VenueCourt extends Model
{
    use BroadcastsContentChanges;

    /** Clients refetch venue lists when a court changes. */
    protected string $contentDomain = 'venues';

    protected $fillable = [
        'venue_id', 'name', 'sports', 'price', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'sports'    => 'array',
        'price'     => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /** Sports this court supports, trimmed and de-duplicated (may be empty → all venue sports). */
    public function sportsList(): array
    {
        $list = is_array($this->sports) ? $this->sports : [];

        return array_values(array_unique(array_filter(array_map('trim', $list))));
    }

    /** True when this court can host the given sport (empty sport list = hosts anything). */
    public function supportsSport(string $sport): bool
    {
        $list = $this->sportsList();

        return $list === [] || in_array($sport, $list, true);
    }
}
