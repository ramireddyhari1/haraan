<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LiveMatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * District Home — the per-district "mini sports community" snapshot that turns a
 * raw match list into a local identity ("I'm #4 in Andhra Pradesh"). Aggregates
 * are computed live so a district page always reflects current activity.
 */
final class DistrictService
{
    /** @return array<string, mixed> */
    public function summary(string $district, ?string $state): array
    {
        $liveMatches = LiveMatch::query()
            ->where('district', $district)
            ->whereRaw("LOWER(status) = 'live'")
            ->count();

        $totalMatches = LiveMatch::query()->where('district', $district)->count();

        $players = User::query()
            ->where('district', $district)
            ->where('is_guest', false)
            ->count();

        $topBatter = User::query()
            ->where('district', $district)
            ->where('is_guest', false)
            ->where('career_runs', '>', 0)
            ->orderByDesc('career_runs')
            ->orderBy('name')
            ->first(['player_id', 'name', 'avatar', 'career_runs']);

        $topBowler = User::query()
            ->where('district', $district)
            ->where('is_guest', false)
            ->where('career_wickets', '>', 0)
            ->orderByDesc('career_wickets')
            ->orderBy('name')
            ->first(['player_id', 'name', 'avatar', 'career_wickets']);

        [$rank, $totalDistricts] = $this->rankWithinState($district, $state);

        return [
            'district'          => $district,
            'state'             => $state,
            'liveMatches'       => $liveMatches,
            'totalMatches'      => $totalMatches,
            'players'           => $players,
            'topBatter'         => $topBatter === null ? null : [
                'player_id' => $topBatter->player_id,
                'name'      => $topBatter->name,
                'avatar'    => $topBatter->avatar,
                'value'     => (int) $topBatter->career_runs,
            ],
            'topBowler'         => $topBowler === null ? null : [
                'player_id' => $topBowler->player_id,
                'name'      => $topBowler->name,
                'avatar'    => $topBowler->avatar,
                'value'     => (int) $topBowler->career_wickets,
            ],
            'districtRank'      => $rank,
            'districtRankTotal' => $totalDistricts,
        ];
    }

    /**
     * Rank a district among its state's districts by total ranked XP (the most
     * "active/strong" districts rank highest). Returns [rank, totalDistricts];
     * rank is null when the state is unknown or the district has no ranked players.
     *
     * @return array{0: int|null, 1: int|null}
     */
    private function rankWithinState(string $district, ?string $state): array
    {
        if ($state === null || $state === '') {
            return [null, null];
        }

        $rows = DB::table('users')
            ->where('state', $state)
            ->where('is_guest', false)
            ->whereNotNull('district')
            ->where('district', '<>', '')
            ->groupBy('district')
            ->selectRaw('district, SUM(ranked_xp) as total_xp')
            ->orderByDesc('total_xp')
            ->get();

        $total = $rows->count();
        $rank = null;
        $i = 0;
        foreach ($rows as $row) {
            $i++;
            if ($row->district === $district) {
                $rank = $i;
                break;
            }
        }

        return [$rank, $total > 0 ? $total : null];
    }
}
