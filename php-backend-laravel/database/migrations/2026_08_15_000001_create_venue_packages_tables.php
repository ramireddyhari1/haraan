<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Venue memberships / session packages — "10 sessions for ₹4,000", "monthly pass".
 *
 * Three tables, because three different things are being recorded:
 *
 *  - `venue_packages`     what a venue SELLS (the offer, owned by the partner)
 *  - `customer_packages`  what a customer BOUGHT (their remaining balance)
 *  - `package_redemptions` each SESSION spent, one row per booking
 *
 * The redemption ledger is separate rather than a counter on the purchase for the
 * same reason booking_payments exists: a number you can only ever decrement can
 * drift and can't be audited, whereas rows can be counted, reversed with the
 * booking they belong to, and explained to a customer who disputes their balance.
 *
 * Customers are keyed on PHONE, matching the partner CRM — a walk-in has no
 * account, and the desk knows people by their number.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_packages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('partner_id')->constrained('users')->cascadeOnDelete();
            // Null = usable at any of this partner's venues.
            $table->foreignId('venue_id')->nullable()->constrained('venues')->nullOnDelete();
            $table->string('name', 120);
            $table->unsignedInteger('price');
            $table->unsignedInteger('sessions');
            // Null = never expires.
            $table->unsignedSmallInteger('validity_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['partner_id', 'is_active']);
        });

        Schema::create('customer_packages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_package_id')->constrained('venue_packages')->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained('users')->cascadeOnDelete();
            $table->string('customer_phone', 15);
            $table->string('customer_name', 120)->nullable();
            $table->unsignedInteger('sessions_total');
            $table->unsignedInteger('amount_paid')->default(0);
            $table->string('payment_method', 20)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            // The desk's hot query: "what does this number have left with us?"
            $table->index(['partner_id', 'customer_phone']);
        });

        Schema::create('package_redemptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_package_id')->constrained('customer_packages')->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->timestamps();

            // One session may only be spent once per booking, so a retried request
            // (or a double-tap at the desk) can't silently drain a customer's pass.
            $table->unique(['customer_package_id', 'booking_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_redemptions');
        Schema::dropIfExists('customer_packages');
        Schema::dropIfExists('venue_packages');
    }
};
