<?php

declare(strict_types=1);

namespace App\Filament\Resources\LiveMatches\Widgets;

use App\Filament\Widgets\ListSummaryWidget;
use App\Models\LiveMatch;
use Illuminate\Support\Facades\DB;

/**
 * Matches list summary: what is live, scheduled and finished, by sport, and
 * the matches being scored right now. When nothing is live it says so — it
 * never fills the space with sample fixtures.
 */
class MatchesExecutiveHeroWidget extends ListSummaryWidget
{
    private const LIVE = ['live', 'in_progress'];

    private const SCHEDULED = ['scheduled', 'upcoming'];

    private const DONE = ['completed', 'finished'];

    public function getSummary(): array
    {
        $count = fn (array $statuses): int => LiveMatch::whereIn(DB::raw('lower(status)'), $statuses)->count();

        $live = $count(self::LIVE);
        $scheduled = $count(self::SCHEDULED);
        $done = $count(self::DONE);
        $doneThisWeek = LiveMatch::whereIn(DB::raw('lower(status)'), self::DONE)
            ->where('updated_at', '>=', now()->subDays(7))
            ->count();

        $bySport = LiveMatch::selectRaw('lower(sport) as s, COUNT(*) as n')
            ->groupBy('s')
            ->pluck('n', 's')
            ->mapWithKeys(fn ($n, $s) => [ucfirst((string) ($s ?: 'unknown')) => (int) $n])
            ->all();

        $now = LiveMatch::whereIn(DB::raw('lower(status)'), self::LIVE)
            ->latest('updated_at')
            ->limit(4)
            ->get();

        return [
            'title' => 'Matches',
            'stats' => [
                ['label' => 'Live now', 'value' => number_format($live), 'tone' => $live > 0 ? 'good' : null],
                ['label' => 'Scheduled', 'value' => number_format($scheduled)],
                ['label' => 'Completed', 'value' => number_format($done), 'sub' => number_format($doneThisWeek) . ' in the last 7 days'],
                ['label' => 'All matches', 'value' => number_format(LiveMatch::count())],
            ],
            'split' => [
                'label' => 'Matches by sport',
                'parts' => self::parts($bySport, money: false),
            ],
            'list' => [
                'title' => 'Being scored now',
                'rows' => $now->map(fn (LiveMatch $m): array => [
                    'primary' => trim(($m->home_full ?: $m->home) . ' vs ' . ($m->away_full ?: $m->away)),
                    'secondary' => collect([ucfirst((string) $m->sport), $m->competition, $m->venue])->filter()->join(' · ') ?: null,
                    'trailing' => $m->score_text ?: ($m->home_score . ' – ' . $m->away_score),
                ])->all(),
                'empty' => 'Nothing is live right now.',
            ],
        ];
    }
}
