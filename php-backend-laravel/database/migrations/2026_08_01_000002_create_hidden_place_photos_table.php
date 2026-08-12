<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Venues whose Google photos we've been asked to stop showing.
     *
     * Keyed by place_id rather than by event on purpose: the objection is always
     * to a photograph of a PLACE ("that's the old fit-out", "that's someone's
     * lunch"), so silencing it once has to cover every event held there —
     * including next month's, which doesn't exist yet. An events column would
     * make each new listing re-surface the same complained-about photo.
     *
     * A row here means hidden. Absence means shown, so nothing needs backfilling
     * and the default stays "show".
     */
    public function up(): void
    {
        Schema::create('hidden_place_photos', function (Blueprint $table): void {
            $table->id();
            $table->string('place_id', 255)->unique();
            // Who switched it off and why — a later admin needs to know whether
            // this was a host's complaint or a passing tidy-up before undoing it.
            $table->foreignId('hidden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hidden_place_photos');
    }
};
