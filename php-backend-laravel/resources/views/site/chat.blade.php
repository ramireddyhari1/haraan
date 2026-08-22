@extends('site.layout')

@section('content')
{{--
    CHAT — the thread list, the twin of the app's Chat tab.

    This is player-to-player messaging, NOT the support desk. The web's Chat button used
    to open /support (you talking to Haraan), which is a different conversation with a
    different other end.

    Starting a conversation is deliberately not offered here: the server only allows a
    thread between two players who follow EACH OTHER, so a "new message" button on the web
    would mostly be a button that refuses. The empty state says the rule instead.
--}}
<div class="hchat">
    <header class="hchat__bar">
        <h1>Chat</h1>
        @if ($unreadTotal > 0)
            <span class="hchat__total">{{ $unreadTotal }}</span>
        @endif
    </header>

    @forelse ($threads as $t)
        <a class="hchat__row {{ $t['unread'] > 0 ? 'is-unread' : '' }}" href="{{ route('site.chat.thread', ['id' => $t['id']]) }}">
            <span class="hchat__av">
                @if ($t['avatar'] !== '')
                    <img src="{{ $t['avatar'] }}" alt="" loading="lazy" onerror="this.remove()">
                @else
                    {{ mb_strtoupper(mb_substr($t['name'], 0, 1)) }}
                @endif
                @if ($t['isGroup'])
                    <i class="hchat__grp" aria-hidden="true"></i>
                @endif
            </span>
            <span class="hchat__mid">
                <span class="hchat__name">{{ $t['name'] }}</span>
                <span class="hchat__last">
                    {{ $t['lastMessage'] !== '' ? $t['lastMessage'] : 'No messages yet' }}
                </span>
            </span>
            <span class="hchat__end">
                @if ($t['lastAt'])
                    {{-- Time today, date before that — the same rule the thread's day rules
                         use, so a row and its thread never disagree about when. --}}
                    <time datetime="{{ $t['lastAt']->toIso8601String() }}">
                        {{ $t['lastAt']->isToday() ? $t['lastAt']->format('g:i A') : ($t['lastAt']->isYesterday() ? 'Yesterday' : $t['lastAt']->format('j M')) }}
                    </time>
                @endif
                @if ($t['unread'] > 0)
                    <span class="hchat__badge">{{ $t['unread'] > 99 ? '99+' : $t['unread'] }}</span>
                @endif
            </span>
        </a>
    @empty
        <div class="hchat__empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" aria-hidden="true"><path d="M21 11.6a8 8 0 0 1-8.6 8 8.6 8.6 0 0 1-3.6-.85L3.4 20.4l1.65-5.4A8 8 0 0 1 12.4 3.6a8 8 0 0 1 8.6 8Z"/></svg>
            <b>No conversations yet</b>
            <p>You can message a player once you follow each other. Find someone from a match or the leaderboard, follow them, and the conversation opens up.</p>
            <a class="hchat__cta" href="{{ route('site.gamehub.actionboard') }}">Browse matches</a>
        </div>
    @endforelse
</div>

@include('site.partials.mab-bottombar', ['active' => 'Chat'])

<style>
/* =============================================================================
   CHAT LIST (.hchat) — port of the app's chat inbox.
   ========================================================================== */
@media (max-width: 720px) {
    body:has(.hchat) .topbar { display: none !important; }
    body:has(.hchat) main.container { padding-left: 0; padding-right: 0; max-width: none; }
}
.hchat {
    --ink: #0F172A; --ink2: #475569; --muted: #94A3B8; --line: #E7ECF3; --blue: #2563EB;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    max-width: 520px; margin: 0 auto; background: #fff; color: var(--ink);
    min-height: 100vh; padding-bottom: 116px;
}
.hchat__bar {
    position: sticky; top: 0; z-index: 20; background: #fff;
    display: flex; align-items: center; gap: 10px; padding: 16px 18px 12px;
    border-bottom: 1px solid var(--line);
}
.hchat__bar h1 { margin: 0; font-size: 21px; font-weight: 800; letter-spacing: -.4px; }
.hchat__total {
    background: var(--blue); color: #fff; font-size: 11.5px; font-weight: 800;
    border-radius: 11px; padding: 2px 8px; min-width: 22px; text-align: center;
}

.hchat__row {
    display: flex; align-items: center; gap: 12px; padding: 11px 16px;
    text-decoration: none; color: inherit; -webkit-tap-highlight-color: transparent;
}
.hchat__row:active { background: #F8FAFC; }
.hchat__av {
    position: relative; flex: 0 0 auto; width: 52px; height: 52px; border-radius: 50%;
    background: #F1F5F9; overflow: hidden; display: flex; align-items: center; justify-content: center;
    font-size: 19px; font-weight: 800; color: var(--ink2);
}
.hchat__av img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
.hchat__mid { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.hchat__name { font-size: 14.5px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.hchat__last { font-size: 13px; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
/* Unread darkens the preview and bolds it — the badge alone is easy to miss in a list. */
.hchat__row.is-unread .hchat__last { color: var(--ink2); font-weight: 600; }
.hchat__end { flex: 0 0 auto; display: flex; flex-direction: column; align-items: flex-end; gap: 5px; }
.hchat__end time { font-size: 11.5px; color: var(--muted); }
.hchat__badge {
    background: var(--blue); color: #fff; font-size: 11px; font-weight: 800;
    border-radius: 10px; padding: 1px 7px; min-width: 20px; text-align: center;
}

.hchat__empty { padding: 64px 32px; text-align: center; color: var(--ink2); }
.hchat__empty svg { width: 46px; height: 46px; color: var(--muted); }
.hchat__empty b { display: block; margin-top: 12px; font-size: 17px; color: var(--ink); }
.hchat__empty p { margin: 6px 0 16px; font-size: 13.5px; line-height: 1.55; }
.hchat__cta {
    display: inline-block; background: var(--blue); color: #fff; text-decoration: none;
    font-size: 13.5px; font-weight: 700; padding: 11px 20px; border-radius: 22px;
}
</style>
@endsection
