@extends('site.layout')

@section('content')
<div class="leaderboard-page" style="padding: 40px 0; font-family: 'Plus Jakarta Sans', sans-serif;">
    <!-- Leaderboard Header Card -->
    <div style="background: linear-gradient(135deg, #093727 0%, #0d543c 50%, #111c18 100%); padding: 40px; border-radius: 16px; color: #fff; margin-bottom: 30px; box-shadow: 0 10px 30px rgba(9,55,39,0.15);">
        <span style="font-size: 11px; font-weight: 800; letter-spacing: 1.5px; color: #34d399; text-transform: uppercase;">Haraan Stats & Standings</span>
        <h1 style="margin: 8px 0 12px 0; font-size: 36px; font-weight: 900; color: #fff; border: none; padding: 0; line-height: 1.1;">Leaderboard Rankings</h1>
        <p style="color: rgba(255,255,255,0.85); font-size: 14px; max-width: 600px; margin: 0; line-height: 1.5;">Discover the top batting and bowling performers across districts, states, and the nation. Ranks update in real-time as match scorecards freeze.</p>
    </div>

    <!-- Filter Bar Card -->
    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 20px; margin-bottom: 30px; box-shadow: 0 4px 12px rgba(0,0,0,0.02);">
        <form method="GET" action="{{ route('site.gamehub.leaderboard') }}" id="filterForm" style="display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end;">
            <!-- Scope Selector -->
            <div style="flex: 1; min-width: 180px;">
                <label style="display: block; margin-bottom: 6px; font-size: 12px; font-weight: 800; color: #475569; text-transform: uppercase;">Ranking Scope</label>
                <select name="scope" id="scopeSelect" onchange="toggleScopeInputs(); document.getElementById('filterForm').submit();" style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; font-weight: 700; background: #fff; height: 44px; color: #1e293b;">
                    <option value="country" {{ $scope === 'country' ? 'selected' : '' }}>India (National)</option>
                    <option value="state" {{ $scope === 'state' ? 'selected' : '' }}>State Leaderboard</option>
                    <option value="district" {{ $scope === 'district' ? 'selected' : '' }}>District Leaderboard</option>
                </select>
            </div>

            <!-- State Selector (hidden if country) -->
            <div id="stateInputGroup" style="flex: 1; min-width: 180px; display: {{ $scope === 'country' ? 'none' : 'block' }};">
                <label style="display: block; margin-bottom: 6px; font-size: 12px; font-weight: 800; color: #475569; text-transform: uppercase;">Select State</label>
                <select name="state" onchange="document.getElementById('filterForm').submit();" style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; font-weight: 700; background: #fff; height: 44px; color: #1e293b;">
                    @foreach($states as $st)
                        <option value="{{ $st }}" {{ $selectedState === $st ? 'selected' : '' }}>{{ $st }}</option>
                    @endforeach
                    @if(!collect($states)->contains('Andhra Pradesh'))
                        <option value="Andhra Pradesh" {{ $selectedState === 'Andhra Pradesh' ? 'selected' : '' }}>Andhra Pradesh</option>
                    @endif
                </select>
            </div>

            <!-- District Selector (hidden if country/state) -->
            <div id="districtInputGroup" style="flex: 1; min-width: 180px; display: {{ $scope === 'district' ? 'block' : 'none' }};">
                <label style="display: block; margin-bottom: 6px; font-size: 12px; font-weight: 800; color: #475569; text-transform: uppercase;">Select District</label>
                <select name="district" onchange="document.getElementById('filterForm').submit();" style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; font-weight: 700; background: #fff; height: 44px; color: #1e293b;">
                    @foreach($districts as $dt)
                        <option value="{{ $dt }}" {{ $selectedDistrict === $dt ? 'selected' : '' }}>{{ $dt }}</option>
                    @endforeach
                    @if(!collect($districts)->contains('Kadapa'))
                        <option value="Kadapa" {{ $selectedDistrict === 'Kadapa' ? 'selected' : '' }}>Kadapa</option>
                    @endif
                </select>
            </div>
        </form>
    </div>

    <!-- Leaderboard Switcher Tabs -->
    <div style="display: flex; border-bottom: 2px solid #e2e8f0; margin-bottom: 24px; gap: 30px;">
        <button class="tab-btn active" id="btn-batting" onclick="switchLeaderboard('batting')" style="background: none; border: none; padding: 12px 0; font-size: 15px; font-weight: 800; color: #0d543c; cursor: pointer; border-bottom: 3px solid #0d543c; text-transform: uppercase; letter-spacing: 0.5px;">Batting Leaders</button>
        <button class="tab-btn" id="btn-bowling" onclick="switchLeaderboard('bowling')" style="background: none; border: none; padding: 12px 0; font-size: 15px; font-weight: 800; color: #64748b; cursor: pointer; border-bottom: 3px solid transparent; text-transform: uppercase; letter-spacing: 0.5px;">Bowling Leaders</button>
    </div>

    <!-- BATTING LEADERBOARD -->
    <div id="tab-batting" class="leaderboard-container">
        @if(count($battingLeaderboard) > 0)
            <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.02);">
                <table style="width: 100%; border-collapse: collapse; text-align: left;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                            <th style="padding: 16px 20px; font-size: 12px; font-weight: 800; color: #64748b; text-transform: uppercase; width: 80px;">Rank</th>
                            <th style="padding: 16px 20px; font-size: 12px; font-weight: 800; color: #64748b; text-transform: uppercase;">Player</th>
                            <th style="padding: 16px 20px; font-size: 12px; font-weight: 800; color: #64748b; text-transform: uppercase; text-align: center;">Matches</th>
                            <th style="padding: 16px 20px; font-size: 12px; font-weight: 800; color: #64748b; text-transform: uppercase; text-align: center;">Runs</th>
                            <th style="padding: 16px 20px; font-size: 12px; font-weight: 800; color: #64748b; text-transform: uppercase; text-align: center;">SR</th>
                            <th style="padding: 16px 20px; font-size: 12px; font-weight: 800; color: #64748b; text-transform: uppercase; text-align: center;">Averages</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($battingLeaderboard as $index => $player)
                            @php
                                $rank = $index + 1;
                                $sr = $player->career_balls > 0 ? ($player->career_runs / $player->career_balls * 100) : 0;
                                $avg = $player->career_matches > 0 ? ($player->career_runs / $player->career_matches) : 0;
                            @endphp
                            <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                                <td style="padding: 16px 20px; font-size: 16px; font-weight: 800; color: #1e293b;">
                                    @if($rank === 1) 🥇 @elseif($rank === 2) 🥈 @elseif($rank === 3) 🥉 @else #{{ $rank }} @endif
                                </td>
                                <td style="padding: 16px 20px;">
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <div style="width: 40px; height: 40px; border-radius: 50%; overflow: hidden; background: #e2e8f0; border: 2px solid #fff; box-shadow: 0 2px 6px rgba(0,0,0,0.08); flex-shrink: 0;">
                                            @if($player->avatar)
                                                <img src="{{ asset('storage/' . $player->avatar) }}" style="width: 100%; height: 100%; object-fit: cover;">
                                            @else
                                                <img src="https://api.dicebear.com/7.x/avataaars/svg?seed={{ urlencode($player->name) }}&backgroundColor=b6e3f4" style="width: 100%; height: 100%; object-fit: cover;">
                                            @endif
                                        </div>
                                        <div>
                                            <a href="{{ route('site.player.profile', ['player_id' => $player->player_id]) }}" style="font-weight: 800; color: #1e293b; text-decoration: none; display: flex; align-items: center; gap: 6px;">
                                                {{ $player->name }}
                                                @if($player->career_runs > 500)
                                                    <span title="Verified Star" style="color: #3b82f6; font-size: 13px;">✓</span>
                                                @endif
                                            </a>
                                            <span style="font-size: 11px; color: #64748b; font-weight: 600; display: block; margin-top: 2px;">{{ $player->player_id }} • {{ $player->district }}, {{ $player->state }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td style="padding: 16px 20px; font-weight: 700; text-align: center; color: #475569;">{{ $player->career_matches }}</td>
                                <td style="padding: 16px 20px; font-weight: 900; text-align: center; color: #0d543c; font-size: 16px;">{{ $player->career_runs }}</td>
                                <td style="padding: 16px 20px; font-weight: 700; text-align: center; color: #475569;">{{ number_format($sr, 1) }}</td>
                                <td style="padding: 16px 20px; font-weight: 700; text-align: center; color: #475569;">{{ number_format($avg, 1) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div style="background: #fff; padding: 60px 20px; text-align: center; border-radius: 16px; border: 1px dashed #cbd5e1;">
                <span style="font-size: 40px; display: block; margin-bottom: 16px;">🏏</span>
                <h4 style="margin: 0 0 8px 0; font-size: 16px; font-weight: 800; color: #1e293b;">No Batting Leaders Yet</h4>
                <p style="margin: 0; font-size: 13px; color: #64748b;">Complete and freeze matches to populate rankings.</p>
            </div>
        @endif
    </div>

    <!-- BOWLING LEADERBOARD -->
    <div id="tab-bowling" class="leaderboard-container" style="display: none;">
        @if(count($bowlingLeaderboard) > 0)
            <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.02);">
                <table style="width: 100%; border-collapse: collapse; text-align: left;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                            <th style="padding: 16px 20px; font-size: 12px; font-weight: 800; color: #64748b; text-transform: uppercase; width: 80px;">Rank</th>
                            <th style="padding: 16px 20px; font-size: 12px; font-weight: 800; color: #64748b; text-transform: uppercase;">Player</th>
                            <th style="padding: 16px 20px; font-size: 12px; font-weight: 800; color: #64748b; text-transform: uppercase; text-align: center;">Matches</th>
                            <th style="padding: 16px 20px; font-size: 12px; font-weight: 800; color: #64748b; text-transform: uppercase; text-align: center;">Wickets</th>
                            <th style="padding: 16px 20px; font-size: 12px; font-weight: 800; color: #64748b; text-transform: uppercase; text-align: center;">Overs</th>
                            <th style="padding: 16px 20px; font-size: 12px; font-weight: 800; color: #64748b; text-transform: uppercase; text-align: center;">Economy</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($bowlingLeaderboard as $index => $player)
                            @php
                                $rank = $index + 1;
                                
                                // Economy calculation
                                $oversParts = explode('.', $player->career_overs_bowled ?? '0.0');
                                $completed = isset($oversParts[0]) ? (int)$oversParts[0] : 0;
                                $balls = isset($oversParts[1]) ? (int)$oversParts[1] : 0;
                                $totalBalls = ($completed * 6) + $balls;
                                $econ = $totalBalls > 0 ? ($player->career_runs_conceded / $totalBalls * 6) : 0;
                            @endphp
                            <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                                <td style="padding: 16px 20px; font-size: 16px; font-weight: 800; color: #1e293b;">
                                    @if($rank === 1) 🥇 @elseif($rank === 2) 🥈 @elseif($rank === 3) 🥉 @else #{{ $rank }} @endif
                                </td>
                                <td style="padding: 16px 20px;">
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <div style="width: 40px; height: 40px; border-radius: 50%; overflow: hidden; background: #e2e8f0; border: 2px solid #fff; box-shadow: 0 2px 6px rgba(0,0,0,0.08); flex-shrink: 0;">
                                            @if($player->avatar)
                                                <img src="{{ asset('storage/' . $player->avatar) }}" style="width: 100%; height: 100%; object-fit: cover;">
                                            @else
                                                <img src="https://api.dicebear.com/7.x/avataaars/svg?seed={{ urlencode($player->name) }}&backgroundColor=b6e3f4" style="width: 100%; height: 100%; object-fit: cover;">
                                            @endif
                                        </div>
                                        <div>
                                            <a href="{{ route('site.player.profile', ['player_id' => $player->player_id]) }}" style="font-weight: 800; color: #1e293b; text-decoration: none; display: flex; align-items: center; gap: 6px;">
                                                {{ $player->name }}
                                                @if($player->career_wickets > 25)
                                                    <span title="Verified Star" style="color: #3b82f6; font-size: 13px;">✓</span>
                                                @endif
                                            </a>
                                            <span style="font-size: 11px; color: #64748b; font-weight: 600; display: block; margin-top: 2px;">{{ $player->player_id }} • {{ $player->district }}, {{ $player->state }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td style="padding: 16px 20px; font-weight: 700; text-align: center; color: #475569;">{{ $player->career_matches }}</td>
                                <td style="padding: 16px 20px; font-weight: 900; text-align: center; color: #e11d48; font-size: 16px;">{{ $player->career_wickets }}</td>
                                <td style="padding: 16px 20px; font-weight: 700; text-align: center; color: #475569;">{{ $player->career_overs_bowled }}</td>
                                <td style="padding: 16px 20px; font-weight: 700; text-align: center; color: #475569;">{{ number_format($econ, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div style="background: #fff; padding: 60px 20px; text-align: center; border-radius: 16px; border: 1px dashed #cbd5e1;">
                <span style="font-size: 40px; display: block; margin-bottom: 16px;">🏏</span>
                <h4 style="margin: 0 0 8px 0; font-size: 16px; font-weight: 800; color: #1e293b;">No Bowling Leaders Yet</h4>
                <p style="margin: 0; font-size: 13px; color: #64748b;">Complete and freeze matches to populate rankings.</p>
            </div>
        @endif
    </div>
</div>

<script>
    function toggleScopeInputs() {
        const scope = document.getElementById('scopeSelect').value;
        const stateGroup = document.getElementById('stateInputGroup');
        const districtGroup = document.getElementById('districtInputGroup');
        
        if (scope === 'country') {
            stateGroup.style.display = 'none';
            districtGroup.style.display = 'none';
        } else if (scope === 'state') {
            stateGroup.style.display = 'block';
            districtGroup.style.display = 'none';
        } else if (scope === 'district') {
            stateGroup.style.display = 'block';
            districtGroup.style.display = 'block';
        }
    }

    function switchLeaderboard(tab) {
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.style.color = '#64748b';
            btn.style.borderBottomColor = 'transparent';
        });
        
        const activeBtn = document.getElementById('btn-' + tab);
        activeBtn.style.color = '#0d543c';
        activeBtn.style.borderBottomColor = '#0d543c';

        document.getElementById('tab-batting').style.display = tab === 'batting' ? 'block' : 'none';
        document.getElementById('tab-bowling').style.display = tab === 'bowling' ? 'block' : 'none';
    }
</script>
@endsection
