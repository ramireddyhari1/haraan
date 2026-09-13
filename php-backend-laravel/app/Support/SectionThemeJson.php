<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\SectionTheme;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * The one portable format for a campaign theme — what /control imports and exports, so a
 * designer can hand over a single file and last year's Diwali can be reused next year.
 *
 *   {
 *     "format": "haraan.section-theme",
 *     "version": 1,
 *     "section": "events",                    // events | pulse
 *     "campaign_name": "Diwali Nights",
 *     "colors": { "primary": "#C2410C", "deep": "#7C2D12", "tint": "#FED7AA", "on_primary": null },
 *     "decoration_url": "https://…/lights.json", // optional: PNG/WebP image or Lottie .json
 *     "starts_at": "2026-10-20T18:00:00+05:30",  // ISO-8601; no offset = IST
 *     "ends_at": "2026-10-27T23:59:00+05:30",
 *     "priority": 0,
 *     "is_active": true
 *   }
 */
final class SectionThemeJson
{
    public const FORMAT = 'haraan.section-theme';

    public const VERSION = 1;

    private const ADMIN_TZ = 'Asia/Kolkata';

    public static function export(SectionTheme $theme): array
    {
        return [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'section' => $theme->section,
            'campaign_name' => $theme->campaign_name,
            'colors' => [
                'primary' => SectionTheme::hexOrNull($theme->accent_primary),
                'deep' => SectionTheme::hexOrNull($theme->accent_deep),
                'tint' => SectionTheme::hexOrNull($theme->accent_tint),
                'on_primary' => SectionTheme::hexOrNull($theme->on_primary),
            ],
            'decoration_url' => $theme->decorationUrl(),
            'starts_at' => $theme->starts_at->copy()->setTimezone(self::ADMIN_TZ)->toIso8601String(),
            'ends_at' => $theme->ends_at->copy()->setTimezone(self::ADMIN_TZ)->toIso8601String(),
            'priority' => $theme->priority,
            'is_active' => $theme->is_active,
        ];
    }

    /**
     * Validate a decoded file and turn it into model attributes. Throws a ValidationException
     * whose messages name the offending key, so the admin knows what to fix in the file.
     *
     * @return array<string, mixed>
     */
    public static function toAttributes(string $json): array
    {
        $data = self::decode($json);
        if ($data === null) {
            throw ValidationException::withMessages(['file' => 'This file is not valid JSON.']);
        }
        if (self::kindOf($data) === self::KIND_LOTTIE) {
            throw ValidationException::withMessages(['file' => 'This is a Lottie animation, not a theme file. Open "New campaign theme" and use Import JSON there, or upload it under Decoration.']);
        }
        if (($data['format'] ?? null) !== self::FORMAT) {
            throw ValidationException::withMessages(['file' => 'Not a Haraan theme file. Use a file from "Export JSON", whose "format" is "'.self::FORMAT.'".']);
        }

        $hex = 'regex:'.SectionTheme::HEX;
        $validator = Validator::make($data, [
            'version' => ['required', 'integer', 'in:'.self::VERSION],
            'section' => ['required', 'in:'.implode(',', array_keys(SectionTheme::SECTIONS))],
            'campaign_name' => ['required', 'string', 'max:80'],
            'colors' => ['required', 'array'],
            'colors.primary' => ['required', 'string', $hex],
            'colors.deep' => ['nullable', 'string', $hex],
            'colors.tint' => ['nullable', 'string', $hex],
            'colors.on_primary' => ['nullable', 'string', $hex],
            'decoration_url' => ['nullable', 'url:https', 'max:255', 'regex:/\.(png|webp|json)(\?.*)?$/i'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date'],
            'priority' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'decoration_url.regex' => 'decoration_url must point to a .png, .webp or Lottie .json file.',
        ]);
        $validator->validate();

        $starts = CarbonImmutable::parse($data['starts_at'], self::ADMIN_TZ)->utc();
        $ends = CarbonImmutable::parse($data['ends_at'], self::ADMIN_TZ)->utc();
        if ($ends->lessThanOrEqualTo($starts)) {
            throw ValidationException::withMessages(['ends_at' => '"ends_at" must be after "starts_at".']);
        }

        return [
            'section' => $data['section'],
            'campaign_name' => $data['campaign_name'],
            'accent_primary' => strtoupper($data['colors']['primary']),
            'accent_deep' => isset($data['colors']['deep']) ? strtoupper($data['colors']['deep']) : null,
            'accent_tint' => isset($data['colors']['tint']) ? strtoupper($data['colors']['tint']) : null,
            'on_primary' => isset($data['colors']['on_primary']) ? strtoupper($data['colors']['on_primary']) : null,
            'decoration' => $data['decoration_url'] ?? null,
            'starts_at' => $starts,
            'ends_at' => $ends,
            'priority' => (int) ($data['priority'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }

    /**
     * Why a decoration file would draw nothing in the app, or null when it is a usable Lottie.
     *
     * The Android player rejects the WHOLE file on these faults and draws a blank strip, with
     * no error anywhere an admin would see — so they are caught at upload instead. Both came
     * from a real upload: a generator that wrapped every property twice, and text layers
     * exported without their fonts.
     */
    public static function lottieProblem(string $json): ?string
    {
        $data = self::decode($json);
        if ($data === null) {
            return 'That file is not valid JSON.';
        }

        if (! isset($data['v'], $data['w'], $data['h'], $data['layers']) || ! is_array($data['layers'])) {
            return 'That .json file is not a Lottie animation. Export it from After Effects (Bodymovin) or LottieFiles.';
        }
        if ($data['layers'] === []) {
            return 'That Lottie animation has no layers, so it would draw nothing.';
        }
        if (self::hasDoubleWrappedProperty($data)) {
            return 'That Lottie file is malformed: its properties are wrapped twice ({"a":0,"k":{"a":0,"k":…}}), so the app cannot play it. Re-export it from After Effects (Bodymovin) or LottieFiles.';
        }

        $hasText = false;
        foreach (self::allLayers($data) as $layer) {
            if (($layer['ty'] ?? null) === 5) {
                $hasText = true;
                break;
            }
        }
        if ($hasText && empty($data['fonts']['list'])) {
            return 'That Lottie file has text layers but no embedded fonts, so the app cannot play it. Convert the text to shapes before exporting.';
        }

        return null;
    }

    public static function isLottie(string $json): bool
    {
        return self::lottieProblem($json) === null;
    }

    public const KIND_THEME = 'theme';

    public const KIND_LOTTIE = 'lottie';

    public const KIND_UNKNOWN = 'unknown';

    /** Which of the two .json files an admin handed us: a theme settings file or an animation. */
    public static function kind(string $json): string
    {
        $data = self::decode($json);

        return $data === null ? self::KIND_UNKNOWN : self::kindOf($data);
    }

    private static function kindOf(array $data): string
    {
        return match (true) {
            ($data['format'] ?? null) === self::FORMAT => self::KIND_THEME,
            isset($data['v'], $data['layers']) => self::KIND_LOTTIE,
            default => self::KIND_UNKNOWN,
        };
    }

    /**
     * Decode to an array, or null. Tolerates a UTF-8 byte-order mark: Windows editors add one
     * invisibly, and PHP's decoder rejects the whole file because of it.
     */
    private static function decode(string $json): ?array
    {
        if (str_starts_with($json, "\xEF\xBB\xBF")) {
            $json = substr($json, 3);
        }
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        return is_array($data) ? $data : null;
    }

    /** An animatable property whose "k" is itself a whole {a, k} property. */
    private static function hasDoubleWrappedProperty(mixed $node): bool
    {
        if (! is_array($node)) {
            return false;
        }
        if (array_key_exists('a', $node) && array_key_exists('k', $node)
            && is_array($node['k']) && array_key_exists('a', $node['k']) && array_key_exists('k', $node['k'])) {
            return true;
        }
        foreach ($node as $child) {
            if (self::hasDoubleWrappedProperty($child)) {
                return true;
            }
        }

        return false;
    }

    /** Top-level layers plus layers inside precomp assets. */
    private static function allLayers(array $data): array
    {
        $layers = $data['layers'];
        foreach ($data['assets'] ?? [] as $asset) {
            if (is_array($asset) && is_array($asset['layers'] ?? null)) {
                $layers = array_merge($layers, $asset['layers']);
            }
        }

        return array_filter($layers, 'is_array');
    }
}
