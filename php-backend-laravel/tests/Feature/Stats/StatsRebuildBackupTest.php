<?php

declare(strict_types=1);

namespace Tests\Feature\Stats;

use App\Models\LiveMatch;
use App\Models\User;
use App\Services\DatabaseBackup;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * `stats:rebuild` rewrites every career, so it must never run without a verified backup.
 *
 * Migrated per test without RefreshDatabase: SQLite's VACUUM INTO (how the snapshot is taken)
 * cannot run inside the transaction RefreshDatabase wraps every test in, and production runs it
 * outside one too. The in-memory database is new for every test, so nothing leaks.
 */
final class StatsRebuildBackupTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate')->assertSuccessful();
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'haraan-backup-test-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . DIRECTORY_SEPARATOR . '*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function inflatedPlayer(): User
    {
        return User::create([
            'name' => 'Inflated', 'email' => 'inflated@haraan.test', 'password' => Hash::make('secret123'),
            'role' => 'user', 'status' => 'active', 'player_id' => 'HRNFAKE', 'is_guest' => false,
            'career_runs' => 999, 'career_matches' => 40, 'career_wickets' => 12,
        ]);
    }

    public function test_the_rebuild_takes_and_verifies_a_backup_before_touching_careers(): void
    {
        $u = $this->inflatedPlayer();
        LiveMatch::create([
            'title' => 'Done', 'home' => 'A', 'away' => 'B', 'home_score' => 1, 'away_score' => 0,
            'status' => 'Completed', 'sport' => 'football', 'user_id' => $u->id,
        ]);

        $this->artisan('stats:rebuild', ['--backup-dir' => $this->dir])
            ->expectsOutputToContain('Backup verified')
            ->assertSuccessful();

        $files = glob($this->dir . DIRECTORY_SEPARATOR . '*.sqlite');
        self::assertCount(1, $files, 'exactly one snapshot');
        $manifest = json_decode((string) file_get_contents($files[0] . '.json'), true);
        self::assertSame('ok', $manifest['integrity_check']);
        self::assertSame(1, $manifest['row_counts']['live_matches']);
        self::assertSame(hash_file('sha256', $files[0]), $manifest['sha256']);

        // The snapshot holds the numbers as they were BEFORE the rebuild.
        $copy = new \PDO('sqlite:' . $files[0]);
        self::assertSame(999, (int) $copy->query("SELECT career_runs FROM users WHERE player_id = 'HRNFAKE'")->fetchColumn());

        // …and the live database now holds the real ones.
        self::assertSame(0, (int) $u->fresh()->career_runs);
        self::assertSame(0, (int) $u->fresh()->career_matches);
    }

    public function test_no_backup_means_no_rebuild(): void
    {
        $u = $this->inflatedPlayer();
        // A FILE where the backup directory should be: the snapshot cannot be written.
        @mkdir(dirname($this->dir), 0777, true);
        file_put_contents($this->dir, 'not a directory');

        try {
            $this->artisan('stats:rebuild', ['--backup-dir' => $this->dir])
                ->expectsOutputToContain('nothing was rebuilt')
                ->assertFailed();
        } finally {
            @unlink($this->dir);
        }

        self::assertSame(999, (int) $u->fresh()->career_runs, 'careers untouched');
    }

    public function test_a_dry_run_writes_nothing_and_takes_no_backup(): void
    {
        $u = $this->inflatedPlayer();

        $this->artisan('stats:rebuild', ['--dry-run' => true, '--backup-dir' => $this->dir])
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();

        self::assertSame(999, (int) $u->fresh()->career_runs);
        self::assertFalse(is_dir($this->dir) && glob($this->dir . '/*') !== [], 'no snapshot for a dry run');
    }

    public function test_an_operator_supplied_backup_must_be_fresh_and_sound(): void
    {
        $u = $this->inflatedPlayer();
        @mkdir($this->dir, 0777, true);

        $stale = $this->dir . DIRECTORY_SEPARATOR . 'old.sql';
        file_put_contents($stale, 'dump');
        touch($stale, time() - 3 * 86400);
        $this->artisan('stats:rebuild', ['--backup-file' => $stale])->assertFailed();

        $empty = $this->dir . DIRECTORY_SEPARATOR . 'empty.sql';
        touch($empty);
        $this->artisan('stats:rebuild', ['--backup-file' => $empty])->assertFailed();

        $corrupt = $this->dir . DIRECTORY_SEPARATOR . 'corrupt.sqlite';
        file_put_contents($corrupt, 'SQLite format 3' . "\0" . str_repeat("\xFF", 4096));
        $this->artisan('stats:rebuild', ['--backup-file' => $corrupt])->assertFailed();

        self::assertSame(999, (int) $u->fresh()->career_runs, 'every refusal left careers alone');

        // A real, fresh snapshot is accepted.
        $good = app(DatabaseBackup::class)->snapshot($this->dir, 'manual');
        $this->artisan('stats:rebuild', ['--backup-file' => $good['path']])->assertSuccessful();
        self::assertSame(0, (int) $u->fresh()->career_runs);
    }

    public function test_the_migration_keeps_the_old_rows_in_a_legacy_table(): void
    {
        self::assertTrue(Schema::hasTable('player_match_stats_legacy'));
    }
}
