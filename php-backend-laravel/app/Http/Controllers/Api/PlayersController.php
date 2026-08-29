<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LiveMatch;
use App\Models\MatchEvent;
use App\Models\MatchXpLedger;
use App\Models\PlayerCareerBatting;
use App\Models\PlayerCareerBowling;
use App\Models\PlayerCareerFielding;
use App\Models\PlayerPost;
use App\Models\PostComment;
use App\Models\PostImage;
use App\Models\PostLike;
use App\Models\PostSave;
use App\Models\User;
use App\Services\CareerBattingService;
use App\Services\PlayerCareerAnalysis;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Player directory lookups + the logged-in player's ActionBoard profile.
 */
final class PlayersController extends Controller
{
    /**
     * The logged-in player's full ActionBoard card.
     * GET /api/players/me
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->attributes->get('auth_user');
        if (!$user instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return response()->json($this->profilePayload($user, $user));
    }

    /**
     * Any player's public ActionBoard card, by their Player ID (HRN…). Same shape as
     * {@see me()} so the app opens the real profile from the leaderboard instead of a
     * fabricated one. Public (read-only) — no auth required.
     * GET /api/players/{playerId}
     */
    public function show(Request $request, string $playerId): JsonResponse
    {
        $user = User::query()
            ->where('player_id', $playerId)
            ->where('is_guest', false)
            ->first();

        if (!$user instanceof User) {
            return response()->json(['error' => 'No player with that ID'], 404);
        }

        // The viewer, when there is one: a signed-out visitor still gets the profile,
        // just with no follow state to settle a button against.
        return response()->json($this->profilePayload($user, $request->attributes->get('auth_user')));
    }

    /**
     * Assemble a player's full ActionBoard card (profile + ranks + career + recent
     * matches + achievements). Shared by {@see me()} and {@see show()}.
     *
     * @return array<string, mixed>
     */
    private function profilePayload(User $user, mixed $viewer = null): array
    {
        $pid = $user->player_id;
        $month = now()->format('Y-m');

        $monthRankedXp = (int) MatchXpLedger::where('player_id', $pid)
            ->where('is_ranked', true)
            ->where('season_month', $month)
            ->sum('xp');

        $recent = DB::table('match_xp_ledger as l')
            ->join('live_matches as m', 'm.id', '=', 'l.match_id')
            ->where('l.player_id', $pid)
            ->orderByDesc('l.awarded_at')
            ->limit(10)
            ->get([
                'm.id as match_id', 'm.title', 'm.home', 'm.away', 'm.match_type',
                'l.xp', 'l.trust_level', 'l.is_ranked', 'l.won', 'l.mom', 'l.awarded_at',
            ])
            ->map(fn ($r) => [
                'match_id'   => (int) $r->match_id,
                'title'      => $r->title ?: ($r->home . ' vs ' . $r->away),
                'home'       => $r->home,
                'away'       => $r->away,
                'match_type' => $r->match_type,
                'xp'         => (int) $r->xp,
                'trust_level'=> $r->trust_level,
                'is_ranked'  => (bool) $r->is_ranked,
                'won'        => (bool) $r->won,
                'mom'        => (bool) $r->mom,
                'awarded_at' => $r->awarded_at,
            ])
            ->all();

        // The scoreboard for each of those matches, in the shape the ActionBoard card
        // already reads. The profile used to list a match as one line of text and an XP
        // number — the same game the feed draws as a scorecard. There is no reason for
        // a player's own history to be the poorest view of it in the app.
        $recent = $this->attachMatchCards($recent);

        return [
            'id'               => $user->id,
            'player_id'        => $pid,
            'username'         => $user->username,
            'name'             => $user->name,
            'bio'              => $user->bio,
            'avatar'           => $user->avatar,
            'district'         => $user->district,
            'state'            => $user->state,
            'player_role'      => $user->player_role,
            'batting_style'    => $user->batting_style,
            'bowling_style'    => $user->bowling_style,
            'primary_sport'    => $user->primary_sport,
            'sport_attributes' => $user->sport_attributes,
            'is_organizer'     => (bool) ($user->is_organizer ?? false),
            // Blue tick. Admin-granted in /control — the app only ever reads it, so a
            // profile can't award itself one.
            'is_verified'      => (bool) ($user->is_verified ?? false),
            'profile_complete' => $user->isActionboardProfileComplete(),
            // Account privacy (Instagram-style). Private accounts are hidden from the Home feed.
            'is_private'       => ! $user->privacy_public_profile,
            'about'            => $this->aboutPayload($user),

            'ranked_xp'       => (int) ($user->ranked_xp ?? 0),
            'casual_xp'       => (int) ($user->casual_xp ?? 0),
            'trust_score'     => (int) ($user->trust_score ?? 100),
            'month_ranked_xp' => $monthRankedXp,

            'rank_district'   => $user->rank_district,
            'rank_state'      => $user->rank_state,
            'rank_country'    => $user->rank_country,

            // Cricket's career, kept at this key unchanged so older app builds that read
            // career.runs / career.wickets keep working.
            'career' => [
                'matches'       => (int) ($user->career_matches ?? 0),
                'runs'          => (int) ($user->career_runs ?? 0),
                'balls'         => (int) ($user->career_balls ?? 0),
                'wickets'       => (int) ($user->career_wickets ?? 0),
                'overs_bowled'  => $user->career_overs_bowled ?? '0.0',
            ],

            // The same question asked in the player's OWN sport. `career` above is
            // cricket-only (runs/wickets), so a footballer's profile was advertising
            // batting figures that can never be anything but zero.
            'sport_career' => $this->sportCareer($user),

            // The full record, one entry per sport this player has actually played.
            // `career` and `sport_career` above are a handful of totals; this is the
            // batting and bowling line a cricketer expects to see about themselves,
            // and it carries the other sports on the same shape so the profile can
            // simply offer them as tabs.
            'career_book' => $this->careerBook($user),

            // The social graph. The follow system was built and wired into player
            // SEARCH, but the profile — the one screen where following belongs —
            // never received any of it: no counts, no button, no way to tell whether
            // you already follow the person you are looking at.
            'social' => $this->socialState($user, $viewer),

            // What this player still has to fill in, in THEIR sport's terms. The app
            // used to compute this itself against cricket's fields, so a footballer was
            // asked for a batting style and could never reach 100% however complete
            // their profile actually was.
            'profile_completion' => $this->profileCompletion($user),

            'recent_matches'  => $recent,
            'achievements'    => $this->buildAchievements($pid, $user),
        ];
    }

    /**
     * How complete this profile is, measured against what the player's OWN sport
     * requires — {@see User::SPORT_REQUIRED_ATTRS}, the same list
     * {@see User::isActionboardProfileComplete()} gates on.
     *
     * Returns KEYS, not sentences: the app owns the wording so a label can change
     * without a deploy, exactly as with `sport_career`.
     *
     * Deliberately profile FIELDS only. "Play a match" is not a field you can fill in,
     * and the empty-state card already asks for it — counting it here meant a fully
     * filled-in profile still read as incomplete.
     *
     * @return array<string, mixed>
     */
    private function profileCompletion(User $user): array
    {
        $attrs = is_array($user->sport_attributes) ? $user->sport_attributes : [];

        // Ordered: identity first, then the sport's own attributes.
        $checks = [
            'state'    => filled($user->state),
            'district' => filled($user->district),
            'avatar'   => filled($user->avatar),
        ];

        foreach (User::SPORT_REQUIRED_ATTRS[$user->primary_sport] ?? [] as $key) {
            $checks[$key] = ! empty($attrs[$key]);
        }

        $missing = array_keys(array_filter($checks, static fn (bool $ok): bool => ! $ok));
        $total = count($checks);

        return [
            'pct' => $total === 0 ? 100 : (int) round((($total - count($missing)) / $total) * 100),
            'missing' => array_values($missing),
        ];
    }

    /**
     * Follower graph for the profile header, plus whether the viewer already follows
     * this player so the button opens in the right state instead of flickering.
     *
     * [$viewer] is null for a signed-out visitor — a public profile still renders, it
     * just cannot offer a Follow button.
     *
     * @return array<string, mixed>
     */
    private function socialState(User $user, mixed $viewer): array
    {
        $isSelf = $viewer instanceof User && (int) $viewer->id === (int) $user->id;
        $blocked = $viewer instanceof User && ! $isSelf && $viewer->hasBlocked($user);

        return [
            'followers_count' => $user->followers()->count(),
            'following_count' => $user->following()->count(),
            // Never true for your own profile: you cannot follow yourself, and the
            // button slot becomes Share there instead.
            'is_following'    => $viewer instanceof User && ! $isSelf && $viewer->isFollowing($user),
            // The other half of "mutual". Messaging requires both directions, and the
            // client cannot work that out from is_following alone.
            'follows_me'      => $viewer instanceof User && ! $isSelf && $user->isFollowing($viewer),
            'is_self'         => $isSelf,
            // A signed-out viewer has no follow state to act on at all, and neither has
            // anyone looking at a profile they've blocked — the button becomes Unblock.
            'can_follow'      => $viewer instanceof User && ! $isSelf && ! $blocked,
            // Only the viewer's OWN block is reported. Telling someone they have been
            // blocked hands them the information a block exists to withhold, so
            // `is_blocked_by` is deliberately absent from every payload.
            'is_blocked'      => $blocked,
        ];
    }

    /**
     * Career figures phrased in the player's own sport.
     *
     * Every number here is derived from a real record — the ball log for cricket, the
     * `match_events` timeline for football, squad membership for match counts. Where a
     * sport has no per-player record, this returns FEWER cells rather than inventing
     * one: badminton points are recorded per side, not per player, so a badminton
     * player gets matches only. An empty-but-honest profile beats a padded one.
     *
     * @return array<string, mixed>
     */
    /**
     * Every sport this player has a record in, each as {key, label, matches, headline,
     * groups}. The app renders it without knowing any sport's rules: `headline` is the
     * three numbers that lead, `groups` are the labelled tables under them.
     *
     * Values are formatted STRINGS on purpose. An average is "-" until a player has
     * been out, best bowling reads "4/23", and milestones read "3 / 0" — none of those
     * survive being an int, and the alternative is six format rules in the client.
     */
    /**
     * Hydrate ledger rows with the real scoreline, badges and place of each match.
     *
     * Mirrors {@see LiveMatchController::index}'s row shape field for field, so the
     * profile card and the feed card are fed identically and cannot drift apart.
     */
    private function attachMatchCards(array $recent): array
    {
        $ids = array_values(array_filter(array_map(fn ($r) => (int) ($r['match_id'] ?? 0), $recent)));
        if ($ids === []) {
            return $recent;
        }
        $matches = LiveMatch::query()->whereIn('id', $ids)->get()->keyBy('id');

        foreach ($recent as $i => $row) {
            $m = $matches->get((int) ($row['match_id'] ?? 0));
            if ($m === null) {
                continue;
            }

            // Which side batted last — drives score attribution and which team the card
            // puts on top. Same derivation the feed uses.
            $overSummary = is_array($m->over_summary) ? $m->over_summary : [];
            $battingTeam = 1;
            for ($j = count($overSummary) - 1; $j >= 0; $j--) {
                $tag = $overSummary[$j]['batting'] ?? null;
                if ($tag !== null && $tag !== '') {
                    $battingTeam = ($tag === $m->away || $tag === 'away') ? 2 : 1;
                    break;
                }
            }

            // score_text carries wickets, which only cricket has. On every other sport it
            // holds the whole line ("2 - 1") and must not be printed as one side's score.
            $isCricket = strtolower((string) ($m->sport ?: 'cricket')) === 'cricket';
            $scoreText = $isCricket ? (string) ($m->score_text ?: '') : '';
            $overs = (string) ($m->overs ?? '');

            $recent[$i]['card'] = [
                'team1'       => (string) $m->home,
                'team2'       => (string) $m->away,
                'team1Full'   => (string) ($m->home_full ?? ''),
                'team2Full'   => (string) ($m->away_full ?? ''),
                'team1Logo'   => $this->absoluteMatchLogo($m->home_logo),
                'team2Logo'   => $this->absoluteMatchLogo($m->away_logo),
                'team1Emblem' => (string) ($m->home_emblem ?? ''),
                'team2Emblem' => (string) ($m->away_emblem ?? ''),
                'score1'      => ($battingTeam === 1 && $scoreText !== '') ? $scoreText : (string) ($m->home_score ?? 0),
                'score2'      => ($battingTeam === 2 && $scoreText !== '') ? $scoreText : (string) ($m->away_score ?? 0),
                'overs1'      => $isCricket && $battingTeam !== 2 ? $overs : '',
                'overs2'      => $isCricket && $battingTeam === 2 ? $overs : '',
                'battingTeam' => $battingTeam,
                'sport'       => strtolower((string) ($m->sport ?: 'cricket')),
                'status'      => (string) ($m->status ?? ''),
                'isLive'      => strtolower((string) $m->status) === 'live',
                'result'      => (string) ($m->result ?? ''),
                'competition' => (string) ($m->competition ?? ''),
                'venue'       => (string) ($m->venue ?? ''),
                'district'    => (string) ($m->district ?? ''),
                'locality'    => (string) ($m->locality ?? ''),
            ];
        }

        return $recent;
    }

    /** A stored logo path made absolute; blank stays blank so the app draws its emblem. */
    private function absoluteMatchLogo(?string $path): string
    {
        $p = trim((string) $path);
        if ($p === '' || str_starts_with($p, 'http')) {
            return $p;
        }
        return rtrim(config('app.url', ''), '/') . '/' . ltrim($p, '/');
    }

    private function careerBook(User $user): array
    {
        $pid = (string) $user->player_id;
        $primary = strtolower((string) ($user->primary_sport ?? 'cricket')) ?: 'cricket';

        // What they have actually played, counted once per sport. A player who has
        // never finished a match still gets their primary sport, so the tab strip is
        // never empty on a brand-new profile.
        $played = [];
        if ($pid !== '') {
            $rows = LiveMatch::query()
                ->selectRaw('lower(sport) as sport, count(*) as played')
                ->whereRaw('lower(status) = ?', ['completed'])
                ->where(function ($q) use ($pid): void {
                    $q->where('home_squad', 'like', '%"' . $pid . '"%')
                      ->orWhere('away_squad', 'like', '%"' . $pid . '"%');
                })
                ->groupBy(DB::raw('lower(sport)'))
                ->get();
            foreach ($rows as $row) {
                $key = (string) ($row->sport ?: 'cricket');
                $played[$key] = (int) $row->played;
            }
        }
        if (!isset($played[$primary])) {
            $played[$primary] = 0;
        }

        // The player's own sport leads; the rest follow by how much they have played.
        uksort($played, function (string $a, string $b) use ($played, $primary): int {
            if ($a === $primary) {
                return -1;
            }
            if ($b === $primary) {
                return 1;
            }
            return $played[$b] <=> $played[$a];
        });

        $book = [];
        foreach ($played as $sport => $matches) {
            $book[] = $sport === 'cricket'
                ? $this->cricketCareer($user, $pid, $matches)
                : $this->otherSportCareer($user, $sport, $matches);
        }

        return ['primary' => $primary, 'sports' => $book];
    }

    /** Cricket: the batting and bowling lines, replayed from the ball log. */
    private function cricketCareer(User $user, string $pid, int $matches): array
    {
        $bat = CareerBattingService::forPlayer($pid);
        $bowl = $pid === '' ? null : PlayerCareerBowling::where('player_id', $pid)->first();
        $field = $pid === '' ? null : PlayerCareerFielding::where('player_id', $pid)->first();

        // The replay only counts matches a player actually appeared in. career_matches
        // is the same number, so prefer whichever is larger rather than showing a zero
        // next to a career that plainly exists.
        $matches = max($matches, (int) ($user->career_matches ?? 0));

        $innings = (int) ($bat->innings ?? 0);
        $outs = (int) ($bat->outs ?? 0);
        $runs = (int) ($bat->runs ?? 0);
        $balls = (int) ($bat->balls ?? 0);

        $groups = [];
        if ($innings > 0 || $runs > 0) {
            $fours = (int) ($bat->fours ?? 0);
            $sixes = (int) ($bat->sixes ?? 0);
            $boundaryRuns = $fours * 4 + $sixes * 6;
            $groups[] = [
                'title' => 'Batting',
                'lead' => ['label' => 'Runs', 'value' => (string) $runs],
                // How the runs were made. A strike rate says how fast; this says how -
                // and it is the difference between a player who milks singles and one
                // who clears the rope, which no total can express.
                'visual' => $runs > 0 ? [
                    'kind' => 'split',
                    'title' => 'How the runs came',
                    'caption' => $boundaryRuns > 0
                        ? round($boundaryRuns * 100 / max(1, $runs)) . '% of your runs in boundaries'
                        : null,
                    'segments' => [
                        ['label' => 'Sixes', 'value' => $sixes * 6],
                        ['label' => 'Fours', 'value' => $fours * 4],
                        ['label' => 'Running', 'value' => max(0, $runs - $boundaryRuns)],
                    ],
                ] : null,
                'stats' => [
                    ['label' => 'Innings', 'value' => (string) $innings],
                    ['label' => 'Not outs', 'value' => (string) max(0, $innings - $outs)],
                    ['label' => 'Highest', 'value' => (string) (int) ($bat->high_score ?? 0)],
                    ['label' => 'Average', 'value' => self::num($bat?->average())],
                    ['label' => 'Strike rate', 'value' => self::num($bat?->strikeRate())],
                    ['label' => 'Balls faced', 'value' => (string) $balls],
                    ['label' => 'Fours', 'value' => (string) (int) ($bat->fours ?? 0)],
                    ['label' => 'Sixes', 'value' => (string) (int) ($bat->sixes ?? 0)],
                    [
                        'label' => '50s / 100s',
                        'value' => (int) ($bat->fifties ?? 0) . ' / ' . (int) ($bat->hundreds ?? 0),
                    ],
                ],
            ];
        }

        $bowlBalls = (int) ($bowl->balls ?? 0);
        $wickets = (int) ($bowl->wickets ?? 0);
        if ($bowlBalls > 0 || $wickets > 0) {
            $economy = $bowl?->economy();
            $groups[] = [
                'title' => 'Bowling',
                'lead' => ['label' => 'Wickets', 'value' => (string) $wickets],
                // An economy rate means nothing to most players as a bare decimal. On a
                // 0-15 scale with the verdict written out, it reads at a glance.
                'visual' => $economy === null ? null : [
                    'kind' => 'meter',
                    'title' => 'Economy',
                    'value' => $economy,
                    'max' => 15,
                    'caption' => $economy . ' runs per over — ' . match (true) {
                        $economy < 5 => 'tight',
                        $economy < 7 => 'steady',
                        $economy < 9 => 'gettable',
                        default => 'expensive',
                    },
                ],
                'stats' => [
                    ['label' => 'Innings', 'value' => (string) (int) ($bowl->innings ?? 0)],
                    ['label' => 'Overs', 'value' => $bowl?->oversText() ?? '0.0'],
                    ['label' => 'Runs', 'value' => (string) (int) ($bowl->runs ?? 0)],
                    ['label' => 'Economy', 'value' => self::num($bowl?->economy())],
                    ['label' => 'Average', 'value' => self::num($bowl?->average())],
                    ['label' => 'Strike rate', 'value' => self::num($bowl?->strikeRate())],
                    ['label' => 'Best', 'value' => $bowl?->bestText() ?? '-'],
                    ['label' => 'Maidens', 'value' => (string) (int) ($bowl->maidens ?? 0)],
                    [
                        'label' => '3w / 5w',
                        'value' => (int) ($bowl->three_fers ?? 0) . ' / ' . (int) ($bowl->five_fers ?? 0),
                    ],
                ],
            ];
        }

        $catches = (int) ($field->catches ?? 0);
        $runOuts = (int) ($field->run_outs ?? 0);
        $stumpings = (int) ($field->stumpings ?? 0);
        $dismissals = $catches + $runOuts + $stumpings;
        if ($dismissals > 0) {
            $groups[] = [
                'title' => 'Fielding',
                'lead' => ['label' => 'Dismissals', 'value' => (string) $dismissals],
                'visual' => [
                    'kind' => 'split',
                    'title' => 'How they went',
                    'caption' => null,
                    'segments' => [
                        ['label' => 'Catches', 'value' => $catches],
                        ['label' => 'Run outs', 'value' => $runOuts],
                        ['label' => 'Stumpings', 'value' => $stumpings],
                    ],
                ],
                'stats' => [
                    ['label' => 'Catches', 'value' => (string) $catches],
                    ['label' => 'Run outs', 'value' => (string) $runOuts],
                    ['label' => 'Stumpings', 'value' => (string) $stumpings],
                ],
            ];
        }

        // An empty cricket career has two different causes and the player deserves to
        // know which: they have not played yet, or they played a match nobody scored
        // ball by ball. Neither is "cricket does not track this".
        $note = null;
        if ($groups === []) {
            $note = $matches > 0
                ? 'These matches were not scored ball by ball, so there is no batting or bowling line from them yet.'
                : 'Your batting and bowling line builds itself from the first match someone scores ball by ball.';
        }

        return [
            'key' => 'cricket',
            'label' => 'Cricket',
            'matches' => $matches,
            'note' => $note,
            // Where the runs actually went. Only boundaries the scorer placed are in
            // here, so the wheel is a record of real shots, never a reconstruction.
            'wagon' => $this->wagon($bat),
            // Three sentences about the figures above, written by a model that was
            // handed those figures and forbidden to produce any of its own.
            'analysis' => $this->careerAnalysis($user),
            'headline' => [
                ['label' => 'Matches', 'value' => (string) $matches],
                ['label' => 'Runs', 'value' => (string) $runs],
                ['label' => 'Wickets', 'value' => (string) $wickets],
            ],
            'groups' => $groups,
        ];
    }

    /**
     * The career wagon wheel: eight regions, each with the shots and runs the player
     * actually hit there.
     *
     * Returns null rather than an empty wheel — a ground with no dots on it says the
     * feature is broken, when the truth is that nobody placed a boundary yet.
     *
     * @return array<string,mixed>|null
     */
    private function wagon(?PlayerCareerBatting $bat): ?array
    {
        $zones = $bat?->zones;
        if (! is_array($zones) || $zones === []) {
            return null;
        }

        $out = [];
        $total = 0;
        $shots = 0;
        foreach ($zones as $z) {
            $index = (int) ($z['zone'] ?? 0);
            $runs = (int) ($z['runs'] ?? 0);
            $total += $runs;
            $shots += (int) ($z['shots'] ?? 0);
            $out[] = [
                'zone' => $index,
                'label' => PlayerCareerBatting::ZONE_LABELS[$index] ?? 'Unknown',
                'shots' => (int) ($z['shots'] ?? 0),
                'fours' => (int) ($z['fours'] ?? 0),
                'sixes' => (int) ($z['sixes'] ?? 0),
                'runs' => $runs,
            ];
        }
        if ($total <= 0) {
            return null;
        }

        // Strongest region, so the card can say in words what the drawing shows.
        $best = $out[0];
        foreach ($out as $z) {
            if ($z['runs'] > $best['runs']) {
                $best = $z;
            }
        }

        return [
            'title' => 'Where the runs go',
            'total' => $total,
            'shots' => $shots,
            'zones' => $out,
            'caption' => $best['runs'] . ' of these runs went ' . strtolower($best['label'])
                . ' — ' . (int) round($best['runs'] * 100 / $total) . '% of the placed boundaries.',
        ];
    }

    /**
     * The written read, if one has been generated for the player's current figures.
     *
     * @return array<string,mixed>|null
     */
    private function careerAnalysis(User $user): ?array
    {
        $service = app(PlayerCareerAnalysis::class);
        // Schedule the write for after this response, then serve whatever is already
        // cached. The profile is never slower than the database because of this card.
        $service->refreshAfterResponse($user, $service->facts($user));
        $written = $service->cached($user);
        if ($written === null) {
            return null;
        }

        return [
            'title' => 'The read on your game',
            'lines' => $written['lines'],
            // Named, not hidden. A reader is entitled to know which sentences on a page
            // of real figures were written by a model rather than counted.
            'source' => 'Written by Haraan AI from your own figures.',
        ];
    }

    /** Every other sport: what its own scorer actually records, and nothing more. */
    private function otherSportCareer(User $user, string $sport, int $matches): array
    {
        $label = ucfirst($sport);
        $headline = [['label' => 'Matches', 'value' => (string) $matches]];
        $groups = [];

        if ($sport === 'football') {
            $tally = function (string $kind) use ($user): int {
                return (int) MatchEvent::query()
                    ->where('player_id', $user->id)
                    ->where('kind', $kind)
                    ->count();
            };
            // Own goals move the opposition's score; they are not this player's tally.
            $goals = $tally(MatchEvent::GOAL);
            $assists = $tally(MatchEvent::ASSIST);
            $headline[] = ['label' => 'Goals', 'value' => (string) $goals];
            $headline[] = ['label' => 'Assists', 'value' => (string) $assists];
            $groups[] = [
                'title' => 'Attacking',
                'lead' => ['label' => 'Goals', 'value' => (string) $goals],
                'visual' => ($goals + $assists) > 0 ? [
                    'kind' => 'split',
                    'title' => 'Goal involvements',
                    'caption' => null,
                    'segments' => [
                        ['label' => 'Goals', 'value' => $goals],
                        ['label' => 'Assists', 'value' => $assists],
                    ],
                ] : null,
                'stats' => [
                    ['label' => 'Assists', 'value' => (string) $assists],
                    ['label' => 'Involvements', 'value' => (string) ($goals + $assists)],
                    [
                        'label' => 'Per match',
                        'value' => $matches > 0 ? self::num(round(($goals + $assists) / $matches, 2)) : '-',
                    ],
                ],
            ];
        }

        // Volleyball, badminton, basketball, kabaddi and the racket sports all score
        // points per SIDE. There is no per-player figure to withhold — there is none.
        $note = null;
        if ($groups === []) {
            $note = $sport === 'football'
                ? 'No goals or assists recorded in these matches yet.'
                : 'This sport is scored per side, not per player, so matches are the only figure it keeps.';
        }

        return [
            'key' => $sport,
            'label' => $label,
            'matches' => $matches,
            'note' => $note,
            'headline' => $headline,
            'groups' => $groups,
        ];
    }

    /** A rate as it should read, or "-" when there is honestly no number yet. */
    private static function num(?float $value): string
    {
        if ($value === null) {
            return '-';
        }
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') ?: '0';
    }

    private function sportCareer(User $user): array
    {
        $sport = strtolower((string) ($user->primary_sport ?? 'cricket'));
        $pid   = (string) $user->player_id;

        if ($sport === 'cricket' || $sport === '') {
            return [
                'sport'   => 'cricket',
                'matches' => (int) ($user->career_matches ?? 0),
                'runs'    => (int) ($user->career_runs ?? 0),
                'wickets' => (int) ($user->career_wickets ?? 0),
            ];
        }

        // Matches played in THIS sport. career_matches is written by the cricket ball-log
        // replay, so it is always 0 for a footballer no matter how many games they play.
        $matches = $pid === '' ? 0 : LiveMatch::query()
            ->whereRaw('lower(sport) = ?', [$sport])
            ->whereRaw('lower(status) = ?', ['completed'])
            ->where(function ($q) use ($pid): void {
                $q->where('home_squad', 'like', '%"' . $pid . '"%')
                  ->orWhere('away_squad', 'like', '%"' . $pid . '"%');
            })
            ->count();

        if ($sport === 'football') {
            $tally = function (string $kind) use ($user): int {
                return (int) MatchEvent::query()
                    ->where('player_id', $user->id)
                    ->where('kind', $kind)
                    ->count();
            };

            return [
                'sport'   => 'football',
                'matches' => $matches,
                // Own goals deliberately excluded — they move the opposition's score and
                // are not the player's goal tally.
                'goals'   => $tally(MatchEvent::GOAL),
                'assists' => $tally(MatchEvent::ASSIST),
            ];
        }

        // Badminton (and anything newer): points are recorded per side, so there is no
        // honest per-player figure to show yet.
        return [
            'sport'   => $sport,
            'matches' => $matches,
        ];
    }

    /**
     * Real, earned achievements — computed from the full match ledger, career batting
     * (high score) and rankings. Locked ones carry a "progress" hint. No invented data.
     */
    private function buildAchievements(?string $pid, User $user): array
    {
        $pid = (string) $pid;
        $ledger = $pid === '' ? collect() : DB::table('match_xp_ledger')
            ->where('player_id', $pid)->orderBy('awarded_at')->get(['won', 'mom']);

        $matches = $ledger->count();
        $wins = $ledger->filter(fn ($r) => (bool) $r->won)->count();
        $moms = $ledger->filter(fn ($r) => (bool) $r->mom)->count();
        $bestStreak = 0; $run = 0;
        foreach ($ledger as $r) {
            if ((bool) $r->won) { $run++; $bestStreak = max($bestStreak, $run); } else { $run = 0; }
        }

        $hs = 0;
        if ($pid !== '') {
            $cb = DB::table('player_career_batting')->where('player_id', $pid)->first();
            $hs = (int) ($cb->high_score ?? 0);
        }
        $wickets = (int) ($user->career_wickets ?? 0);
        $rankD = $user->rank_district;

        $mk = fn (string $key, string $icon, string $label, string $tier, bool $unlocked, ?string $progress = null): array =>
            compact('key', 'icon', 'label', 'tier', 'unlocked', 'progress');

        return [
            $mk('first_match', 'SportsCricket', 'First Match', 'bronze', $matches >= 1, $matches >= 1 ? null : '0/1'),
            $mk('first_win', 'EmojiEvents', 'First Win', 'bronze', $wins >= 1),
            $mk('fifty', 'Star', 'Half Century', 'silver', $hs >= 50, $hs >= 50 ? null : "$hs/50"),
            $mk('century', 'WorkspacePremium', 'First Century', 'gold', $hs >= 100, $hs >= 100 ? null : "$hs/100"),
            $mk('mom', 'MilitaryTech', 'Man of the Match', 'silver', $moms >= 1),
            $mk('mvp5', 'MilitaryTech', 'MVP x5', 'gold', $moms >= 5, $moms >= 5 ? null : "$moms/5"),
            $mk('streak5', 'Whatshot', '5-Win Streak', 'gold', $bestStreak >= 5, $bestStreak >= 5 ? null : "$bestStreak/5"),
            $mk('veteran', 'Shield', '10 Matches', 'silver', $matches >= 10, $matches >= 10 ? null : "$matches/10"),
            $mk('top100', 'TrendingUp', 'District Top 100', 'bronze', $rankD !== null && $rankD <= 100),
            $mk('wkts50', 'SportsCricket', '50 Wickets', 'gold', $wickets >= 50, $wickets >= 50 ? null : "$wickets/50"),
        ];
    }

    /**
     * Create / complete the ActionBoard player profile. The saving hook on the
     * User model mints a structured Player ID once state + district are present.
     * POST /api/players/profile
     */
    public function saveProfile(Request $request): JsonResponse
    {
        $user = $request->attributes->get('auth_user');
        if (!$user instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $sports = array_keys(User::SPORT_REQUIRED_ATTRS);

        $validated = $request->validate([
            'name'             => ['required', 'string', 'max:255'],
            // Nullable so older clients (and existing accounts) keep saving fine — the
            // app asks for one, but a profile without a handle is still valid.
            'username'         => ['nullable', 'string', 'max:30'],
            'state'            => ['required', 'string', 'max:255'],
            'district'         => ['required', 'string', 'max:255'],
            // Multi-sport: the chosen sport drives which attributes are required (below).
            'primary_sport'    => ['required', 'string', 'in:' . implode(',', $sports)],
            'sport_attributes' => ['required', 'array'],
            // Crex-style "About" fields — optional so older clients still work.
            'gender'        => ['nullable', 'string', 'in:Male,Female,Other'],
            'date_of_birth' => ['nullable', 'date'],
            'birth_place'   => ['nullable', 'string', 'max:255'],
            'height'        => ['nullable', 'string', 'max:50'],
            'nationality'   => ['nullable', 'string', 'max:100'],
            // Instagram-style account privacy, chosen at profile creation. Private hides the
            // player's posts from the public Home feed (and their profile from the public).
            // Nullable so older clients that never send it keep their current setting.
            'is_private'    => ['nullable', 'boolean'],
        ]);

        // Handle: validated here rather than via a `unique:` rule so the shape complaint
        // and the taken complaint come back as distinct, specific messages. Re-checked on
        // save even though the app checks availability live — the live check is a
        // courtesy, this is the guarantee (two people can claim the same handle between
        // one keystroke and the next).
        $username = User::normalizeUsername($validated['username'] ?? null);
        if ($username !== '') {
            if ($reason = User::usernameRejection($username)) {
                throw ValidationException::withMessages(['username' => $reason]);
            }
            if (!User::usernameIsFree($username, (int) $user->id)) {
                throw ValidationException::withMessages(['username' => 'That username is already taken.']);
            }
        }

        $sport = $validated['primary_sport'];
        $attrsIn = $validated['sport_attributes'];

        // Keep only this sport's known keys, and require each to be a non-empty string.
        $attrs = [];
        foreach (User::SPORT_REQUIRED_ATTRS[$sport] as $key) {
            $value = is_string($attrsIn[$key] ?? null) ? trim($attrsIn[$key]) : '';
            if ($value === '') {
                throw ValidationException::withMessages([
                    "sport_attributes.$key" => "Missing $key for $sport.",
                ]);
            }
            $attrs[$key] = mb_substr($value, 0, 100);
        }

        // Map the chosen state/district onto the canonical org tree so the user
        // gets a home organization (drives district leaderboards + future scoping).
        $orgId = \App\Support\OrganizationResolver::districtUnitId($validated['state'], $validated['district']);

        $user->update([
            'name'             => $validated['name'],
            // Never clear an existing handle just because a client omitted the field.
            'username'         => $username !== '' ? $username : $user->username,
            'state'            => $validated['state'],
            'district'         => $validated['district'],
            'organization_id'  => $orgId,
            'primary_sport'    => $sport,
            'sport_attributes' => $attrs,
            // Mirror cricket into the legacy columns so existing screens/leaderboards keep working.
            'player_role'   => $sport === 'Cricket' ? $attrs['role'] : $user->player_role,
            'batting_style' => $sport === 'Cricket' ? $attrs['batting'] : $user->batting_style,
            'bowling_style' => $sport === 'Cricket' ? $attrs['bowling'] : $user->bowling_style,
            'gender'        => $validated['gender'] ?? $user->gender,
            'date_of_birth' => $validated['date_of_birth'] ?? $user->date_of_birth,
            'birth_place'   => $validated['birth_place'] ?? $user->birth_place,
            'height'        => $validated['height'] ?? $user->height,
            'nationality'   => $validated['nationality'] ?? $user->nationality,
            // Public account = posts eligible for the Home feed. Only touched when the
            // client actually sends the choice, so an omitted field never flips privacy.
            'privacy_public_profile' => $request->has('is_private')
                ? ! $request->boolean('is_private')
                : $user->privacy_public_profile,
            'is_guest'      => false,
        ]);

        // Mirror the home org into the membership pivot as the primary unit.
        if ($orgId !== null) {
            $user->organizations()->syncWithoutDetaching([$orgId => ['is_primary' => true]]);
        }
        $user->refresh();

        return response()->json([
            'message'          => 'Player profile saved',
            'player_id'        => $user->player_id,
            'username'         => $user->username,
            'profile_complete' => $user->isActionboardProfileComplete(),
            'name'             => $user->name,
            'state'            => $user->state,
            'district'         => $user->district,
            'primary_sport'    => $user->primary_sport,
            'sport_attributes' => $user->sport_attributes,
            'is_private'       => ! $user->privacy_public_profile,
            'about'            => $this->aboutPayload($user),
        ]);
    }

    /**
     * Inline edit of just the display name + bio (the profile's Edit button). Lighter than
     * saveProfile, which requires the full setup payload (state/district/sport/attributes).
     * POST /api/players/profile/basics
     */
    public function updateBasics(Request $request): JsonResponse
    {
        $user = $request->attributes->get('auth_user');
        if (! $user instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:160'],
        ]);

        $user->update([
            'name' => trim($validated['name']),
            'bio' => isset($validated['bio']) ? trim((string) $validated['bio']) : null,
        ]);

        return response()->json([
            'name' => $user->name,
            'bio' => $user->bio,
        ]);
    }

    /**
     * Upload / replace the logged-in player's profile photo.
     * POST /api/players/avatar  (multipart: avatar=<image>)
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $user = $request->attributes->get('auth_user');
        if (!$user instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'], // 4 MB
        ]);

        // Replace any previous upload so we don't orphan files on the public disk.
        $previous = $user->avatar;
        if (is_string($previous) && str_starts_with($previous, '/storage/')) {
            \Illuminate\Support\Facades\Storage::disk('public')
                ->delete(substr($previous, strlen('/storage/')));
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $url = '/storage/' . $path;

        $user->update(['avatar' => $url]);

        return response()->json([
            'message' => 'Profile photo updated',
            'avatar'  => $url,
            'url'     => $url,
        ]);
    }

    /**
     * The Crex-style "About" block for a user.
     */
    private function aboutPayload(User $user): array
    {
        return [
            'gender'        => $user->gender,
            'date_of_birth' => $user->date_of_birth?->format('Y-m-d'),
            'birth_place'   => $user->birth_place,
            'height'        => $user->height,
            'nationality'   => $user->nationality,
        ];
    }

    /**
     * Resolve a registered player by their member ID.
     * GET /api/players/lookup?playerId=HRN00042
     */
    public function lookup(Request $request): JsonResponse
    {
        $playerId = trim((string) $request->query('playerId', ''));
        if ($playerId === '') {
            return response()->json(['error' => 'playerId is required'], 422);
        }

        // Accepts EITHER a Player ID or a username, so the old exact-ID flow keeps
        // working while a handle (with or without a leading @) resolves the same way.
        $handle = User::normalizeUsername(ltrim($playerId, '@'));

        $user = User::query()
            ->where('is_guest', false)
            ->where(function ($q) use ($playerId, $handle): void {
                $q->where('player_id', $playerId);
                if ($handle !== '') {
                    $q->orWhere('username', $handle);
                }
            })
            ->first();

        if ($user === null) {
            return response()->json(['error' => 'No player with that ID'], 404);
        }

        return response()->json($this->playerCard($user));
    }

    /**
     * Is this handle free? GET /api/players/username-available?username=…
     *
     * Answers shape and availability separately so the app can say WHY, rather than
     * greying out a button. Never reveals anything about the holder of a taken handle.
     */
    public function usernameAvailable(Request $request): JsonResponse
    {
        $user = $request->attributes->get('auth_user');
        $raw = (string) $request->query('username', '');
        $normalized = User::normalizeUsername(ltrim(trim($raw), '@'));

        if ($normalized === '') {
            return response()->json(['available' => false, 'reason' => 'Enter a username.'], 200);
        }
        if ($reason = User::usernameRejection($normalized)) {
            return response()->json(['available' => false, 'username' => $normalized, 'reason' => $reason], 200);
        }

        $free = User::usernameIsFree($normalized, $user instanceof User ? (int) $user->id : null);

        return response()->json([
            'available' => $free,
            'username'  => $normalized,
            'reason'    => $free ? null : 'That username is already taken.',
        ]);
    }

    /**
     * Find players to add to a squad. GET /api/players/search?q=…
     *
     * This is the reason usernames exist: before it, building a side meant typing each
     * teammate's Player ID (HRN-000123) from memory. Matches username first (prefix,
     * then contains), then name, then an exact Player ID.
     *
     * Honours `privacy_discoverable`: a player who has opted out never appears here.
     * They can still be added by their exact Player ID, which only someone they gave it
     * to would know.
     */
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['results' => []]);
        }

        $handle = User::normalizeUsername(ltrim($q, '@'));
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $handle) . '%';
        $prefix = str_replace(['%', '_'], ['\%', '\_'], $handle) . '%';

        $me = $request->attributes->get('auth_user');

        $rows = User::query()
            ->where('is_guest', false)
            ->where(function ($sub) use ($q): void {
                // Opted-out players are excluded, but the column is nullable on every
                // account created before the privacy toggles shipped — treat null as
                // discoverable so the directory isn't empty.
                $sub->whereNull('privacy_discoverable')->orWhere('privacy_discoverable', true);
            })
            ->where(function ($sub) use ($like, $q): void {
                $sub->where('username', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('player_id', $q);
            })
            ->when($me instanceof User, fn ($query) => $query->where('id', '!=', $me->id))
            // Prefix matches on the handle first — typing "vir" should surface @virat
            // before someone whose surname merely contains those letters.
            ->orderByRaw('CASE WHEN username LIKE ? THEN 0 WHEN username IS NOT NULL THEN 1 ELSE 2 END', [$prefix])
            ->orderByDesc('ranked_xp')
            ->limit(20)
            ->get();

        return response()->json([
            'results' => $this->playerCards($rows, $me instanceof User ? $me : null),
        ]);
    }

    /**
     * Follow a player.
     *
     * Idempotent and self-reporting: the response always carries the resulting
     * state and follower count, so the client can settle the button from the
     * server's answer instead of guessing — which is what stops a double-tap on a
     * slow connection leaving the UI out of sync with the database.
     */
    public function follow(Request $request, string $playerId): JsonResponse
    {
        $me = $request->attributes->get('auth_user');

        if (! $me instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $target = $this->resolvePlayer($playerId);

        if ($target === null) {
            return response()->json(['error' => 'Player not found'], 404);
        }

        if (! $me->follow($target)) {
            return response()->json([
                'error' => $target->id === $me->id
                    ? 'You cannot follow yourself'
                    : 'That account cannot be followed',
            ], 422);
        }

        return response()->json($this->followState($target, $me));
    }

    public function unfollow(Request $request, string $playerId): JsonResponse
    {
        $me = $request->attributes->get('auth_user');

        if (! $me instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $target = $this->resolvePlayer($playerId);

        if ($target === null) {
            return response()->json(['error' => 'Player not found'], 404);
        }

        $me->unfollow($target);

        return response()->json($this->followState($target, $me));
    }

    /** Who follows this player. */
    /**
     * Block a player. Instant and private: no review, no notification to them. The
     * model tears down the follow rows in both directions, so this also answers
     * "why did my follower count drop".
     */
    public function block(Request $request, string $playerId): JsonResponse
    {
        $me = $request->attributes->get('auth_user');

        if (! $me instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $target = $this->resolvePlayer($playerId);

        if ($target === null) {
            return response()->json(['error' => 'Player not found'], 404);
        }

        if (! $me->block($target)) {
            return response()->json(['error' => 'You cannot block yourself'], 422);
        }

        return response()->json([
            'player_id'  => $target->player_id,
            'is_blocked' => true,
            // Both sides were severed, so the client's cached follow state is now wrong.
            'is_following' => false,
            'follows_me'   => false,
            'followers_count' => $target->followers()->count(),
        ]);
    }

    /** Lift a block. Follows are not restored — following again is a fresh decision. */
    public function unblock(Request $request, string $playerId): JsonResponse
    {
        $me = $request->attributes->get('auth_user');

        if (! $me instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $target = $this->resolvePlayer($playerId);

        if ($target === null) {
            return response()->json(['error' => 'Player not found'], 404);
        }

        $me->unblock($target);

        return response()->json([
            'player_id'  => $target->player_id,
            'is_blocked' => false,
            'is_following' => $me->isFollowing($target),
            'follows_me'   => $target->isFollowing($me),
            'followers_count' => $target->followers()->count(),
        ]);
    }

    /**
     * Report a player to the moderation queue.
     *
     * One OPEN report per pair: re-reporting the same person while the first is still
     * unreviewed updates that report rather than filling the queue with duplicates a
     * moderator has to read through. The response is the same either way, so the
     * reporter never learns whether their earlier report is still open.
     */
    public function report(Request $request, string $playerId): JsonResponse
    {
        $me = $request->attributes->get('auth_user');

        if (! $me instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $target = $this->resolvePlayer($playerId);

        if ($target === null) {
            return response()->json(['error' => 'Player not found'], 404);
        }

        if ((int) $target->id === (int) $me->id) {
            return response()->json(['error' => 'You cannot report yourself'], 422);
        }

        $data = $request->validate([
            'reason'  => ['required', 'string', \Illuminate\Validation\Rule::in(\App\Models\PlayerReport::REASONS)],
            'details' => ['nullable', 'string', 'max:1000'],
        ]);

        \App\Models\PlayerReport::updateOrCreate(
            [
                'reporter_id' => $me->id,
                'reported_id' => $target->id,
                'status'      => 'open',
            ],
            [
                'reason'  => $data['reason'],
                'details' => $data['details'] ?? null,
            ],
        );

        return response()->json(['reported' => true]);
    }

    public function followers(Request $request, string $playerId): JsonResponse
    {
        return $this->followList($request, $playerId, 'followers');
    }

    /** Who this player follows. */
    public function following(Request $request, string $playerId): JsonResponse
    {
        return $this->followList($request, $playerId, 'following');
    }

    /**
     * GET /api/players/{player}/posts — the player's photo grid, newest first. Public:
     * anyone who can see the profile can see the posts. [mine] tells the client whether
     * to offer delete on each cell.
     */
    public function posts(Request $request, string $playerId): JsonResponse
    {
        $target = $this->resolvePlayer($playerId);
        if ($target === null) {
            return response()->json(['error' => 'Player not found'], 404);
        }

        $me = $request->attributes->get('auth_user');
        $mine = $me instanceof User && $me->id === $target->id;

        $posts = PlayerPost::query()
            ->where('user_id', $target->id)
            ->orderByDesc('id')
            ->limit(120)
            ->get();

        return response()->json([
            'results' => $posts->map(fn (PlayerPost $p) => [
                'id' => (int) $p->id,
                'image' => $p->image_path,
                'caption' => $p->caption,
                'created_at' => $p->created_at?->toIso8601String(),
                'mine' => $mine,
            ])->values(),
        ]);
    }

    /**
     * POST /api/players/posts — add a photo post (one or many images = carousel).
     *
     * Accepts `images[]` (multi) or a single `image` (older clients). The first image is the
     * cover, stored on `player_posts.image_path` so pre-carousel readers keep working; every
     * image also gets a `post_images` row in order.
     */
    public function storePost(Request $request): JsonResponse
    {
        $user = $request->attributes->get('auth_user');
        if (! $user instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'images' => ['sometimes', 'array', 'max:10'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:8192'], // 8 MB each
            'image' => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'caption' => ['nullable', 'string', 'max:300'],
        ]);

        // Normalize to an ordered list of uploaded files.
        $files = $request->file('images');
        if (! is_array($files) || count($files) === 0) {
            $single = $request->file('image');
            $files = $single ? [$single] : [];
        }
        if (count($files) === 0) {
            return response()->json(['error' => 'No image provided'], 422);
        }

        $paths = [];
        foreach ($files as $file) {
            $paths[] = '/storage/' . $file->store('posts', 'public');
        }

        $post = PlayerPost::create([
            'user_id' => $user->id,
            'image_path' => $paths[0],
            'caption' => $request->input('caption'),
        ]);
        foreach ($paths as $i => $p) {
            $post->images()->create(['image_path' => $p, 'position' => $i]);
        }

        return response()->json([
            'id' => (int) $post->id,
            'image' => $post->image_path,
            'images' => $paths,
            'caption' => $post->caption,
            'created_at' => $post->created_at?->toIso8601String(),
            'like_count' => 0,
            'liked' => false,
            'mine' => true,
        ], 201);
    }

    /** POST /api/players/posts/{id}/caption — edit your own post's caption. */
    public function updatePost(Request $request, int $id): JsonResponse
    {
        $user = $request->attributes->get('auth_user');
        if (! $user instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $post = PlayerPost::query()->find($id);
        if ($post === null) {
            return response()->json(['error' => 'Not found'], 404);
        }
        if ((int) $post->user_id !== (int) $user->id) {
            return response()->json(['error' => 'Not your post'], 403);
        }

        $validated = $request->validate([
            'caption' => ['nullable', 'string', 'max:300'],
        ]);
        $caption = isset($validated['caption']) ? trim((string) $validated['caption']) : null;
        $post->update(['caption' => ($caption === '' ? null : $caption)]);

        return response()->json([
            'id' => (int) $post->id,
            'caption' => $post->caption,
        ]);
    }

    /** DELETE /api/players/posts/{id} — remove your own post (file + row). */
    public function destroyPost(Request $request, int $id): JsonResponse
    {
        $user = $request->attributes->get('auth_user');
        if (! $user instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $post = PlayerPost::query()->find($id);
        if ($post === null) {
            return response()->json(['error' => 'Not found'], 404);
        }
        if ((int) $post->user_id !== (int) $user->id) {
            return response()->json(['error' => 'Not your post'], 403);
        }

        // Delete every image file (cover + carousel), then the row (cascade drops post_images).
        $paths = $post->images()->pluck('image_path')->all();
        if (empty($paths)) {
            $paths = [$post->image_path];
        }
        foreach ($paths as $path) {
            if (is_string($path) && str_starts_with($path, '/storage/')) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete(substr($path, strlen('/storage/')));
            }
        }
        $post->delete();

        return response()->json(['deleted' => true]);
    }

    /**
     * GET /api/posts/feed — the Instagram-style Home feed.
     *
     * Recent photo posts from PUBLIC accounts only (privacy_public_profile), newest
     * first, each carrying its author card + like state. A `stories` strip (one entry
     * per recent public poster) rides along in the same payload so the Home screen
     * renders in a single round trip. Optional auth: guests see the feed but every
     * post reads `liked=false` and `mine=false`.
     */
    public function feed(Request $request): JsonResponse
    {
        $me = $request->attributes->get('auth_user');
        $meId = $me instanceof User ? (int) $me->id : null;

        $posts = PlayerPost::query()
            ->with(['user', 'images'])
            ->withCount(['likes', 'comments'])
            ->whereHas('user', function ($q): void {
                $q->where('is_guest', false)
                    // Null = pre-privacy accounts, treated as public (same rule the
                    // player search uses for privacy_discoverable).
                    ->where(function ($sub): void {
                        $sub->whereNull('privacy_public_profile')
                            ->orWhere('privacy_public_profile', true);
                    });
            })
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $likedIds = [];
        $savedIds = [];
        if ($meId !== null && $posts->isNotEmpty()) {
            $postIds = $posts->pluck('id');
            $likedIds = PostLike::query()
                ->where('user_id', $meId)
                ->whereIn('post_id', $postIds)
                ->pluck('post_id')
                ->flip()
                ->all();
            $savedIds = PostSave::query()
                ->where('user_id', $meId)
                ->whereIn('post_id', $postIds)
                ->pluck('post_id')
                ->flip()
                ->all();
        }

        $items = $posts->map(function (PlayerPost $p) use ($likedIds, $savedIds, $meId): array {
            $author = $p->user;

            $images = $p->images->pluck('image_path')->values()->all();
            if (empty($images)) {
                $images = [$p->image_path];
            }

            return [
                'id' => (int) $p->id,
                'image' => $p->image_path,
                'images' => $images,
                'caption' => $p->caption,
                'created_at' => $p->created_at?->toIso8601String(),
                'like_count' => (int) $p->likes_count,
                'liked' => isset($likedIds[$p->id]),
                'comment_count' => (int) $p->comments_count,
                'saved' => isset($savedIds[$p->id]),
                'mine' => $meId !== null && (int) $p->user_id === $meId,
                'author' => [
                    'player_id' => $author?->player_id,
                    'username' => $author?->username,
                    'name' => $author?->name,
                    'avatar' => $author?->avatar,
                ],
            ];
        })->values();

        // One story bubble per recent public poster (their newest post), newest first.
        $stories = $posts
            ->unique('user_id')
            ->take(20)
            ->map(function (PlayerPost $p): array {
                $author = $p->user;

                return [
                    'player_id' => $author?->player_id,
                    'username' => $author?->username,
                    'name' => $author?->name,
                    'avatar' => $author?->avatar,
                    'image' => $p->image_path,
                ];
            })->values();

        return response()->json([
            'stories' => $stories,
            'posts' => $items,
        ]);
    }

    /** POST /api/players/posts/{id}/like — like a post. Idempotent. */
    public function likePost(Request $request, int $id): JsonResponse
    {
        $user = $request->attributes->get('auth_user');
        if (! $user instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $post = PlayerPost::query()->find($id);
        if ($post === null) {
            return response()->json(['error' => 'Not found'], 404);
        }

        PostLike::query()->firstOrCreate([
            'post_id' => (int) $post->id,
            'user_id' => (int) $user->id,
        ]);

        return response()->json([
            'liked' => true,
            'like_count' => (int) $post->likes()->count(),
        ]);
    }

    /** DELETE /api/players/posts/{id}/like — remove your like. Idempotent. */
    public function unlikePost(Request $request, int $id): JsonResponse
    {
        $user = $request->attributes->get('auth_user');
        if (! $user instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $post = PlayerPost::query()->find($id);
        if ($post === null) {
            return response()->json(['error' => 'Not found'], 404);
        }

        PostLike::query()
            ->where('post_id', (int) $post->id)
            ->where('user_id', (int) $user->id)
            ->delete();

        return response()->json([
            'liked' => false,
            'like_count' => (int) $post->likes()->count(),
        ]);
    }

    /** GET /api/posts/{id}/comments — the comment thread, oldest first. Optional auth. */
    public function comments(Request $request, int $id): JsonResponse
    {
        $post = PlayerPost::query()->find($id);
        if ($post === null) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $rows = PostComment::query()
            ->with('user')
            ->where('post_id', $post->id)
            ->orderBy('id')
            ->limit(300)
            ->get();

        return response()->json([
            'results' => $rows->map(fn (PostComment $c) => [
                'id' => (int) $c->id,
                'body' => $c->body,
                'created_at' => $c->created_at?->toIso8601String(),
                'author' => [
                    'player_id' => $c->user?->player_id,
                    'username' => $c->user?->username,
                    'name' => $c->user?->name,
                    'avatar' => $c->user?->avatar,
                ],
            ])->values(),
        ]);
    }

    /** POST /api/players/posts/{id}/comments — add a comment. */
    public function addComment(Request $request, int $id): JsonResponse
    {
        $user = $request->attributes->get('auth_user');
        if (! $user instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:500'],
        ]);
        $body = trim($validated['body']);
        if ($body === '') {
            return response()->json(['error' => 'Empty comment'], 422);
        }

        $post = PlayerPost::query()->find($id);
        if ($post === null) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $comment = PostComment::create([
            'post_id' => (int) $post->id,
            'user_id' => (int) $user->id,
            'body' => $body,
        ]);

        return response()->json([
            'id' => (int) $comment->id,
            'body' => $comment->body,
            'created_at' => $comment->created_at?->toIso8601String(),
            'comment_count' => (int) $post->comments()->count(),
            'author' => [
                'player_id' => $user->player_id,
                'username' => $user->username,
                'name' => $user->name,
                'avatar' => $user->avatar,
            ],
        ], 201);
    }

    /** POST /api/players/posts/{id}/save — bookmark a post. Idempotent. */
    public function savePost(Request $request, int $id): JsonResponse
    {
        $user = $request->attributes->get('auth_user');
        if (! $user instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $post = PlayerPost::query()->find($id);
        if ($post === null) {
            return response()->json(['error' => 'Not found'], 404);
        }

        PostSave::query()->firstOrCreate([
            'post_id' => (int) $post->id,
            'user_id' => (int) $user->id,
        ]);

        return response()->json(['saved' => true]);
    }

    /** DELETE /api/players/posts/{id}/save — remove the bookmark. Idempotent. */
    public function unsavePost(Request $request, int $id): JsonResponse
    {
        $user = $request->attributes->get('auth_user');
        if (! $user instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $post = PlayerPost::query()->find($id);
        if ($post === null) {
            return response()->json(['error' => 'Not found'], 404);
        }

        PostSave::query()
            ->where('post_id', (int) $post->id)
            ->where('user_id', (int) $user->id)
            ->delete();

        return response()->json(['saved' => false]);
    }

    /** Shared body of followers()/following(). */
    private function followList(Request $request, string $playerId, string $relation): JsonResponse
    {
        $target = $this->resolvePlayer($playerId);

        if ($target === null) {
            return response()->json(['error' => 'Player not found'], 404);
        }

        $me = $request->attributes->get('auth_user');

        $rows = $target->{$relation}()
            ->where('is_guest', false)
            ->orderByDesc('player_follows.created_at')
            ->limit(100)
            ->get();

        return response()->json([
            'results' => $this->playerCards($rows, $me instanceof User ? $me : null),
        ]);
    }

    /** Accepts an HRN player id or an @handle, so deep links work either way. */
    private function resolvePlayer(string $playerId): ?User
    {
        $playerId = trim($playerId);

        if (str_starts_with($playerId, '@')) {
            return User::query()
                ->where('username', User::normalizeUsername(ltrim($playerId, '@')))
                ->first();
        }

        return User::query()->where('player_id', $playerId)->first();
    }

    /** @return array{is_following: bool, followers_count: int, player_id: string|null} */
    private function followState(User $target, User $viewer): array
    {
        return [
            'player_id' => $target->player_id,
            'is_following' => $viewer->isFollowing($target),
            'followers_count' => $target->followers()->count(),
        ];
    }

    /**
     * Map a set of players to cards, resolving the viewer's follow state for all of
     * them in ONE query rather than per row — a 20-result search that asks
     * "am I following this one?" twenty times is the classic N+1 that makes a
     * search feel sluggish on a phone.
     *
     * @param  \Illuminate\Support\Collection<int, User>  $players
     * @return array<int, array<string, mixed>>
     */
    private function playerCards($players, ?User $viewer): array
    {
        $followedIds = [];

        if ($viewer !== null && $players->isNotEmpty()) {
            $followedIds = $viewer->following()
                ->whereIn('users.id', $players->pluck('id'))
                ->pluck('users.id')
                ->flip()
                ->all();
        }

        return $players->map(fn (User $u) => $this->playerCard($u, isset($followedIds[$u->id])))->all();
    }

    /** The one shape every player-picker in the app reads. */
    private function playerCard(User $user, ?bool $isFollowing = null): array
    {
        return [
            'player_id' => $user->player_id,
            'username'  => $user->username,
            'name'      => $user->name,
            'district'  => $user->district,
            'state'     => $user->state,
            'avatar'    => $user->avatar,
            // Social signal — a search result with nothing but a name reads dead.
            // These are already on the row, so they cost nothing to include.
            'primary_sport' => $user->primary_sport,
            'matches'       => (int) ($user->career_matches ?? 0),
            'xp'            => (int) ($user->ranked_xp ?? 0),
            'is_following'  => $isFollowing,
        ];
    }
}
