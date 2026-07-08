<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MatchXpLedger;
use App\Models\User;
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

        return response()->json([
            'id'               => $user->id,
            'player_id'        => $pid,
            'name'             => $user->name,
            'avatar'           => $user->avatar,
            'district'         => $user->district,
            'state'            => $user->state,
            'player_role'      => $user->player_role,
            'batting_style'    => $user->batting_style,
            'bowling_style'    => $user->bowling_style,
            'primary_sport'    => $user->primary_sport,
            'sport_attributes' => $user->sport_attributes,
            'is_organizer'     => (bool) ($user->is_organizer ?? false),
            'profile_complete' => $user->isActionboardProfileComplete(),
            'about'            => $this->aboutPayload($user),

            'ranked_xp'       => (int) ($user->ranked_xp ?? 0),
            'casual_xp'       => (int) ($user->casual_xp ?? 0),
            'trust_score'     => (int) ($user->trust_score ?? 100),
            'month_ranked_xp' => $monthRankedXp,

            'rank_district'   => $user->rank_district,
            'rank_state'      => $user->rank_state,
            'rank_country'    => $user->rank_country,

            'career' => [
                'matches'       => (int) ($user->career_matches ?? 0),
                'runs'          => (int) ($user->career_runs ?? 0),
                'balls'         => (int) ($user->career_balls ?? 0),
                'wickets'       => (int) ($user->career_wickets ?? 0),
                'overs_bowled'  => $user->career_overs_bowled ?? '0.0',
            ],

            'recent_matches'  => $recent,
            'achievements'    => $this->buildAchievements($pid, $user),
        ]);
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
        ]);

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
            'profile_complete' => $user->isActionboardProfileComplete(),
            'name'             => $user->name,
            'state'            => $user->state,
            'district'         => $user->district,
            'primary_sport'    => $user->primary_sport,
            'sport_attributes' => $user->sport_attributes,
            'about'            => $this->aboutPayload($user),
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
     * Resolve a registered player by their Player ID.
     * GET /api/players/lookup?playerId=HRN-AP-KDP-00042
     */
    public function lookup(Request $request): JsonResponse
    {
        $playerId = trim((string) $request->query('playerId', ''));
        if ($playerId === '') {
            return response()->json(['error' => 'playerId is required'], 422);
        }

        $user = User::query()
            ->where('player_id', $playerId)
            ->where('is_guest', false)
            ->first();

        if ($user === null) {
            return response()->json(['error' => 'No player with that ID'], 404);
        }

        return response()->json([
            'player_id' => $user->player_id,
            'name'      => $user->name,
            'district'  => $user->district,
            'state'     => $user->state,
            'avatar'    => $user->avatar,
        ]);
    }
}
