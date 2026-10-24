<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\LiveMatch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Local-only: friends and contacts on ActionBoard cards ("Ravi and Kiran are playing").
 *
 *   php artisan db:seed --class=LocalFriendsPlayingSeeder
 *
 * Builds on LocalActionBoardSeeder's matches (runs it first when they are missing) and
 * APPENDS four real accounts to their squads — existing squad entries are never edited,
 * because the ball log references them by id.
 *
 * viewer : player@haraan.test / player1234  (from LocalActionBoardSeeder)
 *   follows Ravi + Kiran, is followed by Suresh; Anil is linked only by phone, so he shows
 *   up once the device's contacts hold +91 90000 10004.
 *
 * Idempotent: re-running re-appends nothing that is already there.
 */
class LocalFriendsPlayingSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->error('LocalFriendsPlayingSeeder only runs locally.');

            return;
        }

        $scorer = User::where('email', 'scorer@haraan.test')->first();
        if ($scorer === null || ! LiveMatch::where('user_id', $scorer->id)->exists()) {
            $this->call(LocalActionBoardSeeder::class);
            $scorer = User::where('email', 'scorer@haraan.test')->firstOrFail();
        }
        $viewer = User::where('email', 'player@haraan.test')->firstOrFail();

        $ravi = $this->player('ravi.demo@haraan.test', 'Ravi Teja', 'HRNDEMO1', '+91 90000 10001');
        $kiran = $this->player('kiran.demo@haraan.test', 'Kiran Rao', 'HRNDEMO2', '+91 90000 10002');
        $suresh = $this->player('suresh.demo@haraan.test', 'Suresh Babu', 'HRNDEMO3', '+91 90000 10003');
        $anil = $this->player('anil.demo@haraan.test', 'Anil Varma', 'HRNDEMO4', '+91 90000 10004');

        $viewer->follow($ravi);
        $viewer->follow($kiran);
        $suresh->follow($viewer);

        $matches = LiveMatch::where('user_id', $scorer->id)->orderBy('id')->get();
        $live = $matches->filter(fn (LiveMatch $m) => strtolower((string) $m->status) === 'live')->values();
        $done = $matches->filter(fn (LiveMatch $m) => $m->isFinished())->values();

        // Three friends on one card → "Ravi, Kiran +1 are playing".
        if ($m = $live->get(0)) { $this->add($m, 'home', [$ravi, $kiran]); $this->add($m, 'away', [$suresh]); }
        // A contact only → named by what the phone book calls him.
        if ($m = $live->get(1)) { $this->add($m, 'home', [$anil]); }
        // One friend → "Kiran is playing".
        if ($m = $live->get(2)) { $this->add($m, 'away', [$kiran]); }
        // Finished → "Suresh played".
        if ($m = $done->get(0)) { $this->add($m, 'home', [$suresh]); }

        $this->command?->info('Friends playing: '.$live->count().' live, '.$done->count().' finished demo matches linked.');
    }

    private function player(string $email, string $name, string $playerId, string $phone): User
    {
        $u = User::firstOrNew(['email' => $email]);
        $u->forceFill([
            'name' => $name, 'status' => 'active', 'role' => 'user', 'password' => Hash::make('player1234'),
            'player_id' => $playerId, 'username' => strtolower(strtok($name, ' ')).'_demo',
            'phone' => $phone, 'is_guest' => false, 'district' => 'NTR', 'state' => 'Andhra Pradesh',
        ])->save();

        return $u->fresh();
    }

    /** Appends players to a side's squad, skipping anyone already on it. */
    private function add(LiveMatch $m, string $side, array $players): void
    {
        $col = $side === 'home' ? 'home_squad' : 'away_squad';
        $squad = (array) ($m->{$col} ?? []);
        $have = array_column($squad, 'id');
        foreach ($players as $p) {
            if (! in_array($p->player_id, $have, true)) {
                $squad[] = ['id' => $p->player_id, 'name' => $p->name];
            }
        }
        $m->{$col} = $squad;
        $m->save();
    }
}
