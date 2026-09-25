<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Performance and Scale optimization for User 360 and core user management.
 *
 * Adds:
 *  1. Materialized/denormalized KPI columns on `users` (lifetime_spend, bookings_count, matches_played_count)
 *     for single-row O(1) reads in User 360 profile views and high-performance VIP sorting.
 *  2. Search and filter indexes on `users` (phone, name, is_verified, lifetime_spend, bookings_count).
 *  3. Fast relationship composite indexes on `bookings` ([user_id, status], [user_id, created_at]).
 *  4. Activity indexes on `player_match_stats` ([user_id, played]) and `support_threads` ([user_id, status]).
 *  5. Data backfill for all existing users.
 */
return new class extends Migration
{
    private const PAID_STATUSES = ['confirmed', 'paid', 'completed', 'checked_in'];

    public function up(): void
    {
        // 1. Add denormalized KPI columns to `users`
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'lifetime_spend')) {
                $table->decimal('lifetime_spend', 10, 2)->default(0.00)->after('trust_score');
            }
            if (! Schema::hasColumn('users', 'bookings_count')) {
                $table->unsignedInteger('bookings_count')->default(0)->after('lifetime_spend');
            }
            if (! Schema::hasColumn('users', 'matches_played_count')) {
                $table->unsignedInteger('matches_played_count')->default(0)->after('bookings_count');
            }
            if (! Schema::hasColumn('users', 'last_kpi_calculated_at')) {
                $table->timestamp('last_kpi_calculated_at')->nullable()->after('matches_played_count');
            }
        });

        // 2. Add performance indexes on `users`
        Schema::table('users', function (Blueprint $table): void {
            $table->index('phone', 'users_phone_idx');
            $table->index('name', 'users_name_idx');
            $table->index('is_verified', 'users_is_verified_idx');
            $table->index('lifetime_spend', 'users_lifetime_spend_idx');
            $table->index('bookings_count', 'users_bookings_count_idx');
        });

        // 3. Add composite indexes on `bookings`
        if (Schema::hasTable('bookings')) {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->index(['user_id', 'status'], 'bookings_user_id_status_idx');
                $table->index(['user_id', 'created_at'], 'bookings_user_id_created_at_idx');
            });
        }

        // 4. Add composite index on `player_match_stats`
        if (Schema::hasTable('player_match_stats')) {
            Schema::table('player_match_stats', function (Blueprint $table): void {
                $table->index(['user_id', 'played'], 'player_match_stats_user_id_played_idx');
            });
        }

        // 5. Add composite index on `support_threads`
        if (Schema::hasTable('support_threads')) {
            Schema::table('support_threads', function (Blueprint $table): void {
                $table->index(['user_id', 'status'], 'support_threads_user_id_status_idx');
            });
        }

        // 6. Backfill existing users' KPI counters
        $now = now();
        DB::table('users')->orderBy('id')->chunk(200, function ($users) use ($now): void {
            foreach ($users as $u) {
                $lifetimeSpend = 0.0;
                $bookingsCount = 0;
                $matchesPlayed = 0;

                if (Schema::hasTable('bookings')) {
                    $lifetimeSpend = (float) DB::table('bookings')
                        ->where('user_id', $u->id)
                        ->whereIn(DB::raw('lower(status)'), self::PAID_STATUSES)
                        ->sum('total_amount');

                    $bookingsCount = (int) DB::table('bookings')
                        ->where('user_id', $u->id)
                        ->count();
                }

                if (Schema::hasTable('player_match_stats')) {
                    $matchesPlayed = (int) DB::table('player_match_stats')
                        ->where('user_id', $u->id)
                        ->where('played', true)
                        ->count();
                }

                DB::table('users')
                    ->where('id', $u->id)
                    ->update([
                        'lifetime_spend' => $lifetimeSpend,
                        'bookings_count' => $bookingsCount,
                        'matches_played_count' => $matchesPlayed,
                        'last_kpi_calculated_at' => $now,
                    ]);
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('support_threads')) {
            Schema::table('support_threads', function (Blueprint $table): void {
                $table->dropIndex('support_threads_user_id_status_idx');
            });
        }

        if (Schema::hasTable('player_match_stats')) {
            Schema::table('player_match_stats', function (Blueprint $table): void {
                $table->dropIndex('player_match_stats_user_id_played_idx');
            });
        }

        if (Schema::hasTable('bookings')) {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->dropIndex('bookings_user_id_status_idx');
                $table->dropIndex('bookings_user_id_created_at_idx');
            });
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_phone_idx');
            $table->dropIndex('users_name_idx');
            $table->dropIndex('users_is_verified_idx');
            $table->dropIndex('users_lifetime_spend_idx');
            $table->dropIndex('users_bookings_count_idx');

            $table->dropColumn([
                'lifetime_spend',
                'bookings_count',
                'matches_played_count',
                'last_kpi_calculated_at',
            ]);
        });
    }
};
