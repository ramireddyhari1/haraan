<?php

declare(strict_types=1);

namespace App\Services\VenueDiscovery;

use App\Support\PlatformRules;

/** The `venue_discovery` rules from /control, parsed once into the shapes the services use. */
final class DiscoverySettings
{
    /** @return list<string> */
    public static function cities(): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn (string $c): string => trim($c),
            explode(',', PlatformRules::string('venue_discovery.cities'))
        ))));
    }

    /**
     * Search lines as [sport|null, phrase].
     *
     * @return list<array{sport:?string, phrase:string}>
     */
    public static function terms(): array
    {
        $terms = [];
        foreach (preg_split('/\R/', PlatformRules::string('venue_discovery.terms')) ?: [] as $line) {
            if (trim($line) === '') {
                continue;
            }
            $parts = array_map('trim', explode('|', $line, 2));
            $phrase = $parts[1] ?? $parts[0];
            $sport = isset($parts[1]) && $parts[0] !== '' ? ucfirst(strtolower($parts[0])) : null;
            if ($phrase !== '') {
                $terms[] = ['sport' => $sport, 'phrase' => $phrase];
            }
        }

        return $terms;
    }

    public static function state(): string
    {
        return trim(PlatformRules::string('venue_discovery.state'));
    }

    public static function pages(): int
    {
        return max(1, min(3, PlatformRules::int('venue_discovery.pages')));
    }

    public static function publicPages(): bool
    {
        return PlatformRules::bool('venue_discovery.public_pages');
    }
}
