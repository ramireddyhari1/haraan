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

    /**
     * Aggregate star rating across this host's rated events.
     *
     * @return array{avg: float|null, count: int}
     */
    public function ratingSummary(): array
    {
        $row = Event::query()
            ->where('partner_id', $this->user_id)
            ->whereNotNull('rating')
            ->selectRaw('AVG(rating) AS a, SUM(ratings_count) AS c')
            ->first();

        return [
            'avg' => $row && $row->a !== null ? round((float) $row->a, 1) : null,
            'count' => (int) ($row->c ?? 0),
        ];
    }
}
