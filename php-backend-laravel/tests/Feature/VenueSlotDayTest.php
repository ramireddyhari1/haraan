<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Venue;
use App\Models\VenueSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * A slot shows on the website and in the app only on its day. The admin slot editor never
 * asked for one, so new rows got the column default "Today" — which matches no date — and
 * older rows kept whatever day they were seeded with, invisible in admin.
 */
class VenueSlotDayTest extends TestCase
{
    use RefreshDatabase;

    private function venue(): Venue
    {
        return Venue::create(['name' => 'Day Rule Turf', 'location' => 'Gachibowli', 'price' => 800]);
    }

    public function test_a_slot_created_without_a_day_runs_every_day(): void
    {
        $venue = $this->venue();
        $slot = VenueSlot::create(['venue_id' => $venue->id, 'time' => '6:00 AM']);

        $this->assertSame(VenueSlot::EVERY_DAY, $slot->fresh()->day);
        foreach (range(0, 6) as $i) {
            $this->assertCount(1, $venue->fresh()->slotsOn(Carbon::parse('2026-09-28')->addDays($i)));
        }
    }

    public function test_labels_that_name_no_weekday_are_saved_as_every_day(): void
    {
        $venue = $this->venue();

        foreach (['Today' => 'Every day', 'Daily' => 'Every day', '' => 'Every day', 'mon' => 'Monday', 'SATURDAY' => 'Saturday'] as $in => $out) {
            $slot = VenueSlot::create(['venue_id' => $venue->id, 'day' => $in, 'time' => '6:00 AM']);
            $this->assertSame($out, $slot->fresh()->day, "label '{$in}'");
        }
    }

    public function test_a_weekday_row_adds_to_the_every_day_rows_on_its_day_only(): void
    {
        $venue = $this->venue();
        VenueSlot::create(['venue_id' => $venue->id, 'day' => 'Every day', 'time' => '6:00 AM']);
        VenueSlot::create(['venue_id' => $venue->id, 'day' => 'Monday', 'time' => '7:00 PM']);

        $monday = $venue->fresh()->slotsOn(Carbon::parse('2026-09-28'));
        $tuesday = $venue->fresh()->slotsOn(Carbon::parse('2026-09-29'));

        $this->assertSame(['6:00 AM', '7:00 PM'], $monday->pluck('time')->all());
        $this->assertSame(['6:00 AM'], $tuesday->pluck('time')->all());
    }

    public function test_every_day_rows_still_sell_on_days_that_have_weekday_rows(): void
    {
        // Prod venue 4: Mon–Sun 6 AM rows, and "Every day" 8–11 AM rows that the website,
        // checkout and WhatsApp bot never offered on any day.
        $venue = $this->venue();
        foreach (VenueSlot::WEEKDAYS as $d) {
            VenueSlot::create(['venue_id' => $venue->id, 'day' => $d, 'time' => '6:00 AM']);
        }
        VenueSlot::create(['venue_id' => $venue->id, 'day' => 'Every day', 'time' => '8:00 AM']);

        foreach (range(0, 6) as $i) {
            $this->assertSame(['6:00 AM', '8:00 AM'], $venue->fresh()->slotsOn(Carbon::parse('2026-09-28')->addDays($i))->pluck('time')->all());
        }
    }

    public function test_a_weekday_row_replaces_the_every_day_row_at_the_same_time(): void
    {
        $venue = $this->venue();
        VenueSlot::create(['venue_id' => $venue->id, 'day' => 'Every day', 'time' => '6:00 AM', 'price' => 500]);
        $saturday = VenueSlot::create(['venue_id' => $venue->id, 'day' => 'Saturday', 'time' => '06:00', 'price' => 800]);

        $sat = $venue->fresh()->slotsOn(Carbon::parse('2026-10-03'));

        $this->assertSame([$saturday->id], $sat->pluck('id')->all());
    }

    public function test_the_migration_rewrites_legacy_labels(): void
    {
        $venue = $this->venue();
        $id = \DB::table('venue_slots')->insertGetId(['venue_id' => $venue->id, 'day' => 'Today', 'time' => '6:00 AM']);
        $keep = \DB::table('venue_slots')->insertGetId(['venue_id' => $venue->id, 'day' => 'Monday', 'time' => '7:00 AM']);

        (require database_path('migrations/2026_09_25_000003_normalise_venue_slot_days.php'))->up();

        $this->assertSame('Every day', \DB::table('venue_slots')->where('id', $id)->value('day'));
        $this->assertSame('Monday', \DB::table('venue_slots')->where('id', $keep)->value('day'));
    }
}
