<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\MediaUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Ad extends Model
{
    /**
     * Every slot a client actually renders, and what it is called in the console. A placement
     * outside this list is a slot nothing reads — the ad would be live and never seen — so the
     * console offers these and the API accepts only these.
     */
    public const PLACEMENTS = [
        'match_live' => 'Match detail — live board (all sports)',
        'events' => 'Events feed (app + web)',
        'login_poster' => 'Login poster carousel',
    ];

    protected $fillable = [
        'sponsor', 'title', 'subtitle', 'image', 'logo', 'cta_text', 'cta_url',
        'placement', 'is_active', 'sort_order', 'starts_at', 'ends_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'impressions_count' => 'integer',
        'clicks_count' => 'integer',
    ];

    public function events(): HasMany
    {
        return $this->hasMany(AdEvent::class);
    }

    /** Active and inside its date window. */
    public function scopeServing(Builder $query, ?string $placement = null): Builder
    {
        return $query->where('is_active', true)
            ->when($placement !== null, fn (Builder $q) => $q->where('placement', $placement))
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    public function isServing(): bool
    {
        return $this->is_active
            && ($this->starts_at === null || $this->starts_at->lte(now()))
            && ($this->ends_at === null || $this->ends_at->gte(now()));
    }

    /** Absolute creative URL. The web view read `image_url`, which never existed. */
    public function getImageUrlAttribute(): ?string
    {
        $url = MediaUrl::resolve($this->image);

        return $url !== null && $url !== '' ? $url : null;
    }

    /** Where the ad goes — http(s) only, so a stored javascript: URL can never render. */
    public function getLinkUrlAttribute(): ?string
    {
        return self::safeUrl($this->cta_url);
    }

    /** Click-through rate as a percentage, or null before any impression. */
    public function ctr(): ?float
    {
        return $this->impressions_count > 0
            ? round($this->clicks_count * 100 / $this->impressions_count, 2)
            : null;
    }

    public static function safeUrl(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) ? $url : null;
    }
}
