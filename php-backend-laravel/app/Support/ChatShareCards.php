<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\LiveMatch;
use App\Models\User;
use App\Models\Venue;

/**
 * The match, venue and player cards a chat message can carry.
 *
 * A message stores only `share_type` + `share_ref`. Every card is built HERE, at read time,
 * for the person reading — that is what lets a shared live match show the score as it is now,
 * a venue that was taken down stop advertising itself, and a private match keep its details
 * from anyone who isn't in it. A card that cannot be shown to this reader comes back with
 * `available: false` and a neutral title, never with the details.
 */
final class ChatShareCards
{
    public const TYPES = ['match', 'venue', 'player'];

    /**
     * The card for one shared thing, as [$viewer] may see it. Null only for an unknown type —
     * a known type that is gone or hidden still returns a card, marked unavailable, so the
     * message keeps its place in the thread.
     *
     * @return array<string, mixed>|null
     */
    public static function resolve(string $type, string $ref, ?User $viewer): ?array
    {
        return match ($type) {
            'match' => self::match($ref, $viewer),
            'venue' => self::venue($ref),
            'player' => self::player($ref, $viewer),
            default => null,
        };
    }

    /** True when [$viewer] may share this — the same test as seeing it. */
    public static function canShare(string $type, string $ref, User $viewer): bool
    {
        $card = self::resolve($type, $ref, $viewer);

        return $card !== null && $card['available'] === true;
    }

    /** The line the thread list shows for a message that is only a card. */
    public static function preview(string $type, ?array $card): string
    {
        $title = trim((string) ($card['title'] ?? ''));
        $what = match ($type) {
            'match' => 'a match',
            'venue' => 'a venue',
            'player' => 'a player',
            default => 'something',
        };

        return $title !== '' && ($card['available'] ?? false) ? "Shared {$what}: {$title}" : "Shared {$what}";
    }

    /**
     * What the share picker lists: up to 20 cards of one type, filtered by [$q].
     *
     * Matches are the viewer's own (created or in a squad) plus, when they search, public ones.
     * Venues are published venues. Players are mutual follows, or anyone discoverable when they
     * search. Each row goes through [resolve] so the picker can never offer a card the
     * recipient would then be refused.
     *
     * @return list<array<string, mixed>>
     */
    public static function search(string $type, string $q, User $viewer): array
    {
        $q = trim($q);
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $q).'%';

        if ($type === 'match') {
            $pid = (string) ($viewer->player_id ?? '');
            $rows = LiveMatch::query()
                ->where(function ($w) use ($viewer, $pid, $q, $like): void {
                    $w->where('user_id', $viewer->id);
                    if ($pid !== '') {
                        $w->orWhere('home_squad', 'like', '%"'.$pid.'"%')
                            ->orWhere('away_squad', 'like', '%"'.$pid.'"%');
                    }
                    if ($q !== '') {
                        $w->orWhere(function ($pub) use ($like): void {
                            $pub->where('is_private', false)->where(function ($t) use ($like): void {
                                $t->whereRaw("home LIKE ? ESCAPE '\\'", [$like])
                                    ->orWhereRaw("away LIKE ? ESCAPE '\\'", [$like])
                                    ->orWhereRaw("title LIKE ? ESCAPE '\\'", [$like]);
                            });
                        });
                    }
                })
                ->orderByDesc('updated_at')
                ->limit(40)
                ->get();

            return $rows
                ->map(fn (LiveMatch $m) => self::matchCard($m, $viewer))
                ->filter(fn (array $c) => $c['available'])
                ->filter(fn (array $c) => $q === '' || mb_stripos($c['title'].' '.$c['subtitle'], $q) !== false)
                ->take(20)
                ->values()
                ->all();
        }

        if ($type === 'venue') {
            $rows = Venue::query()
                ->published()
                ->when($q !== '', fn ($w) => $w->where(function ($t) use ($like): void {
                    $t->whereRaw("name LIKE ? ESCAPE '\\'", [$like])
                        ->orWhereRaw("location LIKE ? ESCAPE '\\'", [$like])
                        ->orWhereRaw("city LIKE ? ESCAPE '\\'", [$like]);
                }))
                ->with('courts')
                ->orderByDesc('is_featured')
                ->orderBy('name')
                ->limit(20)
                ->get();

            return $rows->map(fn (Venue $v) => self::venueCard($v))->values()->all();
        }

        if ($type === 'player') {
            if ($q === '') {
                $followingIds = $viewer->following()->pluck('users.id');
                $rows = $viewer->followers()
                    ->whereIn('users.id', $followingIds)
                    ->where('is_guest', false)
                    ->orderBy('name')
                    ->limit(20)
                    ->get();
            } else {
                $rows = User::query()
                    ->where('is_guest', false)
                    ->whereNotNull('player_id')
                    ->where('id', '!=', $viewer->id)
                    ->where(fn ($s) => $s->whereNull('privacy_discoverable')->orWhere('privacy_discoverable', true))
                    ->where(fn ($s) => $s->whereRaw("name LIKE ? ESCAPE '\\'", [$like])
                        ->orWhereRaw("username LIKE ? ESCAPE '\\'", [$like]))
                    ->orderBy('name')
                    ->limit(20)
                    ->get();
            }

            return $rows
                ->reject(fn (User $u) => User::blockExistsBetween($viewer, $u))
                ->map(fn (User $u) => self::playerCard($u))
                ->values()
                ->all();
        }

        return [];
    }

    /** @return array<string, mixed> */
    private static function match(string $ref, ?User $viewer): array
    {
        $m = ctype_digit($ref) ? LiveMatch::query()->find((int) $ref) : null;
        if ($m === null) {
            return self::unavailable('match', $ref, 'Match removed');
        }

        return self::matchCard($m, $viewer);
    }

    /** @return array<string, mixed> */
    private static function matchCard(LiveMatch $m, ?User $viewer): array
    {
        if (! $m->isVisibleTo($viewer)) {
            return self::unavailable('match', (string) $m->id, 'Private match');
        }

        $sport = strtolower((string) ($m->sport ?: 'cricket'));
        $status = strtolower(trim((string) $m->status));
        $finished = $m->completed_at !== null || in_array($status, ['completed', 'finished', 'abandoned'], true);
        $live = ! $finished && $status === 'live';

        // The scoreline. Cricket's `score_text` is the batting side's "120/4"; every other
        // sport keeps the line in home/away scores. A match that hasn't started has none.
        $line = '';
        if ($live || $finished) {
            if ($sport === 'cricket') {
                $overs = trim((string) $m->overs);
                $line = trim((string) ($m->score_text ?: ''));
                if ($line !== '' && $overs !== '' && $overs !== '0.0') {
                    $line .= " ({$overs} ov)";
                }
            } else {
                $line = ((int) $m->home_score).' – '.((int) $m->away_score);
            }
        }

        $when = $m->scheduled_at !== null
            ? BusinessClock::local($m->scheduled_at)->format('D j M, g:i A')
            : null;

        return [
            'type' => 'match',
            'id' => (string) $m->id,
            'available' => true,
            'title' => trim((string) $m->home).' vs '.trim((string) $m->away),
            'subtitle' => implode(' · ', array_values(array_filter([
                ucfirst($sport),
                trim((string) $m->venue) ?: null,
                ! $live && ! $finished ? $when : null,
            ]))),
            'image' => null,
            'line' => $line,
            // Finished matches show their result in place of a badge, which is what anyone
            // reading an old share wants to know.
            'badge' => $live ? 'LIVE' : ($finished ? 'RESULT' : 'UPCOMING'),
            'note' => $finished ? trim((string) $m->status) : null,
            'live' => $live,
            'sport' => $sport,
        ];
    }

    /** @return array<string, mixed> */
    private static function venue(string $ref): array
    {
        $v = ctype_digit($ref)
            ? Venue::query()->published()->with('courts')->find((int) $ref)
            : null;

        return $v === null ? self::unavailable('venue', $ref, 'Venue unavailable') : self::venueCard($v);
    }

    /** @return array<string, mixed> */
    private static function venueCard(Venue $v): array
    {
        $from = $v->fromPrice();
        $rating = ($v->ratings_count > 0 && $v->rating) ? number_format((float) $v->rating, 1) : null;

        return [
            'type' => 'venue',
            'id' => (string) $v->id,
            'available' => true,
            'title' => (string) $v->name,
            'subtitle' => implode(', ', array_values(array_filter([trim((string) $v->location), trim((string) $v->city)]))),
            'image' => VenuePhoto::url(is_array($v->images) ? ($v->images[0] ?? null) : null),
            'line' => $from > 0 ? 'From ₹'.number_format($from).'/hr' : '',
            'badge' => $rating !== null ? "★ {$rating}" : null,
            'note' => null,
            'live' => false,
            'sport' => strtolower((string) ($v->sportsList()[0] ?? '')),
        ];
    }

    /** @return array<string, mixed> */
    private static function player(string $ref, ?User $viewer): array
    {
        $u = User::query()->where('player_id', $ref)->where('is_guest', false)->first();
        if ($u === null || ($viewer !== null && User::blockExistsBetween($viewer, $u))) {
            return self::unavailable('player', $ref, 'Player unavailable');
        }

        return self::playerCard($u);
    }

    /** @return array<string, mixed> */
    private static function playerCard(User $u): array
    {
        return [
            'type' => 'player',
            'id' => (string) $u->player_id,
            'available' => true,
            'title' => (string) $u->name,
            'subtitle' => implode(' · ', array_values(array_filter([
                $u->username ? '@'.$u->username : null,
                $u->primary_sport ? ucfirst((string) $u->primary_sport) : null,
            ]))),
            'image' => $u->avatar,
            'line' => '',
            'badge' => $u->is_verified ? 'VERIFIED' : null,
            'note' => null,
            'live' => false,
            'sport' => strtolower((string) ($u->primary_sport ?? '')),
        ];
    }

    /** @return array<string, mixed> */
    private static function unavailable(string $type, string $ref, string $title): array
    {
        return [
            'type' => $type,
            'id' => $ref,
            'available' => false,
            'title' => $title,
            'subtitle' => '',
            'image' => null,
            'line' => '',
            'badge' => null,
            'note' => null,
            'live' => false,
            'sport' => '',
        ];
    }
}
