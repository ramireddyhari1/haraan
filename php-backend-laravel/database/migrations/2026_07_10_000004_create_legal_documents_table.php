<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Legal copy (Terms & Conditions, Privacy Policy) lives in the database so it can
 * be edited in /control without shipping an app release — the app fetches it from
 * /api/legal/{slug}.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_documents', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();   // 'terms' | 'privacy'
            $table->string('title');
            $table->longText('body');           // markdown-ish plain text
            $table->timestamps();
        });

        // Seed the two documents the app links to, so the rows always exist and an
        // admin only ever has to *edit*, never remember to create them.
        $now = now();
        DB::table('legal_documents')->insert([
            [
                'slug' => 'terms',
                'title' => 'Terms & Conditions',
                'body' => "These Terms & Conditions have not been published yet.\n\nAn administrator can edit this document in the Haraan control panel under Platform → Legal documents.",
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug' => 'privacy',
                'title' => 'Privacy Policy',
                'body' => "This Privacy Policy has not been published yet.\n\nAn administrator can edit this document in the Haraan control panel under Platform → Legal documents.",
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_documents');
    }
};
