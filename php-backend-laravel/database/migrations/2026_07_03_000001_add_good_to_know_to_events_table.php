<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Good to Know" — structured, admin-picked attributes shown on the event
 * detail screen (language, age limit, kid/pet friendly, layout, seating,
 * duration, entry rule) plus real T&C bullets (info_notes, previously
 * hardcoded on the client) and a JSON bag for arbitrary extra rows.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->json('languages')->nullable()->after('description');
            $table->string('age_limit')->nullable()->after('languages');
            $table->boolean('kid_friendly')->nullable()->after('age_limit');
            $table->boolean('pet_friendly')->nullable()->after('kid_friendly');
            $table->string('layout')->nullable()->after('pet_friendly');
            $table->string('seating_type')->nullable()->after('layout');
            $table->string('duration')->nullable()->after('seating_type');
            $table->string('entry_note')->nullable()->after('duration');
            $table->json('info_notes')->nullable()->after('entry_note');
            $table->json('good_to_know')->nullable()->after('info_notes');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn([
                'languages', 'age_limit', 'kid_friendly', 'pet_friendly',
                'layout', 'seating_type', 'duration', 'entry_note',
                'info_notes', 'good_to_know',
            ]);
        });
    }
};
