<?php

namespace App\Services;

use App\Models\LiveMatch;
use App\Models\User;
use App\Support\MediaUrl;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * "Your people are playing" — who on a match card the viewer actually knows.
 *
 * Two sources, deliberately kept apart:
 *  - FRIENDS come from `player_follows` and are resolved here, server-side, for the whole
 *    feed in a handful of queries.
 *  - CONTACTS never reach this class. The app sends hashed phone numbers to [matchContacts]
 *    once, keeps the answer (player id → the name saved in the phone book) on the device, and
 *    intersects it with each card's [playerIds] itself. The server never stores an address
 *    book, and the name a user saved for their friend never leaves their phone.
 *
 * Squads are `{id, name}` with the id set only when the creator picked a real player from
 * search (see LiveMatch squads), so this only ever finds people who were linked properly —
 * a typed-in name is never matched on, because a name match would put the wrong face on a card.
 */
class FriendsPlaying
{
    /** Names shown per card; the app turns the rest into "+N". */
    private const MAX_FRIENDS_PER_CARD = 3;

    /**
     * Per match id: the real accounts in its squads and which of them the viewer follows or
     * is followed by. Guests get nothing — they have no friends to find and no contacts sync.
     *
     * @param  Collection<int, LiveMatch>  $matches
     * @return array<string, array{playerIds: list<string>, friends: list<array>, friendsCount: int}>
     */
    public function forFeed(?User $viewer, Collection $matches): array
    {
        if ($viewer === null || $viewer->is_guest) {
            return [];
        }

        $idsByMatch = [];
        $all = [];
        foreach ($matches as $m) {
            $ids = [];
            foreach (array_merge((array) ($m->home_squad ?? []), (array) ($m->away_squad ?? [])) as $p) {
                $id = trim((string) (is_array($p) ? ($p['id'] ?? '') : ''));
                if ($id !== '' && strtolower($id) !== 'null') {
                    $ids[$id] = true;
                }
            }
            $idsByMatch[(string) $m->id] = array_keys($ids);
            $all += $ids;
        }
        if ($all === []) {
            return [];
        }

        $users = User::query()
            ->whereIn('player_id', array_keys($all))
            ->where('is_guest', false)
            ->where('id', '!=', $viewer->id)
            ->get(['id', 'player_id', 'name', 'username', 'avatar'])
            ->keyBy('player_id');
        if ($users->isEmpty()) {
            return [];
        }

        $hidden = $this->blockedEitherWay($viewer, $users->pluck('id')->all());
        $users = $users->reject(fn (User $u) => isset($hidden[$u->id]));

        $uids = $users->pluck('id')->all();
        $iFollow = array_flip(DB::table('player_follows')
            ->where('follower_id', $viewer->id)->whereIn('followee_id', $uids)
            ->pluck('followee_id')->all());
        $followsMe = array_flip(DB::table('player_follows')
            ->where('followee_id', $viewer->id)->whereIn('follower_id', $uids)
            ->pluck('follower_id')->all());

        $out = [];
        foreach ($idsByMatch as $matchId => $ids) {
            $playerIds = [];
            $friends = [];
            foreach ($ids as $pid) {
                $u = $users->get($pid);
                if ($u === null) {
                    continue;
                }
                $playerIds[] = (string) $pid;
                $relation = isset($iFollow[$u->id]) && isset($followsMe[$u->id]) ? 'mutual'
                    : (isset($iFollow[$u->id]) ? 'following' : (isset($followsMe[$u->id]) ? 'follows_you' : null));
                if ($relation !== null) {
                    $friends[] = $this->card($u, $relation);
                }
            }
            if ($playerIds === []) {
                continue;
            }
            // People you follow lead: you chose them. Mutuals first of all.
            $rank = ['mutual' => 0, 'following' => 1, 'follows_you' => 2];
            usort($friends, fn ($a, $b) => $rank[$a['relation']] <=> $rank[$b['relation']]);
            $out[$matchId] = [
                'playerIds'    => $playerIds,
                'friends'      => array_slice($friends, 0, self::MAX_FRIENDS_PER_CARD),
                'friendsCount' => count($friends),
            ];
        }

        return $out;
    }

    /**
     * Which of these hashed phone numbers belong to Haraan players. Hashes are SHA-256 of the
     * number's last 10 digits ([phoneKey]); the app hashes the same way, so a raw number is
     * never sent. Nothing about the request is stored.
     *
     * @param  list<string>  $hashes
     * @return list<array{hash: string, playerId: string, name: string, username: ?string, avatar: ?string}>
     */
    public function matchContacts(User $viewer, array $hashes): array
    {
        $byHash = $this->phoneHashIndex();
        $hits = [];
        foreach (array_unique($hashes) as $h) {
            if (isset($byHash[$h]) && (int) $byHash[$h] !== (int) $viewer->id) {
                $hits[$h] = (int) $byHash[$h];
            }
        }
        if ($hits === []) {
            return [];
        }

        $users = User::query()
            ->whereIn('id', array_values($hits))
            ->where('is_guest', false)
            ->whereNotNull('player_id')
            ->get(['id', 'player_id', 'name', 'username', 'avatar'])
            ->keyBy('id');
        $hidden = $this->blockedEitherWay($viewer, $users->keys()->all());

        $out = [];
        foreach ($hits as $hash => $uid) {
            $u = $users->get($uid);
            if ($u === null || isset($hidden[$uid])) {
                continue;
            }
            $out[] = ['hash' => $hash] + $this->card($u, 'contact');
        }

        return $out;
    }

    /** The comparable form of an Indian phone number: its last 10 digits, or null. */
    public static function phoneKey(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        return strlen($digits) >= 10 ? substr($digits, -10) : null;
    }

    /** sha256(last 10 digits) → user id, for every account with a phone. Short-cached. */
    private function phoneHashIndex(): array
    {
        return Cache::remember('friends-playing:phone-hashes', now()->addMinutes(10), function (): array {
            $map = [];
            User::query()
                ->whereNotNull('phone')->where('phone', '!=', '')
                ->where('is_guest', false)
                ->select(['id', 'phone'])
                ->chunkById(1000, function ($rows) use (&$map): void {
                    foreach ($rows as $u) {
                        $key = self::phoneKey($u->phone);
                        if ($key !== null) {
                            $map[hash('sha256', $key)] = (int) $u->id;
                        }
                    }
                });
            return $map;
        });
    }

    /** User ids (as keys) that the viewer blocked or that blocked the viewer. */
    private function blockedEitherWay(User $viewer, array $uids): array
    {
        if ($uids === []) {
            return [];
        }
        $a = DB::table('player_blocks')->where('blocker_id', $viewer->id)->whereIn('blocked_id', $uids)->pluck('blocked_id')->all();
        $b = DB::table('player_blocks')->where('blocked_id', $viewer->id)->whereIn('blocker_id', $uids)->pluck('blocker_id')->all();
        return array_flip(array_merge($a, $b));
    }

    private function card(User $u, string $relation): array
    {
        return [
            'playerId' => (string) $u->player_id,
            'name'     => (string) ($u->name ?: ($u->username ? '@'.$u->username : 'Player')),
            'username' => $u->username ? (string) $u->username : null,
            'avatar'   => MediaUrl::resolve($u->avatar !== null ? (string) $u->avatar : null),
            'relation' => $relation,
        ];
    }
}
