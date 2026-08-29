<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The written read on a career, cached against the career it describes.
 *
 * `fingerprint` is a hash of the exact figures the lines were written from. A profile
 * request regenerates only when that hash no longer matches — which is precisely when
 * the analysis has become wrong, and never merely because time passed. A timer would
 * either burn quota on players who have not batted since, or leave a paragraph
 * describing a career two matches out of date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_career_analysis', function (Blueprint $table): void {
            $table->id();
            $table->string('player_id')->unique();
            $table->string('fingerprint', 32);
            // A JSON list of short observations, at most three.
            $table->text('lines');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_career_analysis');
    }
};
