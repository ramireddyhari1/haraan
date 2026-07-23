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
            // Operating hours shown under the venue name, e.g. "6 AM – 9 AM & 8 PM – 11 PM".
            $table->string('hours')->nullable()->after('tagline');
            // House rules / policies rendered as a checklist in the detail page.
            $table->json('rules')->nullable()->after('about');
        });
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table): void {
            $table->dropColumn(['hours', 'rules']);
        });
    }
};
