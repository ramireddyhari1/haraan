<?php

namespace App\Console\Commands;

use App\Models\PlayerCareerBatting;
use App\Services\CareerBattingService;
use Illuminate\Console\Command;

/**
 * Rebuild the real career-batting table from the ball-by-ball log. Run after deploying,
 * or any time you want to re-derive career totals from scratch.
 *   php artisan career:rebuild
 */
class RebuildCareerBatting extends Command
{
    protected $signature = 'career:rebuild {--top=10 : How many players to preview}';

    protected $description = 'Aggregate real career batting from match_actions into player_career_batting';

    public function handle(): int
    {
        $this->info('Replaying completed matches…');
        $count = CareerBattingService::rebuildAll();
        $this->info("Done. Career lines for {$count} player(s).");

        $top = (int) $this->option('top');
        $rows = PlayerCareerBatting::orderByDesc('runs')->limit($top)->get();
        if ($rows->isEmpty()) {
            $this->warn('No career rows produced — is there any completed match with registered (non-guest) players?');
            return self::SUCCESS;
        }

        $this->table(
            ['Player', 'Inns', 'Runs', 'Balls', 'HS', 'Avg', 'SR'],
            $rows->map(fn ($r) => [
                $r->player_name,
                $r->innings,
                $r->runs,
                $r->balls,
                $r->high_score,
                $r->average() ?? '—',
                $r->strikeRate() ?? '—',
            ])->all()
        );

        return self::SUCCESS;
    }
}
