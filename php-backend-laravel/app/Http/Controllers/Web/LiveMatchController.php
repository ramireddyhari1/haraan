<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\LiveMatch;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class LiveMatchController extends Controller
{
    /**
     * Show the form for creating a new match
     */
    public function create(): View
    {
        return view('site.actionboard-create', [
            'title' => 'Create Live Match - Haran Live'
        ]);
    }

    /**
     * Store a newly created match in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'home' => 'required|string|max:255',
            'away' => 'required|string|max:255',
            'total_overs' => 'required|integer|min:1',
            'toss' => 'nullable|string|max:255',
            'competition' => 'nullable|string|max:255',
        ]);

        $match = LiveMatch::create([
            'title' => $validated['home'] . ' Vs ' . $validated['away'],
            'home' => $validated['home'],
            'away' => $validated['away'],
            'home_full' => $validated['home'],
            'away_full' => $validated['away'],
            'competition' => $validated['competition'] ?: ($validated['total_overs'] . ' Over Match'),
            'venue' => 'Custom Match',
            'time' => 'Live',
            'decision' => $validated['toss'] ?? '',
            'status' => 'Live',
            'home_score' => 0,
            'away_score' => 0,
            'overs' => '0.0',
            'crr' => '0.00',
            'batters' => [],
            'bowler' => [],
            'timeline' => [],
            'over_summary' => [],
            'user_id' => auth()->id(),
        ]);

        return redirect()->route('site.gamehub.actionboard');
    }

    /**
     * Show the form for editing the specified match (Control Room).
     */
    public function edit(string $id): View
    {
        $match = LiveMatch::findOrFail($id);
        
        if ($match->user_id !== auth()->id()) {
            abort(403, 'Unauthorized. You can only edit matches you created.');
        }

        return view('site.actionboard-control', [
            'title' => 'Control Room: ' . $match->home . ' vs ' . $match->away,
            'match' => $match
        ]);
    }

    /**
     * Update the specified match in storage.
     */
    public function update(Request $request, string $id): \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $match = LiveMatch::findOrFail($id);

        if ($match->user_id !== auth()->id()) {
            abort(403, 'Unauthorized. You can only update matches you created.');
        }

        $validated = $request->validate([
            'home_score' => 'required|integer',
            'away_score' => 'required|integer',
            'overs' => 'required|string',
            'status' => 'required|string',
            'score_text' => 'nullable|string',
            'crr' => 'nullable|string',
            'decision' => 'nullable|string',
            'timeline_event' => 'nullable|string',
            'striker_name' => 'nullable|string',
            'striker_runs' => 'nullable|integer',
            'striker_balls' => 'nullable|integer',
            'non_striker_name' => 'nullable|string',
            'non_striker_runs' => 'nullable|integer',
            'non_striker_balls' => 'nullable|integer',
            'bowler_name' => 'nullable|string',
            'bowler_figures' => 'nullable|string',
            'bowler_overs' => 'nullable|string',
            'home_squad' => 'nullable|string',
            'away_squad' => 'nullable|string',
            'over_summary' => 'nullable|string',
            'prob_home' => 'nullable|integer',
            'prob_away' => 'nullable|integer',
            'proj_range' => 'nullable|string',
        ]);

        $updateData = [
            'home_score' => $validated['home_score'],
            'away_score' => $validated['away_score'],
            'overs' => $validated['overs'],
            'status' => $validated['status'],
            'score_text' => $validated['score_text'] ?? null,
            'crr' => $validated['crr'] ?? $match->crr,
            'decision' => $validated['decision'] ?? $match->decision,
        ];

        if (isset($validated['prob_home']) || isset($validated['prob_away'])) {
            $updateData['probability'] = [
                'home' => $validated['prob_home'] ?? 50,
                'away' => $validated['prob_away'] ?? 50
            ];
        }

        if (isset($validated['proj_range'])) {
            $updateData['projected_score'] = [
                'range' => $validated['proj_range'],
                'label' => 'as per RR'
            ];
        }

        if (array_key_exists('home_squad', $validated)) {
            $homeSquadIds = $validated['home_squad'] ? array_filter(array_map('trim', explode(',', $validated['home_squad']))) : [];
            $homeSquadData = [];
            if (!empty($homeSquadIds)) {
                $users = \App\Models\User::whereIn('player_id', $homeSquadIds)->get()->keyBy('player_id');
                foreach ($homeSquadIds as $uid) {
                    if ($users->has($uid)) {
                        $homeSquadData[] = ['id' => $uid, 'name' => $users[$uid]->name];
                    } else {
                        // Allow fallback to custom names if not an ID (optional, but good for flexibility)
                        $homeSquadData[] = ['id' => null, 'name' => $uid];
                    }
                }
            }
            $updateData['home_squad'] = $homeSquadData;
        }

        if (array_key_exists('away_squad', $validated)) {
            $awaySquadIds = $validated['away_squad'] ? array_filter(array_map('trim', explode(',', $validated['away_squad']))) : [];
            $awaySquadData = [];
            if (!empty($awaySquadIds)) {
                $users = \App\Models\User::whereIn('player_id', $awaySquadIds)->get()->keyBy('player_id');
                foreach ($awaySquadIds as $uid) {
                    if ($users->has($uid)) {
                        $awaySquadData[] = ['id' => $uid, 'name' => $users[$uid]->name];
                    } else {
                        $awaySquadData[] = ['id' => null, 'name' => $uid];
                    }
                }
            }
            $updateData['away_squad'] = $awaySquadData;
        }

        // Handle over summary
        if (!empty($validated['over_summary'])) {
            $decoded = json_decode($validated['over_summary'], true);
            if (is_array($decoded)) {
                $updateData['over_summary'] = $decoded;
            }
        }

        // Handle batters
        $batters = [];
        if (!empty($validated['striker_name'])) {
            $batters[] = [
                'name' => $validated['striker_name'],
                'runs' => $validated['striker_runs'] ?? 0,
                'balls' => $validated['striker_balls'] ?? 0,
            ];
        }
        if (!empty($validated['non_striker_name'])) {
            $batters[] = [
                'name' => $validated['non_striker_name'],
                'runs' => $validated['non_striker_runs'] ?? 0,
                'balls' => $validated['non_striker_balls'] ?? 0,
            ];
        }
        if (!empty($batters)) {
            $updateData['batters'] = $batters;
        }

        // Handle bowler
        if (!empty($validated['bowler_name'])) {
            $updateData['bowler'] = [
                'name' => $validated['bowler_name'],
                'figures' => $validated['bowler_figures'] ?? '0-0',
                'overs' => $validated['bowler_overs'] ?? '0.0',
            ];
        }

        // Add timeline event
        if (!empty($validated['timeline_event'])) {
            $timeline = $match->timeline ?? [];
            array_unshift($timeline, [
                'id' => uniqid('tl_', true),
                'time' => $validated['overs'],
                'tag' => 'Update',
                'text' => $validated['timeline_event']
            ]);
            $updateData['timeline'] = $timeline;
        }

        $match->update($updateData);

        if (in_array(strtolower($validated['status']), ['completed', 'finished'])) {
            \App\Services\PlayerStatsService::freezeMatchStats($match);
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Match updated successfully!']);
        }

        return redirect()->route('site.gamehub.actionboard.match', ['id' => $match->id])
                         ->with('success', 'Match updated successfully!');
    }
}
