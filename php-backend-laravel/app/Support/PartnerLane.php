<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The partner consoles, and the vocabulary each one speaks.
 *
 * There are three, and a partner mounts exactly one:
 *
 *   events   an event host — publishes events, sells tickets, scans them
 *   gamehub  a sports venue — courts, turf, slot bookings
 *   cafe     a café venue  — tables and stations, plus the nights it hosts
 *
 * A café is NOT the sports console with different data in it. It was modelled
 * that way first, and that was wrong: a café owner reading "Courts" and
 * "occupancy by sport" is looking at somebody else's business. The lane is
 * separate so nothing sports-shaped can leak in by default.
 *
 * What the two branch lanes DO share is arithmetic — money collected, bookings
 * taken, peak hours, who's coming — which is why their widgets are shared and
 * only the nouns resolve per lane. Duplicating four widgets to change one word
 * would mean every future fix has to be made twice.
 *
 * This class is the single place that knows the vocabulary. A lane added here
 * without a noun is a fatal misconfiguration, not a silent fallback to "court".
 */
final class PartnerLane
{
    public const EVENTS = 'events';
    public const GAMEHUB = 'gamehub';
    public const CAFE = 'cafe';

    /** Every console. Order is not significant. */
    public const ALL = [self::EVENTS, self::GAMEHUB, self::CAFE];

    /**
     * The lanes that operate physical branches — bookings, resources, a desk.
     * The events lane has none of that, which is why it shares no widgets.
     */
    public const BRANCH_LANES = [self::GAMEHUB, self::CAFE];

    /**
     * Which console each `users.partner_type` mounts.
     *
     * Written out rather than derived from `partner_type !== 'event'`, which is
     * how this used to read — that test silently handed any new type the whole
     * sports console. Adding a row here is a decision somebody made on purpose.
     *
     * @var array<string, string>
     */
    public const FOR_TYPE = [
        'event' => self::EVENTS,
        'venue' => self::GAMEHUB,
        'cafe' => self::CAFE,
    ];

    /**
     * The partner types an admin may assign, and what to call them.
     *
     * The stored values stay `event` / `venue` / `cafe`. They are deliberately
     * NOT renamed to `event_host` / `sports_venue` / `cafe_venue`: the published
     * Android app reads `partner.type` off the API and maps `'event'` to its
     * events tab set, so a rename would drop every already-installed copy into
     * the wrong console until users updated. The labels carry the meaning; the
     * column values only have to be stable.
     *
     * @var array<string, string>
     */
    public const TYPE_LABELS = [
        'event' => 'Event host',
        'venue' => 'Sports venue',
        'cafe' => 'Café venue',
    ];

    /**
     * What a bookable unit is called in each branch lane, singular and plural.
     *
     * `venue_courts` is one table storing "the physical thing that can hold only
     * one booking at a time". A café calls that a table; a turf calls it a court.
     * Same row, same conflict rule, different word.
     *
     * @var array<string, array{0:string, 1:string}>
     */
    private const RESOURCE_NOUNS = [
        self::GAMEHUB => ['court', 'courts'],
        self::CAFE => ['table', 'tables'],
    ];

    /**
     * The console for a `partner_type`.
     *
     * Unknown and null types keep the historical GameHub default deliberately: a
     * partner with a legacy or mistyped value lands somewhere usable rather than
     * on a blank console with no way out. Failing closed here would strand real
     * accounts to guard against a case FOR_TYPE already covers.
     */
    public static function forType(?string $partnerType): string
    {
        return self::FOR_TYPE[(string) $partnerType] ?? self::GAMEHUB;
    }

    /** True when this lane runs branches (bookings, resources, a desk). */
    public static function isBranchLane(string $lane): bool
    {
        return in_array($lane, self::BRANCH_LANES, true);
    }

    /**
     * The bookable-unit noun for a lane: `resourceNoun('cafe')` → "table".
     *
     * Only branch lanes have one. Asking the events lane for a court is a bug in
     * the caller, so it throws rather than inventing a word that would then show
     * up in somebody's dashboard.
     */
    public static function resourceNoun(string $lane, bool $plural = false): string
    {
        if (! isset(self::RESOURCE_NOUNS[$lane])) {
            throw new \InvalidArgumentException("Lane [{$lane}] has no bookable resource.");
        }

        return self::RESOURCE_NOUNS[$lane][$plural ? 1 : 0];
    }

    /** "court-hours" / "table-hours" — the occupancy denominator's unit. */
    public static function resourceHours(string $lane): string
    {
        return self::resourceNoun($lane).'-hours';
    }

    /** Human label for a partner type, for admin lists and badges. */
    public static function typeLabel(?string $partnerType): string
    {
        return self::TYPE_LABELS[(string) $partnerType] ?? '—';
    }
}
