<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for the Event model.
 */
final class EventResource extends JsonResource
{
    /**
     * Whether this is the single-event response rather than a row in the list.
     *
     * Gates the fields that cost real work to build: the venue's Google photos
     * (up to a Places call) and the organiser card (a couple of counts). On the
     * paginated list that would be twenty lookups apiece to render a screen that
     * shows neither.
     */
    private bool $asDetail = false;

    public function asDetail(bool $on = true): self
    {
        $this->asDetail = $on;

        return $this;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'title'          => $this->title,
            'description'    => $this->description,
            'category'       => $this->category,
            'bookingFormat'  => $this->booking_format,
            'visibility'     => $this->visibility,
            'location'       => $this->location,
            'mapLink'        => $this->map_link,
            // Host-set venue coordinates (nullable) — drives the app's venue map preview.
            'latitude'       => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude'      => $this->longitude !== null ? (float) $this->longitude : null,
            'city'           => $this->city,
            'venue'          => $this->venue,
            'date'           => $this->date,
            'time'           => $this->time,
            // Optional end time + the ready-made "5:00 PM – 8:00 PM" label, so
            // clients don't each re-implement the joining rules.
            'endTime'        => $this->end_time,
            'timeRange'      => $this->resource->timeRangeLabel(),
            // The "from" price the app prints on cards and the detail header. NOT the
            // `price` column — CreateEvent pins that to 0 for every wizard-created
            // event because the tiers do the pricing, so reading it raw made the app
            // label real events "Free". fromPrice() falls back to the column for
            // legacy tier-less events, so older builds see no change there.
            'price'          => $this->fromPrice(),
            // The raw column, kept for any client that still wants the flat rate.
            'basePrice'      => (float) $this->price,
            // Host-set order fees (Convenience fee, Gateway fee, …) — the app previews
            // them, the server charges them. Each is { label, type, value }.
            'fees' => collect((array) ($this->fees ?? []))->map(fn (array $f): array => [
                'label' => ($f['label'] ?? 'Fee') ?: 'Fee',
                'type'  => $f['type'] ?? 'flat',
                'value' => (float) ($f['value'] ?? 0),
            ])->values(),
            // Legacy single-fee shape for older app builds that predate `fees`; mirrors
            // the first fee row so they still show one line. Newer builds read `fees`.
            'convenienceFee' => [
                'type'  => $this->fees[0]['type'] ?? 'none',
                'value' => (float) ($this->fees[0]['value'] ?? 0),
                'label' => $this->fees[0]['label'] ?? 'Convenience fee',
            ],
            'totalSlots'     => $this->total_slots,
            'availableSlots' => $this->available_slots,
            // Authoritative sold-out flag: the manual host override OR genuinely no
            // slots left. Clients should gate the buy UI on this, not on the raw
            // slot count (which stays truthful for "filling fast" heuristics).
            'soldOut'        => $this->resource->soldOut(),
            // Whether tickets are configured per session ("Customize per slot").
            'ticketsPerSlot' => (bool) $this->tickets_per_slot,
            // Ordered release-phase names; empty when the event sells everything at once.
            'releasePhases'  => array_values(array_map(
                static fn ($p): string => (string) ($p['name'] ?? ''),
                (array) ($this->release_phases ?? []),
            )),
            // Sessions ("time slots") this event runs across. Always present; a
            // single-slot event just has one. Buyers pick one when there's >1.
            'slots'          => $this->whenLoaded('slots', fn () => $this->slots->map(fn ($s) => [
                'id'        => $s->id,
                'label'     => $s->displayLabel(),
                'startsAt'  => $s->starts_at,
                'endsAt'    => $s->ends_at,
                'capacity'  => $s->capacity,
                'remaining' => $s->remaining(),
                'soldOut'   => $s->soldOut(),
            ])->values()),
            'images'         => \App\Support\MediaUrl::resolveMany($this->images),
            'gallery'        => $this->galleryUrls(),
            // Who is actually running this event: live host profile, else the
            // partner account. The app used to hardcode "Haraan Events /
            // Verified ticketing partner" for every event — a caption, not a fact.
            'organiser'      => $this->when(
                $this->asDetail,
                fn (): array => $this->resource->organiserCard(),
            ),
            // The venue's own photos off its Google listing — the app's "Venue
            // ambiance" rail. Absent (not empty) on list responses; empty when
            // there's nothing worth showing or an admin hid this venue. Each row
            // carries the contributor credit Google requires to be displayed.
            'venuePhotos'    => $this->when(
                $this->asDetail,
                fn (): array => array_map(static fn (array $p): array => [
                    'url'    => $p['url'],
                    'credit' => $p['credit'],
                ], $this->resource->venuePhotos()),
            ),
            'status'         => $this->status,
            // Curated app rails this event appears in (e.g. ["for_you","trending"]).
            'placements'     => array_values(array_filter(
                (array) ($this->placements ?? []),
                static fn ($p): bool => is_string($p) && trim($p) !== '',
            )),
            // Aggregate rating; null when unrated so the app shows nothing (no fake star).
            'rating'         => $this->rating !== null ? (float) $this->rating : null,
            'ratingsCount'   => (int) ($this->ratings_count ?? 0),
            'partnerId'      => $this->partner_id,
            // The organiser's public page, when they have a live one (Phase 2).
            'host'           => $this->hostPayload($request),
            'createdAt'      => $this->created_at,
            'infoNotes'      => $this->resource->infoNoteRows(),
            'goodToKnow'     => $this->resource->goodToKnowRows(),
            'schedule'       => $this->resource->scheduleRows(),
            'lineup'         => $this->resource->lineupRows(),
            // Per-event FAQs — clean {question, answer} rows for the app's detail page.
            'faqs'           => $this->resource->faqRows(),
            // Buyer-facing tiers only — a host can hide a tier (visible = false)
            // without deleting it, and hidden tiers must never reach checkout.
            'ticketTypes'    => $this->whenLoaded('ticketTypes', fn () => $this->ticketTypes
                ->filter(fn ($t) => $t->isVisible())
                ->map(fn ($t) => [
                    'id'          => $t->id,
                    'name'        => $t->name,
                    'description' => $t->description,
                    'kind'        => $t->kind,
                    // The session this tier belongs to, or null when it applies to all.
                    'slotId'      => $t->event_slot_id,
                    // `price` is the live price a buyer pays now (current phase, else flat).
                    'price'       => $t->effectivePrice(),
                    'basePrice'   => $t->price,
                    'admits'      => $t->admits,
                    'minPrice'    => $t->min_price,
                    'capacity'    => $t->capacity,
                    'sold'        => $t->sold,
                    'remaining'   => $t->remaining(),
                    // On sale = inside its sales window AND its release phase has opened.
                    'onSale'      => $t->isOnSale() && $this->resource->phaseReleased((int) $t->release_phase),
                    'releasePhase' => (int) $t->release_phase,
                    // Bulk-booking bounds the app/web quantity stepper should honour.
                    'bulkBooking' => (bool) $t->bulk_booking,
                    'minPerOrder' => $t->orderBounds()['min'],
                    'maxPerOrder' => $t->orderBounds()['max'],
                    // Empty for flat-price tiers; drives the app's "Pricing Schedule" widget.
                    'phases'      => $t->phaseSchedule(),
                ])->values()),
        ];
    }

    /**
     * The event organiser's public profile, or null when they don't have a live one.
     *
     * @return array<string, mixed>|null
     */
    private function hostPayload(Request $request): ?array
    {
        $profile = $this->resource->partner?->hostProfile;

        if ($profile === null || ! $profile->isLive()) {
            return null;
        }

        return [
            'name'        => $profile->display_name,
            'slug'        => $profile->slug,
            'logo'        => $profile->logoUrl(),
            'verified'    => $profile->isVerified(),
            'url'         => url('/host/' . $profile->slug),
            'followers'   => $profile->followersCount(),
            'isFollowing' => $profile->isFollowedBy($request->user()),
        ];
    }
}
