<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Public host (organiser) profiles — the brand page attendees see at
 * /host/{slug}. One row per partner owner; kept out of the users table so the
 * users schema stays lean and only partners who opt in have a profile. Hidden
 * until is_public is on and the required fields (name + about) are filled.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('host_profiles', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('slug')->unique();
            $table->string('display_name');
            $table->string('tagline')->nullable();
            $table->text('about')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('cover_path')->nullable();
            $table->string('website')->nullable();
            $table->json('socials')->nullable(); // {instagram, x, youtube, facebook}
            $table->string('city')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamp('verified_at')->nullable(); // admin-granted ✓ (P3)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('host_profiles');
    }
};
