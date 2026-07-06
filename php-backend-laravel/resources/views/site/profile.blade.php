@extends('site.layout')

@section('content')
@php
    $user = auth()->user();
    
    // Redirect if not logged in
    if(!$user) {
        header("Location: /");
        exit();
    }

    $avatar = $user->avatar ?? null;
    if (!empty($avatar) && !preg_match('/^(http|https):\/\//', $avatar) && strpos($avatar, '/') !== 0) {
        $avatar = asset('storage/' . ltrim($avatar, '/'));
    }
    if(empty($avatar)) {
        $avatar = 'https://ui-avatars.com/api/?name='.urlencode($user->name).'&background=475569&color=fff&size=256';
    }

    $myMatches = \App\Models\LiveMatch::where('user_id', $user->id)->orderBy('id', 'desc')->get();
    $myBookings = $user->bookings ?? [];
@endphp

<div class="profile-container" style="padding: 40px 0;">
    <!-- Profile Header (Native Style) -->
    <div class="detail-header" style="display: flex; align-items: center; gap: 24px; margin-bottom: 48px;">
        <div style="position: relative; width: 120px; height: 120px; flex-shrink: 0;">
            <img src="{{ $avatar }}" alt="{{ $user->name }}" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover; border: 4px solid #fff; box-shadow: 0 10px 30px rgba(18,22,32,0.08);">
            <button class="action-round-btn" style="position: absolute; bottom: 0; right: 0; width: 32px; height: 32px;" title="Edit Avatar">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
            </button>
        </div>
        <div style="flex-grow: 1;">
            <h1 class="detail-header__title" style="margin-bottom: 8px;">{{ $user->name }}</h1>
            <div class="detail-header__meta">
                <span class="detail-meta-item">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6b7280" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg> 
                    {{ $user->email }}
                </span>
                @if($user->phone)
                    <span class="detail-meta-divider">•</span>
                    <span class="detail-meta-item">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6b7280" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg> 
                        {{ $user->phone }}
                    </span>
                @endif
                <span class="detail-meta-divider">•</span>
                <span class="detail-badge" style="margin:0; background: #111827;">{{ ucfirst($user->role) }}</span>
            </div>
        </div>
        <div>
            <button class="btn btn--solid" style="background: #111827; color: #fff; display: flex; align-items: center; gap: 8px;" onclick="document.getElementById('editProfileModal').style.display='flex'">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                Edit Profile
            </button>
        </div>
    </div>

    <!-- Edit Profile Modal -->
    <div id="editProfileModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: #fff; padding: 32px; border-radius: 16px; width: 100%; max-width: 480px; box-shadow: 0 20px 40px rgba(0,0,0,0.2);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                <h3 style="margin: 0; font-size: 20px; font-weight: 800;">Edit Profile Details</h3>
                <button onclick="document.getElementById('editProfileModal').style.display='none'" style="background: none; border: none; cursor: pointer; color: #6b7280;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
            </div>
            <form action="{{ route('site.profile.update') }}" method="POST">
                @csrf
                @method('PUT')
                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 8px; font-size: 14px; font-weight: 600; color: #374151;">Full Name</label>
                    <input type="text" name="name" value="{{ $user->name }}" style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 15px;" required>
                </div>
                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 8px; font-size: 14px; font-weight: 600; color: #374151;">Email Address</label>
                    <input type="email" name="email" value="{{ $user->email }}" style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 15px;" required>
                </div>
                <div style="margin-bottom: 24px;">
                    <label style="display: block; margin-bottom: 8px; font-size: 14px; font-weight: 600; color: #374151;">Phone Number</label>
                    <input type="tel" name="phone" value="{{ $user->phone }}" style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 15px;">
                </div>
                <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 12px;">
                    <button type="button" onclick="document.getElementById('editProfileModal').style.display='none'" class="btn" style="background: #f1f5f9; color: #475569; border: none;">Cancel</button>
                    <button type="submit" class="btn btn--solid" style="background: #111827; color: #fff; border: none;">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Main Content Two-Column -->
    <div class="detail-two-column">
        
        <!-- Main Column (Events & Matches) -->
        <div>
            
            <!-- Bookings / Events Section -->
            <div class="reviews-section-header">
                <h3>My Event Bookings</h3>
                <a href="{{ url('/events') }}" class="btn btn--solid" style="background: #2563EB; border-color: #2563EB; color: #fff;">Browse Events</a>
            </div>

            @if(count($myBookings) > 0)
                <div style="display: flex; flex-direction: column; gap: 16px; margin-bottom: 48px;">
                    <!-- Placeholder for actual bookings loop, using first as example if array wasn't empty -->
                    @foreach($myBookings as $booking)
                        <div class="detail-card-panel" style="padding: 24px; margin-bottom: 0; display: flex; align-items: center; justify-content: space-between; border-left: 4px solid #2563EB;">
                            <div>
                                <h4 style="margin: 0 0 8px 0; font-size: 18px; font-weight: 800; color: #111827;">Booking #{{ $booking->id }}</h4>
                                <span style="font-size: 14px; color: #6b7280;">Confirmed</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="detail-card-panel" style="text-align: center; padding: 40px 20px; margin-bottom: 48px; border-left: 4px solid #2563EB;">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="1.5" style="margin-bottom: 16px;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    <h4 class="detail-card-panel__title" style="margin-bottom: 8px;">No event bookings</h4>
                    <p class="detail-card-panel__text">You haven't booked any events yet. Check out what's happening nearby!</p>
                </div>
            @endif

            <!-- GameHub / Live Matches Section -->
            <div class="reviews-section-header">
                <h3>My ActionBoard Matches</h3>
                <a href="{{ route('site.gamehub.actionboard.create') }}" class="btn btn--solid" style="background: #16a34a; border-color: #16a34a; color: #fff;">+ Create Match</a>
            </div>

            <!-- ActionBoard Player Info -->
            <div class="detail-card-panel" style="padding: 24px; margin-bottom: 24px; border-left: 4px solid #3b82f6;">
                <h4 style="margin: 0 0 16px 0; font-size: 18px; font-weight: 800; color: #111827;">My ActionBoard Profile</h4>
                <form action="{{ route('site.profile.update') }}" method="POST">
                    @csrf
                    @method('PUT')
                    <!-- Hidden inputs for general profile fields to pass validation -->
                    <input type="hidden" name="name" value="{{ $user->name }}">
                    <input type="hidden" name="email" value="{{ $user->email }}">
                    <input type="hidden" name="phone" value="{{ $user->phone }}">
                    
                    <div style="display: flex; gap: 16px; align-items: flex-end;">
                        <div style="flex: 1;">
                            <label style="display: block; margin-bottom: 8px; font-size: 14px; font-weight: 600; color: #374151;">Player Role</label>
                            <select name="player_role" style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 15px; background: #fff;">
                                <option value="">Select Role</option>
                                <option value="Batter" {{ ($user->player_role ?? '') == 'Batter' ? 'selected' : '' }}>Batter</option>
                                <option value="Bowler" {{ ($user->player_role ?? '') == 'Bowler' ? 'selected' : '' }}>Bowler</option>
                                <option value="All-Rounder" {{ ($user->player_role ?? '') == 'All-Rounder' ? 'selected' : '' }}>All-Rounder</option>
                                <option value="Wicket-Keeper" {{ ($user->player_role ?? '') == 'Wicket-Keeper' ? 'selected' : '' }}>Wicket-Keeper</option>
                            </select>
                        </div>
                        <div style="flex: 1;">
                            <label style="display: block; margin-bottom: 8px; font-size: 14px; font-weight: 600; color: #374151;">Playing Style</label>
                            <select name="playing_style" style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 15px; background: #fff;">
                                <option value="">Select Style</option>
                                <optgroup label="Batting">
                                    <option value="Right-hand bat" {{ ($user->playing_style ?? '') == 'Right-hand bat' ? 'selected' : '' }}>Right-hand bat</option>
                                    <option value="Left-hand bat" {{ ($user->playing_style ?? '') == 'Left-hand bat' ? 'selected' : '' }}>Left-hand bat</option>
                                </optgroup>
                                <optgroup label="Bowling">
                                    <option value="Right-arm fast" {{ ($user->playing_style ?? '') == 'Right-arm fast' ? 'selected' : '' }}>Right-arm fast</option>
                                    <option value="Right-arm medium" {{ ($user->playing_style ?? '') == 'Right-arm medium' ? 'selected' : '' }}>Right-arm medium</option>
                                    <option value="Right-arm spin" {{ ($user->playing_style ?? '') == 'Right-arm spin' ? 'selected' : '' }}>Right-arm spin</option>
                                    <option value="Left-arm fast" {{ ($user->playing_style ?? '') == 'Left-arm fast' ? 'selected' : '' }}>Left-arm fast</option>
                                    <option value="Left-arm medium" {{ ($user->playing_style ?? '') == 'Left-arm medium' ? 'selected' : '' }}>Left-arm medium</option>
                                    <option value="Left-arm spin" {{ ($user->playing_style ?? '') == 'Left-arm spin' ? 'selected' : '' }}>Left-arm spin</option>
                                </optgroup>
                            </select>
                        </div>
                        <button type="submit" class="btn btn--solid" style="background: #3b82f6; color: #fff; border: none; height: 48px; padding: 0 24px;">Update</button>
                    </div>
                </form>
            </div>

            @if(count($myMatches) > 0)
                <div style="display: flex; flex-direction: column; gap: 16px;">
                    @foreach($myMatches as $match)
                        <div class="detail-card-panel" style="padding: 24px; margin-bottom: 0; display: flex; align-items: center; justify-content: space-between; border-left: 4px solid #16a34a;">
                            <div>
                                <div style="display: flex; gap: 8px; margin-bottom: 12px; align-items: center;">
                                    <span class="detail-badge detail-badge--dark">{{ $match->status }}</span>
                                    <span style="font-size: 13px; color: #6b7280; font-weight: 600;">Overs: {{ $match->overs }}</span>
                                </div>
                                <h4 style="margin: 0; font-size: 18px; font-weight: 800; color: #111827; font-family: 'Plus Jakarta Sans', sans-serif;">
                                    {{ $match->home }} ({{ $match->home_score }}) <span style="color:#9ca3af; font-size: 14px; margin: 0 8px;">vs</span> {{ $match->away }} ({{ $match->away_score }})
                                </h4>
                            </div>
                            <div style="display: flex; gap: 12px;">
                                <a href="{{ route('site.gamehub.actionboard.match', ['id' => $match->id]) }}" class="court-pill" style="text-decoration:none; display:flex; align-items:center; height: 42px;">View</a>
                                <a href="{{ route('site.gamehub.actionboard.control', ['id' => $match->id]) }}" class="court-pill is-active" style="text-decoration:none; display:flex; align-items:center; height: 42px; background: #eab308; border-color: #eab308; color: #fff;">Control Room</a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="detail-card-panel" style="text-align: center; padding: 40px 20px; border-left: 4px solid #16a34a;">
                    <lord-icon src="https://cdn.lordicon.com/pbrgppbb.json" trigger="loop" delay="2000" colors="primary:#16a34a,secondary:#111827" style="width:48px;height:48px; margin-bottom: 16px;"></lord-icon>
                    <h4 class="detail-card-panel__title" style="margin-bottom: 8px;">No matches created yet</h4>
                    <p class="detail-card-panel__text">Start your first live game on the ActionBoard today.</p>
                </div>
            @endif
        </div>

        <!-- Sidebar Column (Stats & Settings) -->
        <div>
            <div class="detail-card-panel">
                <h3 class="detail-card-panel__title" style="font-size: 18px;">Quick Stats</h3>
                
                <div style="display: flex; flex-direction: column; gap: 20px;">
                    <div style="display: flex; align-items: center; gap: 16px;">
                        <div class="action-round-btn" style="background: #f0fdf4; border-color: #bbf7d0; cursor: default;">
                            <img src="https://cdn-icons-png.flaticon.com/512/7186/7186401.png" alt="Member" style="width: 20px; height: 20px;">
                        </div>
                        <div>
                            <div style="font-size: 13px; color: #6b7280; font-weight: 600;">Member Since</div>
                            <div style="font-size: 16px; font-weight: 800; color: #111827;">{{ $user->created_at->format('M Y') }}</div>
                        </div>
                    </div>

                    <div style="display: flex; align-items: center; gap: 16px;">
                        <div class="action-round-btn" style="background: #fff6ed; border-color: #fed7aa; cursor: default;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#f97316" stroke-width="2.5"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                        </div>
                        <div>
                            <div style="font-size: 13px; color: #6b7280; font-weight: 600;">Event Bookings</div>
                            <div style="font-size: 16px; font-weight: 800; color: #111827;">{{ count($myBookings) }}</div>
                        </div>
                    </div>
                    
                    <div style="display: flex; align-items: center; gap: 16px;">
                        <div class="action-round-btn" style="background: #eff6ff; border-color: #bfdbfe; cursor: default;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2.5"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                        </div>
                        <div>
                            <div style="font-size: 13px; color: #6b7280; font-weight: 600;">ActionBoard Matches</div>
                            <div style="font-size: 16px; font-weight: 800; color: #111827;">{{ count($myMatches) }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="detail-card-panel">
                <h3 class="detail-card-panel__title" style="font-size: 18px;">Account</h3>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <a href="#" class="court-pill" style="text-align: center; text-decoration: none;">Account Settings</a>
                    <a href="#" class="court-pill" style="text-align: center; text-decoration: none; color: #ef4444; background: #fef2f2; border-color: #fecaca;" onclick="alert('Logout functionality placeholder'); return false;">Log Out</a>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection
