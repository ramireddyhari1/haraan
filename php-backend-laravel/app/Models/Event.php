<?php

declare(strict_types=1);

namespace App\Models;

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
    use HasFactory;

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
        'seat_rows',
        'seats_per_row',
        'seat_selection',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date'           => 'datetime',
            'price'          => 'float',
            'total_slots'    => 'integer',
            'available_slots'=> 'integer',
            'images'         => 'array',
            'seat_selection' => 'boolean',
            'seat_rows'      => 'integer',
            'seats_per_row'  => 'integer',
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

    /** All bookings placed for this event. */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
