<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\ActionboardXp;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * ActionBoard leaderboards.
 *
 * Monthly boards (District / State / India) are computed live from the XP
 * ledger so they always reflect the latest settled matches. Only RANKED XP
 * counts, and only verified (non-guest, located) players appear — casual and
 * guest play can never reach these boards.
 *
 * The All-Time Hall of Fame reads the pre-aggregated users.ranked_xp so early
 * users keep their legacy standing while the monthly boards give everyone a
 * fresh start each month.
 */
final class LeaderboardService
{
    /**
     * Monthly ranked board for a scope.
     *
     * @param  'india'|'state'|'district'  $scope
     * @param  string|null  $month     'YYYY-MM' (defaults to current month)
     * @param  string|null  $location  required for state/district scope
     * @return list<array<string, mixed>>
     */
    public function monthly(string $scope, ?string $month = null, ?string $location = null, int $limit = 100): array
    {
        $month = $month ?: Carbon::now()->format('Y-m');

        $query = DB::table('match_xp_ledger as l')
            ->join('users as u', 'u.player_id', '=', 'l.player_id')
            ->where('l.is_ranked', true)
            ->where('l.season_month', $month)
            ->where('u.is_guest', false)
            ->whereNotNull('u.district')
            ->whereNotNull('u.state');

        if ($scope === 'state' && $location !== null) {
            $query->where('u.state', $location);
        } elseif ($scope === 'district' && $location !== null) {
            $query->where('u.district', $location);
        }

        $rows = $query
            ->groupBy('l.player_id', 'u.name', 'u.avatar', 'u.district', 'u.state')
            ->selectRaw('l.player_id, u.name, u.avatar, u.district, u.state, '
                . 'SUM(l.xp) as month_xp, COUNT(l.id) as matches')
            ->orderByDesc('month_xp')
            ->orderBy('u.name')
            ->limit($limit)
            ->get();

        return $this->ranked($rows, 'month_xp');
    }

    /**
     * All-Time Hall of Fame — pre-aggregated ranked XP across all seasons.
     *
     * @return list<array<string, mixed>>
     */
    public function allTime(int $limit = 100): array
    {
        $rows = DB::table('users')
            ->where('is_guest', false)
            ->whereNotNull('district')
            ->whereNotNull('state')
            ->where('ranked_xp', '>', 0)
            ->orderByDesc('ranked_xp')
            ->orderBy('name')
            ->limit($limit)
            ->get(['player_id', 'name', 'avatar', 'district', 'state', 'ranked_xp', 'trust_score']);

        return $this->ranked($rows, 'ranked_xp');
    }

    /**
     * Attach a 1-based rank to each row, normalizing the XP column to `xp`.
     *
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     * @return list<array<string, mixed>>
     */
    private function ranked($rows, string $xpKey): array
    {
        $out = [];
        $rank = 1;
        foreach ($rows as $r) {
            $out[] = [
                'rank'        => $rank++,
                'player_id'   => $r->player_id,
                'name'        => $r->name,
                'avatar'      => $r->avatar ?? null,
                'district'    => $r->district,
                'state'       => $r->state,
                'xp'          => (int) ($r->{$xpKey} ?? 0),
                'matches'     => isset($r->matches) ? (int) $r->matches : null,
                'trust_score' => isset($r->trust_score) ? (int) $r->trust_score : null,
            ];
        }
        return $out;
    }

    /** Valid scopes for the monthly board. */
    public static function isValidScope(string $scope): bool
    {
        return in_array($scope, ['india', 'state', 'district'], true);
    }
}
