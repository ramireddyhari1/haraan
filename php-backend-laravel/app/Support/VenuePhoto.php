<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Sized WebP copies of venue photos, and the URLs that pick between them.
 *
 * Venue photos were served exactly as uploaded: a 4032px, 3.5 MB PNG went to a
 * 92px thumbnail and to a phone card alike. So every local upload also gets
 * WebP copies cut at fixed widths, written beside it under `variants/`:
 *
 *   venues/01ABC.png  →  variants/venues/01ABC.png-480w.webp
 *                        variants/venues/01ABC.png-960w.webp  …
 *
 * The width is in the filename, so the set on disk is the whole manifest: no
 * column, no cache to fall out of step. A photo is never upscaled; one narrower
 * than the largest width gets a full-size copy at its own width instead, so the
 * original PNG/JPEG never has to be sent to a browser at all.
 *
 * Pasted external URLs have no copies and pass through untouched, as do photos
 * whose copies haven't been made yet — every lookup falls back to the original.
 */
final class VenuePhoto
{
    /** Widths a copy is cut at. The last one also caps the full-size copy. */
    public const WIDTHS = [480, 960, 1600, 2400];

    /**
     * What the API and a plain `<img src>` get. A 1080px phone covering a 280dp
     * hero needs about this much; anything wider is weight nobody sees.
     */
    public const DISPLAY_WIDTH = 1600;

    private const QUALITY = 86;

    private const DIR = 'variants';

    /** Big photos decode to ~100 MB of pixels; the FPM default of 128M isn't enough. */
    private const MEMORY = '512M';

    /** @var array<string, array<int, string>> path → [width → variant path] */
    private static array $memo = [];

    /**
     * The public-disk path behind a stored value or one of our own URLs, or null
     * for anything we don't host (an external CDN link).
     */
    public static function pathOf(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $value)) {
            $host = parse_url($value, PHP_URL_HOST);
            $ours = array_filter([
                parse_url((string) config('app.url'), PHP_URL_HOST),
                app()->runningInConsole() ? null : request()->getHost(),
            ]);
            if (! in_array($host, $ours, true)) {
                return null;
            }
            $value = (string) parse_url($value, PHP_URL_PATH);
        }

        if (str_starts_with($value, '/')) {
            if (! str_starts_with($value, '/storage/')) {
                return null;
            }
            $value = substr($value, strlen('/storage/'));
        }

        $value = ltrim(rawurldecode($value), '/');

        // One of our own copies stands for its original, so a view holding a copy's
        // URL can still ask for the whole set.
        if (preg_match('#^'.self::DIR.'/(.+)-\d+w\.webp$#', $value, $m)) {
            $value = $m[1];
        }

        return $value === '' || str_contains($value, '..') ? null : $value;
    }

    /**
     * {@see url()} for a whole photo list, dropping empties.
     *
     * @param  iterable<int, string|null>|null  $values
     * @return list<string>
     */
    public static function urls(?iterable $values, int $max = self::DISPLAY_WIDTH): array
    {
        $out = [];
        foreach ($values ?? [] as $value) {
            if (is_string($value) && ($url = self::url($value, $max)) !== null) {
                $out[] = $url;
            }
        }

        return $out;
    }

    /**
     * {@see sets()} for a whole photo list, index-aligned with {@see urls()}.
     *
     * @param  iterable<int, string|null>|null  $values
     * @return list<list<array{w:int, url:string}>>
     */
    public static function setsMany(?iterable $values): array
    {
        $out = [];
        foreach ($values ?? [] as $value) {
            if (is_string($value) && MediaUrl::resolve($value) !== null) {
                $out[] = self::sets($value);
            }
        }

        return $out;
    }

    /**
     * The copies that exist for a photo, smallest first.
     *
     * @return array<int, string> width → variant path
     */
    public static function variantsOf(?string $value): array
    {
        $path = self::pathOf($value);
        if ($path === null) {
            return [];
        }

        if (array_key_exists($path, self::$memo)) {
            return self::$memo[$path];
        }

        $found = [];
        $prefix = self::DIR.'/'.$path.'-';
        foreach (glob(Storage::disk('public')->path($prefix).'*w.webp') ?: [] as $file) {
            if (preg_match('/-(\d+)w\.webp$/', $file, $m)) {
                $found[(int) $m[1]] = $prefix.$m[1].'w.webp';
            }
        }
        ksort($found);

        return self::$memo[$path] = $found;
    }

    /**
     * The URL to load when only one fits: the largest copy no wider than $max,
     * else the original (external links and photos without copies).
     */
    public static function url(?string $value, int $max = self::DISPLAY_WIDTH): ?string
    {
        $variants = self::variantsOf($value);
        $pick = null;
        foreach ($variants as $w => $path) {
            if ($w <= $max || $pick === null) {
                $pick = $path;
            }
        }

        return $pick !== null ? self::publicUrl($pick) : MediaUrl::resolve($value);
    }

    /**
     * On the host the request came in on, as the web pages always built photo URLs
     * (`asset()`), rather than APP_URL — so www., the nip.io fallback and a local
     * dev port all load their own copies.
     */
    private static function publicUrl(string $path): string
    {
        return asset('storage/'.$path);
    }

    /** A `srcset` value for an `<img>`, or null when the photo has no copies. */
    public static function srcset(?string $value): ?string
    {
        $parts = array_map(
            static fn (array $s): string => $s['url'].' '.$s['w'].'w',
            self::sets($value),
        );

        return $parts === [] ? null : implode(', ', $parts);
    }

    /**
     * ` srcset="…" sizes="…"` for an `<img>` tag (already escaped), or '' when the
     * photo has no copies and the tag's plain src has to do. Accepts a stored value
     * or any URL views were handed, including a copy's own URL.
     */
    public static function imgAttrs(?string $value, string $sizes): string
    {
        $set = self::srcset($value);

        return $set === null ? '' : ' srcset="'.e($set).'" sizes="'.e($sizes).'"';
    }

    /**
     * Every copy as {w, url}, smallest first — for the app to pick by screen width.
     *
     * @return list<array{w:int, url:string}>
     */
    public static function sets(?string $value): array
    {
        $out = [];
        foreach (self::variantsOf($value) as $w => $path) {
            $out[] = ['w' => $w, 'url' => self::publicUrl($path)];
        }

        return $out;
    }

    /**
     * A photo's displayed size, with the camera's EXIF rotation applied — a phone
     * portrait is often stored landscape plus a "rotate 90°" tag.
     *
     * @return array{0:int, 1:int}|null
     */
    public static function dimensions(string $file): ?array
    {
        $info = @getimagesize($file);
        if (! is_array($info) || ($info[0] ?? 0) < 1) {
            return null;
        }

        [$w, $h] = $info;

        return in_array(self::orientation($file, (int) $info[2]), [5, 6, 7, 8], true) ? [$h, $w] : [$w, $h];
    }

    /**
     * Why a newly uploaded file shouldn't become a venue photo, in words the
     * uploader can act on, or null when it's fine (or the check is off in
     * /control → Platform rules → Venue photos).
     */
    public static function uploadProblem(string $file): ?string
    {
        if (! PlatformRules::bool('venue_photos.check_uploads')) {
            return null;
        }

        $dims = self::dimensions($file);
        if ($dims === null) {
            return null; // Not an image at all: the field's own image rule says so.
        }

        [$w, $h] = $dims;
        $minW = PlatformRules::int('venue_photos.min_width');
        $minH = PlatformRules::int('venue_photos.min_height');

        if (PlatformRules::bool('venue_photos.require_landscape') && $w <= $h) {
            return "This photo is {$w}×{$h}, taller than it is wide. Venue photos are shown wide, so a portrait shot or poster gets cropped to a thin slice. Use a landscape photo, or crop this one wide with the editor.";
        }

        if ($w < $minW || $h < $minH) {
            return "This photo is only {$w}×{$h} px, so it will look blurry. Use one at least {$minW}×{$minH} px. Photos sent on WhatsApp get shrunk: ask for the original, sent as a Document.";
        }

        return null;
    }

    /**
     * Make the copies for one photo. Returns how many were written; 0 when they
     * were already current, the file isn't ours, or this PHP can't write WebP.
     */
    public static function generate(string $value, bool $force = false): int
    {
        $path = self::pathOf($value);
        $disk = Storage::disk('public');
        if ($path === null || ! $disk->exists($path)) {
            return 0;
        }

        if (! function_exists('imagewebp')) {
            Log::warning('VenuePhoto: GD with WebP is not available; photo copies were not made.');

            return 0;
        }

        $src = $disk->path($path);
        $dims = self::dimensions($src);
        if ($dims === null) {
            return 0;
        }

        $targets = self::targetsFor($dims[0]);
        $existing = self::variantsOf($path);
        $srcTime = (int) @filemtime($src);
        $current = array_keys($existing) === $targets
            && array_reduce($existing, static fn (bool $ok, string $p): bool => $ok && (int) @filemtime($disk->path($p)) >= $srcTime, true);
        if ($current && ! $force) {
            return 0;
        }

        $memory = ini_get('memory_limit');
        @ini_set('memory_limit', self::MEMORY);
        $img = null;
        $written = 0;

        try {
            $img = self::load($src);
            if ($img === null) {
                return 0;
            }

            $w = imagesx($img);
            $h = imagesy($img);
            $disk->makeDirectory(dirname(self::DIR.'/'.$path));

            foreach ($targets as $tw) {
                $th = max(1, (int) round($h * $tw / $w));
                $dst = imagecreatetruecolor($tw, $th);
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
                imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
                imagecopyresampled($dst, $img, 0, 0, 0, 0, $tw, $th, $w, $h);

                if (imagewebp($dst, $disk->path(self::DIR.'/'.$path.'-'.$tw.'w.webp'), self::QUALITY)) {
                    $written++;
                }
                imagedestroy($dst);
            }

            // A copy at a width this photo no longer gets (it was replaced by a
            // smaller file under the same name) would otherwise linger in srcset.
            foreach ($existing as $ew => $old) {
                if (! in_array($ew, $targets, true)) {
                    $disk->delete($old);
                }
            }
        } catch (Throwable $e) {
            Log::warning('VenuePhoto: could not make copies of '.$path.': '.$e->getMessage());
        } finally {
            if ($img !== null) {
                imagedestroy($img);
            }
            @ini_set('memory_limit', (string) $memory);
            unset(self::$memo[$path]);
        }

        return $written;
    }

    /** Remove every copy of a photo (it was taken off the venue). */
    public static function forget(string $value): void
    {
        $path = self::pathOf($value);
        if ($path === null) {
            return;
        }

        $disk = Storage::disk('public');
        foreach (self::variantsOf($path) as $variant) {
            $disk->delete($variant);
        }
        unset(self::$memo[$path]);
    }

    /**
     * Widths to cut a photo this wide at: every standard width below it, plus
     * one at its own width (capped at the largest) so nothing is upscaled.
     *
     * @return list<int>
     */
    public static function targetsFor(int $width): array
    {
        $out = array_values(array_filter(self::WIDTHS, static fn (int $w): bool => $w < $width));
        $out[] = min($width, max(self::WIDTHS));

        return array_values(array_unique($out));
    }

    /** Decode a JPEG/PNG/WebP into upright pixels. */
    private static function load(string $file): ?\GdImage
    {
        $type = (int) (@getimagesize($file)[2] ?? 0);
        $img = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($file),
            IMAGETYPE_PNG => @imagecreatefrompng($file),
            IMAGETYPE_WEBP => @imagecreatefromwebp($file),
            default => false,
        };
        if (! $img instanceof \GdImage) {
            return null;
        }

        if (! imageistruecolor($img)) {
            imagepalettetotruecolor($img);
        }

        // Browsers show a JPEG rotated by its EXIF tag; the WebP copy carries no
        // tag, so the rotation has to be baked into its pixels.
        // (imagerotate turns counter-clockwise; 2, 4, 5 and 7 are the mirrored tags.)
        $orientation = self::orientation($file, $type);
        $turned = match ($orientation) {
            3, 4 => imagerotate($img, 180, 0),
            5, 6 => imagerotate($img, -90, 0),
            7, 8 => imagerotate($img, 90, 0),
            default => null,
        };
        if ($turned instanceof \GdImage) {
            imagedestroy($img);
            $img = $turned;
        }
        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($img, IMG_FLIP_HORIZONTAL);
        }

        return $img;
    }

    private static function orientation(string $file, int $type): int
    {
        if ($type !== IMAGETYPE_JPEG || ! function_exists('exif_read_data')) {
            return 1;
        }

        $exif = @exif_read_data($file);

        return is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
    }
}
