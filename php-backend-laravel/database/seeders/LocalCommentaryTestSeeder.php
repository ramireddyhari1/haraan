<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\LiveMatch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * LOCAL ONLY. A Hero commentator with a live cricket match, for testing live commentary on a
 * phone against the local backend:
 *
 *   php artisan db:seed --class=LocalCommentaryTestSeeder
 *
 * Sign in on the app with commentator@haraan.test / Commentate@123 (local database only).
 * Refuses to run anywhere but APP_ENV=local.
 */
class LocalCommentaryTestSeeder extends Seeder
{
    public const EMAIL = 'commentator@haraan.test';

    public const PASSWORD = 'Commentate@123';

    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->error('LocalCommentaryTestSeeder only runs with APP_ENV=local.');

            return;
        }

        $user = User::query()->firstOrNew(['email' => self::EMAIL]);
        $user->forceFill([
            'name' => 'Commentary Tester',
            'password' => bcrypt(self::PASSWORD),
            'role' => 'USER',
            'status' => 'active',
            'state' => 'Andhra Pradesh',
            'district' => 'YSR Kadapa',
            'primary_sport' => 'Cricket',
            'sport_attributes' => ['role' => 'Batter', 'batting' => 'Right', 'bowling' => 'Right arm medium'],
            'trust_score' => 100,
        ])->save();

        // Hero's commentary without a payment: a per-member override, the same lever /control uses.
        DB::table('member_entitlement_overrides')->updateOrInsert(
            ['user_id' => $user->id, 'feature_key' => 'matches.live_commentary'],
            ['enabled' => true, 'limit_value' => null, 'expires_at' => null, 'reason' => 'Local commentary test', 'created_at' => now(), 'updated_at' => now()],
        );

        $squad = fn (string $p, array $names) => array_map(fn ($n, $i) => ['id' => $p.($i + 1), 'name' => $n], $names, array_keys($names));
        $match = LiveMatch::query()->firstOrNew(['title' => 'Commentary Kings vs Mic Check XI', 'user_id' => $user->id]);
        $match->forceFill([
            'home' => 'CKG', 'away' => 'MCX',
            'home_full' => 'Commentary Kings', 'away_full' => 'Mic Check XI',
            'sport' => 'cricket', 'status' => 'live', 'overs' => '0.0',
            'visibility' => 'FEATURED', 'is_private' => false,
            'state' => 'Andhra Pradesh', 'district' => 'YSR Kadapa',
            'home_squad' => $squad('A', ['Ravi', 'Teja', 'Sai', 'Kiran', 'Vamsi', 'Arjun']),
            'away_squad' => $squad('B', ['Rahul', 'Ajay', 'Suresh', 'Gopi', 'Rakesh', 'Venkat']),
            'user_id' => $user->id,
        ])->save();

        $this->command?->info("Commentator user {$user->id}, match {$match->id}. Sign in with ".self::EMAIL);
    }
}
