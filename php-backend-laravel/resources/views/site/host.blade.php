@extends('site.layout')

@php
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
    <header class="hp-hero">
        <div class="hp-cover" @if ($cover) style="background-image:url('{{ $cover }}')" @endif></div>
        <div class="hp-id">
            <div class="hp-logo">
                @if ($logo)
                    <img src="{{ $logo }}" alt="{{ $profile->display_name }}">
                @else
                    <span>{{ $init }}</span>
                @endif
            </div>
            <div class="hp-idmeta">
                <h1 class="hp-name">
                    {{ $profile->display_name }}
                    @if ($profile->isVerified())
                        <svg class="hp-verified" viewBox="0 0 24 24" fill="currentColor" aria-label="Verified"><path d="M23 12l-2.44-2.79.34-3.69-3.61-.82-1.89-3.2L12 2.96 8.6 1.5 6.71 4.69 3.1 5.5l.34 3.7L1 12l2.44 2.79-.34 3.7 3.61.82L8.6 22.5l3.4-1.47 3.4 1.46 1.89-3.19 3.61-.82-.34-3.69L23 12zm-12.91 4.72l-3.8-3.81 1.48-1.48 2.32 2.33 5.85-5.87 1.48 1.48-7.33 7.35z"/></svg>
                    @endif
                </h1>
                @if ($profile->tagline)<p class="hp-tag">{{ $profile->tagline }}</p>@endif
                <div class="hp-sub">
                    @if ($profile->city)<span class="hp-city">📍 {{ $profile->city }}</span>@endif
                    <span class="hp-count">{{ $events->count() }} upcoming {{ \Illuminate\Support\Str::plural('event', $events->count()) }}</span>
                    <span class="hp-followers"><strong>{{ number_format($followers ?? 0) }}</strong> {{ \Illuminate\Support\Str::plural('follower', $followers ?? 0) }}</span>
                    @if (($rating['avg'] ?? null) !== null)
                        <span class="hp-rating">★ {{ number_format($rating['avg'], 1) }} <span class="hp-rating-n">({{ number_format($rating['count']) }})</span></span>
                    @endif
                </div>

                <div class="hp-actions">
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
                </div>

                @if ($socials->count())
                    <div class="hp-socials">
                        @foreach ($socials as $s)
                            <a href="{{ \Illuminate\Support\Str::startsWith($s['url'], ['http://','https://']) ? $s['url'] : 'https://'.$s['url'] }}"
                               target="_blank" rel="noopener nofollow" class="hp-social">{{ $s['label'] }}</a>
                        @endforeach
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
</div>

<style>
    .host-page{max-width:960px;margin:0 auto;padding:0 16px 48px;color:#0b1220;}
    .hp-hero{position:relative;margin-top:8px;}
    .hp-cover{height:190px;border-radius:20px;background:linear-gradient(135deg,#1e50e6,#3b82f6 60%,#6366f1);
        background-size:cover;background-position:center;box-shadow:0 12px 30px -16px rgba(20,30,60,.5);}
    .hp-id{display:flex;gap:16px;align-items:flex-end;padding:0 8px;margin-top:-44px;position:relative;}
    .hp-logo{width:96px;height:96px;border-radius:22px;flex:none;overflow:hidden;background:#111722;
        border:4px solid #fff;box-shadow:0 8px 20px -8px rgba(0,0,0,.35);display:flex;align-items:center;
        justify-content:center;color:#fff;font-size:34px;font-weight:800;}
    .hp-logo img{width:100%;height:100%;object-fit:cover;display:block;}
    .hp-idmeta{padding-bottom:4px;min-width:0;flex:1;}
    .hp-name{display:flex;align-items:center;gap:8px;font-size:26px;font-weight:800;letter-spacing:-.02em;
        margin:6px 0 2px;line-height:1.1;}
    .hp-verified{width:20px;height:20px;color:#1e50e6;flex:none;}
    .hp-tag{font-size:14.5px;color:#4b5563;margin:2px 0 0;}
    .hp-sub{display:flex;gap:14px;flex-wrap:wrap;align-items:center;margin-top:8px;font-size:13px;color:#6b7382;}
    .hp-count{font-weight:600;color:#0a7d4e;}
    .hp-followers strong{color:#0b1220;}
    .hp-rating{font-weight:700;color:#b45309;}
    .hp-rating-n{font-weight:500;color:#9aa2b1;}
    .hp-actions{margin-top:12px;}
    .hp-follow{display:inline-flex;align-items:center;gap:6px;border:0;cursor:pointer;
        font-size:13.5px;font-weight:700;padding:9px 20px;border-radius:999px;text-decoration:none;
        background:linear-gradient(180deg,#2f6bff,#1e50e6);color:#fff;
        box-shadow:0 8px 18px -8px rgba(37,99,235,.6);transition:filter .15s,transform .05s;}
    .hp-follow:hover{filter:brightness(1.06);}
    .hp-follow:active{transform:translateY(1px);}
    .hp-follow.is-on{background:#eef3ff;color:#1e50e6;box-shadow:inset 0 0 0 1.5px #c7d7ff;}
    .hp-socials{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;}
    .hp-social{font-size:12.5px;font-weight:600;color:#1e50e6;text-decoration:none;
        padding:5px 11px;border-radius:999px;background:#eef3ff;}
    .hp-social:hover{background:#e0e9ff;}

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
    .hp-card-past{opacity:.9;}
    .hp-card-past .hp-poster{filter:saturate(.85);}

    @media (max-width:560px){
        .hp-cover{height:140px;border-radius:16px;}
        .hp-id{margin-top:-38px;gap:12px;}
        .hp-logo{width:78px;height:78px;border-radius:18px;font-size:28px;}
        .hp-name{font-size:22px;}
        .hp-grid{grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px;}
    }
</style>
@endsection
