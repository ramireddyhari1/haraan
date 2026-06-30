<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venues', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('category')->default('Badminton'); // Badminton / Cricket / Football / Basketball
            $table->string('location');                        // area, e.g. "Bandra"
            $table->string('distance')->nullable();            // e.g. "3.1 km"
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('price')->default(0);       // per hour, ₹
            $table->string('rating')->default('4.5');
            $table->unsignedInteger('ratings_count')->default(0);
            $table->unsignedInteger('reviews_count')->default(0);
            $table->string('tagline')->nullable();              // e.g. "6 wooden indoor courts"
            $table->text('about')->nullable();
            $table->json('images')->nullable();                 // gallery urls
            $table->json('amenities')->nullable();              // ["Floodlights","Parking",...]
            // The flag you asked for: info-only listing vs a bookable venue.
            $table->boolean('is_bookable')->default(true);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);     // "Top rated" badge / Popular Venues
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('partner_id')->nullable();        // owning partner (existing users table)
            $table->timestamps();
        });

        Schema::create('venue_slots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->string('day')->default('Today');            // Today / Tomorrow / Sat ...
            $table->string('time');                             // "06:00 AM"
            $table->boolean('is_available')->default(true);
            $table->boolean('filling_fast')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('venue_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedTinyInteger('rating')->default(5);
            $table->text('text');
            $table->string('avatar')->nullable();
            $table->string('ago')->nullable();                  // "2 weeks ago"
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_reviews');
        Schema::dropIfExists('venue_slots');
        Schema::dropIfExists('venues');
    }
};
