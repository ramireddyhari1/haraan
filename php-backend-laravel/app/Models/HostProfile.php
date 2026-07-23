<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\MediaUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * A partner's public-facing organiser page (see {@see \App\Http\Controllers\Web\PublicWebController::hostProfile}).
 * Ownership is 1:1 with the partner {@see User}; a profile only becomes visible
 * once {@see isLive()} — the owner has opted in and filled the required fields.
 */
class HostProfile extends Model
{
    protected $fillable = [
        'user_id', 'slug', 'display_name', 'tagline', 'about',
        'logo_path', 'cover_path', 'website', 'socials', 'city',
        'is_public', 'verified_at',
    ];

    protected $casts = [
        'socials' => 'array',
        'is_public' => 'boolean',
        'verified_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function logoUrl(): ?string
    {
        return MediaUrl::resolve($this->logo_path);
    }

    public function coverUrl(): ?string
    {
        return MediaUrl::resolve($this->cover_path);
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    /** Publicly viewable? Opted in AND the minimum content is present. */
    public function isLive(): bool
    {
        return $this->is_public && filled($this->display_name) && filled($this->about);
    }

    /** A single social handle/url by key (instagram|x|youtube|facebook). */
    public function social(string $key): ?string
    {
        $value = $this->socials[$key] ?? null;

        return filled($value) ? $value : null;
    }

    /** Venue owners get a venue-oriented page; event organisers an event one. */
    public function isVenueLane(): bool
    {
        return ($this->user?->partner_type ?? 'venue') !== 'event';
    }

    /** A venue owner's active venues — the venue-lane page's grid. */
    public function venuesQuery(): Builder
    {
        return Venue::query()
            ->where('partner_id', $this->user_id)
            ->where('is_active', true)
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    /** This host's published, upcoming events — the page's booking rail. */
    public function upcomingEventsQuery(): Builder
    {
        return Event::query()
            ->where('partner_id', $this->user_id)
            ->whereRaw('lower(status) = ?', ['published'])
            ->where('date', '>=', now()->startOfDay())
            ->orderBy('date');
    }

    // ---- Followers (Phase 2) -------------------------------------------------

    public function followersCount(): int
    {
        return DB::table('host_followers')->where('host_id', $this->user_id)->count();
    }

    public function isFollowedBy(?User $user): bool
    {
        return $user !== null && DB::table('host_followers')
            ->where('host_id', $this->user_id)
            ->where('user_id', $user->id)
            ->exists();
    }

    /** Toggle a follow for $user; returns the new following state. No-op for self. */
    public function toggleFollow(User $user): bool
    {
        if ($user->id === $this->user_id) {
            return false;
        }

        if ($this->isFollowedBy($user)) {
            DB::table('host_followers')
                ->where('host_id', $this->user_id)->where('user_id', $user->id)->delete();

            return false;
        }

        DB::table('host_followers')->insert([
            'host_id' => $this->user_id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return true;
    }

    /** This host's past (published, already-happened) events — the archive. */
    public function pastEventsQuery(): Builder
    {
        return Event::query()
            ->where('partner_id', $this->user_id)
            ->whereRaw('lower(status) = ?', ['published'])
            ->where('date', '<', now()->startOfDay())
            ->orderByDesc('date');
    }

    // ---- Analytics (Phase 3) -------------------------------------------------

    /** Bump today's view counter (callers dedupe per session). */
    public function recordView(): void
    {
        DB::table('host_profile_views')->upsert(
            [['host_profile_id' => $this->id, 'day' => today()->toDateString(), 'views' => 1, 'created_at' => now(), 'updated_at' => now()]],
            ['host_profile_id', 'day'],
            ['views' => DB::raw('views + 1'), 'updated_at' => now()],
        );
    }

    /**
     * View totals + a 14-day daily series for the sparkline.
     *
     * @return array{total:int, last7:int, last30:int, daily:array<int,int>}
     */
    public function viewStats(): array
    {
        $rows = DB::table('host_profile_views')->where('host_profile_id', $this->id)->get(['day', 'views']);
        $byDay = [];
        foreach ($rows as $r) {
            $byDay[(string) $r->day] = (int) $r->views;
        }

        $total = array_sum($byDay);
        $sinceFn = fn (int $days): int => collect($byDay)
            ->filter(fn ($v, $day): bool => $day >= now()->subDays($days - 1)->toDateString())
            ->sum();

        $daily = [];
        for ($i = 13; $i >= 0; $i--) {
            $daily[] = $byDay[now()->subDays($i)->toDateString()] ?? 0;
        }

        return ['total' => $total, 'last7' => $sinceFn(7), 'last30' => $sinceFn(30), 'daily' => $daily];
    }

    /**
     * Follower totals + recent growth.
     *
     * @return array{total:int, new7:int, new30:int}
     */
    public function followerGrowth(): array
    {
        $base = DB::table('host_followers')->where('host_id', $this->user_id);

        return [
            'total' => (clone $base)->count(),
            'new7' => (clone $base)->where('created_at', '>=', now()->subDays(6)->startOfDay())->count(),
            'new30' => (clone $base)->where('created_at', '>=', now()->subDays(29)->startOfDay())->count(),
        ];
    }

    /**
     * Aggregate star rating across this host's rated events.
     *
     * @return array{avg: float|null, count: int}
     */
    public function ratingSummary(): array
    {
        $query = $this->isVenueLane()
            ? Venue::query()->where('partner_id', $this->user_id)
            : Event::query()->where('partner_id', $this->user_id);

        $row = $query
            ->whereNotNull('rating')
            ->selectRaw('AVG(rating) AS a, SUM(ratings_count) AS c')
            ->first();

        return [
            'avg' => $row && $row->a !== null ? round((float) $row->a, 1) : null,
            'count' => (int) ($row->c ?? 0),
        ];
    }
}
