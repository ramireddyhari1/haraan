<?php

declare(strict_types=1);

namespace App\Support;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * The emergency switches from /control → Platform → Operations, enforced on the server.
 *
 * Every refusal is a ConflictHttpException carrying the admin's message: the app shows the
 * API's `message`, the website flashes it, and the checkout paths already catch 409s — so a
 * paused booking reads as a clear sentence, never a crash page.
 */
final class Operations
{
    public const EVENTS = 'event';

    public const VENUES = 'venue';

    public static function maintenance(): bool
    {
        return PlatformRules::bool('ops.maintenance_mode');
    }

    public static function bookingsPaused(string $kind): bool
    {
        if (PlatformRules::bool('ops.pause_all_bookings')) {
            return true;
        }

        return match ($kind) {
            self::EVENTS => PlatformRules::bool('ops.pause_event_bookings'),
            self::VENUES => PlatformRules::bool('ops.pause_venue_bookings'),
            default => false,
        };
    }

    public static function assertBookingsOpen(string $kind): void
    {
        if (self::bookingsPaused($kind)) {
            throw new ConflictHttpException(PlatformRules::string('ops.pause_message'));
        }
    }

    public static function paymentsDisabled(): bool
    {
        return PlatformRules::bool('ops.payments_disabled');
    }

    public static function paymentsMessage(): string
    {
        return 'Online payments are paused for a short while. Please try again soon.';
    }

    public static function assertPaymentsOn(): void
    {
        if (self::paymentsDisabled()) {
            throw new ConflictHttpException(self::paymentsMessage());
        }
    }

    public static function assertMatchCreationOpen(): void
    {
        if (PlatformRules::bool('ops.pause_match_creation')) {
            throw new ConflictHttpException('New matches are paused for a short while. Matches already running keep scoring.');
        }
    }

    public static function assertTournamentCreationOpen(): void
    {
        if (PlatformRules::bool('ops.pause_tournament_creation')) {
            throw new ConflictHttpException('New tournaments are paused for a short while. Please try again soon.');
        }
    }

    /**
     * The version an app must be at or above, or null when every version is allowed.
     * Force update promotes the latest version to the minimum.
     */
    public static function minimumAppVersion(): ?string
    {
        $min = trim(PlatformRules::string('app.min_version'));
        $latest = trim(PlatformRules::string('app.latest_version'));

        if (PlatformRules::bool('app.force_update') && $latest !== '') {
            $min = ($min === '' || version_compare($latest, $min, '>')) ? $latest : $min;
        }

        return $min !== '' ? $min : null;
    }

    public static function appNeedsUpdate(?string $appVersion): bool
    {
        $min = self::minimumAppVersion();
        $v = self::cleanVersion($appVersion);

        return $min !== null && $v !== null && version_compare($v, $min, '<');
    }

    public static function appUpdateAvailable(?string $appVersion): bool
    {
        $latest = trim(PlatformRules::string('app.latest_version'));
        $v = self::cleanVersion($appVersion);

        return $latest !== '' && $v !== null && version_compare($v, $latest, '<');
    }

    /**
     * What /api/config tells the client about operations. The app reads this to show the
     * maintenance and update screens; the server enforces the same rules regardless.
     *
     * @return array<string, mixed>
     */
    public static function clientState(?string $appVersion): array
    {
        return [
            'maintenance' => [
                'enabled' => self::maintenance(),
                'message' => PlatformRules::string('ops.maintenance_message'),
            ],
            'update' => [
                'required' => self::appNeedsUpdate($appVersion),
                'available' => self::appUpdateAvailable($appVersion),
                'min_version' => self::minimumAppVersion(),
                'latest_version' => trim(PlatformRules::string('app.latest_version')) ?: null,
                'message' => PlatformRules::string('app.update_message'),
                'url' => PlatformRules::string('app.update_url'),
            ],
            'bookings' => [
                'events_paused' => self::bookingsPaused(self::EVENTS),
                'venues_paused' => self::bookingsPaused(self::VENUES),
                'message' => PlatformRules::string('ops.pause_message'),
            ],
            'payments_enabled' => ! self::paymentsDisabled(),
            'match_creation_paused' => PlatformRules::bool('ops.pause_match_creation'),
            'tournament_creation_paused' => PlatformRules::bool('ops.pause_tournament_creation'),
            'ai_enabled' => ! PlatformRules::bool('ops.ai_disabled'),
        ];
    }

    /** "1.0.38", "1.0.38-debug", "v1.0.38" → "1.0.38"; anything unparseable → null. */
    public static function cleanVersion(?string $version): ?string
    {
        if ($version === null || ! preg_match('/(\d+(?:\.\d+){0,3})/', $version, $m)) {
            return null;
        }

        return $m[1];
    }
}
