@extends('site.layout')

@section('content')
{{--
    HOME — the app's HomeFeedScreen, ported.

    The ActionBoard's Home button opens this in the app: photo posts from public players,
    newest first, over a strip of story bubbles. On the web that button used to jump to
    Pulse (venue discovery), so the same label opened two unrelated products.

    Layout notes that came from the app:
      · Blue header with the white wordmark and rounded bottom corners, so the white feed
        tucks under it.
      · Square, edge-to-edge photos. Multiple images are a swipeable rail with a "1/3"
        counter and dots — never a grid, which would turn one post into several.
      · Actions read like Instagram because the app's do: like · comment · share left,
        save right; then the like count, then "@name caption", then the comments link.
    Unlike the app there is no create-post flow here yet, so the ＋ bubble and the empty
    state point at the app rather than offering a button that goes nowhere.
--}}
@php
    $me = auth()->user();
    $meAvatar = $me->avatar ?? '';
    if ($meAvatar !== '' && !preg_match('~^https?://~i', $meAvatar) && !str_starts_with($meAvatar, '/')) {
        $meAvatar = asset('storage/' . ltrim($meAvatar, '/'));
    }
    $meInitial = ($n = trim(strtok(trim($me->name ?? ''), ' '))) !== '' ? mb_strtoupper(mb_substr($n, 0, 1)) : '';
@endphp

<div class="hfeed">
    {{-- Brand header. No back arrow: the bottom bar is the way between screens here. --}}
    <header class="hfeed__bar">
        <img class="hfeed__mark" src="{{ asset('images/haraan-logo-white.png') }}" alt="Haraan">
        <span class="hfeed__beta">BETA</span>
    </header>

    @if (count($feedStories) > 0)
        <div class="hfeed__stories">
            {{-- Your own bubble leads, as in the app. Posting happens in the app, so this
                 says so instead of opening a dead composer. --}}
            <a class="hfeed__story hfeed__story--me" href="https://play.google.com/store/apps/details?id=com.haraan.app"
               target="_blank" rel="noopener" title="Post from the Haraan app">
                <span class="hfeed__sring hfeed__sring--new">
                    <span class="hfeed__sav">
                        @if ($meAvatar !== '')
                            <img src="{{ $meAvatar }}" alt="" loading="lazy" onerror="this.remove()">
                        @elseif ($meInitial !== '')
                            {{ $meInitial }}
                        @endif
                    </span>
                    <span class="hfeed__splus" aria-hidden="true">+</span>
                </span>
                <span class="hfeed__sname">New</span>
            </a>
            @foreach ($feedStories as $s)
                <a class="hfeed__story" href="{{ $s['playerId'] !== '' ? '/player/' . $s['playerId'] : '#' }}">
                    <span class="hfeed__sring">
                        <span class="hfeed__sav">
                            @if ($s['avatar'] !== '')
                                <img src="{{ $s['avatar'] }}" alt="" loading="lazy" onerror="this.remove()">
                            @else
                                {{ mb_strtoupper(mb_substr($s['name'], 0, 1)) }}
                            @endif
                        </span>
                    </span>
                    <span class="hfeed__sname">{{ $s['username'] !== '' ? '@' . $s['username'] : $s['name'] }}</span>
                </a>
            @endforeach
        </div>
        <div class="hfeed__rule"></div>
    @endif

    @forelse ($feedPosts as $p)
        <article class="hpost" data-post="{{ $p['id'] }}">
            <div class="hpost__head">
                <a class="hpost__who" href="{{ $p['authorId'] !== '' ? '/player/' . $p['authorId'] : '#' }}">
                    <span class="hpost__av">
                        @if ($p['authorAvatar'] !== '')
                            <img src="{{ $p['authorAvatar'] }}" alt="" loading="lazy" onerror="this.remove()">
                        @else
                            {{ mb_strtoupper(mb_substr($p['authorName'], 0, 1)) }}
                        @endif
                    </span>
                    <span class="hpost__names">
                        <b>{{ $p['authorName'] }}</b>
                        @if ($p['authorUsername'] !== '')<span>{{ '@' . $p['authorUsername'] }}</span>@endif
                    </span>
                </a>
                <time class="hpost__ago" datetime="{{ optional($p['createdAt'])->toIso8601String() }}">
                    {{ optional($p['createdAt'])->diffForHumans(null, true) }}
                </time>
            </div>

            @if (count($p['images']) > 1)
                <div class="hpost__rail" data-count="{{ count($p['images']) }}">
                    <div class="hpost__track">
                        @foreach ($p['images'] as $img)
                            <img class="hpost__img" src="{{ $img }}" alt="{{ $p['caption'] !== '' ? $p['caption'] : 'Post by ' . $p['authorName'] }}" loading="lazy">
                        @endforeach
                    </div>
                    <span class="hpost__count">1/{{ count($p['images']) }}</span>
                    <span class="hpost__dots">
                        @foreach ($p['images'] as $i => $img)
                            <i @class(['is-on' => $i === 0])></i>
                        @endforeach
                    </span>
                </div>
            @elseif (count($p['images']) === 1)
                <img class="hpost__img hpost__img--solo" src="{{ $p['images'][0] }}" alt="{{ $p['caption'] !== '' ? $p['caption'] : 'Post by ' . $p['authorName'] }}" loading="lazy">
            @endif

            <div class="hpost__acts">
                <button class="hpost__act hpost__like {{ $p['liked'] ? 'is-on' : '' }}" type="button"
                        data-like="{{ $p['id'] }}" @guest data-login-open @endguest
                        aria-pressed="{{ $p['liked'] ? 'true' : 'false' }}" aria-label="Like">
                    {{-- Two hearts, one filled: the fill is what animates on a like, and
                         swapping paths mid-transition would kill the pop. --}}
                    <svg class="hpost__heart" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M12 20.4S3.6 15 3.6 9.3A4.7 4.7 0 0 1 12 6.5a4.7 4.7 0 0 1 8.4 2.8c0 5.7-8.4 11.1-8.4 11.1Z"/>
                    </svg>
                </button>
                <button class="hpost__act" type="button" data-comments="{{ $p['id'] }}" aria-label="Comments">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round" aria-hidden="true"><path d="M21 11.6a8 8 0 0 1-8.6 8 8.6 8.6 0 0 1-3.6-.85L3.4 20.4l1.65-5.4A8 8 0 0 1 12.4 3.6a8 8 0 0 1 8.6 8Z"/></svg>
                </button>
                <button class="hpost__act" type="button" data-share="{{ $p['id'] }}" aria-label="Share">
                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M21.3 3 2.9 11.05l6.6 2.6 2.6 6.6z"/></svg>
                </button>
                <span class="hpost__sp"></span>
                <button class="hpost__act hpost__save {{ $p['saved'] ? 'is-on' : '' }}" type="button" aria-label="Save" disabled title="Saving is in the app">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round" aria-hidden="true"><path d="M6.4 3.6h11.2v16.8L12 16.2l-5.6 4.2z"/></svg>
                </button>
            </div>

            <div class="hpost__likes" data-likes="{{ $p['id'] }}" @if ($p['likeCount'] === 0) hidden @endif>
                <b>{{ $p['likeCount'] }}</b> {{ $p['likeCount'] === 1 ? 'like' : 'likes' }}
            </div>

            @if ($p['caption'] !== '')
                <p class="hpost__cap">
                    <b>{{ $p['authorUsername'] !== '' ? '@' . $p['authorUsername'] : $p['authorName'] }}</b>
                    {{ $p['caption'] }}
                </p>
            @endif

            <button class="hpost__more" type="button" data-comments="{{ $p['id'] }}" data-ccount="{{ $p['commentCount'] }}"
                    @if ($p['commentCount'] === 0) hidden @endif>
                {{ $p['commentCount'] === 1 ? 'View 1 comment' : 'View all ' . $p['commentCount'] . ' comments' }}
            </button>

            {{-- The comment thread opens in place. It is fetched on first open, so a feed
                 of forty posts does not load forty threads nobody asked for. --}}
            <div class="hpost__thread" data-thread="{{ $p['id'] }}" hidden>
                <div class="hpost__clist" data-clist="{{ $p['id'] }}"></div>
                @auth
                    <form class="hpost__cform" data-cform="{{ $p['id'] }}">
                        <input type="text" name="body" maxlength="500" placeholder="Add a comment…" autocomplete="off" required>
                        <button type="submit">Post</button>
                    </form>
                @else
                    <button class="hpost__csignin" type="button" data-login-open>Sign in to comment</button>
                @endauth
            </div>
        </article>
    @empty
        <div class="hfeed__empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M12 20.4S3.6 15 3.6 9.3A4.7 4.7 0 0 1 12 6.5a4.7 4.7 0 0 1 8.4 2.8c0 5.7-8.4 11.1-8.4 11.1Z"/></svg>
            <b>No posts yet</b>
            <p>Photos from public players show up here. Post the first one from the Haraan app.</p>
            <a class="hfeed__cta" href="https://play.google.com/store/apps/details?id=com.haraan.app" target="_blank" rel="noopener">Get the app</a>
        </div>
    @endforelse
</div>

@include('site.partials.mab-bottombar', ['active' => 'Home'])

<style>
/* =============================================================================
   HOME FEED (.hfeed / .hpost) — port of the app's HomeFeedScreen.
   A phone-shaped column: the app has no desktop counterpart for this screen, and
   inventing a three-column one would be a different product, so it centres and
   caps its width instead of stretching a 1440px row of square photos.
   ========================================================================== */
/* On a phone this is a full app screen, so the site's own topbar and page padding
   step aside — the same thing the ActionBoard does at this width. Above 720px the
   site chrome stays: a feed column under the normal header is a website, and hiding
   a desktop's only navigation to look more like a phone would be a downgrade. */
@media (max-width: 720px) {
    body:has(.hfeed) .topbar { display: none !important; }
    body:has(.hfeed) main.container { padding-left: 0; padding-right: 0; max-width: none; }
}
.hfeed {
    --ink: #0F172A; --ink2: #475569; --muted: #94A3B8; --line: #E7ECF3; --blue: #2563EB;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    max-width: 520px; margin: 0 auto; background: #fff; color: var(--ink);
    min-height: 100vh; padding-bottom: 116px; /* clears the floating bar */
}
.hfeed__bar {
    display: flex; align-items: center; justify-content: center; gap: 7px;
    background: var(--blue); padding: 16px 16px 18px;
    border-radius: 0 0 22px 22px;
}
/* The white wordmark artwork, used directly. NOT haraan-wordmark.png masked over a
   white fill (the trick .mab__logo uses for the H mark): that file is RGB with no
   alpha channel, so masking it yields a solid white rectangle rather than letters. */
.hfeed__mark { height: 30px; width: auto; display: block; }
.hfeed__beta {
    align-self: flex-start; font-size: 9px; font-weight: 800; letter-spacing: .6px; color: #fff;
    background: rgba(255, 255, 255, .22); border-radius: 5px; padding: 2px 5px;
}

/* ── Stories ─────────────────────────────────────────────────────────────── */
.hfeed__stories {
    display: flex; gap: 14px; padding: 12px 16px 10px;
    overflow-x: auto; scrollbar-width: none; overscroll-behavior-x: contain;
}
.hfeed__stories::-webkit-scrollbar { display: none; }
.hfeed__story { flex: 0 0 auto; width: 68px; text-decoration: none; color: var(--ink2); text-align: center; }
/* The ring is the gradient; the avatar sits inside a white gap so the two never touch. */
.hfeed__sring {
    position: relative; display: block; width: 64px; height: 64px; border-radius: 50%;
    background: conic-gradient(from 210deg, #2563EB, #38BDF8, #A855F7, #2563EB);
    padding: 2.5px;
}
.hfeed__sring--new { background: var(--line); }
.hfeed__sav {
    display: flex; align-items: center; justify-content: center; position: relative;
    width: 100%; height: 100%; border-radius: 50%; overflow: hidden;
    background: #F1F5F9; border: 2.5px solid #fff; box-sizing: border-box;
    font-size: 20px; font-weight: 800; color: var(--ink2);
}
.hfeed__sav img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
.hfeed__splus {
    position: absolute; right: -1px; bottom: -1px; width: 21px; height: 21px; border-radius: 50%;
    background: var(--blue); color: #fff; border: 2px solid #fff; font-size: 14px; font-weight: 700;
    display: flex; align-items: center; justify-content: center; line-height: 1;
}
.hfeed__sname {
    display: block; margin-top: 6px; font-size: 11.5px; font-weight: 600;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.hfeed__rule { height: 1px; background: var(--line); margin: 4px 0 0; }

/* ── Post ────────────────────────────────────────────────────────────────── */
.hpost { padding-bottom: 6px; border-bottom: 1px solid var(--line); }
.hpost:last-child { border-bottom: 0; }
.hpost__head { display: flex; align-items: center; gap: 10px; padding: 10px 14px; }
.hpost__who { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 0; text-decoration: none; color: inherit; }
.hpost__av {
    position: relative; flex: 0 0 auto; width: 38px; height: 38px; border-radius: 50%;
    background: #F1F5F9; overflow: hidden; display: flex; align-items: center; justify-content: center;
    font-size: 15px; font-weight: 800; color: var(--ink2);
}
.hpost__av img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
.hpost__names { min-width: 0; display: flex; flex-direction: column; line-height: 1.25; }
.hpost__names b { font-size: 14px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.hpost__names span { font-size: 12px; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.hpost__ago { flex: 0 0 auto; font-size: 11.5px; color: var(--muted); }

.hpost__img { display: block; width: 100%; aspect-ratio: 1 / 1; object-fit: cover; background: #F1F5F9; }
/* Multi-image posts scroll horizontally and SNAP, so a half-shown photo can't happen. */
.hpost__rail { position: relative; }
.hpost__track {
    display: flex; overflow-x: auto; scroll-snap-type: x mandatory;
    scrollbar-width: none; overscroll-behavior-x: contain;
}
.hpost__track::-webkit-scrollbar { display: none; }
.hpost__track .hpost__img { flex: 0 0 100%; scroll-snap-align: center; }
.hpost__count {
    position: absolute; top: 10px; right: 10px; padding: 3px 8px; border-radius: 10px;
    background: rgba(0, 0, 0, .55); color: #fff; font-size: 11px; font-weight: 700;
}
.hpost__dots { position: absolute; left: 0; right: 0; bottom: 8px; display: flex; justify-content: center; gap: 5px; }
.hpost__dots i { width: 5px; height: 5px; border-radius: 50%; background: rgba(255, 255, 255, .5); }
.hpost__dots i.is-on { width: 6px; height: 6px; background: #fff; }

.hpost__acts { display: flex; align-items: center; gap: 2px; padding: 6px 8px 0; }
.hpost__act {
    background: none; border: 0; cursor: pointer; color: var(--ink); padding: 7px; border-radius: 50%;
    display: inline-flex; -webkit-tap-highlight-color: transparent;
}
.hpost__act svg { width: 26px; height: 26px; display: block; }
.hpost__act[disabled] { cursor: default; color: var(--muted); }
.hpost__sp { flex: 1; }
/* A like fills the heart and gives it one beat — the whole feedback for the tap. */
.hpost__heart { fill: none; stroke: currentColor; stroke-width: 1.9; stroke-linejoin: round; transition: transform .18s ease; }
.hpost__like.is-on { color: #E11D2A; }
.hpost__like.is-on .hpost__heart { fill: currentColor; stroke: currentColor; }
.hpost__like.is-beat .hpost__heart { animation: hbeat .42s cubic-bezier(.2, 1.5, .4, 1); }
@keyframes hbeat { 0% { transform: scale(1); } 42% { transform: scale(1.28); } 100% { transform: scale(1); } }
.hpost__save.is-on svg { fill: currentColor; }

.hpost__likes { padding: 2px 16px 0; font-size: 13px; }
.hpost__likes b { font-weight: 700; }
.hpost__cap { margin: 4px 0 0; padding: 0 16px; font-size: 13.5px; line-height: 1.45; }
.hpost__cap b { font-weight: 700; }
.hpost__more {
    background: none; border: 0; padding: 4px 16px 6px; cursor: pointer;
    font: inherit; font-size: 13px; color: var(--muted);
}

/* ── Comments, inline ────────────────────────────────────────────────────── */
.hpost__thread { padding: 2px 16px 10px; }
.hpost__crow { display: flex; gap: 9px; padding: 6px 0; }
.hpost__cav {
    position: relative; flex: 0 0 auto; width: 28px; height: 28px; border-radius: 50%;
    background: #F1F5F9; overflow: hidden; display: flex; align-items: center; justify-content: center;
    font-size: 12px; font-weight: 800; color: var(--ink2);
}
.hpost__cav img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
.hpost__cbody { font-size: 13px; line-height: 1.45; }
.hpost__cbody b { font-weight: 700; }
.hpost__cago { display: block; font-size: 11px; color: var(--muted); margin-top: 1px; }
.hpost__cempty { font-size: 12.5px; color: var(--muted); padding: 6px 0; }
.hpost__cform { display: flex; gap: 8px; align-items: center; margin-top: 6px; }
.hpost__cform input {
    flex: 1; min-width: 0; height: 38px; padding: 0 14px; border-radius: 19px;
    border: 1px solid var(--line); background: #F8FAFC; font: inherit; font-size: 13px; color: var(--ink);
}
.hpost__cform input:focus { outline: none; border-color: #BFD3F5; background: #fff; }
.hpost__cform button, .hpost__csignin {
    border: 0; background: none; color: var(--blue); font: inherit; font-size: 13px; font-weight: 700;
    cursor: pointer; padding: 8px 4px;
}
.hpost__cform button[disabled] { color: var(--muted); cursor: default; }

/* ── Empty ───────────────────────────────────────────────────────────────── */
.hfeed__empty { padding: 64px 32px; text-align: center; color: var(--ink2); }
.hfeed__empty svg { width: 46px; height: 46px; color: var(--muted); }
.hfeed__empty b { display: block; margin-top: 12px; font-size: 17px; color: var(--ink); }
.hfeed__empty p { margin: 6px 0 16px; font-size: 13.5px; line-height: 1.5; }
.hfeed__cta {
    display: inline-block; background: var(--blue); color: #fff; text-decoration: none;
    font-size: 13.5px; font-weight: 700; padding: 11px 20px; border-radius: 22px;
}
[hidden] { display: none !important; }
</style>

<script>
(function () {
    var CSRF = document.querySelector('meta[name="csrf-token"]');
    CSRF = CSRF ? CSRF.content : '';
    var SIGNED_IN = {{ auth()->check() ? 'true' : 'false' }};

    function post(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': CSRF,
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: JSON.stringify(body || {})
        }).then(function (r) { if (!r.ok) throw r; return r.json(); });
    }

    // ── Likes. Optimistic, then settled from the server's authoritative count — the
    // same contract the app uses, so a double-tap can't leave the number wrong.
    document.querySelectorAll('[data-like]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!SIGNED_IN) return;               // the login modal handles it
            var id = btn.dataset.like;
            var on = !btn.classList.contains('is-on');
            var counter = document.querySelector('[data-likes="' + id + '"]');
            var n = parseInt(counter.querySelector('b').textContent, 10) || 0;

            paintLike(btn, counter, on, Math.max(0, n + (on ? 1 : -1)));
            btn.classList.remove('is-beat');
            if (on) { void btn.offsetWidth; btn.classList.add('is-beat'); }

            post('/feed/posts/' + id + '/like')
                .then(function (j) { paintLike(btn, counter, j.liked, j.like_count); })
                .catch(function () { paintLike(btn, counter, !on, n); });
        });
    });

    function paintLike(btn, counter, on, count) {
        btn.classList.toggle('is-on', on);
        btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        counter.querySelector('b').textContent = count;
        counter.lastChild.textContent = count === 1 ? ' like' : ' likes';
        counter.hidden = count === 0;
    }

    // ── Comments. Fetched the first time a thread is opened, never before.
    document.querySelectorAll('[data-comments]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.dataset.comments;
            var thread = document.querySelector('[data-thread="' + id + '"]');
            var list = document.querySelector('[data-clist="' + id + '"]');
            if (!thread.hidden) { thread.hidden = true; return; }
            thread.hidden = false;
            if (list.dataset.loaded) return;
            list.dataset.loaded = '1';
            list.innerHTML = '<div class="hpost__cempty">Loading…</div>';
            fetch('/feed/posts/' + id + '/comments', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (j) { renderComments(list, j.results || []); })
                .catch(function () { list.innerHTML = '<div class="hpost__cempty">Couldn\'t load comments.</div>'; });
        });
    });

    function renderComments(list, rows) {
        if (!rows.length) { list.innerHTML = '<div class="hpost__cempty">No comments yet. Say something.</div>'; return; }
        list.innerHTML = '';
        rows.forEach(function (c) { list.appendChild(commentRow(c)); });
    }

    function commentRow(c) {
        var row = document.createElement('div');
        row.className = 'hpost__crow';
        var av = document.createElement('span');
        av.className = 'hpost__cav';
        if (c.avatar) {
            var img = document.createElement('img');
            img.src = c.avatar; img.alt = '';
            img.onerror = function () { img.remove(); };
            av.appendChild(img);
        } else {
            av.textContent = (c.name || '?').charAt(0).toUpperCase();
        }
        var body = document.createElement('div');
        body.className = 'hpost__cbody';
        var who = document.createElement('b');
        who.textContent = c.username ? '@' + c.username : c.name;
        // textContent throughout: a comment is user input and must never be parsed as markup.
        body.appendChild(who);
        body.appendChild(document.createTextNode(' ' + c.body));
        var ago = document.createElement('span');
        ago.className = 'hpost__cago';
        ago.textContent = c.ago ? c.ago + ' ago' : '';
        body.appendChild(ago);
        row.appendChild(av);
        row.appendChild(body);
        return row;
    }

    document.querySelectorAll('[data-cform]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var id = form.dataset.cform;
            var input = form.querySelector('input');
            var btn = form.querySelector('button');
            var body = input.value.trim();
            if (!body) return;
            btn.disabled = true;
            post('/feed/posts/' + id + '/comments', { body: body })
                .then(function (c) {
                    var list = document.querySelector('[data-clist="' + id + '"]');
                    var empty = list.querySelector('.hpost__cempty');
                    if (empty) empty.remove();
                    list.appendChild(commentRow(c));
                    input.value = '';
                    var more = document.querySelector('[data-comments="' + id + '"].hpost__more');
                    if (more) {
                        more.hidden = false;
                        more.textContent = c.comment_count === 1
                            ? 'View 1 comment' : 'View all ' + c.comment_count + ' comments';
                    }
                })
                .catch(function () { input.placeholder = "Couldn't post — try again"; })
                .then(function () { btn.disabled = false; });
        });
    });

    // ── Share. The native sheet where there is one, the clipboard otherwise; the
    // button never pretends to have done something it didn't.
    document.querySelectorAll('[data-share]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var url = location.origin + '/feed#post-' + btn.dataset.share;
            if (navigator.share) { navigator.share({ url: url }).catch(function () {}); return; }
            if (navigator.clipboard) {
                navigator.clipboard.writeText(url).then(function () {
                    btn.setAttribute('aria-label', 'Link copied');
                    btn.title = 'Link copied';
                });
            }
        });
    });

    // ── Carousel counter + dots follow the scroll position.
    document.querySelectorAll('.hpost__rail').forEach(function (rail) {
        var track = rail.querySelector('.hpost__track');
        var count = rail.querySelector('.hpost__count');
        var dots = rail.querySelectorAll('.hpost__dots i');
        var total = +rail.dataset.count;
        track.addEventListener('scroll', function () {
            var i = Math.round(track.scrollLeft / track.clientWidth);
            i = Math.max(0, Math.min(total - 1, i));
            count.textContent = (i + 1) + '/' + total;
            dots.forEach(function (d, k) { d.classList.toggle('is-on', k === i); });
        }, { passive: true });
    });
})();
</script>
@endsection
