<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cookie;

/**
 * Resolves a canonical city from free-text location fields, and reads the
 * viewer's currently-selected city from the request cookie.
 *
 * The public site has no structured city column historically — venue/event
 * locations are free text ("...Bandra West, Mumbai" / "Kondapur, Hyderabad").
 * This maps that text onto a small canonical city list (aligned with
 * public/data/cities.json) so content can be scoped by the header pill.
 */
final class CityResolver
{
    /** Cookie the header pill writes when a city is chosen. */
    public const COOKIE = 'haraan_city';

    /** Canonical city names (must match the labels in cities.json). */
    public const CITIES = [
        'Mumbai',
        'Delhi NCR',
        'Bangalore',
        'Hyderabad',
        'Pune',
        'Chennai',
        'Kolkata',
        'Ahmedabad',
        'Jaipur',
        'Goa',
    ];

    /**
     * Well-known localities → canonical city, for records whose text names the
     * area but not the city (e.g. venue location "Powai" with an empty address).
     *
     * @var array<string, string>
     */
    private const AREA_ALIASES = [
        // Mumbai
        'mumbai' => 'Mumbai', 'bombay' => 'Mumbai', 'bandra' => 'Mumbai',
        'powai' => 'Mumbai', 'andheri' => 'Mumbai', 'juhu' => 'Mumbai',
        'colaba' => 'Mumbai', 'dadar' => 'Mumbai', 'worli' => 'Mumbai',
        'bkc' => 'Mumbai', 'thane' => 'Mumbai', 'navi mumbai' => 'Mumbai',
        // Hyderabad
        'hyderabad' => 'Hyderabad', 'kondapur' => 'Hyderabad', 'gachibowli' => 'Hyderabad',
        'hitech city' => 'Hyderabad', 'hitec city' => 'Hyderabad', 'madhapur' => 'Hyderabad',
        'banjara hills' => 'Hyderabad', 'jubilee hills' => 'Hyderabad', 'secunderabad' => 'Hyderabad',
        // Delhi NCR
        'delhi' => 'Delhi NCR', 'new delhi' => 'Delhi NCR', 'gurgaon' => 'Delhi NCR',
        'gurugram' => 'Delhi NCR', 'noida' => 'Delhi NCR',
        // Bangalore
        'bangalore' => 'Bangalore', 'bengaluru' => 'Bangalore', 'koramangala' => 'Bangalore',
        'whitefield' => 'Bangalore', 'indiranagar' => 'Bangalore',
        // Others
        'pune' => 'Pune', 'chennai' => 'Chennai', 'kolkata' => 'Kolkata',
        'ahmedabad' => 'Ahmedabad', 'jaipur' => 'Jaipur', 'goa' => 'Goa',
    ];

    /**
     * Detect a canonical city from one or more free-text fields.
     * Returns null when nothing matches (record stays visible under "All India").
     */
    public static function detect(?string ...$texts): ?string
    {
        $haystack = mb_strtolower(trim(implode(' ', array_filter($texts))));
        if ($haystack === '') {
            return null;
        }

        // Longest aliases first so "navi mumbai" wins over "mumbai", etc.
        $aliases = self::AREA_ALIASES;
        uksort($aliases, static fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        foreach ($aliases as $needle => $city) {
            if (str_contains($haystack, $needle)) {
                return $city;
            }
        }

        return null;
    }

    /**
     * The viewer's selected city from the request cookie, validated against the
     * canonical list. Null means "All India" (no scoping).
     */
    public static function selected(): ?string
    {
        $value = request()->cookie(self::COOKIE);
        if (!is_string($value) || $value === '' || strtolower($value) === 'all') {
            return null;
        }

        foreach (self::CITIES as $city) {
            if (strcasecmp($city, $value) === 0) {
                return $city;
            }
        }

        return null;
    }
}
