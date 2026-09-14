<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BroadcastsVenueAvailability;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One bookable time row on a venue's weekly template.
 *
 * A slot may be limited to some of the venue's sports ({@see $sports}) — a turf that only
 * opens for football in the evenings. Empty means every sport, which is what every row
 * meant before the column existed. Whether a given court may be sold at this time is the
 * intersection of this list and {@see VenueCourt::$sports}.
 */
final class VenueSlot extends Model
{
    use BroadcastsVenueAvailability;

    /**
     * `price` and `capacity` belong here. They were missing while saveSlot() wrote
     * both, so mass assignment dropped them in silence: the partner set a slot rate
     * of 500 for 4 people, the API answered {"status":"ok"}, and the row kept
     * price NULL and capacity 1 — which is why every desk cell read "0/1".
     */
    protected $fillable = [
        'venue_id', 'day', 'time', 'price', 'capacity', 'sports', 'is_available', 'filling_fast', 'sort_order',
    ];

    protected $casts = [
        'is_available' => 'boolean',
        'filling_fast' => 'boolean',
        'capacity'     => 'integer',
        'sports'       => 'array',
    ];

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /**
     * The sports this slot runs for, cleaned up. Same normalisation as
     * {@see VenueCourt::sportsList()} so the two lists compare like for like.
     *
     * @return list<string>
     */
    public function sportsList(): array
    {
        $list = is_array($this->sports) ? $this->sports : [];

        return array_values(array_unique(array_filter(array_map('trim', $list))));
    }

    /** True when this slot runs for the given sport (empty sport list = runs for anything). */
    public function supportsSport(string $sport): bool
    {
        $list = $this->sportsList();

        return $list === [] || in_array($sport, $list, true);
    }

    /**
     * Can this court be sold at this time?
     *
     * Either side leaving its list empty means "no restriction", so a venue that never
     * touches sports behaves exactly as it did before the column existed. Only when both
     * sides name sports and share none is the pairing refused.
     */
    public function allowsCourt(VenueCourt $court): bool
    {
        $mine = $this->sportsList();
        $theirs = $court->sportsList();

        return $mine === [] || $theirs === [] || array_intersect($mine, $theirs) !== [];
    }
}
