<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BroadcastsContentChanges;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * A time-boxed campaign skin for one home lane of the app (Events or Pulse): header colours
 * plus an optional decoration strip. With nothing live the app uses its normal palette.
 *
 * Saving broadcasts `content.updated` with domain "themes", so a running app refetches
 * the moment /control publishes a change instead of on its next revalidation.
 */
final class SectionTheme extends Model
{
    use BroadcastsContentChanges;

    public const SECTIONS = ['events' => 'Events', 'pulse' => 'Pulse'];

    /** Exactly #RRGGBB — what Filament's ColorPicker emits and what the app parses. */
    public const HEX = '/^#[0-9A-Fa-f]{6}$/';

    /** How far ahead a scheduled campaign is shipped, so the app can switch on time offline. */
    public const LOOKAHEAD_DAYS = 14;

    protected string $contentDomain = 'themes';

    protected $fillable = [
        'section', 'campaign_name', 'accent_primary', 'accent_deep', 'accent_tint', 'on_primary',
        'decoration', 'starts_at', 'ends_at', 'priority', 'is_active',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];

    /** Published rows that are running now or start within the lookahead. */
    public function scopeDeliverable(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where('ends_at', '>', now())
            ->where('starts_at', '<=', now()->addDays(self::LOOKAHEAD_DAYS))
            ->whereColumn('ends_at', '>', 'starts_at');
    }

    /** The wire shape the app's RemoteThemeConfig decodes. Keep the two in step. */
    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'section' => $this->section,
            'campaign_name' => $this->campaign_name,
            'accent' => [
                'primary' => self::hexOrNull($this->accent_primary),
                'deep' => self::hexOrNull($this->accent_deep),
                'tint' => self::hexOrNull($this->accent_tint),
                'on_primary' => self::hexOrNull($this->on_primary),
            ],
            'decoration' => $this->decorationUrl() === null ? null : [
                'url' => $this->decorationUrl(),
                'type' => $this->decorationType(),
            ],
            // Epoch seconds, not ISO strings: the app's minSdk 24 has no java.time, and a
            // number can't be misread in the wrong zone.
            'valid_from' => $this->starts_at->getTimestamp(),
            'valid_until' => $this->ends_at->getTimestamp(),
            'priority' => $this->priority,
        ];
    }

    public function decorationUrl(): ?string
    {
        $path = trim((string) $this->decoration);
        if ($path === '') {
            return null;
        }

        return str_starts_with($path, 'http') ? $path : Storage::disk('public')->url($path);
    }

    /** 'lottie' for a .json animation, otherwise 'image'. Decided by extension, which uploads enforce. */
    public function decorationType(): string
    {
        $path = strtolower((string) parse_url((string) $this->decoration, PHP_URL_PATH));

        return str_ends_with($path, '.json') ? 'lottie' : 'image';
    }

    public static function hexOrNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return preg_match(self::HEX, $value) === 1 ? strtoupper($value) : null;
    }
}
