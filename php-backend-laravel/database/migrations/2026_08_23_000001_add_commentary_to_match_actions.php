<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a written commentary line lives.
 *
 * Commentary is otherwise DERIVED: buildCommentary replays match_actions on every
 * detail request and formats "$bowler to $striker, $outcome" fresh each time. That is
 * fine for a template and hopeless for generated prose — the same delivery would read
 * differently on every refresh, and each render would cost a round trip to a model.
 *
 * So the written line is stored ON the ball it describes, once, and every later read
 * serves the same words. A null here is not an error: it means this ball has no written
 * line (no API key, the call failed, or the row predates the feature) and the template
 * speaks for it, exactly as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('match_actions', function (Blueprint $table): void {
            $table->text('commentary')->nullable()->after('payload');
        });
    }

    public function down(): void
    {
        Schema::table('match_actions', function (Blueprint $table): void {
            $table->dropColumn('commentary');
        });
    }
};
