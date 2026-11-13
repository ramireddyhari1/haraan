<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Venue discovery: places found on Google that Haraan does not list yet.
 *
 * `venue_candidates` is a review queue, never read by the customer side. An admin
 * approves a row in /control → Venues → Discovery and only then does a `venues` row
 * exist, in the "listed" state (a page with no booking).
 *
 * `venue_interests` is what players leave on such a page: "I want to book here" or
 * "tell me when I can".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_candidates', function (Blueprint $table): void {
            $table->id();
            $table->string('place_id')->unique();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('area')->nullable();
            $table->string('city')->nullable()->index();
            $table->string('state')->nullable();
            $table->double('latitude')->nullable();
            $table->double('longitude')->nullable();
            $table->string('phone')->nullable();
            $table->string('website', 500)->nullable();
            $table->string('maps_url', 500)->nullable();
            $table->string('primary_type')->nullable();
            $table->json('google_types')->nullable();
            $table->json('sports')->nullable();
            $table->json('found_by')->nullable();
            // unchecked | matched | unmatched
            $table->string('crosscheck_status')->default('unchecked')->index();
            $table->string('crosscheck_source')->nullable();
            $table->string('crosscheck_name')->nullable();
            $table->string('crosscheck_url', 500)->nullable();
            $table->timestamp('crosschecked_at')->nullable();
            // new | approved | rejected | duplicate
            $table->string('status')->default('new')->index();
            $table->unsignedBigInteger('venue_id')->nullable()->index();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('venue_interests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('venue_id')->index();
            $table->unsignedBigInteger('user_id')->nullable();
            // book | notify
            $table->string('kind')->default('book');
            $table->string('name');
            $table->string('phone', 20);
            $table->string('sport')->nullable();
            $table->string('preferred_time')->nullable();
            $table->timestamps();
            $table->index(['venue_id', 'phone']);
        });

        // Additive only. NO ->change() on `venues`: on SQLite that rebuilds the table and
        // the foreign-key cascade deletes every venue's courts, slots, reviews and blocks.
        Schema::table('venues', function (Blueprint $table): void {
            if (! Schema::hasColumn('venues', 'contact_phone')) {
                $table->string('contact_phone', 30)->nullable();
            }
            if (! Schema::hasColumn('venues', 'listing_source')) {
                $table->string('listing_source', 40)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_interests');
        Schema::dropIfExists('venue_candidates');

        Schema::table('venues', function (Blueprint $table): void {
            foreach (['contact_phone', 'listing_source'] as $column) {
                if (Schema::hasColumn('venues', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
