<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Event sessions ("time slots"). An event used to be a single date/time; now it
 * can run across several sessions (e.g. "7:00 PM" and "9:30 PM", or "Day 1" /
 * "Day 2"), each with its own capacity and — optionally — its own ticket tiers.
 *
 * Backwards compatible: every existing event is backfilled with exactly one slot
 * built from its own date/time, and an event with a single slot behaves just like
 * it did before. Two or more slots turns on the multi-session experience.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('event_slots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            // Optional human label; when blank the app derives one from starts_at.
            $table->string('label')->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            // null capacity = bounded only by the event's overall available_slots.
            $table->unsignedInteger('capacity')->nullable();
            $table->unsignedInteger('sold')->default(0);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['event_id', 'sort']);
        });

        // Backfill one slot per existing event from its date + time, so old events
        // keep working and immediately have a session to book against.
        DB::table('events')->orderBy('id')->select('id', 'date', 'time')->chunkById(200, function ($events): void {
            $now = now();
            $rows = [];

            foreach ($events as $event) {
                $rows[] = [
                    'event_id'   => $event->id,
                    'label'      => null,
                    'starts_at'  => self::combine($event->date, $event->time),
                    'ends_at'    => null,
                    'capacity'   => null,
                    'sold'       => 0,
                    'sort'       => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($rows !== []) {
                DB::table('event_slots')->insert($rows);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_slots');
    }

    /** Combine a stored date and a "7:00 PM" time label into a datetime string, best-effort. */
    private static function combine(?string $date, ?string $time): ?string
    {
        $date = trim((string) $date);
        if ($date === '') {
            return null;
        }

        try {
            $day = Carbon::parse($date);
        } catch (\Throwable) {
            return null;
        }

        $time = trim((string) $time);
        if ($time !== '') {
            $ts = strtotime($time);
            if ($ts !== false) {
                $day->setTime((int) date('G', $ts), (int) date('i', $ts));
            }
        }

        return $day->toDateTimeString();
    }
};
