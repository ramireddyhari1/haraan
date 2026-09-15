<?php

namespace App\Console\Commands;

use App\Models\LiveMatch;
use App\Models\PlayerCareerBatting;
use App\Models\PlayerMatchStat;
use App\Models\User;
use App\Services\CareerBattingService;
use App\Services\DatabaseBackup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Re-derive every finished match's per-player stats from its real log, then every career
 * and the rankings.
 *
 * It rewrites `player_match_stats`, the career tables and every account's `career_*`
 * columns, so it never runs without a verified backup taken first:
 *
 *   php artisan stats:rebuild --dry-run                     what would change, no writes
 *   php artisan stats:rebuild                               SQLite: snapshot, verify, rebuild
 *   php artisan stats:rebuild --backup-file=/root/dump.sql  other drivers: verify your dump, rebuild
 *
 * If the backup can't be taken or doesn't verify, nothing is rebuilt.
 */
class RebuildCareerBatting extends Command
{
    protected $signature = 'stats:rebuild
        {--dry-run : Report what a rebuild would touch without writing anything}
        {--backup-dir= : Where the automatic SQLite snapshot goes (default storage/app/backups)}
        {--backup-file= : A backup you already took (required on non-SQLite databases)}
        {--top=10 : How many batters to preview afterwards}';

    protected $aliases = ['career:rebuild'];

    protected $description = 'Rebuild per-match player stats and careers from the real match logs (backs up first)';

    public function handle(DatabaseBackup $backups): int
    {
        $finished = LiveMatch::query()->finished()->count();
        $inflated = User::query()->whereNotNull('player_id')
            ->where(fn ($q) => $q->where('career_runs', '>', 0)->orWhere('career_wickets', '>', 0)->orWhere('career_matches', '>', 0))
            ->count();

        $this->table(['', 'Now'], [
            ['Finished matches to replay', $finished],
            ['Player-match rows (will be replaced)', PlayerMatchStat::query()->count()],
            ['Accounts with career figures (will be recomputed)', $inflated],
        ]);

        if ($this->option('dry-run')) {
            $this->warn('Dry run — nothing was backed up or changed.');

            return self::SUCCESS;
        }

        // Backup first, or nothing.
        try {
            if (filled($this->option('backup-file'))) {
                $b = $backups->verifyExternal((string) $this->option('backup-file'));
                $this->info("Using your backup {$b['path']} ({$b['bytes']} bytes, sha256 {$b['sha256']}).");
            } else {
                $dir = (string) ($this->option('backup-dir') ?: storage_path('app/backups'));
                $this->info('Taking a verified snapshot before rebuilding…');
                $b = $backups->snapshot($dir, 'before-stats-rebuild');
                $this->info("Backup verified: {$b['path']} ({$b['bytes']} bytes, integrity ok, row counts match).");
                $this->line("Manifest: {$b['manifest']}");
            }
        } catch (RuntimeException $e) {
            $this->error('No verified backup, so nothing was rebuilt. ' . $e->getMessage());
            Log::warning('stats:rebuild refused — backup failed', ['error' => $e->getMessage()]);

            return self::FAILURE;
        }

        Log::info('stats:rebuild starting', ['backup' => $b['path'], 'sha256' => $b['sha256'], 'finished_matches' => $finished]);

        $this->info("Replaying {$finished} finished match(es)…");
        $bar = $this->output->createProgressBar($finished);
        $count = CareerBattingService::rebuildAll(fn () => $bar->advance());
        $bar->finish();
        $this->newLine();
        $this->info("Done. Careers refreshed for {$count} player(s). To undo, restore {$b['path']}.");
        Log::info('stats:rebuild finished', ['players' => $count, 'backup' => $b['path']]);

        $rows = PlayerCareerBatting::orderByDesc('runs')->limit((int) $this->option('top'))->get();
        if ($rows->isNotEmpty()) {
            $this->table(
                ['Player', 'Inns', 'Runs', 'Balls', 'HS', 'Avg', 'SR'],
                $rows->map(fn ($r) => [
                    $r->player_name, $r->innings, $r->runs, $r->balls, $r->high_score,
                    $r->average() ?? '—', $r->strikeRate() ?? '—',
                ])->all()
            );
        }

        return self::SUCCESS;
    }
}
