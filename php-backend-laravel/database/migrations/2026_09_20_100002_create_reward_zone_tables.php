<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Location-based targeted rewards, phase 1 (docs/location-rewards-design.md).
 *
 * Purely additive. A program with no zones targets everywhere, which is exactly what every
 * existing program does today — so this migration changes no live behaviour, and the feature
 * stays dark until an admin turns on `rewards.geo_enabled`.
 *
 *  - reward_zones: named, reusable targeting geometry drawn in /control. A circle (point +
 *    radius) or an administrative area (locality / district / state names). A circle may be
 *    ANCHORED to a venue, ground or event, in which case it tracks that record's own
 *    coordinates instead of a copy that silently goes stale.
 *  - reward_program_zones / reward_rule_zones: which zones a program (the commercial envelope)
 *    or a single rule (a tier inside it) includes or excludes. Empty on a rule = inherit the
 *    program's.
 *  - reward_grants.zone_id + value['geo']: the snapshot of WHY this player got this, kept on
 *    the grant so the answer survives the zone later being moved, resized or deleted.
 *  - reward_ground_days: the per-ground velocity guard. Without it one group playing all
 *    weekend at one turf can drain a sponsor's whole city budget.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reward_zones', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('kind', 12)->default('circle');          // circle|admin_area

            // For a circle. `anchor_type` point = use the lat/lng below; venue|ground|event =
            // read the coordinates from that record every time, so moving the venue moves the zone.
            $table->string('anchor_type', 10)->default('point');    // point|venue|ground|event
            $table->unsignedBigInteger('anchor_id')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('radius_m')->nullable();

            // For an administrative area. Matched by the same normalised name comparison the
            // ActionBoard feed already uses, against a match's locality/district/state.
            $table->string('locality', 120)->nullable();
            $table->string('district', 120)->nullable();
            $table->string('state', 120)->nullable();

            $table->string('place_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->index(['is_active', 'kind']);
        });

        Schema::create('reward_program_zones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('program_id')->constrained('reward_programs')->cascadeOnDelete();
            $table->foreignId('zone_id')->constrained('reward_zones')->cascadeOnDelete();
            $table->string('mode', 8)->default('include');          // include|exclude
            $table->unique(['program_id', 'zone_id']);
        });

        Schema::create('reward_rule_zones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rule_id')->constrained('reward_rules')->cascadeOnDelete();
            $table->foreignId('zone_id')->constrained('reward_zones')->cascadeOnDelete();
            $table->string('mode', 8)->default('include');
            $table->unique(['rule_id', 'zone_id']);
        });

        Schema::create('reward_ground_days', function (Blueprint $table): void {
            $table->id();
            // match_grounds.id when the ground is known, else a coarse rounded-coordinate key.
            // A string, not an FK: the point is to count activity at a place, and most gully
            // grounds have no canonical row.
            $table->string('ground_key', 64);
            $table->date('day');
            $table->unsignedInteger('grants')->default(0);
            $table->unique(['ground_key', 'day']);
        });

        Schema::table('reward_grants', function (Blueprint $table): void {
            $table->unsignedBigInteger('zone_id')->nullable()->after('rule_id');
            $table->index(['zone_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('reward_grants', function (Blueprint $table): void {
            $table->dropIndex(['zone_id', 'created_at']);
            $table->dropColumn('zone_id');
        });

        foreach (['reward_ground_days', 'reward_rule_zones', 'reward_program_zones', 'reward_zones'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
