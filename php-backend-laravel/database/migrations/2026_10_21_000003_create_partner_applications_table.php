<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Applications from venue owners, cafés, academies and event hosts who want to sell on
 * Haraan, sent from the public /become-a-partner form. Nobody becomes a partner by
 * submitting it: an admin reviews the row in /control → People → Partner applications,
 * creates the account, and can create the draft venue from it (venues stay
 * admin-created, see VenueResource).
 *
 * The venue columns mirror the /control venue form top to bottom (Basics → Location →
 * Courts & pricing → Hours & cancellation → Photos → Amenities → Good to know), so the
 * draft venue needs no retyping.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('partner_applications')) {
            return;
        }

        Schema::create('partner_applications', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->string('status', 20)->default('new')->index();

            // Basics.
            $table->string('business_type', 20);
            $table->string('business_name', 120);
            $table->json('sports')->nullable();
            $table->string('tagline', 120)->nullable();
            $table->text('about')->nullable();

            // Location.
            $table->string('area', 120)->nullable();
            $table->string('city', 80);
            $table->string('address', 255)->nullable();
            $table->string('map_link', 600)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // Courts & pricing.
            $table->json('courts_detail')->nullable();
            $table->unsignedSmallInteger('courts')->nullable();
            $table->unsignedInteger('base_price')->nullable();

            // Hours & cancellation.
            $table->json('hours_json')->nullable();
            $table->unsignedSmallInteger('slot_minutes')->nullable();
            $table->unsignedSmallInteger('cancel_free_hours')->nullable();
            $table->unsignedTinyInteger('cancel_refund_percent')->nullable();

            // Photos, amenities, good to know.
            $table->json('images')->nullable();
            $table->json('amenities')->nullable();
            $table->json('rules')->nullable();

            // How they run it today.
            $table->json('booking_channels')->nullable();
            $table->string('monthly_bookings', 20)->nullable();
            $table->json('goals')->nullable();

            // Who to call.
            $table->string('contact_name', 100);
            $table->string('contact_role', 20)->nullable();
            $table->string('phone', 20)->index();
            $table->string('email', 160)->nullable();
            $table->string('call_time', 20)->nullable();
            $table->string('gstin', 15)->nullable();
            $table->text('note')->nullable();

            // Review trail.
            $table->text('admin_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('contacted_at')->nullable();
            $table->foreignId('partner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('venue_id')->nullable()->constrained('venues')->nullOnDelete();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 120)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_applications');
    }
};
