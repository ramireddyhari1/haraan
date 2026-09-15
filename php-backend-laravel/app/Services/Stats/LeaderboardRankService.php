<?php

declare(strict_types=1);

namespace App\Services\Stats;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Country / state / district career-runs rankings on `users.rank_*`.
 *
 * The previous version loaded every ranked user three times and issued one UPDATE per user
 * per scope, synchronously, on every match completion. This reads the four columns it needs
 * once, computes all three rankings in memory, and writes only the rows whose rank actually
 * moved, in batched CASE updates.
 */
final class LeaderboardRankService
{
    private const CHUNK = 500;

    /** @return int rows updated */
    public function recalculate(): int
    {
        $players = User::query()
            ->where('is_guest', false)
            ->where(fn ($q) => $q->whereNotNull('state')->orWhereNotNull('district'))
            ->orderByDesc('career_runs')
            ->orderBy('id')
            ->get(['id', 'state', 'district', 'career_runs', 'rank_country', 'rank_state', 'rank_district']);

        $country = 1;
        $stateRank = [];
        $districtRank = [];
        $changes = [];

        foreach ($players as $p) {
            $new = [
                'rank_country' => $p->district !== null && $p->state !== null ? $country++ : null,
                'rank_state' => $p->state !== null
                    ? ($stateRank[$p->state] = ($stateRank[$p->state] ?? 0) + 1)
                    : null,
                'rank_district' => $p->district !== null
                    ? ($districtRank[$p->district] = ($districtRank[$p->district] ?? 0) + 1)
                    : null,
            ];
            foreach ($new as $col => $value) {
                if ($value !== null && (int) $p->{$col} !== $value) {
                    $changes[$col][(int) $p->id] = $value;
                }
            }
        }

        $updated = 0;
        foreach ($changes as $col => $byId) {
            foreach (array_chunk($byId, self::CHUNK, true) as $chunk) {
                $cases = [];
                $bindings = [];
                foreach ($chunk as $id => $rank) {
                    $cases[] = 'WHEN ? THEN ?';
                    $bindings[] = $id;
                    $bindings[] = $rank;
                }
                $ids = array_keys($chunk);
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                DB::update(
                    "UPDATE users SET {$col} = CASE id " . implode(' ', $cases) . " END WHERE id IN ({$placeholders})",
                    array_merge($bindings, $ids),
                );
                $updated += count($chunk);
            }
        }

        return $updated;
    }
}
