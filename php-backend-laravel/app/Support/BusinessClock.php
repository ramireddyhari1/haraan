<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Carbon;
use Carbon\CarbonInterface;

/**
 * "What day is it, and what time is it" — at the venue, not on the server.
 *
 * The app runs in UTC (config/app.php) and every timestamp column is written in
 * UTC; that stays true, because moving the app timezone would shift every stored
 * hold, OTP and booking by the offset. But a day is a local idea. Asking
 * `now()->toDateString()` between midnight and 05:30 IST answered with
 * yesterday, so the partner Home read "TODAY · FRI 25 SEP" at 3 AM on the 26th
 * and today's bookings vanished from it.
 *
 * So: anything that decides WHICH DAY it is, or compares a slot's wall-clock
 * time with now, asks this class. Anything that stores or compares instants
 * keeps using UTC. The zone is an admin setting (Platform rules ▸ Bookings),
 * India by default.
 */
final class BusinessClock
{
    public const DEFAULT_ZONE = 'Asia/Kolkata';

    public static function zone(): string
    {
        try {
            $zone = PlatformRules::string('bookings.business_timezone');
        } catch (\Throwable) {
            $zone = self::DEFAULT_ZONE;
        }

        return in_array($zone, \DateTimeZone::listIdentifiers(), true) ? $zone : self::DEFAULT_ZONE;
    }

    /** The current moment, expressed in the business zone. */
    public static function now(): Carbon
    {
        return Carbon::now(self::zone());
    }

    /** Today's date at the venue, as Y-m-d — the value slot_date and date columns hold. */
    public static function today(): string
    {
        return self::now()->toDateString();
    }

    /**
     * Today's date as a Carbon at midnight in the APP zone, for code that compares
     * against date-cast columns (which Eloquent reads at app-zone midnight). Only
     * the calendar date changes; the zone stays the one those columns use.
     */
    public static function todayDate(): Carbon
    {
        return Carbon::parse(self::today());
    }

    /**
     * The UTC instants a local day runs between, for querying UTC timestamp
     * columns (created_at, paid_at …) by business day.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function dayBoundsUtc(?string $date = null): array
    {
        $start = Carbon::parse($date ?? self::today(), self::zone())->startOfDay();

        return [$start->copy()->utc(), $start->copy()->endOfDay()->utc()];
    }

    /** A stored UTC instant, shown in the business zone — for grouping by local day. */
    public static function local(CarbonInterface $instant): Carbon
    {
        return Carbon::instance($instant)->setTimezone(self::zone());
    }

    /** A slot's wall-clock time on a date ("2026-09-26", "6:00 AM") as a real instant. */
    public static function at(string $date, string $time): ?Carbon
    {
        $time = trim($time);
        if ($time === '') {
            return null;
        }

        try {
            return Carbon::parse($date.' '.$time, self::zone());
        } catch (\Throwable) {
            return null;
        }
    }
}
