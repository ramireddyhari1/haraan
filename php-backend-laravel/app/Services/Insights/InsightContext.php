<?php

declare(strict_types=1);

namespace App\Services\Insights;

use App\Models\LiveMatch;
use App\Models\MatchEvent;
use Illuminate\Support\Collection;

/**
 * Everything a sport's insight builder may read — the replayed moments, the match flow and
 * the per-player contributions. Builders get facts, never the database.
 */
final class InsightContext
{
    /** @var array<string, mixed> */
    public array $flow = [];

    /** @var array{players: array<string, array<string, mixed>>, team: array{home:int, away:int}, unattributed: array{home:int, away:int}} */
    public array $contributors = ['players' => [], 'team' => ['home' => 0, 'away' => 0], 'unattributed' => ['home' => 0, 'away' => 0]];

    /**
     * @param  array<string, mixed>  $format
     * @param  Collection<int, MatchEvent>  $events
     * @param  array<int, array<string, mixed>>  $moments
     */
    public function __construct(
        public readonly LiveMatch $match,
        public readonly string $sport,
        public readonly string $family,
        public readonly array $format,
        public readonly Collection $events,
        public readonly array $moments,
        public readonly bool $live,
        public readonly bool $finished,
        public readonly string $homeName,
        public readonly string $awayName,
    ) {
    }

    public function teamName(string $side): string
    {
        return $side === 'home' ? $this->homeName : $this->awayName;
    }

    /** Share of a side's total, as a whole percent. 0 when the side has not scored. */
    public function share(string $side, int $value): int
    {
        $total = $this->contributors['team'][$side] ?? 0;

        return $total > 0 ? (int) round($value * 100 / $total) : 0;
    }

    /** 'home' | 'away' | null — the winner, only once the match is over and not drawn. */
    public function winner(): ?string
    {
        if (! $this->finished) {
            return null;
        }
        $h = (int) $this->match->home_score;
        $a = (int) $this->match->away_score;

        return $h === $a ? null : ($h > $a ? 'home' : 'away');
    }

    /** One player card in the shape every sport's tab renders. */
    public static function card(
        array $p,
        int $headline,
        string $headlineLabel,
        int $share,
        array $stats,
        array $tags,
    ): array {
        return [
            'name' => $p['name'],
            'side' => $p['side'],
            'headline' => $headline,
            'headline_label' => $headlineLabel,
            'share' => $share,
            'stats' => array_values($stats),
            'tags' => array_values($tags),
        ];
    }

    /** @return array{key: string, label: string} */
    public static function tag(string $key, string $label): array
    {
        return ['key' => $key, 'label' => $label];
    }

    /** @return array{label: string, value: int|string} */
    public static function stat(string $label, int|string $value): array
    {
        return ['label' => $label, 'value' => $value];
    }
}
