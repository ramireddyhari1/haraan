<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Normalize existing event `status` values to lowercase.
 *
 * The Filament admin form and the events API historically stored uppercase
 * statuses ('PUBLISHED' / 'DRAFT'), while every public listing queries the
 * lowercase 'published'. That mismatch made admin-published events invisible
 * on the public site. New writes are canonicalized by the Event model's
 * status mutator; this backfills rows written before that fix.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('events')->update(['status' => DB::raw('lower(status)')]);
    }

    public function down(): void
    {
        // Non-reversible: original per-row casing is not recoverable, and the
        // lowercase form is the intended canonical value.
    }
};
