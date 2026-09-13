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
        try {
            $data = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw ValidationException::withMessages(['file' => 'This file is not valid JSON.']);
        }
        if (! is_array($data)) {
            throw ValidationException::withMessages(['file' => 'The file must contain one JSON object.']);
        }
        if (($data['format'] ?? null) !== self::FORMAT) {
            throw ValidationException::withMessages(['file' => 'Not a Haraan theme file — "format" must be "'.self::FORMAT.'".']);
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

    /** True when the text looks like a Lottie animation rather than some other JSON. */
    public static function isLottie(string $json): bool
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return false;
        }

        return is_array($data) && isset($data['v'], $data['layers']) && is_array($data['layers']);
    }
}
