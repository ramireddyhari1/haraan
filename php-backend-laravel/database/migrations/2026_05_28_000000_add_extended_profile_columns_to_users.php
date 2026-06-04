<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_guest')->default(false)->after('status');
            $table->string('district')->nullable()->after('is_guest');
            $table->string('state')->nullable()->after('district');
            $table->string('batting_style')->nullable()->after('playing_style');
            $table->string('bowling_style')->nullable()->after('batting_style');
            
            // Cached stats fields
            $table->integer('career_runs')->default(0)->after('bowling_style');
            $table->integer('career_balls')->default(0)->after('career_runs');
            $table->integer('career_matches')->default(0)->after('career_balls');
            $table->integer('career_wickets')->default(0)->after('career_matches');
            $table->integer('career_runs_conceded')->default(0)->after('career_wickets');
            $table->string('career_overs_bowled')->default('0.0')->after('career_runs_conceded');
            
            // Cached rankings fields
            $table->integer('rank_district')->nullable()->after('career_overs_bowled');
            $table->integer('rank_state')->nullable()->after('rank_district');
            $table->integer('rank_country')->nullable()->after('rank_state');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'is_guest',
                'district',
                'state',
                'batting_style',
                'bowling_style',
                'career_runs',
                'career_balls',
                'career_matches',
                'career_wickets',
                'career_runs_conceded',
                'career_overs_bowled',
                'rank_district',
                'rank_state',
                'rank_country'
            ]);
        });
    }
};
