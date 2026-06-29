<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BroadcastsContentChanges;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Key/value runtime settings (branding, theme, misc remote config). Reads are
 * served from a single cached snapshot so the hot /api/config path doesn't hit
 * the DB per request; the cache is busted on any write.
 */
final class AppSetting extends Model
{
    use BroadcastsContentChanges;

    /** Branding/theme rides in /api/config. */
    protected string $contentDomain = 'config';

    protected $fillable = ['key', 'value', 'group'];

    private const CACHE_KEY = 'app_settings.all';

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    /** @return Collection<int, AppSetting> */
    public static function allCached(): Collection
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => self::all(['key', 'value', 'group']));
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        return self::allCached()->firstWhere('key', $key)?->value ?? $default;
    }

    /** @return array<string, string|null> key => value for a group. */
    public static function group(string $group): array
    {
        return self::allCached()->where('group', $group)->pluck('value', 'key')->all();
    }

    public static function set(string $key, ?string $value, string $group = 'general'): void
    {
        self::updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
    }
}
