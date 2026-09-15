<?php

declare(strict_types=1);

namespace App\Services\Stats;

use App\Models\LiveMatch;
use App\Models\User;

/**
 * Who took part in one match, and the stat rows being built for them.
 *
 * Identity is resolved in exactly one place, by the rules the match screens already follow:
 *
 *  1. A squad entry with an id is a registered player — the id is users.player_id.
 *  2. An event that carries `player_id` (users.id) belongs to that account's squad entry.
 *  3. Otherwise a name is joined to a squad entry on the SAME side, exactly (trimmed,
 *     case-insensitive). Names are never matched across sides or fuzzily — when the result
 *     decides whose career a goal lands on, a wrong join is worse than no join.
 *  4. A name that matches nobody becomes a guest row for this match only.
 */
final class MatchRoster
{
    /** @var array<string, array<string, mixed>> player_key => row */
    private array $rows = [];

    /** @var array<string, array<string, string>> side => lower(name) => player_key */
    private array $byName = ['home' => [], 'away' => []];

    /** @var array<string, int> player_id => users.id */
    private array $userIdByPlayerId = [];

    /** @var array<int, string> users.id => player_id, filled lazily */
    private array $playerIdByUserId = [];

    public function __construct(private readonly LiveMatch $match)
    {
        $ids = [];
        foreach (['home' => $match->home_squad, 'away' => $match->away_squad] as $side => $squad) {
            foreach ((array) $squad as $entry) {
                if (! is_array($entry)) {
                    continue;
                }
                $id = self::cleanId($entry['id'] ?? null);
                if ($id !== '') {
                    $ids[] = $id;
                }
            }
        }

        if ($ids !== []) {
            foreach (User::query()->whereIn('player_id', array_unique($ids))->get(['id', 'player_id']) as $u) {
                $this->userIdByPlayerId[(string) $u->player_id] = (int) $u->id;
                $this->playerIdByUserId[(int) $u->id] = (string) $u->player_id;
            }
        }

        foreach (['home' => $match->home_squad, 'away' => $match->away_squad] as $side => $squad) {
            foreach ((array) $squad as $entry) {
                if (! is_array($entry)) {
                    continue;
                }
                $name = trim((string) ($entry['name'] ?? ''));
                $id = self::cleanId($entry['id'] ?? null);
                if ($name === '' && $id === '') {
                    continue;
                }
                $key = $id !== '' ? $id : self::guestKey($side, $name);
                $this->rows[$key] ??= $this->blank($key, $id === '' ? null : $id, $name ?: $id, $side, true);
                if ($name !== '') {
                    $this->byName[$side][mb_strtolower($name)] ??= $key;
                }
            }
        }
    }

    /** Add figures to whoever `$name` / `$userId` resolves to on `$side`. */
    public function bump(string $side, ?string $name, ?int $userId, array $by): void
    {
        $key = $this->resolve($side, $name, $userId);
        if ($key === null) {
            return;
        }
        $this->bumpKey($key, $by);
    }

    /** @param array<string, int> $by */
    public function bumpKey(string $key, array $by): void
    {
        if (! isset($this->rows[$key])) {
            return;
        }
        foreach ($by as $stat => $value) {
            $this->rows[$key]['stats'][$stat] = ($this->rows[$key]['stats'][$stat] ?? 0) + $value;
        }
    }

    /**
     * Merge structured figures into a registered player's row (cricket's replay is keyed by
     * squad id). An id no longer in either squad still gets a row, with no side.
     */
    public function mergeById(string $playerId, ?string $name, callable $merge): void
    {
        $id = self::cleanId($playerId);
        if ($id === '') {
            return;
        }
        $this->rows[$id] ??= $this->blank($id, $id, trim((string) $name) ?: $id, null, true);
        $this->rows[$id]['stats'] = $merge($this->rows[$id]['stats']);
    }

    /** @return array<int, string> player keys on a side, in squad order */
    public function sideMembers(string $side): array
    {
        return array_values(array_keys(array_filter($this->rows, static fn (array $r): bool => $r['side'] === $side)));
    }

    /** @return array<int, array<string, mixed>> */
    public function rows(): array
    {
        return array_values($this->rows);
    }

    private function resolve(string $side, ?string $name, ?int $userId): ?string
    {
        if (! in_array($side, ['home', 'away'], true)) {
            return null;
        }

        if ($userId !== null && $userId > 0) {
            $pid = $this->playerIdByUserId[$userId] ??= (string) (User::query()->whereKey($userId)->value('player_id') ?? '');
            if ($pid !== '' && isset($this->rows[$pid])) {
                return $pid;
            }
        }

        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }
        $lower = mb_strtolower($name);
        if (isset($this->byName[$side][$lower])) {
            return $this->byName[$side][$lower];
        }

        $key = self::guestKey($side, $name);
        $this->rows[$key] ??= $this->blank($key, null, $name, $side, false);
        $this->byName[$side][$lower] = $key;

        return $key;
    }

    /** @return array<string, mixed> */
    private function blank(string $key, ?string $playerId, string $name, ?string $side, bool $inSquad): array
    {
        return [
            'player_key' => $key,
            'player_id' => $playerId,
            'user_id' => $playerId !== null ? ($this->userIdByPlayerId[$playerId] ?? null) : null,
            'player_name' => $name,
            'side' => $side,
            'played' => $inSquad,
            'stats' => [],
        ];
    }

    public static function guestKey(string $side, string $name): string
    {
        return 'guest:' . $side . ':' . mb_substr(mb_strtolower(trim($name)), 0, 120);
    }

    public static function cleanId(mixed $value): string
    {
        $s = trim((string) ($value ?? ''));

        return ($s === '' || strtolower($s) === 'null') ? '' : $s;
    }
}
