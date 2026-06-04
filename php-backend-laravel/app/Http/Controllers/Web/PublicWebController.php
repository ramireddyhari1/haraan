<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\EventService;
use Illuminate\Support\Collection;
use Illuminate\Contracts\View\View;
use App\Models\Event;
use App\Models\LiveMatch;

final class PublicWebController extends Controller
{
    public function home(): View
    {
        $events = $this->eventFeed(6);

        return view('site.home', ['title' => 'Book & Vibe - Home', 'events' => $events]);
    }

    public function events(): View
    {
        $events = $this->eventFeed(8);
        $bannerEvents = app(EventService::class)->getBannerEvents();

        return view('site.events', [
            'title' => 'Events',
            'events' => $events,
            'bannerEvents' => $bannerEvents,
            'categories' => ['All', 'Concerts', 'Workshops', 'Nightlife', 'Comedy', 'Sports', 'Festivals'],
        ]);
    }

    public function eventDetail(string $id): View
    {
        return view('site.event', ['title' => 'Event Details', 'id' => $id]);
    }

    public function gamehub(): View
    {
        return view('site.gamehub', ['title' => 'GameHub']);
    }

    public function gamehubDetail(string $id): View
    {
        return view('site.gamehub-detail', ['title' => 'GameHub Detail', 'id' => $id]);
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
        
        // Mock search results
        $results = [];
        if ($query) {
            if ($type === 'all' || $type === 'events') {
                $results['events'] = [
                    ['id' => 1, 'title' => 'Zomato Feeding India ft. Dua Lipa', 'category' => 'Music', 'venue' => 'MMRDA Grounds, BKC'],
                    ['id' => 2, 'title' => 'Tech Conference 2026', 'category' => 'Workshops', 'venue' => 'Taj Lands End'],
                ];
            }
            if ($type === 'all' || $type === 'venues') {
                $results['venues'] = [
                    ['id' => 1, 'title' => 'Cricket Ground Mumbai', 'sport' => 'Cricket', 'location' => 'Bombay Gymkhana'],
                    ['id' => 2, 'title' => 'Football Arena', 'sport' => 'Football', 'location' => 'Cooperage Ground'],
                ];
            }
        }
        
        return view('site.search', [
            'title' => "Search Results for \"$query\"",
            'query' => $query,
            'type' => $type,
            'results' => $results,
        ]);
    }

    private function eventFeed(int $limit = 6): Collection
    {
        $events = Event::query()
            ->where('status', 'published')
            ->orderBy('date', 'desc')
            ->take($limit)
            ->get();

        if ($events->isNotEmpty()) {
            return $events;
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
