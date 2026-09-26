<?php

declare(strict_types=1);

use App\Models\VenueSlot;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A slot's day is a weekday name or "Every day". Rows added through the admin slot editor
 * (which never asked for a day) got the column default "Today" — a label no date matches, so
 * the website and the app showed those slots on no day at all. Rewrite every non-weekday
 * label to "Every day", which is what admin always presented them as.
 *
 * Data only, on purpose: no ->change() on the column. On SQLite that rebuilds the table, and
 * rebuilding a table other tables cascade from wipes their rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('venue_slots')->orderBy('id')->select(['id', 'day'])->chunkById(500, function ($rows): void {
            foreach ($rows as $row) {
                $day = VenueSlot::normaliseDay($row->day);
                if ($day !== $row->day) {
                    DB::table('venue_slots')->where('id', $row->id)->update(['day' => $day]);
                }
            }
        });
    }

    public function down(): void
    {
        // Not reversible: the old labels named no day, and nothing read them as one.
    }
};
