<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use App\Models\Concerns\BroadcastsContentChanges;
use Illuminate\Database\Eloquent\Builder;
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
        'place_id',
        'latitude',
        'longitude',
        'city',
        'venue',
        'date',
        'time',
        'end_time',
        'price',
        'convenience_fee_type',
        'convenience_fee_value',
        'convenience_fee_label',
        'fees',
        'tax_type',
        'tax_value',
        'total_slots',
        'tickets_per_slot',
        'release_phases',
        'inquiry_phone',
        'gateway_fee_payer',
        'gateway_fee_type',
        'gateway_fee_value',
        'platform_fee_payer',
        'platform_fee_type',
        'platform_fee_value',
        'available_slots',
        'is_sold_out',
        'images',
        'gallery',
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
        'faqs',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date'           => 'datetime',
            'latitude'       => 'float',
            'longitude'      => 'float',
            'price'          => 'float',
            'convenience_fee_value' => 'float',
            'fees'           => 'array',
            'tax_value'      => 'float',
            'total_slots'    => 'integer',
            'tickets_per_slot' => 'boolean',
            'release_phases' => 'array',
            'gateway_fee_value'  => 'float',
            'platform_fee_value' => 'float',
            'available_slots'=> 'integer',
            'is_sold_out'    => 'boolean',
            'views'          => 'integer',
            'rating'         => 'float',
            'ratings_count'  => 'integer',
            'images'         => 'array',
            'gallery'        => 'array',
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
            'faqs'           => 'array',
            'followers_notified_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Phase 2: the first time an event goes published, ping the host's followers.
        static::saved(static function (self $event): void {
            $event->maybeNotifyFollowers();
        });
    }

    /**
     * Notify this event's host's followers, exactly once, when it first becomes
     * published. Best-effort: any failure is reported but never blocks the save.
     */
    public function maybeNotifyFollowers(): void
    {
        if (strtolower((string) $this->status) !== 'published'
            || $this->followers_notified_at !== null
            || $this->partner_id === null) {
            return;
        }

        try {
            $followerIds = \Illuminate\Support\Facades\DB::table('host_followers')
                ->where('host_id', $this->partner_id)
                ->pluck('user_id');

            if ($followerIds->isNotEmpty()) {
                $profile = $this->partner?->hostProfile;
                $hostName = $profile?->display_name ?? $this->partner?->name ?? 'An organiser';

                $notification = \App\Models\Notification::create([
                    'title' => 'New event from ' . $hostName,
                    'body' => $this->title,
                    'deep_link' => $profile?->slug ? url('/host/' . $profile->slug) : url('/events/' . $this->id),
                    'audience_type' => 'host_followers',
                    'audience_value' => (string) $this->partner_id,
                    'status' => 'sent',
                    'sent_at' => now(),
                    'created_by' => $this->partner_id,
                ]);

                \Illuminate\Support\Facades\DB::table('notification_recipients')->insert(
                    $followerIds->map(fn ($uid): array => [
                        'notification_id' => $notification->id,
                        'user_id' => $uid,
                    ])->all(),
                );
            }

            // Stamp regardless (even with zero followers) so we never re-scan.
            $this->forceFill(['followers_notified_at' => now()])->saveQuietly();
        } catch (\Throwable $e) {
            report($e);
        }
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

    /** True once the event has a real geocoded pin (set via Places Autocomplete). */
    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /**
     * The best map query for this event: the exact "lat,lng" pin when we have it,
     * otherwise the venue + city free text (so the link still lands roughly right
     * on older events created before coordinates existed).
     */
    public function mapsQuery(): string
    {
        if ($this->hasCoordinates()) {
            return $this->latitude . ',' . $this->longitude;
        }

        $bits = array_filter([trim((string) $this->venue), trim((string) ($this->location ?: $this->city))]);

        return $bits === [] ? (string) ($this->city ?: 'India') : implode(' ', $bits);
    }

    /**
     * "5:00 PM – 8:00 PM", or just the start when the host left the end blank
     * (and null when there's no time at all). Both halves are the same free-text
     * "g:i A" strings the form writes, so this only ever joins — it never parses
     * or reformats what a host typed.
     */
    public function timeRangeLabel(): ?string
    {
        $start = trim((string) ($this->time ?? ''));
        $end   = trim((string) ($this->end_time ?? ''));

        if ($start === '') {
            return $end !== '' ? 'Ends ' . $end : null;
        }

        return $end !== '' && $end !== $start ? $start . ' – ' . $end : $start;
    }

    /**
     * The neighbourhood the venue sits in — "Koramangala", not the whole postal
     * address. `location` is a Google-formatted address ("28, 80 Feet Rd, S.T.
     * Bed, 4th Block, Koramangala, Bengaluru, Karnataka 560034, India"), and the
     * part that actually tells a local where this is sits immediately before the
     * city. A venue name alone ("Big bean cafe") means nothing to someone
     * deciding whether they can get there.
     *
     * Returns null when the address carries nothing more specific than the city,
     * so callers can fall back rather than print a duplicate.
     */
    public function venueArea(): ?string
    {
        $city = mb_strtolower(trim((string) $this->city));
        $venue = mb_strtolower(trim((string) $this->venue));

        $parts = collect(explode(',', (string) $this->location))
            ->map(fn ($p) => trim($p))
            ->filter(function (string $p) use ($city, $venue): bool {
                if ($p === '') {
                    return false;
                }
                $low = mb_strtolower($p);

                return $low !== $city
                    && $low !== $venue
                    && $low !== 'india'
                    // "Karnataka 560034" / "560034" — state-with-pincode and bare pins.
                    && ! preg_match('/\b\d{6}\b/', $p)
                    // A bare house/plot number ("28") locates nobody.
                    && ! preg_match('/^\d+[a-z]?$/i', $p);
            })
            ->values();

        // The last surviving part is the one closest to the city — the locality.
        $area = (string) $parts->last();

        return $area !== '' ? $area : null;
    }

    /** A "Directions" deep link — precise when coordinates exist, text search otherwise. */
    public function directionsUrl(): string
    {
        return 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($this->mapsQuery());
    }

    /**
     * An embeddable Google map URL centred on the event, or null when no API key is
     * configured (the page then falls back to its plain Maps-search card). Uses the
     * exact pin when known; otherwise a place search so it never claims a false spot.
     */
    public function mapEmbedUrl(?string $apiKey = null): ?string
    {
        $key = $apiKey ?? (string) config('services.google_maps.key');
        if ($key === '') {
            return null;
        }

        // "place" mode drops a marker at q — which is a "lat,lng" pin when we have
        // coordinates, or a text search otherwise (so it never claims a false spot).
        $params = ['key' => $key, 'q' => $this->mapsQuery()];
        if ($this->hasCoordinates()) {
            $params['zoom'] = '16';
        }

        return 'https://www.google.com/maps/embed/v1/place?' . http_build_query($params);
    }

    /**
     * Who is actually putting this event on.
     *
     * The detail page used to read `artist` here — that's the LINEUP performer
     * relation, not the organiser — so every event without a booked artist
     * introduced itself as "Event Host", even when a real, verified host profile
     * existed on the partner. This resolves the organiser properly and hands the
     * view only facts it can print.
     *
     * Falls back name-wise (host profile → partner account → generic), but never
     * fakes the rest: `verified` is the profile's real flag rather than a label
     * every event wore, and the counts are omitted by the view when zero.
     *
     * @return array{name:string, initial:string, logo:?string, verified:bool,
     *               tagline:?string, url:?string, followers:int, events:int}
     */
    public function organiserCard(): array
    {
        if ($this->organiserCardMemo !== null) {
            return $this->organiserCardMemo;
        }

        $profile = $this->partner?->hostProfile;
        $profile = ($profile && $profile->isLive()) ? $profile : null;

        $name = trim((string) ($profile?->display_name ?: $this->partner?->name ?: ''));
        if ($name === '') {
            $name = 'Event Host';
        }

        // Only count the organiser's OTHER live events, and only when we know who
        // they are — "1 event" on the page you're already looking at says nothing.
        $events = 0;
        if ($this->partner_id !== null) {
            $events = static::query()
                ->where('partner_id', $this->partner_id)
                ->whereKeyNot($this->getKey())
                ->whereRaw('lower(status) = ?', ['published'])
                ->count();
        }

        return $this->organiserCardMemo = [
            'name'      => $name,
            'initial'   => mb_strtoupper(mb_substr($name, 0, 1)),
            'logo'      => $profile?->logoUrl(),
            'verified'  => (bool) $profile?->isVerified(),
            'tagline'   => trim((string) ($profile?->tagline ?? '')) ?: null,
            'slug'      => $profile?->slug,
            'url'       => $profile ? url('/host/' . $profile->slug) : null,
            'followers' => $profile ? $profile->followersCount() : 0,
            'events'    => $events,
            // Drives the Follow button's state. The follow feature already existed
            // on the organiser's own page; the event page — where people actually
            // discover a host — never offered it.
            'following' => (bool) $profile?->isFollowedBy(auth()->user()),
        ];
    }

    /** @var array<string, mixed>|null */
    private ?array $organiserCardMemo = null;

    /**
     * Photos of the venue itself, taken from its Google listing — the interior,
     * the stage, the lighting. What "what's this place like" actually means, and
     * the one thing a map and a poster can't answer.
     *
     * Empty is the normal quiet outcome (no key, no matchable listing, too few
     * photos) and the page then draws no section. Each row carries the
     * contributor credit Google requires to be displayed alongside the photo, so
     * the view must render it — see PlacePhotos.
     *
     * Deliberately NOT merged into `gallery`: these are Google's images, served
     * fresh through our proxy and never adopted as ours.
     *
     * @return list<array{url:string, credit:string, credit_uri:string}>
     */
    public function venuePhotos(): array
    {
        // The detail page renders a mobile and a desktop copy of this strip from
        // the same model, so without a memo every lookup here runs twice.
        if ($this->venuePhotosMemo !== null) {
            return $this->venuePhotosMemo;
        }

        // The host's own curated photos are better than a stranger's and already
        // have a Gallery section. Only fill the gap when there isn't one.
        if (count($this->galleryUrls()) >= 3) {
            return $this->venuePhotosMemo = [];
        }

        return $this->venuePhotosMemo = array_map(fn (array $p): array => [
            'url'        => route('site.event.venuephoto', ['id' => $this->id, 'index' => $p['index']]),
            'credit'     => $p['credit'],
            'credit_uri' => $p['credit_uri'],
        ], \App\Support\PlacePhotos::forEvent($this));
    }

    /** @var list<array{url:string, credit:string, credit_uri:string}>|null */
    private ?array $venuePhotosMemo = null;

    /**
     * The showcase gallery — extra photos beyond the poster, managed from the
     * Gallery step in the partner console and rendered as the "Gallery" section
     * on the event detail page. Absolute, browser-loadable URLs.
     *
     * @return list<string>
     */
    public function galleryUrls(): array
    {
        return \App\Support\MediaUrl::resolveMany(is_array($this->gallery) ? $this->gallery : []);
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

    /**
     * Host-authored FAQs, normalized to {question, answer}. Rows missing either
     * half are dropped.
     *
     * Hosts type these in a repeater and a lot of them paste "Q: ..." / "A: ..."
     * straight out of a doc or an AI draft, so the stored copy is inconsistent —
     * some rows carry the prefix, some don't, and a list where half the entries
     * start with "Q:" is the single loudest "a machine wrote this" tell on the
     * page. Strip the labels here (the UI already says these are questions) and
     * add the missing question mark, so every client renders one clean voice.
     *
     * @return array<int, array{question: string, answer: string}>
     */
    public function faqRows(): array
    {
        $strip = static function (string $text, string $pattern): string {
            return trim(preg_replace($pattern, '', trim($text)) ?? '');
        };

        return collect((array) ($this->faqs ?? []))
            ->filter(fn ($f) => is_array($f))
            ->map(function ($f) use ($strip): array {
                $q = $strip((string) ($f['question'] ?? ''), '/^(?:q|question)\s*[:.\-–)]\s*/i');
                $a = $strip((string) ($f['answer'] ?? ''), '/^(?:a|ans|answer)\s*[:.\-–)]\s*/i');

                // A question that lost its "?" to a paste reads like a heading.
                if ($q !== '' && ! preg_match('/[?!.]$/u', $q)) {
                    $q .= '?';
                }

                return ['question' => $q, 'answer' => $a];
            })
            ->filter(fn (array $f): bool => $f['question'] !== '' && $f['answer'] !== '')
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

    /**
     * The "Important information" bullets, normalised.
     *
     * `info_notes` is a TagsInput, so hosts routinely paste an entire T&C block
     * as ONE tag with the bullets still inside it ("• Carry ID. • No refunds."),
     * which then renders as a single list item containing a wall of text with
     * literal bullet glyphs. Split those apart on newlines and on any leading
     * bullet/dash marker, and strip the marker so the list styles its own.
     *
     * @return array<int, string>
     */
    public function infoNoteRows(): array
    {
        $rows = [];

        foreach ((array) ($this->info_notes ?? []) as $note) {
            if (! is_string($note)) {
                continue;
            }

            // Newlines first, then inline bullet glyphs (•, ●, ▪, ·) and the
            // "- " / "– " dash forms that only count mid-sentence when spaced.
            foreach (preg_split('/\R+|\s*[•●▪·]\s*|\s+[–—-]\s+/u', $note) ?: [] as $part) {
                // NOT trim() with a charlist: trim strips BYTES, so a list holding
                // multi-byte glyphs (•●▪·–—, all starting 0xE2) also strips the
                // lead byte off any part that starts with another 0xE2 character —
                // "₹200 of your ticket…" came out as invalid UTF-8, which made
                // json_encode fail and 500'd the whole /api/events response.
                $part = (string) preg_replace('/^[\s\x{00A0}•●▪·\-–—]+|[\s\x{00A0}•●▪·\-–—]+$/u', '', $part);

                if ($part !== '') {
                    $rows[] = $part;
                }
            }
        }

        return array_values(array_unique($rows));
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

    /** Sessions ("time slots") this event runs across (ordered for display). */
    public function slots(): HasMany
    {
        return $this->hasMany(EventSlot::class)->orderBy('sort')->orderBy('id');
    }

    /** Discount codes scoped to this event. */
    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class);
    }

    /**
     * Whether this event runs across more than one session. A single-slot event
     * behaves like the old single-date event; multi-slot turns on slot selection
     * in the app / web checkout.
     */
    public function hasMultipleSlots(): bool
    {
        return $this->slots()->count() > 1;
    }

    /**
     * Whether the given release phase is open for sale yet. Phase 0 (and any event
     * with no configured phases) is always open; a later phase opens once every
     * capacity-bearing tier in the earlier phases has sold out. Unlimited earlier
     * tiers never block (they'd otherwise keep a later phase closed forever).
     */
    public function phaseReleased(int $phaseIndex): bool
    {
        if ($phaseIndex <= 0 || empty($this->release_phases)) {
            return true;
        }

        foreach ($this->ticketTypes as $tier) {
            if ((int) $tier->release_phase < $phaseIndex) {
                $remaining = $tier->remaining();
                if ($remaining !== null && $remaining > 0) {
                    return false;
                }
            }
        }

        return true;
    }

    /** The display name of a release phase, or null when the index isn't configured. */
    public function phaseName(int $phaseIndex): ?string
    {
        $phase = ((array) ($this->release_phases ?? []))[$phaseIndex] ?? null;
        $name  = is_array($phase) ? trim((string) ($phase['name'] ?? '')) : '';

        return $name !== '' ? $name : null;
    }

    /**
     * The tiers a buyer may actually put in their cart right now: visible, inside
     * their sales window, and in a release phase that has opened. Every buy surface
     * (website sheet, checkout, API) reads this one rule — a hidden tier or an
     * unopened phase must never be purchasable.
     *
     * @return \Illuminate\Support\Collection<int, TicketType>
     */
    public function saleableTicketTypes(): \Illuminate\Support\Collection
    {
        return $this->ticketTypes
            ->filter(fn (TicketType $t): bool => $t->isVisible()
                && $t->isOnSale()
                && $this->phaseReleased((int) $t->release_phase))
            ->values();
    }

    /**
     * Visible tiers still held back by an unopened release phase, so the buy UI can
     * show them as "opens later" instead of pretending they don't exist — that
     * anticipation is the whole point of releasing in phases.
     *
     * @return \Illuminate\Support\Collection<int, TicketType>
     */
    public function lockedTicketTypes(): \Illuminate\Support\Collection
    {
        return $this->ticketTypes
            ->filter(fn (TicketType $t): bool => $t->isVisible()
                && $t->isOnSale()
                && ! $this->phaseReleased((int) $t->release_phase))
            ->values();
    }

    /**
     * Buyer-facing copy for a tier that hasn't been released yet, e.g.
     * "Opens when Early Bird sells out".
     */
    public function phaseUnlockNote(int $phaseIndex): string
    {
        $previous = $this->phaseName($phaseIndex - 1);

        return $previous !== null
            ? 'Opens when ' . $previous . ' sells out'
            : 'Opens in a later release';
    }

    /**
     * Whether the event should read as sold out to buyers. True when the host has
     * manually flipped the "Sold out" override (`is_sold_out`) OR when the event
     * genuinely has no slots left. The manual override lets a host close sales at
     * the door or when inventory is tracked off-platform, even with slots on paper.
     */
    public function soldOut(): bool
    {
        if ($this->is_sold_out) {
            return true;
        }

        return (int) $this->total_slots > 0 && (int) $this->available_slots <= 0;
    }

    /**
     * The moment this event is genuinely over.
     *
     * A multi-session event carries its real span on the `slots` (an event running
     * Aug 1–3 still has `date` = Aug 1), so the last session's end wins when
     * sessions exist. A slot with no `ends_at` is treated as running to the end of
     * its start day. Single-session events fall back to the end of `date`.
     *
     * End-of-day, not the start time, is deliberate: an event should stay listed and
     * bookable for its whole day so walk-ups on the night still work.
     */
    public function endsAt(): ?Carbon
    {
        $slotEnd = $this->slots
            ->map(fn (EventSlot $s): ?Carbon => $s->ends_at ?? $s->starts_at?->copy()->endOfDay())
            ->filter()
            ->max();

        if ($slotEnd !== null) {
            return $slotEnd;
        }

        return $this->date?->copy()->endOfDay();
    }

    /**
     * Has this event already happened? Drives both the public feeds (finished events
     * drop out of "what's on") and the booking guard — without it a buyer could pay
     * for, and be emailed a ticket to, an event that ended days ago.
     *
     * An event with no date at all is never "finished" — better to keep showing
     * something undated than to silently bury it.
     */
    public function hasFinished(): bool
    {
        $end = $this->endsAt();

        return $end !== null && $end->isPast();
    }

    /**
     * Query-side twin of {@see hasFinished()} — the events still worth showing on a
     * "what's on" surface. Day-granular so it matches the PHP rule and stays index
     * friendly; the slot subquery keeps multi-day events alive until their last
     * session. `events.date` is NOT NULL, so there is no null branch to handle here.
     *
     * Deliberately NOT applied to the event detail page: a finished event keeps its
     * own URL so shared links, SEO and the organiser's "past events" list still work.
     * It just can't be booked (see EventBookingController::publishedEvent).
     */
    public function scopeNotFinished(Builder $query): Builder
    {
        $today = now()->toDateString();

        return $query->where(function (Builder $w) use ($today): void {
            $w->whereDate('date', '>=', $today)
                ->orWhereHas('slots', fn (Builder $s): Builder => $s->whereRaw(
                    'date(coalesce(ends_at, starts_at)) >= ?',
                    [$today],
                ));
        });
    }

    /**
     * The "from" price a buyer sees on any card, rail or bar — the cheapest tier
     * they could actually buy right now.
     *
     * READ THIS BEFORE RENDERING A PRICE. The `price` column is NOT the event's
     * price any more: CreateEvent hard-sets it to 0 for everything the wizard
     * creates, because the tiers do the pricing (see CreateEvent::mutateFormData
     * BeforeCreate). Reading `$event->price` raw is why tiered events announced
     * "Free"/"₹0" across the site and the app. Only events with no tiers at all
     * (the legacy flat shape) still carry a meaningful column value, which is the
     * last fallback below.
     *
     * Falls back through: saleable tiers → every tier → the flat column. The middle
     * step matters — a sold-out, sales-closed or fully phase-locked event still has
     * a price, and must not read as free just because nothing is buyable this second.
     *
     * NB callers rendering lists should eager-load `ticketTypes`, or this is an
     * N+1: it reads the relation, not the DB.
     */
    public function fromPrice(): float
    {
        $tiers = $this->saleableTicketTypes();

        if ($tiers->isEmpty()) {
            $tiers = $this->ticketTypes;
        }

        return $tiers->isNotEmpty()
            ? (float) $tiers->min(fn (TicketType $t): float => $t->effectivePrice())
            : (float) $this->price;
    }

    /**
     * How many tiers a buyer could pick from — drives whether a price reads as
     * "onwards" (a range) or "per ticket" (a single rate). Kept beside
     * {@see fromPrice()} so the two never disagree about which tiers count.
     */
    public function priceTierCount(): int
    {
        $tiers = $this->saleableTicketTypes();

        return ($tiers->isEmpty() ? $this->ticketTypes : $tiers)->count();
    }

    /**
     * The admin-set order fees (Convenience fee, Gateway fee, …) computed for a
     * given ticket subtotal, as display lines: [['label' => …, 'amount' => …], …].
     * `flat` is a fixed ₹ amount; `percent` is a share of the subtotal. Rounded to
     * paise, never negative; zero-amount fees are dropped. Empty for a free order.
     *
     * @return list<array{label: string, amount: float}>
     */
    public function feeLinesFor(float $subtotal): array
    {
        if ($subtotal <= 0) {
            return [];
        }

        $lines = [];

        foreach ((array) ($this->fees ?? []) as $fee) {
            $value  = max(0.0, (float) ($fee['value'] ?? 0));
            $amount = match ($fee['type'] ?? null) {
                'flat'    => round($value, 2),
                'percent' => round($subtotal * $value / 100, 2),
                default   => 0.0,
            };

            if ($amount > 0) {
                $lines[] = [
                    'label'  => ($fee['label'] ?? 'Fee') ?: 'Fee',
                    'amount' => $amount,
                ];
            }
        }

        return $lines;
    }

    /** Total of all order fees for the given ticket subtotal. */
    public function feesTotalFor(float $subtotal): float
    {
        return round(array_sum(array_column($this->feeLinesFor($subtotal), 'amount')), 2);
    }

    /**
     * The total order fee charged on top of the subtotal. Kept for the callers
     * that charge/store the aggregate — now backed by the unified fees list.
     */
    public function convenienceFeeFor(float $subtotal): float
    {
        return $this->feesTotalFor($subtotal);
    }

    /**
     * The admin-set tax for an order of the given ticket subtotal, mirroring
     * {@see convenienceFeeFor()}. NOT yet charged at checkout — provided so the
     * later "collect tax" step has a single, tested place to compute it.
     */
    public function taxFor(float $subtotal): float
    {
        if ($subtotal <= 0) {
            return 0.0;
        }

        $value = max(0.0, (float) $this->tax_value);

        return match ($this->tax_type) {
            'flat'    => round($value, 2),
            'percent' => round($subtotal * $value / 100, 2),
            default   => 0.0,
        };
    }
}
