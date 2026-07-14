<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Courts become first-class, sport-aware bookable resources.
 *
 * A court is the *physical* thing that can hold only one booking at a time — the same
 * "Court 1" may host Football AND Cricket, so a booking on it for one sport must block
 * every other sport for that time window. Modelling a court per-sport (two rows for one
 * ground) would let two sports be booked on the same patch at once — a double booking.
 *
 * Each court therefore lists the sports it *supports* and carries its own optional hourly
 * price (a cricket pitch may cost more than a badminton court). Existing venues.courts
 * string labels are migrated into rows supporting the venue's full sport list, then the
 * old flat JSON column is dropped.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('venue_courts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // Sports this court can host. A booking locks the court across ALL of them.
            $table->json('sports')->nullable();
            // Per-court hourly rate. Null → fall back to the venue's base price.
            $table->unsignedInteger('price')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['venue_id', 'is_active']);
        });

        // Backfill: turn each existing flat court label into a real court row that supports
        // every sport the venue offers (the safest default — owners narrow it down later).
        if (Schema::hasColumn('venues', 'courts')) {
            foreach (DB::table('venues')->select('id', 'category', 'sports', 'courts')->get() as $venue) {
                $labels = json_decode((string) ($venue->courts ?? '[]'), true);
                if (! is_array($labels)) {
                    $labels = [];
                }

                $sports = json_decode((string) ($venue->sports ?? '[]'), true);
                $sports = is_array($sports) ? $sports : [];
                array_unshift($sports, $venue->category);
                $sports = array_values(array_unique(array_filter(array_map('trim', $sports))));

                $order = 0;
                foreach ($labels as $label) {
                    $label = trim((string) $label);
                    if ($label === '') {
                        continue;
                    }

                    DB::table('venue_courts')->insert([
                        'venue_id'   => $venue->id,
                        'name'       => $label,
                        'sports'     => json_encode($sports),
                        'price'      => null,
                        'sort_order' => $order++,
                        'is_active'  => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            Schema::table('venues', function (Blueprint $table): void {
                $table->dropColumn('courts');
            });
        }
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table): void {
            if (! Schema::hasColumn('venues', 'courts')) {
                $table->json('courts')->nullable()->after('amenities');
            }
        });

        Schema::dropIfExists('venue_courts');
    }
};
