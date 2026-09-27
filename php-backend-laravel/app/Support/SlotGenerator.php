<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Venue;
use App\Models\VenueSlot;
use App\Services\BookingService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * One-click slot builder, shared by /control and the partner app.
 *
 * The desk types an opening and a closing time, picks 30-minute or 1-hour slots and
 * the days, and every start time in between is created — instead of adding forty rows
 * one "+" at a time. Closing at "12:00 AM" means midnight; the last slot is the one
 * that still ends by closing time.
 *
 * Two modes:
 *  - add:     keeps every slot the venue has (its price, sports, capacity) and only
 *             creates the start times that aren't there yet.
 *  - replace: removes the venue's slot rows first. Bookings are untouched — they keep
 *             their own date and hours, and nothing references a slot row by key.
 *
 * The venue's slot length is set to the step, because checkout, availability and the
 * desk all read it to know how long one slot is.
 */
final class SlotGenerator
{
    public const STEPS = [30, 60];

    public const MODES = ['add', 'replace'];

    /**
     * @param  list<string>  $days  weekday names ("Monday") or ["Every day"]
     * @return array{created: int, kept: int, removed: int, times: list<string>}
     */
    public static function generate(
        Venue $venue,
        string $open,
        string $close,
        int $step,
        array $days,
        ?float $price = null,
        int $capacity = 1,
        string $mode = 'add',
    ): array {
        if (! in_array($step, self::STEPS, true)) {
            throw new InvalidArgumentException('Slot length must be 30 or 60 minutes.');
        }
        if (! in_array($mode, self::MODES, true)) {
            throw new InvalidArgumentException('Mode must be add or replace.');
        }

        $from = BookingService::timeToMinutes($open);
        $to = BookingService::timeToMinutes($close);
        if ($from === null || $to === null) {
            throw new InvalidArgumentException('Opening and closing times must be times, like 6:00 AM.');
        }
        // "12:00 AM" as a closing time is midnight at the end of the day.
        if ($to === 0) {
            $to = 24 * 60;
        }
        if ($to <= $from) {
            throw new InvalidArgumentException('Closing time must be after opening time. For hours past midnight, generate the next day from 12:00 AM.');
        }
        if ($to - $from < $step) {
            throw new InvalidArgumentException('Opening hours are shorter than one slot.');
        }

        $labels = [];
        for ($m = $from; $m + $step <= $to; $m += $step) {
            $labels[] = self::label($m);
        }

        $dayLabels = array_values(array_unique(array_map(
            static fn (string $d): string => VenueSlot::normaliseDay($d),
            $days === [] ? [VenueSlot::EVERY_DAY] : $days,
        )));

        return DB::transaction(function () use ($venue, $labels, $dayLabels, $step, $price, $capacity, $mode): array {
            $removed = 0;
            if ($mode === 'replace') {
                $removed = VenueSlot::query()->where('venue_id', $venue->id)->delete();
            }

            $existing = VenueSlot::query()->where('venue_id', $venue->id)->get()
                ->map(fn (VenueSlot $s): string => VenueSlot::normaliseDay($s->day).'|'.BookingService::timeToMinutes($s->time))
                ->flip();
            $order = (int) VenueSlot::query()->where('venue_id', $venue->id)->max('sort_order');

            $created = 0;
            $kept = 0;
            foreach ($dayLabels as $day) {
                foreach ($labels as $label) {
                    $key = $day.'|'.BookingService::timeToMinutes($label);
                    if ($existing->has($key)) {
                        $kept++;

                        continue;
                    }
                    VenueSlot::query()->create(array_filter([
                        'venue_id'     => $venue->id,
                        'day'          => $day,
                        'time'         => $label,
                        'capacity'     => max(1, $capacity),
                        'is_available' => true,
                        // Null = charge the court/venue rate; a set price wins over it.
                        'price'        => $price !== null && $price > 0 ? $price : null,
                        'sort_order'   => ++$order,
                    ], static fn ($v): bool => $v !== null));
                    $created++;
                }
            }

            if ((int) $venue->slot_minutes !== $step) {
                $venue->forceFill(['slot_minutes' => $step])->save();
            }

            return ['created' => $created, 'kept' => $kept, 'removed' => $removed, 'times' => $labels];
        });
    }

    /** Minutes-from-midnight as the label slots use everywhere: "6:00 AM", "6:30 PM". */
    private static function label(int $minutes): string
    {
        $h = intdiv($minutes, 60) % 24;
        $m = $minutes % 60;

        return sprintf('%d:%02d %s', $h % 12 === 0 ? 12 : $h % 12, $m, $h < 12 ? 'AM' : 'PM');
    }
}
