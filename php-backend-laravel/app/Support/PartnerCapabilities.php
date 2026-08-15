<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use App\Models\Venue;

/**
 * What a business — or one of its branches — is allowed to do.
 *
 * Capabilities are *derived, then overridden*, never hand-assembled. Six free
 * booleans per partner is sixty-four configurations, and every one of them is a
 * support ticket the day a screen renders empty for a reason nobody can explain.
 * So the business type picks a preset, and an override is stored only where a
 * partner genuinely differs from it.
 *
 *     partner_type  →  preset  →  users.capabilities  →  venues.capabilities
 *                                 (business override)   (branch override)
 *
 * Both override columns are nullable and null means INHERIT — not "none". A
 * branch with no row of its own behaves exactly like its business, which is the
 * common case and must stay zero-effort to set up.
 *
 * The per-branch level exists because branches genuinely differ: one café outlet
 * has six consoles and a stage, the next is a twelve-seat coffee counter that
 * takes no bookings. A capability the branch lacks doesn't render a disabled
 * screen — the nav item is simply absent.
 */
final class PartnerCapabilities
{
    /** Everything a branch can be granted. Order is display order. */
    public const ALL = ['bookings', 'resources', 'events', 'memberships', 'offers'];

    /**
     * What each partner type does out of the box, keyed by `users.partner_type`.
     *
     * A café gets `events` where a sports venue doesn't, because the reason a café
     * is on Haraan at all is the open mic and the quiz night — not the coffee.
     *
     * Keyed on `partner_type` directly. There was briefly a second `business_type`
     * column feeding this; it encoded the same fact, could disagree with the first,
     * and no screen ever set it. See {@see \App\Support\PartnerLane}.
     *
     * @var array<string, array<int, string>>
     */
    public const PRESETS = [
        'venue' => ['bookings', 'resources', 'memberships', 'offers'],
        'cafe' => ['bookings', 'resources', 'events', 'memberships', 'offers'],
        'event' => ['events', 'offers'],
    ];

    /**
     * The capability set for a business: its own override, else its type's preset.
     *
     * A partner with a null or unrecognised `partner_type` falls back to the venue
     * preset — the same historical default that decides their lane.
     *
     * @return array<int, string>
     */
    public static function forBusiness(User $partner): array
    {
        $override = self::clean($partner->capabilities);

        if ($override !== null) {
            return $override;
        }

        return self::PRESETS[(string) $partner->partner_type] ?? self::PRESETS['venue'];
    }

    /**
     * The capability set for one branch: its own override, else its business's.
     *
     * The owner is passed in rather than loaded off the relation so a caller
     * listing twenty branches doesn't issue twenty queries for the same partner.
     *
     * @return array<int, string>
     */
    public static function forBranch(Venue $branch, User $partner): array
    {
        return self::clean($branch->capabilities) ?? self::forBusiness($partner);
    }

    /** Whether a branch may do one specific thing. */
    public static function branchCan(Venue $branch, User $partner, string $capability): bool
    {
        return in_array($capability, self::forBranch($branch, $partner), true);
    }

    /**
     * Normalise a stored override: drop anything not a real capability, de-dupe,
     * and keep the canonical display order regardless of how it was written.
     *
     * Returns null for "no override" so callers can tell an *absent* override from
     * a deliberately *empty* one — a branch really can be granted nothing, and that
     * must not silently re-inherit the business's full set.
     *
     * @return array<int, string>|null
     */
    private static function clean(mixed $raw): ?array
    {
        if (! is_array($raw)) {
            return null;
        }

        return array_values(array_filter(
            self::ALL,
            static fn (string $c): bool => in_array($c, $raw, true),
        ));
    }
}
