<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\LiveMatch;
use App\Models\MatchGround;
use App\Services\GroundInsightsService;
use App\Services\GroundResolver;
use Illuminate\Console\Command;

/**
 * Resolve every existing match to a canonical ground, then compute each ground's read.
 *
 * Safe to run repeatedly: resolution is idempotent, and the stats are recomputed from
 * the ball log rather than accumulated.
 */
final class BackfillGrounds extends Command
{
    protected $signature = 'grounds:backfill {--fresh : Clear ground assignments first}';

    protected $description = 'Resolve match venues to canonical grounds and refresh their statistics';

    public function handle(GroundResolver $resolver, GroundInsightsService $insights): int
    {
        if ($this->option('fresh')) {
            LiveMatch::query()->update(['ground_id' => null]);
            MatchGround::query()->delete();
            $this->warn('Cleared existing ground assignments.');
        }

        $matches = LiveMatch::query()
            ->whereNotNull('venue')
            ->where('venue', '!=', '')
            ->orderBy('id')
            ->get();

        $this->info("Resolving {$matches->count()} match venues…");
        $resolved = 0;
        foreach ($matches as $match) {
            if ($resolver->resolve($match) !== null) {
                $resolved++;
            }
        }

        $grounds = MatchGround::all();
        foreach ($grounds as $ground) {
            $insights->refresh($ground);
        }

        $this->info("Resolved {$resolved} matches into {$grounds->count()} grounds.");

        $rows = MatchGround::orderByDesc('matches_played')->limit(10)->get()
            ->map(fn (MatchGround $g) => [
                $g->name,
                $g->matches_played,
                $g->first_innings_avg ?: '-',
                $g->highest_total ?: '-',
                $g->boundary_percent ? $g->boundary_percent . '%' : '-',
                $g->decided_matches > 0 ? "{$g->batting_first_wins}/{$g->decided_matches}" : '-',
                $g->confidence(),
            ])
            ->all();

        if ($rows !== []) {
            $this->table(['Ground', 'Played', '1st avg', 'Highest', 'Bnd%', 'Bat 1st W', 'Band'], $rows);
        }

        return self::SUCCESS;
    }
}
