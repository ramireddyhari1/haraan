<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BroadcastsContentChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * One translated string for a (key, locale). Bundles are cached per locale and
 * fall back to English so the app always receives a complete set. Cache is
 * busted on any write. The default/source locale is English.
 */
final class Translation extends Model
{
    use BroadcastsContentChanges;

    /** Clients refetch /api/i18n/{locale} when translations change. */
    protected string $contentDomain = 'i18n';

    /** Supported app locales (English is the source/fallback). */
    public const LOCALES = ['en', 'te', 'ta', 'kn', 'ml', 'hi'];

    public const FALLBACK = 'en';

    protected $fillable = ['group', 'key', 'locale', 'value'];

    protected static function booted(): void
    {
        static::saved(fn () => self::flushCache());
        static::deleted(fn () => self::flushCache());
    }

    public static function flushCache(): void
    {
        foreach (self::LOCALES as $locale) {
            Cache::forget("i18n.bundle.$locale");
        }
    }

    /**
     * Full key => value map for a locale, with English filling any gaps so the
     * client never sees a missing string.
     *
     * @return array<string, string|null>
     */
    public static function bundle(string $locale): array
    {
        if (! in_array($locale, self::LOCALES, true)) {
            $locale = self::FALLBACK;
        }

        return Cache::rememberForever("i18n.bundle.$locale", function () use ($locale) {
            $base = self::where('locale', self::FALLBACK)->pluck('value', 'key')->all();

            if ($locale === self::FALLBACK) {
                return $base;
            }

            $localized = self::where('locale', $locale)
                ->whereNotNull('value')
                ->pluck('value', 'key')
                ->all();

            return array_merge($base, $localized);
        });
    }
}
