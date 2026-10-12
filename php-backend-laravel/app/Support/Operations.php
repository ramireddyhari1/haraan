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

    public const APP_MEMBER = 'member';

    public const APP_PARTNER = 'partner';

    /** The PlatformRules section holding an app's version rules. */
    private static function section(string $app): string
    {
        return $app === self::APP_PARTNER ? 'partner_app' : 'app';
    }

    /**
     * The version an app must be at or above, or null when every version is allowed.
     * Force update promotes the latest version to the minimum.
     */
    public static function minimumAppVersion(string $app = self::APP_MEMBER): ?string
    {
        $s = self::section($app);
        $min = trim(PlatformRules::string("{$s}.min_version"));
        $latest = trim(PlatformRules::string("{$s}.latest_version"));

        if (PlatformRules::bool("{$s}.force_update") && $latest !== '') {
            $min = ($min === '' || version_compare($latest, $min, '>')) ? $latest : $min;
        }

        return $min !== '' ? $min : null;
    }

    public static function appNeedsUpdate(?string $appVersion, string $app = self::APP_MEMBER): bool
    {
        $min = self::minimumAppVersion($app);
        $v = self::cleanVersion($appVersion);

        return $min !== null && $v !== null && version_compare($v, $min, '<');
    }

    public static function appUpdateAvailable(?string $appVersion, string $app = self::APP_MEMBER): bool
    {
        $latest = trim(PlatformRules::string(self::section($app).'.latest_version'));
        $v = self::cleanVersion($appVersion);

        return $latest !== '' && $v !== null && version_compare($v, $latest, '<');
    }

    /**
     * Everything an app's update sheet needs: whether to show it, whether it can be put off,
     * where to get the build, and the admin's copy. Served by GET /api/app/update.
     *
     * @return array<string, mixed>
     */
    public static function updateState(string $app, ?string $appVersion): array
    {
        $s = self::section($app);
        $required = self::appNeedsUpdate($appVersion, $app);
        $notes = array_values(array_filter(array_map(
            // A regex, not ltrim(): a charlist holding "•" strips single bytes and can eat
            // the lead byte of ₹ or any other multibyte character.
            static fn (string $line): string => trim((string) preg_replace('/^\s*(?:[-*]|\x{2022})\s*/u', '', $line)),
            preg_split('/\R/u', PlatformRules::string("{$s}.whats_new")) ?: [],
        ), static fn (string $line): bool => $line !== ''));

        return [
            'app' => $app,
            'current_version' => self::cleanVersion($appVersion),
            'available' => $required || self::appUpdateAvailable($appVersion, $app),
            'required' => $required,
            'latest_version' => trim(PlatformRules::string("{$s}.latest_version")) ?: null,
            'min_version' => self::minimumAppVersion($app),
            'message' => PlatformRules::string("{$s}.update_message"),
            'whats_new' => $notes,
            'apk_url' => PlatformRules::string("{$s}.apk_url"),
            'store_url' => $app === self::APP_MEMBER ? PlatformRules::string('app.update_url') : null,
            'remind_after_hours' => PlatformRules::int("{$s}.remind_hours"),
        ];
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
