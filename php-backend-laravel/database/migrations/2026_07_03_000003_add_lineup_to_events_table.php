<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lineup — "who takes the stage". An admin-authored list of {name, subtitle,
 * image} performer cards rendered as a coverflow carousel on the event detail
 * screen, below the organizer section.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->json('lineup')->nullable()->after('schedule');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn('lineup');
        });
    }
};
