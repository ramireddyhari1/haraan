<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One "list my business on Haraan" application from the public /become-a-partner form.
 *
 * The option lists below are the form's vocabulary. They are stored as keys and turned
 * back into words with {@see self::labels()}, so renaming a label never orphans old rows.
 */
class PartnerApplication extends Model
{
    public const STATUS_NEW = 'new';

    public const STATUS_CONTACTED = 'contacted';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_NEW => 'New',
        self::STATUS_CONTACTED => 'Contacted',
        self::STATUS_APPROVED => 'Approved',
        self::STATUS_REJECTED => 'Not a fit',
    ];

    /** What they run → the partner lane the account is created in ({@see \App\Support\PartnerLane}). */
    public const BUSINESS_TYPES = [
        'venue' => 'Sports venue or turf',
        'academy' => 'Academy or coaching',
        'cafe' => 'Café or play lounge',
        'event' => 'Events and tournaments',
    ];

    public const PARTNER_TYPE_FOR = [
        'venue' => 'venue',
        'academy' => 'venue',
        'cafe' => 'cafe',
        'event' => 'event',
    ];

    public const BOOKING_CHANNELS = [
        'walk_in' => 'Walk-ins',
        'phone' => 'Phone calls',
        'whatsapp' => 'WhatsApp',
        'other_app' => 'Another booking app',
        'website' => 'Own website',
        'none' => 'Not open yet',
    ];

    public const MONTHLY_BOOKINGS = [
        'starting' => 'Just starting',
        'under_100' => 'Under 100',
        '100_500' => '100 – 500',
        '500_plus' => '500+',
    ];

    public const GOALS = [
        'fill_quiet_hours' => 'Fill quiet hours',
        'online_payments' => 'Take payments online',
        'no_double_booking' => 'Stop double bookings',
        'desk_cash' => 'Track the desk and cash',
        'run_events' => 'Sell event tickets',
        'grow_members' => 'Grow regulars and members',
    ];

    public const CONTACT_ROLES = [
        'owner' => 'Owner',
        'partner' => 'Co-owner',
        'manager' => 'Manager',
    ];

    public const CALL_TIMES = [
        'morning' => 'Morning',
        'afternoon' => 'Afternoon',
        'evening' => 'Evening',
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'sports' => 'array',
        'booking_channels' => 'array',
        'goals' => 'array',
        'courts' => 'integer',
        'courts_detail' => 'array',
        'base_price' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
        'hours_json' => 'array',
        'slot_minutes' => 'integer',
        'cancel_free_hours' => 'integer',
        'cancel_refund_percent' => 'integer',
        'images' => 'array',
        'amenities' => 'array',
        'rules' => 'array',
        'reviewed_at' => 'datetime',
        'contacted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::created(function (self $application): void {
            if ($application->reference === null || str_starts_with($application->reference, 'TMP-')) {
                $application->forceFill(['reference' => self::referenceFor($application->id)])->saveQuietly();
            }
        });
    }

    /** Short, readable over the phone: HP-000123. */
    public static function referenceFor(int $id): string
    {
        return 'HP-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<int, string>|null  $keys
     * @param  array<string, string>  $vocabulary
     * @return array<int, string>
     */
    public static function labels(?array $keys, array $vocabulary): array
    {
        return array_values(array_map(
            static fn (string $key): string => $vocabulary[$key] ?? $key,
            array_filter((array) $keys, 'is_string'),
        ));
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_NEW, self::STATUS_CONTACTED]);
    }

    public function businessTypeLabel(): string
    {
        return self::BUSINESS_TYPES[$this->business_type] ?? (string) $this->business_type;
    }

    public function partnerType(): string
    {
        return self::PARTNER_TYPE_FOR[$this->business_type] ?? 'venue';
    }

    /** Business types that get a place with courts, hours and amenities — events don't. */
    public const PLACE_TYPES = ['venue', 'academy', 'cafe'];

    public const WEEKDAYS = ['Mon' => 'Monday', 'Tue' => 'Tuesday', 'Wed' => 'Wednesday', 'Thu' => 'Thursday', 'Fri' => 'Friday', 'Sat' => 'Saturday', 'Sun' => 'Sunday'];

    public const SLOT_LENGTHS = [30 => '30 minutes', 60 => '1 hour', 90 => '1.5 hours', 120 => '2 hours'];

    public function hasPlace(): bool
    {
        return in_array($this->business_type, self::PLACE_TYPES, true);
    }

    /** "Mon–Fri 6:00 AM – 11:00 PM · Sat closed" style, one line per distinct window. */
    public function hoursLabel(): ?string
    {
        $hours = is_array($this->hours_json) ? $this->hours_json : [];

        if ($hours === []) {
            return null;
        }

        $fmt = static fn (string $t): string => \Carbon\Carbon::createFromFormat('H:i', $t)->format('g:i A');
        $lines = [];

        foreach (self::WEEKDAYS as $key => $full) {
            $day = $hours[$key] ?? null;
            $window = is_array($day) && ! empty($day['open']) && ! empty($day['close'])
                ? $fmt($day['open']) . ' – ' . $fmt($day['close'])
                : 'Closed';
            $lines[$window][] = $key;
        }

        return collect($lines)
            ->map(fn (array $days, string $window): string => (count($days) === 7 ? 'Every day' : implode(', ', $days)) . ': ' . $window)
            ->implode(' · ');
    }

    /** @return array<int, array{name: string, sports: array<int, string>, price: int|null}> */
    public function courtList(): array
    {
        return array_values(array_filter((array) $this->courts_detail, 'is_array'));
    }

    public function cancellationLabel(): ?string
    {
        if ($this->cancel_free_hours === null) {
            return null;
        }

        return 'Free up to ' . $this->cancel_free_hours . ' h before'
            . ($this->cancel_refund_percent !== null ? ', then ' . $this->cancel_refund_percent . '% back' : '');
    }

    /** Public URLs of the uploaded photos, for the review page. */
    public function imageUrls(): array
    {
        return array_map(
            static fn (string $p): string => \Illuminate\Support\Facades\Storage::disk('public')->url($p),
            array_values(array_filter((array) $this->images, 'is_string')),
        );
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class, 'venue_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_id');
    }
}
