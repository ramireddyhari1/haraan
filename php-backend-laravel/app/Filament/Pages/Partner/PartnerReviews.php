<?php

declare(strict_types=1);

namespace App\Filament\Pages\Partner;

use App\Models\Event;
use App\Models\Venue;
use App\Models\VenueReview;
use App\Support\MediaUrl;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;

/**
 * Reviews — what customers said, in one place.
 *
 * Two lanes, because the two partner types carry different feedback shapes:
 *
 *   • Venue owners get real review rows (venue_reviews): rating + text + author,
 *     so the page can show a rating breakdown and a readable feed.
 *   • Event organisers only have the aggregate rating that lives on the event row
 *     (events.rating / ratings_count) — there is no per-attendee review table yet —
 *     so their lane lists events by score instead of a comment feed.
 *
 * Everything is scoped to the partner's own records (effectivePartnerId), and desk
 * staff are further narrowed to the venues/events they're assigned to. Read-only:
 * partners can't edit or delete what a customer said.
 */
class PartnerReviews extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-star';

    protected static ?string $title = 'Reviews';

    protected static ?string $navigationLabel = 'Reviews';

    protected static ?int $navigationSort = 8;

    protected string $view = 'filament.pages.partner.reviews';

    /** Filter: venue id (venue lane) or null for "all". */
    public ?int $subjectId = null;

    /** Filter: exact star rating 1–5, or null for "all ratings". */
    public ?int $stars = null;

    /** Per-request memo — the view asks for venues/summary/feed in one pass. */
    private ?\Illuminate\Support\Collection $venueMemo = null;

    /** @var array<int, array<string, mixed>>|null */
    private ?array $eventMemo = null;

    public static function canAccess(): bool
    {
        // Partner console only. Desk staff need the 'reports' capability — reviews
        // are performance data, same bucket as earnings and analytics.
        $user = auth()->user();

        return Filament::getCurrentPanel()?->getId() === 'partner'
            && $user !== null
            && $user->hasPartnerPermission('reports');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function getHeader(): ?View
    {
        return view('filament.pages.partner.reviews-header');
    }

    // ---- filters -------------------------------------------------------------

    public function filterSubject(?int $id): void
    {
        $this->subjectId = $id;
    }

    public function filterStars(?int $stars): void
    {
        // Clicking the active star chip clears it — the chips act as a toggle.
        $this->stars = ($this->stars === $stars) ? null : $stars;
    }

    // ---- lane ----------------------------------------------------------------

    /** True for event organisers (aggregate-only lane), false for venue owners. */
    public function isEventLane(): bool
    {
        return auth()->user()?->partner_type === 'event';
    }

    private function partnerId(): int
    {
        return (int) auth()->user()->effectivePartnerId();
    }

    // ---- venue lane ----------------------------------------------------------

    /**
     * The partner's venues, narrowed to a desk person's assignments.
     *
     * @return \Illuminate\Support\Collection<int, Venue>
     */
    public function venues(): \Illuminate\Support\Collection
    {
        if ($this->venueMemo !== null) {
            return $this->venueMemo;
        }

        $ids = auth()->user()?->scopedVenueIds();

        return $this->venueMemo = Venue::query()
            ->where('partner_id', $this->partnerId())
            ->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /** Base review query: active reviews on this partner's venues, newest first. */
    private function reviewQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $venueIds = $this->venues()->pluck('id')->all();

        return VenueReview::query()
            ->with('venue:id,name')
            ->whereIn('venue_id', $venueIds)
            ->where('is_active', true)
            ->latest('id');
    }

    /**
     * The filtered feed, shaped for the view.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getReviews(): array
    {
        if ($this->isEventLane()) {
            return [];
        }

        return $this->reviewQuery()
            ->when($this->subjectId, fn ($q) => $q->where('venue_id', $this->subjectId))
            ->when($this->stars, fn ($q) => $q->where('rating', $this->stars))
            ->limit(100)
            ->get()
            ->map(fn (VenueReview $r): array => [
                'name' => $r->name ?: 'Guest',
                'initial' => mb_strtoupper(mb_substr($r->name ?: 'G', 0, 1)),
                'hue' => crc32((string) ($r->name ?: 'G')) % 360,
                'avatar' => MediaUrl::resolve($r->avatar),
                'rating' => (int) $r->rating,
                'text' => (string) $r->text,
                'venue' => $r->venue?->name,
                // Prefer the admin-entered "2 weeks ago" label; fall back to the row's
                // own age so a review without one still reads as dated.
                'ago' => $r->ago ?: ($r->created_at ? Carbon::parse($r->created_at)->diffForHumans() : null),
            ])
            ->all();
    }

    /**
     * Rating summary across the partner's venues — respects the venue filter but
     * NOT the star filter (the breakdown is what you filter *with*).
     *
     * @return array{average: string, total: int, distribution: array<int, array{stars:int,count:int,percent:float}>}
     */
    public function getSummary(): array
    {
        if ($this->isEventLane()) {
            return $this->eventSummary();
        }

        $rows = $this->reviewQuery()
            ->when($this->subjectId, fn ($q) => $q->where('venue_id', $this->subjectId))
            ->get(['rating']);

        $total = $rows->count();
        $average = $total > 0 ? round($rows->avg('rating'), 1) : 0.0;

        $distribution = [];
        foreach ([5, 4, 3, 2, 1] as $star) {
            $count = $rows->where('rating', $star)->count();
            $distribution[] = [
                'stars' => $star,
                'count' => $count,
                'percent' => $total > 0 ? round($count / $total * 100, 1) : 0.0,
            ];
        }

        return [
            'average' => number_format($average, 1),
            'total' => $total,
            'distribution' => $distribution,
        ];
    }

    // ---- event lane ----------------------------------------------------------

    /**
     * Rated events, newest first — the organiser's stand-in for a review feed.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRatedEvents(): array
    {
        if (! $this->isEventLane()) {
            return [];
        }

        if ($this->eventMemo !== null) {
            return $this->eventMemo;
        }

        $ids = auth()->user()?->scopedEventIds();

        return $this->eventMemo = Event::query()
            ->where('partner_id', $this->partnerId())
            ->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))
            ->whereNotNull('rating')
            ->orderByDesc('date')
            ->limit(100)
            ->get(['id', 'title', 'date', 'city', 'rating', 'ratings_count'])
            ->map(fn (Event $e): array => [
                'id' => $e->id,
                'title' => (string) $e->title,
                'city' => $e->city,
                'date' => $e->date ? Carbon::parse($e->date)->format('d M Y') : null,
                'rating' => (float) $e->rating,
                'ratingLabel' => number_format((float) $e->rating, 1),
                'count' => (int) $e->ratings_count,
            ])
            ->all();
    }

    /**
     * Weighted average across the organiser's rated events.
     *
     * @return array{average: string, total: int, distribution: array<int, mixed>}
     */
    private function eventSummary(): array
    {
        $events = collect($this->getRatedEvents());
        $total = (int) $events->sum('count');

        // Weight by how many people rated each event, so a 5.0 from two attendees
        // can't outrank a 4.6 from four hundred.
        $average = $total > 0
            ? $events->sum(fn (array $e): float => $e['rating'] * $e['count']) / $total
            : (float) ($events->avg('rating') ?? 0);

        return [
            'average' => number_format($average, 1),
            'total' => $total,
            'distribution' => [],
        ];
    }
}
