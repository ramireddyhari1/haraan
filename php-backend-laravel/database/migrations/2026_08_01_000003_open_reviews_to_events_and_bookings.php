<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Let a review belong to an EVENT and to the BOOKING it came from.
 *
 * `venue_reviews` was built for venue owners, seeded by admins, and read by the
 * partner dashboard — no customer ever wrote one. Now that the post-event journey
 * links to a review page, three things change:
 *
 *  - `venue_id` becomes nullable, because an event booking has no venue row. Events
 *    keep the venue as free text, so there is nothing to point a foreign key at.
 *  - `event_id` records which event was reviewed.
 *  - `booking_id` is the anti-abuse control AND the dedupe key: the review page is
 *    public and code-addressed (no login), so the booking code is the only proof
 *    that the reviewer actually attended. Unique, so one booking leaves one review
 *    no matter how many times the link is opened.
 *
 * `text` becomes nullable too: a rating with no comment is a complete review, and
 * demanding prose is how you get "good" typed a thousand times.
 *
 * The table keeps its name. Renaming it would touch the model, the partner page,
 * the seeders and the admin resource for no behavioural gain.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venue_reviews', function (Blueprint $table): void {
            $table->foreignId('venue_id')->nullable()->change();
            $table->text('text')->nullable()->change();

            $table->unsignedBigInteger('event_id')->nullable()->after('venue_id');
            $table->unsignedBigInteger('booking_id')->nullable()->after('event_id');

            $table->index('event_id');
            // Nulls don't collide in a unique index, so every pre-existing
            // admin-seeded row stays valid.
            $table->unique('booking_id');
        });
    }

    public function down(): void
    {
        Schema::table('venue_reviews', function (Blueprint $table): void {
            $table->dropUnique(['booking_id']);
            $table->dropIndex(['event_id']);
            $table->dropColumn(['event_id', 'booking_id']);
        });
    }
};
