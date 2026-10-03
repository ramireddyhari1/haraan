<?php

declare(strict_types=1);

use App\Models\VenueSlot;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Slot times in one spelling: "6:00 AM".
 *
 * The partner app's slot form asked for "06:00 AM - 07:00 AM", which the booking engine
 * can't read: those rows were never sold on the website, sorted last on the desk, and sat
 * beside the generator's "6:00 AM" row as a second 6 AM. Rewrite each readable time to its
 * start. A row whose rewritten time would duplicate another row on the same day is left as
 * it is, so no booking loses its slot — the desk shows both and the partner deletes one.
 *
 * Data only, on purpose: no ->change() (see 2026_09_25_000003).
 */
return new class extends Migration
{
    public function up(): void
    {
        $taken = [];
        $rows = DB::table('venue_slots')->orderBy('id')->get(['id', 'venue_id', 'day', 'time']);

        // First pass: the rows already in the right spelling claim their day+time.
        foreach ($rows as $row) {
            if (VenueSlot::normaliseTime($row->time) === $row->time) {
                $taken[$row->venue_id.'|'.VenueSlot::normaliseDay($row->day).'|'.$row->time] = true;
            }
        }

        foreach ($rows as $row) {
            $time = VenueSlot::normaliseTime($row->time);
            if ($time === null || $time === $row->time) {
                continue;
            }
            $key = $row->venue_id.'|'.VenueSlot::normaliseDay($row->day).'|'.$time;
            if (isset($taken[$key])) {
                continue;
            }
            $taken[$key] = true;
            DB::table('venue_slots')->where('id', $row->id)->update(['time' => $time]);
        }
    }

    public function down(): void
    {
        // Not reversible: the old spellings meant the same start time.
    }
};
