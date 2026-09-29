<?php

declare(strict_types=1);

namespace App\Filament\Clusters\GameHub\Pages;

use App\Filament\Clusters\GameHub\Concerns\SummarisesVenues;
use App\Filament\Clusters\GameHub\GameHubCluster;
use App\Models\Tournament;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

/**
 * Tournaments players host in the app (the tournaments table): running now,
 * coming up, by sport, and the next ones to start. There is no status column —
 * running / upcoming / finished comes from the start and end dates.
 *
 * /control only: these belong to players, not to any venue, so there is no
 * honest partner-scoped version to show a venue owner.
 */
class GameHubTournaments extends Page
{
    use SummarisesVenues;

    protected static ?string $cluster = GameHubCluster::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-trophy';

    protected static ?string $title = 'Tournaments';

    protected static ?string $navigationLabel = 'Tournaments';

    protected static ?int $navigationSort = 12;

    protected string $view = 'filament.clusters.game-hub.summary-page';

    public static function canAccess(): bool
    {
        return ! static::inPartnerConsole() && (auth()->user()?->canManage('gamehub') ?? false);
    }

    public function getPanels(): array
    {
        $today = Carbon::today()->toDateString();

        $running = Tournament::whereDate('start_date', '<=', $today)->whereDate('end_date', '>=', $today)->count();
        $upcoming = Tournament::whereDate('start_date', '>', $today)->count();
        $finished = Tournament::whereDate('end_date', '<', $today)->count();
        $teams = (int) Tournament::whereDate('end_date', '>=', $today)->sum('teams_count');

        $bySport = Tournament::whereDate('end_date', '>=', $today)
            ->selectRaw('lower(sport) as s, COUNT(*) as n')
            ->groupBy('s')
            ->pluck('n', 's')
            ->mapWithKeys(fn ($n, $s) => [ucfirst((string) $s) => (int) $n])
            ->all();

        $next = Tournament::whereDate('end_date', '>=', $today)->orderBy('start_date')->limit(5)->get();

        return [[
            'title' => 'Tournaments',
            'stats' => [
                ['label' => 'Running now', 'value' => number_format($running), 'tone' => $running > 0 ? 'good' : null],
                ['label' => 'Coming up', 'value' => number_format($upcoming)],
                ['label' => 'Teams entered', 'value' => number_format($teams), 'sub' => 'in running and upcoming'],
                ['label' => 'Finished', 'value' => number_format($finished)],
            ],
            'split' => ['label' => 'Running and upcoming, by sport', 'parts' => self::parts($bySport, money: false)],
            'list' => [
                'title' => 'Next to start',
                'rows' => $next->map(fn (Tournament $t): array => [
                    'primary' => $t->name,
                    'secondary' => collect([ucfirst((string) $t->sport), $t->city, $t->organizer_name])->filter()->join(' · '),
                    'trailing' => $t->start_date?->isFuture()
                        ? $t->start_date->format('j M')
                        : 'on till ' . $t->end_date?->format('j M'),
                ])->all(),
                'empty' => 'No tournaments running or coming up.',
            ],
        ]];
    }
}
