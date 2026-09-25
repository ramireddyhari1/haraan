<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The audit log answers "what happened to THIS record?" — a plan price, an event's fees, a
 * user's role — so each entry can name the record it touched. Nullable: page-level actions
 * (a Platform rules save) have no single subject.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_actions', function (Blueprint $table): void {
            $table->string('subject_type', 120)->nullable()->after('action');
            $table->unsignedBigInteger('subject_id')->nullable()->after('subject_type');
            $table->index(['subject_type', 'subject_id']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::table('admin_actions', function (Blueprint $table): void {
            $table->dropIndex(['subject_type', 'subject_id']);
            $table->dropIndex(['action']);
            $table->dropColumn(['subject_type', 'subject_id']);
        });
    }
};
