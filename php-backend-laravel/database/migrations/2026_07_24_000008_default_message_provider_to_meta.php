<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Twilio is gone; WhatsApp now goes through Meta's Cloud API. The sending code
 * always writes 'meta' explicitly, but the column default still said 'twilio' —
 * which would mislabel any row inserted by a future path that forgets to set it.
 *
 * The earlier migration is left as it was: it records what actually ran at the
 * time, and rewriting applied history to look tidy is how you lose the ability to
 * trust it.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('message_log', function (Blueprint $table) {
            $table->string('provider')->default('meta')->change();
        });
    }

    public function down(): void
    {
        Schema::table('message_log', function (Blueprint $table) {
            $table->string('provider')->default('twilio')->change();
        });
    }
};
