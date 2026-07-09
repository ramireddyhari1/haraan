<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BroadcastsContentChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Venue extends Model
{
    use BroadcastsContentChanges;

    /** Clients refetch venue lists when a venue changes. */
    protected string $contentDomain = 'venues';

    protected $fillable = [
        'name', 'category', 'sports', 'location', 'city', 'address', 'distance', 'latitude', 'longitude', 'map_link',
        'price', 'price_chart', 'price_note', 'rating', 'ratings_count', 'reviews_count', 'tagline', 'hours',
        'about', 'rules', 'images', 'amenities', 'is_bookable', 'is_active', 'is_featured',
        'sort_order', 'partner_id', 'organization_id',
    ];

    protected $casts = [
        'images' => 'array',
        'amenities' => 'array',
        'sports' => 'array',
        'rules' => 'array',
        'price_chart' => 'array',
        'is_bookable' => 'boolean',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'price' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    /**
     * Sports this venue offers, always non-empty: the explicit `sports` list when set, else the
     * primary category. The category is guaranteed first so the card's leading icon matches the
     * badge. De-duplicated and trimmed.
     */
    public function sportsList(): array
    {
        $list = is_array($this->sports) ? $this->sports : [];
        $list = array_merge([$this->category], $list);

        return array_values(array_unique(array_filter(array_map('trim', $list))));
    }

    public function slots(): HasMany
    {
        return $this->hasMany(VenueSlot::class)->orderBy('sort_order');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(VenueReview::class);
    }

    /** Dates this venue is closed for bookings (holidays / maintenance). */
    public function blockedDates(): HasMany
    {
        return $this->hasMany(VenueBlockedDate::class);
    }

    /** Owning organization unit (district/venue). Nullable; scoping not yet enabled. */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'organization_id');
    }
}
