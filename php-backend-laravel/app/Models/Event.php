<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BroadcastsContentChanges;
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
        'venue',
        'date',
        'time',
        'price',
        'total_slots',
        'available_slots',
        'images',
        'status',
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
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date'           => 'datetime',
            'price'          => 'float',
            'total_slots'    => 'integer',
            'available_slots'=> 'integer',
            'views'          => 'integer',
            'images'         => 'array',
            'seat_selection' => 'boolean',
            'seat_rows'      => 'integer',
            'seats_per_row'  => 'integer',
            'languages'      => 'array',
            'kid_friendly'   => 'boolean',
            'pet_friendly'   => 'boolean',
            'info_notes'     => 'array',
            'good_to_know'   => 'array',
        ];
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
}
