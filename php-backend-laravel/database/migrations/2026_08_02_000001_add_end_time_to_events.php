<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Events only ever stored a start `time` ("5:00 PM"), so the detail page could
 * say when a thing begins but never when it ends — the question every attendee
 * asks before committing an evening. Same varchar shape as `time` so nothing
 * downstream has to learn a new format. Nullable: hosts who genuinely don't
 * know the end (open-ended meetups) leave it empty and the UI stays quiet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->string('end_time')->nullable()->after('time');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn('end_time');
        });
    }
};
