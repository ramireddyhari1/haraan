<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            // Where the booking came from: 'online' (app/web customer) or
            // 'offline' (walk-in created by the partner at the desk).
            $table->string('channel')->default('online')->after('booking_type');
            // Walk-in customers have no app account, so capture their contact
            // details on the booking itself. user_id then holds the partner who
            // created it (the desk), keeping the FK non-null for SQLite safety.
            $table->string('guest_name')->nullable()->after('channel');
            $table->string('guest_phone')->nullable()->after('guest_name');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn(['channel', 'guest_name', 'guest_phone']);
        });
    }
};
