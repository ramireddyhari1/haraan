<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Undo becomes recoverable, and a retried tap becomes harmless.
 *
 * On 2026-09-15 a double-delivered "undo" tap on a live kabaddi board hard-deleted a real
 * raid point (match 49, event 573). The only way back was a SQL backup. Two changes make
 * that class of incident survivable:
 *
 *  - `undone_at` / `undone_by`: an undo HIDES the event (every read goes through the
 *    MatchEvent "active" scope) instead of deleting it, so a wrong undo is one restore away
 *    and the audit trail shows who pressed it and when.
 *  - `client_event_id`: the scorer stamps each tap with its own id. A retry of the same tap
 *    (flaky network, double delivery) finds the row it already created instead of scoring
 *    the point twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('match_events', function (Blueprint $table): void {
            $table->timestamp('undone_at')->nullable()->after('recorded_by');
            $table->foreignId('undone_by')->nullable()->after('undone_at')->constrained('users')->nullOnDelete();
            $table->string('client_event_id', 64)->nullable()->after('undone_by');

            $table->index(['live_match_id', 'undone_at']);
            $table->unique(['live_match_id', 'client_event_id']);
        });
    }

    public function down(): void
    {
        Schema::table('match_events', function (Blueprint $table): void {
            $table->dropUnique(['live_match_id', 'client_event_id']);
            $table->dropIndex(['live_match_id', 'undone_at']);
            $table->dropConstrainedForeignId('undone_by');
            $table->dropColumn(['undone_at', 'client_event_id']);
        });
    }
};
