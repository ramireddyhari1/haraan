<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Normalizes stored image values into URLs the app/web can actually load.
 *
 * Image columns hold a mix: files uploaded through Filament (stored on the `public`
 * disk as a relative path like `venues/abc.png`) and pasted external URLs
 * (`https://cdn…`). The app needs an absolute, host-qualified URL either way — a bare
 * `venues/abc.png` renders as a broken image. This is the API-side twin of the web
 * controller's `venueImageUrl()`.
 */
final class MediaUrl
{
    /** Resolve a single stored value to an absolute URL, or null if empty. */
    public static function resolve(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Already a full URL (external CDN) — leave it untouched.
        if (preg_match('#^https?://#i', $value)) {
            return $value;
        }

        // Already an app-absolute path (e.g. "/storage/…") — just qualify the host.
        if (str_starts_with($value, '/')) {
            return url($value);
        }

        // Relative public-disk path from a Filament upload → absolute /storage/… URL.
        return Storage::disk('public')->url($value);
    }

    /**
     * Resolve a list of stored values, dropping any that are empty.
     *
     * @param  iterable<int,string|null>|null  $values
     * @return array<int,string>
     */
    public static function resolveMany(?iterable $values): array
    {
        if ($values === null) {
            return [];
        }

        $out = [];
        foreach ($values as $value) {
            $url = self::resolve(is_string($value) ? $value : null);
            if ($url !== null) {
                $out[] = $url;
            }
        }

        return $out;
    }
}
