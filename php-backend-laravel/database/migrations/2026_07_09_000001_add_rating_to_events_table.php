<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            // Host/admin-set aggregate rating shown on the event detail row.
            // Nullable so events with no rating simply render nothing (no fake star).
            $table->decimal('rating', 2, 1)->nullable()->after('views');
            $table->unsignedInteger('ratings_count')->default(0)->after('rating');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn(['rating', 'ratings_count']);
        });
    }
};
