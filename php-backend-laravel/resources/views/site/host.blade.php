@extends('site.layout')

@php
    $lane = $lane ?? 'event';
    $isVenue = $lane === 'venue';
    $cover = $profile->coverUrl();
    $logo = $profile->logoUrl();
    $init = strtoupper(mb_substr(trim($profile->display_name), 0, 1)) ?: 'H';
    $ogImg = $cover ?: $logo;
    $desc = \Illuminate\Support\Str::limit(strip_tags($profile->tagline ?: $profile->about), 160);
    $socials = collect([
        ['k' => 'instagram', 'label' => 'Instagram', 'url' => $profile->social('instagram')],
        ['k' => 'x', 'label' => 'X', 'url' => $profile->social('x')],
        ['k' => 'youtube', 'label' => 'YouTube', 'url' => $profile->social('youtube')],
        ['k' => 'facebook', 'label' => 'Facebook', 'url' => $profile->social('facebook')],
        ['k' => 'website', 'label' => 'Website', 'url' => $profile->website],
    ])->filter(fn ($s) => filled($s['url']));
@endphp

@push('head')
    <meta property="og:type" content="profile">
    <meta property="og:title" content="{{ $profile->display_name }}">
    <meta property="og:description" content="{{ $desc }}">
    @if ($ogImg)<meta property="og:image" content="{{ $ogImg }}">@endif
    <meta name="description" content="{{ $desc }}">
@endpush

@section('content')
<div class="host-page">
    @php
        $eventCount = $isVenue ? ($venues ?? collect())->count() : $events->count();
    @endphp
    <header class="hp-hero">
        <div class="hp-cover" @if ($cover) style="background-image:url('{{ $cover }}')" @endif>
            <div class="hp-scrim"></div>
        </div>
        <div class="hp-body">
            <div class="hp-body-top">
                <div class="hp-logo">
                    @if ($logo)
                        <img src="{{ $logo }}" alt="{{ $profile->display_name }}">
                    @else
                        <span>{{ $init }}</span>
                    @endif
                </div>
                <div class="hp-cta">
                    @auth
                        @unless ($isOwner ?? false)
                            <form method="POST" action="{{ route('site.host.follow', ['slug' => $profile->slug]) }}">
                                @csrf
                                <button type="submit" class="hp-follow {{ ($isFollowing ?? false) ? 'is-on' : '' }}">
                                    {{ ($isFollowing ?? false) ? '✓ Following' : '+ Follow' }}
                                </button>
                            </form>
                        @endunless
                    @else
                        <a href="{{ url('/login') }}" class="hp-follow">+ Follow</a>
                    @endauth
                    <button type="button" class="hp-share" onclick="hpShare(this)" data-url="{{ url('/host/'.$profile->slug) }}" aria-label="Share">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="M8.6 13.5l6.8 4M15.4 6.5l-6.8 4"/></svg>
                        <span>Share</span>
                    </button>
                </div>
            </div>

            <h1 class="hp-name">
                {{ $profile->display_name }}
                @if ($profile->isVerified())
                    <svg class="hp-verified" viewBox="0 0 24 24" fill="currentColor" aria-label="Verified"><path d="M23 12l-2.44-2.79.34-3.69-3.61-.82-1.89-3.2L12 2.96 8.6 1.5 6.71 4.69 3.1 5.5l.34 3.7L1 12l2.44 2.79-.34 3.7 3.61.82L8.6 22.5l3.4-1.47 3.4 1.46 1.89-3.19 3.61-.82-.34-3.69L23 12zm-12.91 4.72l-3.8-3.81 1.48-1.48 2.32 2.33 5.85-5.87 1.48 1.48-7.33 7.35z"/></svg>
                @endif
            </h1>
            @if ($profile->isVerified())
                <div class="hp-vlabel">Verified {{ $isVenue ? 'venue' : 'organiser' }}</div>
            @endif
            @if ($profile->tagline)<p class="hp-tag">{{ $profile->tagline }}</p>@endif

            @if ($profile->city || $socials->count())
                <div class="hp-chips">
                    @if ($profile->city)<span class="hp-chip hp-chip-city">📍 {{ $profile->city }}</span>@endif
                    @foreach ($socials as $s)
                        <a href="{{ \Illuminate\Support\Str::startsWith($s['url'], ['http://','https://']) ? $s['url'] : 'https://'.$s['url'] }}"
                           target="_blank" rel="noopener nofollow" class="hp-chip hp-chip-social">{{ $s['label'] }}</a>
                    @endforeach
                </div>
            @endif

            <div class="hp-stats">
                <div class="hp-stat">
                    <b>{{ number_format($followers ?? 0) }}</b>
                    <span>{{ \Illuminate\Support\Str::plural('Follower', $followers ?? 0) }}</span>
                </div>
                <span class="hp-statdiv"></span>
                <div class="hp-stat">
                    <b>{{ number_format($eventCount) }}</b>
                    <span>{{ $isVenue ? \Illuminate\Support\Str::plural('Venue', $eventCount) : 'Upcoming' }}</span>
                </div>
                @if (($rating['avg'] ?? null) !== null)
                    <span class="hp-statdiv"></span>
                    <div class="hp-stat">
                        <b class="hp-stat-rating">★ {{ number_format($rating['avg'], 1) }}</b>
                        <span>{{ number_format($rating['count']) }} ratings</span>
                    </div>
                @endif
            </div>
        </div>
    </header>

    @if ($profile->about)
        <section class="hp-section">
            <h2 class="hp-h2">About</h2>
            <p class="hp-about">{!! nl2br(e($profile->about)) !!}</p>
        </section>
    @endif

    @if ($isVenue)
        <section class="hp-section">
            <h2 class="hp-h2">Venues</h2>
            @if (($venues ?? collect())->count())
                <div class="hp-grid">
                    @foreach ($venues as $venue)
                        @php $img = \App\Support\MediaUrl::resolve(is_array($venue->images) ? ($venue->images[0] ?? null) : null); @endphp
                        <a href="{{ url('/gamehub/'.$venue->id) }}" class="hp-card">
                            <div class="hp-poster hp-poster-venue" @if ($img) style="background-image:url('{{ $img }}')" @endif>
                                @if (! $img)<span>{{ strtoupper(mb_substr($venue->name, 0, 1)) }}</span>@endif
                            </div>
                            <div class="hp-cbody">
                                <div class="hp-ctitle">{{ $venue->name }}</div>
                                <div class="hp-cmeta">
                                    @if ($venue->city){{ $venue->city }}@endif
                                    @if ($venue->rating)<span> · ★ {{ number_format((float) $venue->rating, 1) }}</span>@endif
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            @else
                <p class="hp-empty">No venues listed yet.</p>
            @endif
        </section>
    @else
    <section class="hp-section">
        <h2 class="hp-h2">Upcoming events</h2>
        @if ($events->count())
            <div class="hp-grid">
                @foreach ($events as $event)
                    @php $poster = $event->heroImageUrl(); @endphp
                    <a href="{{ url('/events/'.$event->id) }}" class="hp-card">
                        <div class="hp-poster" @if ($poster) style="background-image:url('{{ $poster }}')" @endif>
                            @if (! $poster)<span>{{ strtoupper(mb_substr($event->title, 0, 1)) }}</span>@endif
                        </div>
                        <div class="hp-cbody">
                            <div class="hp-ctitle">{{ $event->title }}</div>
                            <div class="hp-cmeta">
                                <span>{{ optional($event->date)->format('D, d M') ?: 'Date TBA' }}</span>
                                @if ($event->venue)<span> · {{ \Illuminate\Support\Str::limit($event->venue, 24) }}</span>@endif
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        @else
            <p class="hp-empty">No upcoming events right now — check back soon.</p>
        @endif
    </section>

    @if (($pastEvents ?? collect())->count())
        <section class="hp-section">
            <h2 class="hp-h2">Past events</h2>
            <div class="hp-grid hp-grid-past">
                @foreach ($pastEvents as $event)
                    @php $poster = $event->heroImageUrl(); @endphp
                    <a href="{{ url('/events/'.$event->id) }}" class="hp-card hp-card-past">
                        <div class="hp-poster" @if ($poster) style="background-image:url('{{ $poster }}')" @endif>
                            @if (! $poster)<span>{{ strtoupper(mb_substr($event->title, 0, 1)) }}</span>@endif
                        </div>
                        <div class="hp-cbody">
                            <div class="hp-ctitle">{{ $event->title }}</div>
                            <div class="hp-cmeta">{{ optional($event->date)->format('d M Y') }}</div>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
    @endif {{-- /event lane --}}
</div>

<style>
    .host-page{max-width:960px;margin:0 auto;padding:0 16px 48px;color:#0b1220;}
    .hp-hero{position:relative;margin-top:8px;}
    .hp-cover{position:relative;height:230px;border-radius:22px;overflow:hidden;
        background:linear-gradient(120deg,#1b3fb8,#2f6bff 45%,#6366f1);
        background-size:cover;background-position:center;box-shadow:0 18px 40px -22px rgba(20,30,60,.6);}
    /* subtle branded texture when there's no cover photo + a scrim for legibility */
    .hp-cover::before{content:"";position:absolute;inset:0;opacity:.14;
        background-image:linear-gradient(rgba(255,255,255,.6) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.6) 1px,transparent 1px);
        background-size:38px 38px;-webkit-mask-image:radial-gradient(120% 90% at 15% 0%,#000 30%,transparent 70%);mask-image:radial-gradient(120% 90% at 15% 0%,#000 30%,transparent 70%);}
    .hp-scrim{position:absolute;inset:0;background:linear-gradient(180deg,transparent 45%,rgba(6,12,30,.28));}

    .hp-body{position:relative;margin-top:-40px;padding:0 6px;}
    .hp-body-top{display:flex;align-items:flex-end;justify-content:space-between;gap:12px;}
    .hp-logo{width:104px;height:104px;border-radius:24px;flex:none;overflow:hidden;background:#111722;
        border:4px solid #fff;box-shadow:0 10px 24px -10px rgba(0,0,0,.4);display:flex;align-items:center;
        justify-content:center;color:#fff;font-size:38px;font-weight:800;}
    .hp-logo img{width:100%;height:100%;object-fit:cover;display:block;}

    .hp-cta{display:flex;align-items:center;gap:8px;padding-bottom:6px;}
    .hp-follow{display:inline-flex;align-items:center;gap:6px;border:0;cursor:pointer;
        font-size:14px;font-weight:700;padding:10px 22px;border-radius:999px;text-decoration:none;
        background:linear-gradient(180deg,#2f6bff,#1e50e6);color:#fff;
        box-shadow:0 10px 20px -8px rgba(37,99,235,.6);transition:filter .15s,transform .05s;}
    .hp-follow:hover{filter:brightness(1.06);}
    .hp-follow:active{transform:translateY(1px);}
    .hp-follow.is-on{background:#eef3ff;color:#1e50e6;box-shadow:inset 0 0 0 1.5px #c7d7ff;}
    .hp-share{display:inline-flex;align-items:center;gap:6px;border:0;cursor:pointer;
        font-size:14px;font-weight:700;padding:10px 16px;border-radius:999px;color:#1e50e6;
        background:#eef3ff;box-shadow:inset 0 0 0 1px #dbe4ff;transition:background .15s;}
    .hp-share:hover{background:#e0e9ff;}
    .hp-share svg{width:16px;height:16px;}

    .hp-name{display:flex;align-items:center;gap:9px;font-size:29px;font-weight:800;letter-spacing:-.025em;
        margin:14px 0 0;line-height:1.05;}
    .hp-verified{width:22px;height:22px;color:#1e50e6;flex:none;}
    .hp-vlabel{display:inline-flex;align-items:center;font-size:12px;font-weight:700;color:#1e50e6;
        background:#eef3ff;padding:2px 9px;border-radius:999px;margin-top:7px;}
    .hp-tag{font-size:15.5px;color:#4b5563;margin:8px 0 0;}

    .hp-chips{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;}
    .hp-chip{font-size:12.5px;font-weight:600;padding:5px 12px;border-radius:999px;text-decoration:none;}
    .hp-chip-city{color:#475569;background:#eef1f6;}
    .hp-chip-social{color:#1e50e6;background:#eef3ff;}
    .hp-chip-social:hover{background:#e0e9ff;}

    .hp-stats{display:flex;align-items:center;gap:22px;margin-top:18px;padding:14px 4px 2px;
        border-top:1px solid #eceff4;}
    .hp-stat{display:flex;flex-direction:column;gap:1px;}
    .hp-stat b{font-size:20px;font-weight:800;color:#0b1220;letter-spacing:-.02em;font-variant-numeric:tabular-nums;}
    .hp-stat span{font-size:12px;color:#6b7382;font-weight:600;}
    .hp-stat-rating{color:#b45309;}
    .hp-statdiv{width:1px;height:30px;background:#e6e9ef;}

    .hp-section{margin-top:30px;}
    .hp-h2{font-size:18px;font-weight:800;letter-spacing:-.01em;margin:0 0 14px;}
    .hp-about{font-size:14.5px;line-height:1.6;color:#374151;margin:0;white-space:normal;}

    .hp-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:16px;}
    .hp-card{display:block;text-decoration:none;color:inherit;border-radius:16px;overflow:hidden;
        background:#fff;box-shadow:0 1px 2px rgba(11,18,32,.08),0 8px 24px -18px rgba(11,18,32,.5);
        transition:transform .12s,box-shadow .12s;}
    .hp-card:hover{transform:translateY(-3px);box-shadow:0 10px 28px -14px rgba(11,18,32,.45);}
    .hp-poster{aspect-ratio:3/4;background:linear-gradient(135deg,#334155,#0f172a);background-size:cover;
        background-position:center;display:flex;align-items:center;justify-content:center;color:#94a3b8;
        font-size:40px;font-weight:800;}
    .hp-cbody{padding:11px 13px 14px;}
    .hp-ctitle{font-size:14px;font-weight:700;line-height:1.25;display:-webkit-box;-webkit-line-clamp:2;
        -webkit-box-orient:vertical;overflow:hidden;}
    .hp-cmeta{font-size:12px;color:#6b7382;margin-top:5px;}
    .hp-empty{font-size:14px;color:#6b7382;}
    .hp-poster-venue{aspect-ratio:4/3;}
    .hp-card-past{opacity:.9;}
    .hp-card-past .hp-poster{filter:saturate(.85);}

    @media (max-width:560px){
        .hp-cover{height:150px;border-radius:16px;}
        .hp-body{margin-top:-34px;}
        .hp-logo{width:84px;height:84px;border-radius:20px;font-size:30px;}
        .hp-follow{padding:9px 18px;font-size:13.5px;}
        .hp-share span{display:none;}
        .hp-share{padding:9px 11px;}
        .hp-name{font-size:24px;margin-top:12px;}
        .hp-tag{font-size:14.5px;}
        .hp-stats{gap:16px;}
        .hp-stat b{font-size:18px;}
        .hp-grid{grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px;}
    }
</style>

<script>
    function hpShare(btn){
        var url = btn.getAttribute('data-url') || window.location.href;
        if (navigator.share){ navigator.share({title: document.title, url: url}).catch(function(){}); return; }
        var label = btn.querySelector('span');
        navigator.clipboard.writeText(url).then(function(){
            if (label){ var t = label.textContent; label.textContent = 'Copied ✓'; setTimeout(function(){ label.textContent = t; }, 1500); }
        });
    }
</script>
@endsection
