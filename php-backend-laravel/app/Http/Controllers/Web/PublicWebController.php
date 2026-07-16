<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\EventService;
use App\Support\CityResolver;
use Illuminate\Support\Collection;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\Ad;
use App\Models\Event;
use App\Models\LiveMatch;
use App\Models\User;
use App\Models\Venue;

final class PublicWebController extends Controller
{
    public function home(): View
    {
        $events = $this->eventFeed(6);

        $city = CityResolver::selected();
        $listingCount = Event::query()->where('status', 'published')
                ->when($city, fn ($q) => $q->where('city', $city))->count()
            + Venue::query()->where('is_active', true)
                ->when($city, fn ($q) => $q->where('city', $city))->count();

        return view('site.home', [
            'title' => 'Haraan - Home',
            'events' => $events,
            'listingCount' => $listingCount,
        ]);
    }

    public function events(): View
    {
        // The Categories row used to be decorative — ?category= highlighted a card and
        // filtered nothing. The app filters every rail off its selected category
        // (MainScreen.kt `filteredEvents`), so each feed below takes it too.
        $category = $this->selectedCategory();

        $events = $this->eventFeed(8, $category);
        $trending = $this->trendingFeed(8, $category);
        $bannerEvents = app(EventService::class)->getBannerEvents();

        return view('site.events', [
            'title' => 'Events',
            'events' => $events,
            'forYou' => $this->railFeed('for_you', 20, $category),
            'eventsAd' => $this->usableAd('events'),
            'catRow' => $this->categoryCards(),
            'trending' => $trending,
            'bannerEvents' => $bannerEvents,
            'categories' => ['All', 'Concerts', 'Workshops', 'Nightlife', 'Comedy', 'Sports', 'Festivals'],
        ]);
    }

    public function eventDetail(string $id): View
    {
        $event = Event::query()->findOrFail($id);

        return view('site.event', [
            'title' => $event->title,
            'event' => $event,
            'id'    => $id,
        ]);
    }

    public function gamehub(): View
    {
        $city = CityResolver::selected();

        $venues = Venue::query()
            ->where('is_active', true)
            ->when($city, fn ($q) => $q->where('city', $city))
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Venue $v) => $this->decorateVenueCard($v));

        // Real per-sport venue counts for the "Explore by Sport" tiles.
        $sportCounts = Venue::query()
            ->where('is_active', true)
            ->when($city, fn ($q) => $q->where('city', $city))
            ->selectRaw('category, COUNT(*) as c')
            ->groupBy('category')
            ->pluck('c', 'category');

        // Live now: real in-progress matches for the mobile live strip (app parity).
        $liveMatches = LiveMatch::where('status', 'Live')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get()
            ->map(fn (LiveMatch $m) => $this->decorateLiveStrip($m))
            ->all();

        return view('site.gamehub', [
            'title'       => 'GameHub',
            'venues'      => $venues,
            'sportCounts' => $sportCounts,
            'liveMatches' => $liveMatches,
        ]);
    }

    /**
     * Flatten a LiveMatch into a clean two-row scorecard for the GameHub live
     * strip. Wickets are counted from over_summary (a "W" ball), avoiding the
     * heavier inline parser the full ActionBoard page uses.
     */
    private function decorateLiveStrip(LiveMatch $m): array
    {
        $overs = is_array($m->over_summary)
            ? $m->over_summary
            : (json_decode((string) $m->over_summary, true) ?: []);

        $wkts = static function (string $side) use ($overs): int {
            $w = 0;
            foreach ($overs as $o) {
                if (($o['batting'] ?? 'home') !== $side) {
                    continue;
                }
                foreach ((array) ($o['balls'] ?? []) as $b) {
                    if (is_string($b) && strtoupper(trim($b)) === 'W') {
                        $w++;
                    }
                }
            }
            return $w;
        };

        $battingHome = 'home';
        if (! empty($overs)) {
            $battingHome = ($overs[array_key_last($overs)]['batting'] ?? 'home');
        }
        $battingHome = $battingHome === 'home';

        $homeWkts = $wkts('home');
        $awayWkts = $wkts('away');
        $homeBatted = $battingHome || (int) $m->home_score > 0 || $homeWkts > 0;
        $awayBatted = ! $battingHome || (int) $m->away_score > 0 || $awayWkts > 0;

        return [
            'id'          => $m->id,
            'competition' => $m->competition,
            'home' => [
                'abbr'    => $m->home,
                'name'    => $m->home_full ?: $m->home,
                'score'   => $homeBatted ? ($m->home_score . '/' . $homeWkts) : 'Yet to bat',
                'overs'   => $battingHome ? $m->overs : '',
                'batting' => $battingHome,
            ],
            'away' => [
                'abbr'    => $m->away,
                'name'    => $m->away_full ?: $m->away,
                'score'   => $awayBatted ? ($m->away_score . '/' . $awayWkts) : 'Yet to bat',
                'overs'   => $battingHome ? '' : $m->overs,
                'batting' => ! $battingHome,
            ],
        ];
    }

    public function gamehubDetail(string $id): View
    {
        $venue = Venue::query()
            ->where('is_active', true)
            ->with(['reviews' => fn ($q) => $q->where('is_active', true)->latest()])
            ->findOrFail($id);

        return view('site.gamehub-detail', [
            'title' => $venue->name,
            'id'    => $id,
            'venue' => $this->decorateVenueDetail($venue),
        ]);
    }

    public function actionBoard(): View
    {
        $matches = LiveMatch::orderBy('created_at', 'desc')->get()->toArray();
        return view('site.actionboard', ['title' => 'Action Board', 'matches' => $matches]);
    }

    public function actionBoardMatchLive(string $id): View
    {
        $match = LiveMatch::findOrFail($id)->toArray();
        $matches = LiveMatch::orderBy('created_at', 'desc')->get()->toArray();
        $timeline = $match['timeline'] ?? [];
        return view('site.actionboard-match-live', ['title' => 'Live Match', 'match' => $match, 'id' => $id, 'activeTab' => 'live', 'matches' => $matches, 'timeline' => $timeline]);
    }

    public function actionBoardMatchInfo(string $id): View
    {
        $match = LiveMatch::findOrFail($id)->toArray();
        $matches = LiveMatch::orderBy('created_at', 'desc')->get()->toArray();
        $timeline = $match['timeline'] ?? [];
        return view('site.actionboard-match-info', ['title' => 'Match Info', 'match' => $match, 'id' => $id, 'activeTab' => 'info', 'matches' => $matches, 'timeline' => $timeline]);
    }

    public function actionBoardMatchScorecard(string $id): View
    {
        $match = LiveMatch::findOrFail($id)->toArray();
        $matches = LiveMatch::orderBy('created_at', 'desc')->get()->toArray();
        $timeline = $match['timeline'] ?? [];
        return view('site.actionboard-match-scorecard', ['title' => 'Match Scorecard', 'match' => $match, 'id' => $id, 'activeTab' => 'scorecard', 'matches' => $matches, 'timeline' => $timeline]);
    }

    public function login(): View
    {
        return view('site.auth.login', ['title' => 'Login']);
    }

    public function register(): View
    {
        return view('site.auth.register', ['title' => 'Register']);
    }

    public function profile(): View
    {
        return view('site.profile', ['title' => 'My Profile']);
    }

    public function updateProfile(\Illuminate\Http\Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return redirect()->route('site.login');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'player_role' => 'nullable|string|max:50',
            'playing_style' => 'nullable|string|max:50',
        ]);

        $user->update($validated);

        return redirect()->back()->with('success', 'Profile updated successfully.');
    }

    public function getPlayerDetails($playerId)
    {
        $user = \App\Models\User::where('player_id', $playerId)->first();
        if ($user) {
            return response()->json([
                'success' => true,
                'name' => $user->name,
                'role' => $user->player_role ?? 'Unknown',
                'style' => $user->playing_style ?? 'Unknown'
            ]);
        }
        return response()->json(['success' => false]);
    }

    public function showPlayerProfile(string $player_id): View
    {
        $player = \App\Models\User::where('player_id', $player_id)->firstOrFail();
        
        // Find recent matches where this player is in the squad
        $allMatches = \App\Models\LiveMatch::orderBy('created_at', 'desc')->get();
        $recentMatches = [];
        
        foreach ($allMatches as $match) {
            $inHome = is_array($match->home_squad) && collect($match->home_squad)->contains(function ($p) use ($player_id) {
                $id = is_array($p) ? ($p['id'] ?? null) : $p;
                return (string)$id === (string)$player_id;
            });
            $inAway = is_array($match->away_squad) && collect($match->away_squad)->contains(function ($p) use ($player_id) {
                $id = is_array($p) ? ($p['id'] ?? null) : $p;
                return (string)$id === (string)$player_id;
            });
            
            if ($inHome || $inAway) {
                $recentMatches[] = $match;
            }
        }
        
        return view('site.player-profile', [
            'title' => $player->name . ' - Player Profile',
            'player' => $player,
            'recentMatches' => $recentMatches
        ]);
    }

    public function search(): View
    {
        $query = request()->input('q', '');
        $type = request()->input('type', 'all');
        
        $city = CityResolver::selected();
        $results = [];
        if ($query !== '') {
            $like = '%' . $query . '%';

            if ($type === 'all' || $type === 'events') {
                $events = Event::query()
                    ->where('status', 'published')
                    ->when($city, fn ($q) => $q->where('city', $city))
                    ->where(function ($w) use ($like) {
                        $w->where('title', 'like', $like)
                            ->orWhere('venue', 'like', $like)
                            ->orWhere('category', 'like', $like);
                    })
                    ->orderBy('date', 'desc')
                    ->limit(12)
                    ->get(['id', 'title', 'category', 'venue']);

                if ($events->isNotEmpty()) {
                    $results['events'] = $events->map(fn (Event $e) => [
                        'id'       => $e->id,
                        'title'    => $e->title,
                        'category' => $e->category ?: 'Event',
                        'venue'    => $e->venue ?: 'Mumbai',
                    ])->all();
                }
            }

            if ($type === 'all' || $type === 'venues') {
                $venues = Venue::query()
                    ->where('is_active', true)
                    ->when($city, fn ($q) => $q->where('city', $city))
                    ->where(function ($w) use ($like) {
                        $w->where('name', 'like', $like)
                            ->orWhere('category', 'like', $like)
                            ->orWhere('location', 'like', $like);
                    })
                    ->orderByDesc('is_featured')
                    ->limit(12)
                    ->get(['id', 'name', 'category', 'location']);

                if ($venues->isNotEmpty()) {
                    $results['venues'] = $venues->map(fn (Venue $v) => [
                        'id'       => $v->id,
                        'title'    => $v->name,
                        'sport'    => $v->category ?: 'Sport',
                        'location' => $v->location ?: 'Mumbai',
                    ])->all();
                }
            }
        }
        
        return view('site.search', [
            'title' => "Search Results for \"$query\"",
            'query' => $query,
            'type' => $type,
            'results' => $results,
        ]);
    }

    /**
     * Lightweight JSON autocomplete for the header search bar.
     *
     * Returns a small, city-scoped set of matching events and venues so the
     * topbar can render live suggestions as the user types. Mirrors the query
     * shape of search() but capped tight for latency.
     */
    public function searchSuggest(Request $request): JsonResponse
    {
        $query = trim((string) $request->input('q', ''));

        if (mb_strlen($query) < 2) {
            return response()->json(['events' => [], 'venues' => []]);
        }

        $city = CityResolver::selected();
        $like = '%' . $query . '%';

        $events = Event::query()
            ->where('status', 'published')
            ->when($city, fn ($q) => $q->where('city', $city))
            ->where(function ($w) use ($like) {
                $w->where('title', 'like', $like)
                    ->orWhere('venue', 'like', $like)
                    ->orWhere('category', 'like', $like);
            })
            ->orderBy('date', 'desc')
            ->limit(5)
            ->get(['id', 'title', 'category', 'venue'])
            ->map(fn (Event $e) => [
                'id'    => $e->id,
                'title' => $e->title,
                'meta'  => trim(($e->category ?: 'Event') . ' · ' . ($e->venue ?: 'Mumbai'), ' ·'),
                'url'   => '/events/' . $e->id,
            ])->all();

        $venues = Venue::query()
            ->where('is_active', true)
            ->when($city, fn ($q) => $q->where('city', $city))
            ->where(function ($w) use ($like) {
                $w->where('name', 'like', $like)
                    ->orWhere('category', 'like', $like)
                    ->orWhere('location', 'like', $like);
            })
            ->orderByDesc('is_featured')
            ->limit(5)
            ->get(['id', 'name', 'category', 'location'])
            ->map(fn (Venue $v) => [
                'id'    => $v->id,
                'title' => $v->name,
                'meta'  => trim(($v->category ?: 'Venue') . ' · ' . ($v->location ?: 'Mumbai'), ' ·'),
                'url'   => '/gamehub/' . $v->id,
            ])->all();

        return response()->json(['events' => $events, 'venues' => $venues]);
    }

    /* ------------------------------------------------------------------ */
    /*  Leaderboard (batting / bowling career boards)                      */
    /* ------------------------------------------------------------------ */

    public function leaderboard(): View
    {
        $scope = request()->query('scope', 'country');
        if (!in_array($scope, ['country', 'state', 'district'], true)) {
            $scope = 'country';
        }

        $states = User::query()
            ->where('is_guest', false)
            ->whereNotNull('state')
            ->where('state', '!=', '')
            ->distinct()
            ->orderBy('state')
            ->pluck('state')
            ->values()
            ->all();

        $districts = User::query()
            ->where('is_guest', false)
            ->whereNotNull('district')
            ->where('district', '!=', '')
            ->distinct()
            ->orderBy('district')
            ->pluck('district')
            ->values()
            ->all();

        $selectedState    = request()->query('state', $states[0] ?? 'Andhra Pradesh');
        $selectedDistrict = request()->query('district', $districts[0] ?? 'Kadapa');

        $scopeFilter = function ($query) use ($scope, $selectedState, $selectedDistrict) {
            $query->where('is_guest', false);
            if ($scope === 'state') {
                $query->where('state', $selectedState);
            } elseif ($scope === 'district') {
                $query->where('district', $selectedDistrict);
            }
            return $query;
        };

        $battingLeaderboard = $scopeFilter(User::query())
            ->where('career_runs', '>', 0)
            ->orderByDesc('career_runs')
            ->limit(50)
            ->get();

        $bowlingLeaderboard = $scopeFilter(User::query())
            ->where('career_wickets', '>', 0)
            ->orderByDesc('career_wickets')
            ->limit(50)
            ->get();

        return view('site.leaderboard', [
            'title'              => 'Leaderboard',
            'scope'              => $scope,
            'states'             => $states,
            'districts'          => $districts,
            'selectedState'      => $selectedState,
            'selectedDistrict'   => $selectedDistrict,
            'battingLeaderboard' => $battingLeaderboard,
            'bowlingLeaderboard' => $bowlingLeaderboard,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  ActionBoard match JSON (polled by the live scoreboards)            */
    /* ------------------------------------------------------------------ */

    public function actionBoardMatchJson(string $id): JsonResponse
    {
        return response()->json(LiveMatch::findOrFail($id));
    }

    public function actionBoardMatchesJson(): JsonResponse
    {
        return response()->json(LiveMatch::orderBy('created_at', 'desc')->get());
    }

    /* ------------------------------------------------------------------ */
    /*  Player directory (squad builder + profile-setup claiming)          */
    /* ------------------------------------------------------------------ */

    public function searchPlayers(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $players = User::query()
            ->where('is_guest', false)
            ->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                    ->orWhere('player_id', 'like', "%{$q}%");
            })
            ->orderBy('name')
            ->limit(10)
            ->get();

        return response()->json($players->map(fn (User $u) => [
            'id'       => $u->player_id,
            'name'     => $u->name,
            'role'     => $u->player_role ?: 'Player',
            'style'    => $u->batting_style ?: ($u->playing_style ?: ''),
            'district' => $u->district ?: '—',
        ])->all());
    }

    public function createGuestPlayer(Request $request): JsonResponse
    {
        $validated = $request->validate(['name' => 'required|string|max:255']);
        $name = trim($validated['name']);
        if ($name === '') {
            return response()->json(['success' => false], 422);
        }

        $guest = User::create([
            'name'          => $name,
            'email'         => 'guest_' . Str::random(16) . '@guest.haraan',
            'password'      => Hash::make(Str::random(24)),
            'role'          => 'user',
            'status'        => 'active',
            'is_guest'      => true,
            'player_role'   => 'All-rounder',
            'playing_style' => 'Unknown',
        ]);

        return response()->json([
            'success' => true,
            'id'      => $guest->player_id,
            'name'    => $guest->name,
            'role'    => $guest->player_role,
            'style'   => $guest->playing_style,
        ]);
    }

    public function getClaimablePlayers(Request $request): JsonResponse
    {
        $name = trim((string) $request->query('name', ''));
        if (mb_strlen($name) < 2) {
            return response()->json([]);
        }

        $guests = User::query()
            ->where('is_guest', true)
            ->where('name', 'like', "%{$name}%")
            ->orderBy('name')
            ->limit(10)
            ->get();

        return response()->json($guests->map(function (User $g) {
            $match = LiveMatch::query()
                ->where('home_squad', 'like', "%{$g->player_id}%")
                ->orWhere('away_squad', 'like', "%{$g->player_id}%")
                ->orderByDesc('created_at')
                ->first();

            $playedWith = $match
                ? ($match->title ?: trim(($match->home ?? '') . ' vs ' . ($match->away ?? '')))
                : 'Guest match record';

            return [
                'id'          => $g->id,
                'name'        => $g->name,
                'player_id'   => $g->player_id,
                'played_with' => $playedWith !== 'vs' ? $playedWith : 'Guest match record',
            ];
        })->all());
    }

    /* ------------------------------------------------------------------ */
    /*  ActionBoard profile setup (cricket onboarding)                     */
    /* ------------------------------------------------------------------ */

    public function showProfileSetupForm(): View
    {
        return view('site.profile-setup', [
            'title' => 'Complete Your Profile',
            'user'  => auth()->user(),
        ]);
    }

    public function saveProfileSetup(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return redirect()->route('site.login');
        }

        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'state'         => 'required|string|max:255',
            'district'      => 'required|string|max:255',
            'batting_style' => 'nullable|string|max:100',
            'bowling_style' => 'nullable|string|max:100',
            'claim_user_id' => 'nullable|integer',
            'photo'         => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);

        // Merge a claimed guest profile's career stats into this account.
        if (!empty($validated['claim_user_id'])) {
            $guest = User::query()
                ->where('id', $validated['claim_user_id'])
                ->where('is_guest', true)
                ->first();

            if ($guest) {
                $user->career_runs          += (int) $guest->career_runs;
                $user->career_balls         += (int) $guest->career_balls;
                $user->career_matches       += (int) $guest->career_matches;
                $user->career_wickets       += (int) $guest->career_wickets;
                $user->career_runs_conceded += (int) $guest->career_runs_conceded;
                $guest->delete();
            }
        }

        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('avatars', 'public');
            $user->avatar = '/storage/' . $path;
        }

        $user->name          = $validated['name'];
        $user->state         = $validated['state'];
        $user->district      = $validated['district'];
        $user->batting_style = $validated['batting_style'] ?? $user->batting_style;
        $user->bowling_style = $validated['bowling_style'] ?? $user->bowling_style;
        $user->player_role   = $user->player_role ?: 'All-rounder';
        $user->primary_sport = $user->primary_sport ?: 'Cricket';

        $attrs = $user->sport_attributes ?? [];
        $attrs['role']    = $attrs['role'] ?? $user->player_role;
        $attrs['batting'] = $validated['batting_style'] ?? ($attrs['batting'] ?? '');
        $attrs['bowling'] = $validated['bowling_style'] ?? ($attrs['bowling'] ?? '');
        $user->sport_attributes = $attrs;

        $user->is_guest = false;
        // The User model's saving hook mints a structured player_id from state + district.
        $user->save();

        return redirect()
            ->route('site.gamehub.actionboard')
            ->with('success', 'Your cricket profile is ready.');
    }

    /* ------------------------------------------------------------------ */
    /*  GameHub venue view-model helpers                                   */
    /* ------------------------------------------------------------------ */

    /** Normalize a stored venue image path into a usable URL. */
    private function venueImageUrl(?string $path): string
    {
        if (!$path) {
            return asset('gamehub.png');
        }
        if (preg_match('/^(http|https):\/\//', $path) || str_starts_with($path, '/')) {
            return $path;
        }
        return asset('storage/' . ltrim($path, '/'));
    }

    /** Compact venue shape for the GameHub browse grid. */
    private function decorateVenueCard(Venue $v): object
    {
        $images = is_array($v->images) ? $v->images : [];

        return (object) [
            'id'       => $v->id,
            'title'    => $v->name,
            'image'    => $this->venueImageUrl($images[0] ?? null),
            'location' => $v->location,
            'category' => $v->category,
            'rating'   => $v->rating ?: '0.0',
            'reviews'  => (int) ($v->reviews_count ?? 0),
            'price'    => (int) ($v->price ?? 0),
            'badge'    => $v->is_featured ? 'Featured' : null,
        ];
    }

    /** Full venue shape for the GameHub detail page. */
    private function decorateVenueDetail(Venue $v): object
    {
        $images  = is_array($v->images) ? $v->images : [];
        $gallery = array_values(array_map(
            fn ($p) => $this->venueImageUrl($p),
            array_slice($images, 1)
        ));

        $amenities = is_array($v->amenities) ? $v->amenities : [];

        // The detail scheduler needs at least one sport, each with a bookable court.
        // Courts are real, sport-aware units now: group them by the sports each hosts so the
        // scheduler's "pick sport → pick court" flow matches what the venue actually has.
        $sports = $v->sportsList();
        if ($sports === []) {
            $sports = [$v->category ?: 'Cricket'];
        }

        $courts = [];
        foreach ($v->courtsBySport() as $sport => $list) {
            $names = array_map(fn ($c) => $c->name, $list);
            if ($names !== []) {
                $courts[$sport] = array_values($names);
            }
        }
        // Every offered sport must resolve to at least one court or the scheduler breaks.
        foreach ($sports as $sport) {
            if (empty($courts[$sport])) {
                $courts[$sport] = ['Court 1'];
            }
        }

        // Per-court hourly rate keyed by court name (null → venue price). The scheduler prices
        // each slot by the selected court so a premium pitch costs more than a practice court.
        $basePrice = (int) ($v->price ?? 0);
        $courtPrices = [];
        $courtPeak = [];
        foreach ($v->courts as $c) {
            $courtPrices[$c->name] = $c->price ?? $basePrice;
            // Peak pricing for the scheduler: applied by time window (evenings cost more). The
            // backend stays authoritative on weekday precision when a booking is actually made.
            if ($c->peak_price !== null && $c->peak_start !== null && $c->peak_end !== null) {
                $courtPeak[$c->name] = [
                    'price' => (int) $c->peak_price,
                    'start' => $c->peak_start,
                    'end' => $c->peak_end,
                ];
            }
        }

        $reviewsList = $v->reviews->map(fn ($r) => (object) [
            'user'    => $r->name,
            'date'    => $r->ago ?: 'recently',
            'rating'  => (int) $r->rating,
            'comment' => $r->text ?: '',
        ])->all();

        return (object) [
            'id'           => $v->id,
            'title'        => $v->name,
            'image'        => $this->venueImageUrl($images[0] ?? null),
            'gallery'      => $gallery,
            'location'     => $v->location,
            'category'     => $v->category,
            'rating'       => $v->rating ?: '0.0',
            'reviews'      => (int) ($v->reviews_count ?? 0),
            'reviews_list' => $reviewsList,
            'price'        => (int) ($v->price ?? 0),
            'hours'        => $v->displayHours() ?: '6:00 AM – 11:00 PM',
            'cancellation' => $v->cancellationText(),
            'badge'        => $v->is_featured ? 'Featured' : null,
            'description'  => $v->about ?: 'A premium sports facility with well-maintained playing surfaces and modern amenities.',
            'amenities'    => $amenities,
            'sports'       => $sports,
            'courts'       => $courts,
            'court_prices' => $courtPrices,
            'court_peak'   => $courtPeak,
        ];
    }

    /**
     * The "Trending" rail. Now the admin's `trending` placement, via {@see railFeed()},
     * because that is what the app's row is (MainScreen.kt `trendingEvents`) and the
     * two must agree.
     *
     * This replaces a version ranked by real ticket sales, which was the more honest
     * reading of the word but rendered nothing: no event has a booking yet, so the
     * whole section hid itself and the website was simply missing a row the app has.
     * The tag is a real editorial signal — an admin picked those events — but note the
     * label promises popularity it isn't measuring. If the sales ranking should come
     * back once bookings exist, it belongs on top of the placement, not instead of it.
     *
     * @return Collection<int, Event>
     */
    private function trendingFeed(int $limit = 8, ?string $category = null): Collection
    {
        return $this->railFeed('trending', $limit, $category);
    }

    /** The category the viewer picked, or null for "All" (= no filter). */
    private function selectedCategory(): ?string
    {
        $category = trim((string) request()->query('category', ''));

        return ($category === '' || strcasecmp($category, 'All') === 0) ? null : $category;
    }

    /**
     * The sponsored slot at the top of the Events feed — the app's AdSpaceBanner.
     *
     * Returns the creative only if it can actually carry the slot: a bare title with
     * no image and no link renders as a dead "Shop Now" and is worse than an empty
     * space. The app falls back to a bundled sample ad when the API has nothing; the
     * public site must not, because that invents an advertiser.
     */
    private function usableAd(string $placement): ?Ad
    {
        $ad = Ad::query()
            ->where('placement', $placement)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->orderBy('sort_order')
            ->first();

        if ($ad === null || trim((string) $ad->title) === '') {
            return null;
        }

        // Needs something to look at or somewhere to go — otherwise it's just noise.
        $hasImage = trim((string) $ad->image_url) !== '';
        $hasLink  = trim((string) $ad->link_url) !== '';

        return ($hasImage || $hasLink) ? $ad : null;
    }

    /**
     * The Categories cards — "All" plus the categories that actually have events,
     * biggest first, with real counts.
     *
     * Built from the data rather than hardcoded like the app's row, which names
     * Concerts / Standup and shows "245 Events" / "54 Shows". Neither is real: the
     * admin's events are categorised "Music" and "GENERAL", so those cards read
     * "0 events" on the web and filter to an empty list in the app. A row that
     * advertises zero is worse than no row — this one can only show what exists.
     *
     * @return list<array{title: string, href: string, stat: string, on: bool}>
     */
    private function categoryCards(int $limit = 2): array
    {
        $active = (string) request()->query('category', 'All');

        $cards = [[
            'title' => 'All',
            'href'  => '/events',
            'stat'  => Event::query()->where('status', 'published')->count() . ' Total',
            'on'    => $active === 'All',
        ]];

        $top = Event::query()
            ->where('status', 'published')
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->selectRaw('category, count(*) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();

        foreach ($top as $row) {
            $cards[] = [
                'title' => ucfirst(strtolower((string) $row->category)),
                'href'  => '/events?category=' . urlencode((string) $row->category),
                'stat'  => $row->total . ' ' . ($row->total === 1 ? 'Event' : 'Events'),
                'on'    => strcasecmp($active, (string) $row->category) === 0,
            ];
        }

        return $cards;
    }

    /**
     * One curated rail — `for_you` or `trending` — mirroring the app's rule
     * (MainScreen.kt `forYouEvents` / `trendingEvents`): take the same base list the
     * app reads from /api/events (newest first), float the viewer's city to the top
     * WITHOUT filtering other cities out, then narrow to the rail's placement.
     * Untagged events show everywhere, and an empty rail falls back to the full list
     * rather than rendering blank.
     *
     * NB this deliberately does NOT reuse {@see eventFeed()}, which hard-filters to
     * the selected city — the app floats, it doesn't filter, and the rails have to
     * match it.
     *
     * One deliberate divergence from the app: `published` only. /api/events applies
     * no status filter, so the app would surface a draft; the public site must not.
     */
    private function railFeed(string $rail, int $limit = 20, ?string $category = null): Collection
    {
        $city = CityResolver::selected();

        $events = Event::query()
            ->where('status', 'published')
            ->when($category, fn ($q) => $q->where('category', $category))
            ->orderByDesc('created_at')
            ->take($limit)
            ->get();

        // Local-first. PHP 8's sort is stable, so the newest-first order survives
        // inside each group — same as the app's sortedByDescending.
        if ($city !== null) {
            $events = $events
                ->sortByDesc(fn (Event $e): int => strcasecmp((string) $e->city, $city) === 0 ? 1 : 0)
                ->values();
        }

        // A poster-less event renders the bv-white placeholder — a logo on black. Both
        // rails are poster-led, so both need real artwork. The event still shows in
        // Explore Nearby, which leads with text.
        $events = $events->filter(fn (Event $e): bool => is_array($e->images) && count($e->images) > 0)->values();

        $tagged = $events->filter(function (Event $e) use ($rail): bool {
            $placements = $e->placements;

            return empty($placements) || in_array($rail, $placements, true);
        })->values();

        return $tagged->isNotEmpty() ? $tagged : $events;
    }

    private function eventFeed(int $limit = 6, ?string $category = null): Collection
    {
        $city = CityResolver::selected();

        $events = Event::query()
            ->where('status', 'published')
            ->when($city, fn ($q) => $q->where('city', $city))
            ->when($category, fn ($q) => $q->where('category', $category))
            ->orderBy('date', 'desc')
            ->take($limit)
            ->get();

        if ($events->isNotEmpty()) {
            return $events;
        }

        // A filtered-to-empty result is a real answer ("no Comedy in Hyderabad"), not
        // a reason to fall through to the sample data below.
        if ($category !== null) {
            return collect();
        }

        // When a city is selected but has no events, show its empty state
        // rather than sample data from other cities.
        if ($city !== null) {
            return collect();
        }

        $now = now();

        return collect([
            (object) [
                'id' => 1,
                'title' => 'Zomato Feeding India ft. Dua Lipa',
                'category' => 'Music',
                'venue' => 'MMRDA Grounds, BKC',
                'date' => $now->copy()->addDays(10),
                'price' => 4500,
                'images' => ['/events.png'],
                'status' => 'published',
            ],
            (object) [
                'id' => 2,
                'title' => 'Weekend Game Slots',
                'category' => 'Sports',
                'venue' => 'Turf Arena',
                'date' => $now->copy()->addDays(3),
                'price' => 300,
                'images' => ['/events.png'],
                'status' => 'published',
            ],
            (object) [
                'id' => 3,
                'title' => 'Stand-up Open Mic Night',
                'category' => 'Comedy',
                'venue' => 'South Bombay Studio',
                'date' => $now->copy()->addDays(6),
                'price' => 799,
                'images' => ['/events.png'],
                'status' => 'published',
            ],
            (object) [
                'id' => 4,
                'title' => 'Creator Workshop Series',
                'category' => 'Workshops',
                'venue' => 'Taj Lands End',
                'date' => $now->copy()->addDays(12),
                'price' => 1200,
                'images' => ['/events.png'],
                'status' => 'published',
            ],
        ])->take($limit);
    }
}
