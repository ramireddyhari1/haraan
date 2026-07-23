<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            // Unguessable per-ticket code embedded in the attendee's QR; the gate
            // scanner resolves a booking by this instead of an enumerable id.
            $table->string('ticket_code')->nullable()->after('id');
        });

        // Backfill existing bookings so old tickets are scannable too.
        DB::table('bookings')->select('id')->orderBy('id')->chunkById(200, function ($rows): void {
            foreach ($rows as $row) {
                DB::table('bookings')->where('id', $row->id)->update([
                    'ticket_code' => Str::upper(Str::random(24)),
                ]);
            }
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->unique('ticket_code');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropUnique(['ticket_code']);
            $table->dropColumn('ticket_code');
        });
    }
};
