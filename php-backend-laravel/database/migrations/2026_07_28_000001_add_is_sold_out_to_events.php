<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manual "Sold out" override for an event. When a host flips this on from the
 * analytics page the event reads as sold out everywhere (web + app + booking
 * API) regardless of the real slot count — used to stop sales at the door or
 * when inventory is tracked off-platform.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->boolean('is_sold_out')->default(false)->after('available_slots');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn('is_sold_out');
        });
    }
};
