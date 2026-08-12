<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Give events real coordinates (they previously had only free-text venue/area/
     * city + a hand-pasted map_link). Set from Google Places Autocomplete in the
     * create/edit form; powers a precise "Directions" link + an embedded map on the
     * event page, and makes events distance/radius-filterable like venues already
     * are. Same decimal(10,7) shape as the venues table for consistency.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            if (! Schema::hasColumn('events', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('map_link');
            }
            if (! Schema::hasColumn('events', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};
