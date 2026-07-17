<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BroadcastsContentChanges;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int         $id
 * @property string      $title
 * @property string|null $description
 * @property string      $category
 * @property string      $booking_format
 * @property string      $visibility
 * @property string|null $access_code
 * @property string      $location
 * @property string      $venue
 * @property \Carbon\Carbon|null $date
 * @property string      $time
 * @property float       $price
 * @property int         $total_slots
 * @property int         $available_slots
 * @property array       $images
 * @property string      $status
 * @property int         $views
 * @property float|null  $rating
 * @property int         $ratings_count
 * @property int|null    $partner_id
 * @property int|null    $seat_rows
 * @property int|null    $seats_per_row
 * @property bool        $seat_selection
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read User|null               $partner
 * @property-read \Illuminate\Database\Eloquent\Collection<Booking> $bookings
 */
final class Event extends Model
{
    use BroadcastsContentChanges;
    use HasFactory;

    /** Clients refetch event lists when an event changes. */
    protected string $contentDomain = 'events';

    protected $fillable = [
        'title',
        'description',
        'category',
        'booking_format',
        'visibility',
        'access_code',
        'location',
        'map_link',
        'city',
        'venue',
        'date',
        'time',
        'price',
        'convenience_fee_type',
        'convenience_fee_value',
        'total_slots',
        'available_slots',
        'images',
        'status',
        'placements',
        'rating',
        'ratings_count',
        'partner_id',
        'organization_id',
        'seat_rows',
        'seats_per_row',
        'seat_selection',
        'languages',
        'age_limit',
        'kid_friendly',
        'pet_friendly',
        'layout',
        'seating_type',
        'duration',
        'entry_note',
        'info_notes',
        'good_to_know',
        'schedule',
        'lineup',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date'           => 'datetime',
            'price'          => 'float',
            'convenience_fee_value' => 'float',
            'total_slots'    => 'integer',
            'available_slots'=> 'integer',
            'views'          => 'integer',
            'rating'         => 'float',
            'ratings_count'  => 'integer',
            'images'         => 'array',
            'placements'     => 'array',
            'seat_selection' => 'boolean',
            'seat_rows'      => 'integer',
            'seats_per_row'  => 'integer',
            'languages'      => 'array',
            'kid_friendly'   => 'boolean',
            'pet_friendly'   => 'boolean',
            'info_notes'     => 'array',
            'good_to_know'   => 'array',
            'schedule'       => 'array',
            'lineup'         => 'array',
        ];
    }

    // -------------------------------------------------------------------------
    //  Attributes
    // -------------------------------------------------------------------------

    /**
     * Canonicalize status to lowercase on write.
     *
     * Public listings query lowercase ('published'), but the Filament admin
     * form and the events API historically stored 'PUBLISHED'/'DRAFT'. That
     * mismatch made admin-published events invisible on the public site.
     * Normalizing on write fixes every write path at once and can't recur.
     */
    protected function status(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => is_string($value) ? strtolower(trim($value)) : $value,
        );
    }

    // -------------------------------------------------------------------------
    //  Images
    // -------------------------------------------------------------------------

    /**
     * The event's images as browser-loadable URLs.
     *
     * `images` mixes hotlinked absolute URLs with admin-uploaded paths relative
     * to the `public` storage disk ("events/xyz.png"). The Android app resolves
     * the relative ones client-side (EventRepository → "$baseUrl/storage/$path");
     * the web must do the same server-side or uploaded posters 404.
     *
     * @return array<int, string>
     */
    public function imageUrls(): array
    {
        return \App\Support\MediaUrl::resolveMany(is_array($this->images) ? $this->images : []);
    }

    /** First browser-loadable image, or null when the event has none. */
    public function heroImageUrl(): ?string
    {
        return $this->imageUrls()[0] ?? null;
    }

    /**
     * "Who takes the stage" — the host-authored performer lineup, normalized to
     * {name, subtitle, image(absolute URL or '')}. Rows without a name are
     * dropped; an uploaded photo wins over a pasted image URL. Shared by the
     * API resource and the public site's event page.
     *
     * @return array<int, array{name: string, subtitle: string, image: string}>
     */
    public function lineupRows(): array
    {
        return collect((array) ($this->lineup ?? []))
            ->filter(fn ($r) => is_array($r) && trim((string) ($r['name'] ?? '')) !== '')
            ->map(function ($r) {
                $upload = is_array($r['image'] ?? null) ? ($r['image'][0] ?? '') : ($r['image'] ?? '');
                $upload = trim((string) $upload);
                $image  = $upload !== '' ? $upload : trim((string) ($r['image_url'] ?? ''));

                return [
                    'name'     => trim((string) ($r['name'] ?? '')),
                    'subtitle' => trim((string) ($r['subtitle'] ?? '')),
                    'image'    => \App\Support\MediaUrl::resolve($image !== '' ? $image : null) ?? '',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * The admin-authored run-of-show, normalized to {time, title, note}. Rows
     * without a time are dropped. Shared by the API resource and the public
     * site's event page (schedule sheet).
     *
     * @return array<int, array{time: string, title: string, note: string}>
     */
    public function scheduleRows(): array
    {
        return collect((array) ($this->schedule ?? []))
            ->filter(fn ($r) => is_array($r) && trim((string) ($r['time'] ?? '')) !== '')
            ->map(fn ($r) => [
                'time'  => trim((string) ($r['time'] ?? '')),
                'title' => trim((string) ($r['title'] ?? '')),
                'note'  => trim((string) ($r['note'] ?? '')),
            ])
            ->values()
            ->all();
    }

    // -------------------------------------------------------------------------
    //  Relationships
    // -------------------------------------------------------------------------

    /** The partner (user) who created this event. */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_id');
    }

    /** Owning organization unit (district/venue). Nullable; scoping not yet enabled. */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'organization_id');
    }

    // -------------------------------------------------------------------------
    //  "Good to Know"
    // -------------------------------------------------------------------------

    /**
     * Assemble the structured "Good to Know" rows from the first-class columns
     * plus any admin-authored extras. Each row is {icon, label, value}; clients
     * map `icon` (a stable key) to a vector/SVG. Empty attributes are skipped,
     * so the section only shows what the host actually set. Shared by the API
     * resource and the public site's event page.
     *
     * @return array<int, array{icon: string, label: string, value: string}>
     */
    public function goodToKnowRows(): array
    {
        $rows = [];

        $add = static function (string $icon, string $label, ?string $value) use (&$rows): void {
            $value = $value !== null ? trim($value) : '';
            if ($value !== '') {
                $rows[] = ['icon' => $icon, 'label' => $label, 'value' => $value];
            }
        };

        $languages = array_values(array_filter(
            (array) ($this->languages ?? []),
            static fn ($l): bool => is_string($l) && trim($l) !== '',
        ));
        $add('language', 'Language', $languages === [] ? null : implode(', ', $languages));
        $add('duration', 'Duration', $this->duration);
        $add('age', 'Age limit', $this->age_limit);
        $add('entry', 'Entry', $this->entry_note);
        $add('layout', 'Layout', $this->layout);
        $add('seating', 'Seating', $this->seating_type);

        if ($this->kid_friendly !== null) {
            $add('kids', 'Kids', $this->kid_friendly ? 'Kid friendly' : 'No kids');
        }
        if ($this->pet_friendly !== null) {
            $add('pets', 'Pets', $this->pet_friendly ? 'Pet friendly' : 'No pets');
        }

        foreach ((array) ($this->good_to_know ?? []) as $extra) {
            if (is_array($extra)) {
                $add('info', (string) ($extra['label'] ?? ''), (string) ($extra['value'] ?? ''));
            }
        }

        return $rows;
    }

    /** All bookings placed for this event. */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /** Priced ticket tiers offered for this event (ordered for display). */
    public function ticketTypes(): HasMany
    {
        return $this->hasMany(TicketType::class)->orderBy('sort')->orderBy('id');
    }

    /**
     * The host-set convenience fee for an order of the given ticket subtotal.
     * `flat` is a fixed ₹ amount; `percent` is a share of the subtotal. Rounded to
     * paise and never negative. Returns 0 when no fee is configured or the order is free.
     */
    public function convenienceFeeFor(float $subtotal): float
    {
        if ($subtotal <= 0) {
            return 0.0;
        }

        $value = max(0.0, (float) $this->convenience_fee_value);

        return match ($this->convenience_fee_type) {
            'flat'    => round($value, 2),
            'percent' => round($subtotal * $value / 100, 2),
            default   => 0.0,
        };
    }
}
