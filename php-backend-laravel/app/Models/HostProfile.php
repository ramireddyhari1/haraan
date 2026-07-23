<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\MediaUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
