<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table): void {
            // Bookable units within the venue — "Court 1", "Lane A", "Pitch 2".
            // Shown as the Court dropdown in the app's booking form.
            $table->json('courts')->nullable()->after('amenities');
        });
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table): void {
            $table->dropColumn('courts');
        });
    }
};
