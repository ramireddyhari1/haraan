<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-event FAQs — a list of {question, answer} rows authored in the event form's
 * Extras step and rendered last on the event detail page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            if (! Schema::hasColumn('events', 'faqs')) {
                $table->json('faqs')->nullable()->after('lineup');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            if (Schema::hasColumn('events', 'faqs')) {
                $table->dropColumn('faqs');
            }
        });
    }
};
