<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who the ticket is for.
 *
 * The account is who *paid*; these are the details the order was placed with —
 * captured once per order at checkout and copied onto every row of it. Kept
 * separate from users.name/email/phone on purpose: editing your profile later
 * must not rewrite the contact details a past ticket was issued against.
 *
 * Distinct from guest_name/guest_phone, which are a partner's walk-in bookings
 * (someone with no account at all).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->string('attendee_name')->nullable()->after('guest_phone');
            $table->string('attendee_email')->nullable()->after('attendee_name');
            $table->string('attendee_phone', 32)->nullable()->after('attendee_email');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn(['attendee_name', 'attendee_email', 'attendee_phone']);
        });
    }
};
