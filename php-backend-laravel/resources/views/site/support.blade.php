@extends('site.layout')

@section('content')
{{--
    The web twin of the app's SupportChatScreen: one conversation with the Haraan team,
    replies arriving from the Filament "Support" resource.

    It deliberately reads like the site's own DM thread (.hthr), not like a contact form:
      · The thread owns the viewport on a phone — composer where the thumb is, not
        wherever the page happens to end.
      · Topics are a list you tap, with the admin's one-line examples under each —
        a native <select> in a chat is the framework talking, not the product.
      · Day rules, one time per run, optimistic send with a visible failure, and a
        "Seen" that is true: it comes from admin_unread_count, which only clears when
        someone on the team opens the thread in /control.
      · Every line of copy that isn't the user's own is set in /control
        (Support topics → "Support page text").
--}}
@php
    $firstName   = trim(strtok((string) (auth()->user()->name ?? ''), ' ') ?: '');
    $assignee    = $thread->assignee?->name ? strtok($thread->assignee->name, ' ') : null;
    $hasMessages = $messages->isNotEmpty();
    $last        = $messages->last();
    $startedAt   = $messages->first()?->created_at;

    // Line-drawn topic icons, keyed like the app's (SupportCategory::ICON_KEYS).
    $topicIcons = [
        'ticket'  => '<path d="M3 8a2 2 0 0 0 2-2h14a2 2 0 0 0 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 0-2 2H5a2 2 0 0 0-2-2v-2a2 2 0 0 0 0-4z"/><path d="M14 6v12" stroke-dasharray="2 2.5"/>',
        'card'    => '<rect x="2.5" y="5" width="19" height="14" rx="2.5"/><path d="M2.5 10h19M6.5 15h4"/>',
        'cricket' => '<path d="M15.5 3.5l5 5L10 19a2.1 2.1 0 0 1-3 0l-2-2a2.1 2.1 0 0 1 0-3z"/><path d="M5 17l-2 4"/><circle cx="18.5" cy="17.5" r="2.5"/>',
        'venue'   => '<path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
        'account' => '<circle cx="12" cy="8" r="4"/><path d="M4 20.5c1.3-3.6 4.3-5.5 8-5.5s6.7 1.9 8 5.5"/>',
        'partner' => '<rect x="3" y="7" width="18" height="13" rx="2.5"/><path d="M9 7V5.5A1.5 1.5 0 0 1 10.5 4h3A1.5 1.5 0 0 1 15 5.5V7M3 12.5h18"/>',
        'event'   => '<rect x="3.5" y="5" width="17" height="15.5" rx="2.5"/><path d="M3.5 10h17M8 3v4M16 3v4"/>',
        'chat'    => '<path d="M20 12.5a7.5 7.5 0 0 1-10.9 6.7L4 20.5l1.3-4.6A7.5 7.5 0 1 1 20 12.5z"/>',
    ];
    $icon = fn (?string $key): string => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . ($topicIcons[$key ?? 'chat'] ?? $topicIcons['chat']) . '</svg>';
@endphp

<div class="sup" id="sup"
     data-poll="{{ route('site.support.poll') }}"
     data-last="{{ $last?->id ?? 0 }}"
     data-seen="{{ $seen ? '1' : '0' }}">

    <section class="sup-chat" aria-label="Conversation with Haraan Support">
        <header class="sup-head">
            {{-- Phone only: this bar replaces the layout's pagebar, so the one header
                 names who you're talking to instead of repeating "Support". --}}
            <button type="button" class="sup-back" id="supBack" aria-label="Back">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            </button>
            <span class="sup-av" aria-hidden="true"><img src="{{ asset('images/haraan-mark.png') }}" alt=""></span>
            <span class="sup-head__who">
                <b>Haraan Support</b>
                <span id="supStatus">
                    @if($assignee)
                        {{ $assignee }} is looking after this
                    @else
                        {{ $copy['reply_time'] }}
                    @endif
                </span>
            </span>
            @if($thread->category)
                <span class="sup-head__topic">{{ $thread->category->label }}</span>
            @endif
        </header>

        <div class="sup-scroll" id="supScroll">
            @unless($hasMessages)
                <div class="sup-intro" id="supIntro">
                    <div class="sup-b sup-b--team is-last sup-b--intro">
                        <span class="sup-b__txt">{{ $firstName !== '' ? "Hi {$firstName}. " : '' }}{{ $copy['prompt'] }}</span>
                    </div>

                    @if($categories->isNotEmpty())
                        <ul class="sup-topics" id="supTopics" role="list">
                            @foreach($categories as $category)
                                <li>
                                    <button type="button" class="sup-topic" data-topic="{{ $category->id }}" data-label="{{ $category->label }}">
                                        <span class="sup-topic__ic">{!! $icon($category->icon_key) !!}</span>
                                        <span class="sup-topic__txt">
                                            <b>{{ $category->label }}</b>
                                            @if($category->subtitle)<span>{{ $category->subtitle }}</span>@endif
                                        </span>
                                        <svg class="sup-topic__go" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
                                    </button>
                                </li>
                            @endforeach
                            <li>
                                <button type="button" class="sup-topic sup-topic--other" data-topic="" data-label="">
                                    <span class="sup-topic__ic">{!! $icon('chat') !!}</span>
                                    <span class="sup-topic__txt"><b>Something else</b></span>
                                    <svg class="sup-topic__go" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
                                </button>
                            </li>
                        </ul>
                    @endif
                </div>
            @endunless

            {{-- Rendered by the script below from the same JSON the poll returns, so the
                 first paint and a live reply are one code path. --}}
            <div class="sup-log" id="supLog" aria-live="polite"></div>
            <noscript>
                @foreach($messages as $m)
                    <div class="sup-b {{ $m->sender_type === 'admin' ? 'sup-b--team' : 'sup-b--me' }} is-last">
                        <span class="sup-b__txt">{{ $m->body }}</span>
                    </div>
                @endforeach
            </noscript>
        </div>

        <button type="button" class="sup-jump" id="supJump" hidden>
            New reply
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M6 13l6 6 6-6"/></svg>
        </button>

        <form class="sup-compose" id="supForm" method="POST" action="{{ route('site.support.send') }}" autocomplete="off">
            @csrf
            <input type="hidden" name="category_id" id="supTopic" value="">

            <div class="sup-about" id="supAbout" hidden>
                <span>About <b id="supAboutLabel"></b></span>
                <button type="button" id="supAboutChange">Change</button>
            </div>

            @error('body')
                <p class="sup-err">{{ $message }}</p>
            @enderror

            <div class="sup-box">
                <textarea name="body" id="supInput" rows="1" maxlength="4000" required
                          placeholder="{{ $hasMessages ? 'Message' : $copy['composer_hint'] }}"
                          aria-label="Message to Haraan Support"></textarea>
                <button type="submit" class="sup-send" id="supSend" aria-label="Send" disabled>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5M5.5 11.5 12 5l6.5 6.5"/></svg>
                </button>
            </div>
            <p class="sup-keys" aria-hidden="true"><kbd>Enter</kbd> to send · <kbd>Shift</kbd> + <kbd>Enter</kbd> for a new line</p>
        </form>
    </section>

    {{-- Desktop only: the facts of this conversation, and the pages that often answer
         the question faster than a message does. --}}
    <aside class="sup-side" aria-label="About this conversation">
        <h2>This conversation</h2>
        <dl class="sup-facts">
            <div><dt>Topic</dt><dd id="supFactTopic">{{ $thread->category?->label ?? 'Not chosen yet' }}</dd></div>
            <div><dt>Handled by</dt><dd>{{ $thread->assignee?->name ?? 'Next person on the team' }}</dd></div>
            <div><dt>Started</dt><dd id="supFactStarted" @if($startedAt) data-ts="{{ $startedAt->toIso8601String() }}" @endif>{{ $startedAt ? $startedAt->format('j M Y') : 'When you send your first message' }}</dd></div>
        </dl>

        <h2>Often quicker</h2>
        <nav class="sup-links">
            <a href="{{ route('site.bookings') }}"><b>My bookings</b><span>Tickets, entry passes, payment status</span></a>
            <a href="{{ route('site.profile') }}"><b>Profile &amp; sign-in</b><span>Name, phone number, email</span></a>
            <a href="{{ route('site.legal', 'terms') }}"><b>Terms of use</b><span>Cancellations and refunds</span></a>
        </nav>
    </aside>
</div>

<script type="application/json" id="supData">@json(['messages' => $payload])</script>

<style>
/* =============================================================================
   SUPPORT (.sup) — one conversation with the Haraan team.
   Phone (≤1024px, where the layout shows .pagebar): the thread fills the space
   under the pagebar; the list scrolls, the composer stays on the keyboard.
   Desktop: the thread as a tall column, facts in a quiet side rail.
   ========================================================================== */
.sup {
    --ink: #0F172A; --ink2: #475569; --muted: #94A3B8; --line: #E7ECF3;
    --blue: #2563EB; --blue-d: #1D4ED8; --tint: #EFF6FF; --team: #F1F5F9;
    --ease: cubic-bezier(.2, .8, .2, 1);
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    color: var(--ink);
    display: grid;
    grid-template-columns: minmax(0, 1fr) 272px;
    gap: 40px;
    max-width: 1040px;
    margin: 0 auto;
    padding: 8px 0 32px;
}

/* --- The thread column ---------------------------------------------------- */
.sup-chat {
    position: relative;
    display: flex; flex-direction: column;
    /* Tall enough to feel like a place, short enough that the composer is on screen
       without scrolling the page. 64px topbar + main padding above it. */
    height: calc(100vh - 166px); min-height: 520px; max-height: 860px;
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 18px;
    overflow: hidden;
}

.sup-head {
    flex: 0 0 auto; display: flex; align-items: center; gap: 12px;
    padding: 14px 18px; border-bottom: 1px solid var(--line);
}
.sup-back {
    display: none; flex: 0 0 auto; width: 38px; height: 38px; margin: 0 -4px 0 -8px;
    border: 0; border-radius: 50%; background: none; color: var(--ink); cursor: pointer;
    align-items: center; justify-content: center; -webkit-tap-highlight-color: transparent;
}
.sup-back svg { width: 21px; height: 21px; }
.sup-back:active { background: var(--team); }
.sup-av {
    flex: 0 0 auto; width: 40px; height: 40px; border-radius: 50%;
    background: #fff; border: 1px solid var(--line);
    display: inline-flex; align-items: center; justify-content: center; overflow: hidden;
}
.sup-av img { width: 20px; height: auto; display: block; }
.sup-head__who { min-width: 0; display: flex; flex-direction: column; line-height: 1.3; }
.sup-head__who b { font-size: 15px; font-weight: 700; }
.sup-head__who span { font-size: 12.5px; color: var(--ink2); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sup-head__topic {
    margin-left: auto; flex: 0 0 auto;
    font-size: 12px; font-weight: 600; color: var(--ink2);
    padding: 5px 10px; border-radius: 8px; background: var(--team);
}

.sup-scroll {
    flex: 1; min-height: 0; overflow-y: auto; overscroll-behavior: contain;
    padding: 14px 18px 6px;
    scroll-behavior: auto;
}
.sup-log { display: flex; flex-direction: column; }

/* Day rules: a centred label on a hairline — same object as the DM thread. */
.sup-day { position: relative; text-align: center; margin: 14px 0 10px; }
.sup-day::before { content: ""; position: absolute; left: 0; right: 0; top: 50%; height: 1px; background: var(--line); }
.sup-day span { position: relative; background: #fff; padding: 0 10px; font-size: 11.5px; font-weight: 700; color: var(--muted); }

/* --- Bubbles ---------------------------------------------------------------- */
.sup-b {
    align-self: flex-start; max-width: min(78%, 520px); margin-bottom: 3px;
    padding: 9px 13px 7px; border-radius: 18px 18px 18px 6px;
    background: var(--team); color: var(--ink);
    display: flex; flex-direction: column; gap: 2px;
    transform-origin: 0 100%;
}
/* A change of speaker opens the gap; a run from one speaker hugs. */
.sup-b.is-last { margin-bottom: 12px; }
.sup-b--me { align-self: flex-end; background: var(--blue); color: #fff; border-radius: 18px 18px 6px 18px; transform-origin: 100% 100%; }
.sup-b__from { font-size: 11.5px; font-weight: 700; color: var(--blue); }
.sup-b__txt {
    font-size: 14.5px; line-height: 1.45; white-space: pre-wrap; overflow-wrap: anywhere;
    /* Inter's contextual alternates turn the x in "Q8x2" into a ×. People paste
       payment and booking IDs here; they must read exactly as typed. */
    font-feature-settings: "calt" 0;
}
.sup-b time { align-self: flex-end; font-size: 10.5px; color: var(--muted); font-variant-numeric: tabular-nums; }
.sup-b--me time { color: rgba(255, 255, 255, .78); }
.sup-b.is-sending { opacity: .6; }
.sup-b.is-failed { background: #FEE2E2; color: #991B1B; cursor: pointer; }
.sup-b.is-failed time { color: #B91C1C; }
/* New arrivals rise into place; the first paint doesn't animate. */
.sup-b.is-new { animation: supIn .26s var(--ease) both; }
@keyframes supIn { from { opacity: 0; transform: translateY(8px) scale(.96); } }

/* Under the reader's own last line: Sending… / Sent / Seen — nothing invented. */
.sup-receipt { align-self: flex-end; margin: -8px 2px 12px; font-size: 11px; font-weight: 600; color: var(--muted); }
.sup-receipt.is-seen { color: var(--blue); }

/* --- First visit: greeting + topic list -------------------------------------- */
.sup-intro { display: flex; flex-direction: column; padding-top: 6px; }
.sup-b--intro { max-width: 88%; }
.sup-topics {
    list-style: none; margin: 0 0 12px; padding: 0;
    max-width: 440px; border: 1px solid var(--line); border-radius: 16px; overflow: hidden;
    background: #fff;
}
.sup-topics li + li { border-top: 1px solid var(--line); }
.sup-topic {
    width: 100%; display: flex; align-items: center; gap: 12px;
    padding: 11px 14px; background: #fff; border: 0; cursor: pointer;
    font: inherit; color: inherit; text-align: left;
    transition: background-color .15s ease;
    -webkit-tap-highlight-color: transparent;
}
.sup-topic:hover { background: #F8FAFC; }
.sup-topic:active { background: var(--tint); }
.sup-topic:focus-visible { outline: 2px solid var(--blue); outline-offset: -2px; }
.sup-topic__ic {
    flex: 0 0 auto; width: 34px; height: 34px; border-radius: 10px;
    background: var(--tint); color: var(--blue);
    display: inline-flex; align-items: center; justify-content: center;
}
.sup-topic__ic svg { width: 19px; height: 19px; }
.sup-topic__txt { flex: 1; min-width: 0; display: flex; flex-direction: column; line-height: 1.3; }
.sup-topic__txt b { font-size: 14px; font-weight: 600; }
.sup-topic__txt span { font-size: 12.5px; color: var(--ink2); }
.sup-topic__go { flex: 0 0 auto; width: 16px; height: 16px; color: var(--muted); transition: transform .18s var(--ease); }
.sup-topic:hover .sup-topic__go { transform: translateX(2px); color: var(--ink2); }
.sup-topic--other .sup-topic__ic { background: var(--team); color: var(--ink2); }

/* Picked: the list folds into the user's answer, the way a chat would record it. */
.sup-intro.is-picked .sup-topics { display: none; }

/* --- Jump to latest --------------------------------------------------------- */
.sup-jump {
    position: absolute; left: 50%; bottom: 96px; transform: translateX(-50%);
    display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 14px; border-radius: 999px; border: 0;
    background: var(--ink); color: #fff; font: 600 12.5px/1 'Inter', sans-serif;
    box-shadow: 0 6px 18px rgba(15, 23, 42, .18); cursor: pointer;
    animation: supIn .22s var(--ease) both;
}
.sup-jump svg { width: 14px; height: 14px; }
.sup-jump[hidden] { display: none; }

/* --- Composer ----------------------------------------------------------------- */
.sup-compose { flex: 0 0 auto; padding: 8px 14px 12px; border-top: 1px solid var(--line); background: #fff; }
.sup-about {
    display: flex; align-items: center; gap: 10px;
    margin: 2px 4px 8px; font-size: 12.5px; color: var(--ink2);
}
.sup-about[hidden] { display: none; }
.sup-about b { color: var(--ink); font-weight: 600; }
.sup-about button {
    margin-left: auto; border: 0; background: none; padding: 4px 6px; border-radius: 6px;
    font: 600 12.5px/1 'Inter', sans-serif; color: var(--blue); cursor: pointer;
}
.sup-about button:hover { background: var(--tint); }
.sup-err { margin: 0 4px 8px; font-size: 13px; color: #DC2626; }
.sup-err:empty { display: none; }

.sup-box {
    display: flex; align-items: flex-end; gap: 8px;
    padding: 5px 5px 5px 16px; border-radius: 24px;
    background: #F4F6FA; border: 1px solid transparent;
    transition: border-color .15s ease, background-color .15s ease;
}
.sup-box:focus-within { background: #fff; border-color: #C7D2FE; box-shadow: 0 0 0 3px rgba(37, 99, 235, .08); }
.sup-box textarea {
    flex: 1; min-width: 0; resize: none; border: 0; outline: 0; background: transparent;
    padding: 9px 0; max-height: 132px;
    font: 15px/1.4 'Inter', -apple-system, sans-serif; color: var(--ink);
}
.sup-box textarea::placeholder { color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sup-send {
    flex: 0 0 auto; width: 38px; height: 38px; border-radius: 50%; border: 0;
    display: inline-flex; align-items: center; justify-content: center;
    background: var(--blue); color: #fff; cursor: pointer;
    transition: background-color .15s ease, transform .12s var(--ease), opacity .15s ease;
}
.sup-send svg { width: 18px; height: 18px; }
.sup-send:hover { background: var(--blue-d); }
.sup-send:active { transform: scale(.9); }
.sup-send:disabled { background: #CBD5E1; cursor: default; transform: none; }
.sup-send.is-fired svg { animation: supFire .32s var(--ease); }
@keyframes supFire { 50% { transform: translateY(-5px); opacity: 0; } 51% { transform: translateY(5px); } }
.sup-keys { margin: 6px 6px 0; font-size: 11px; color: var(--muted); }
.sup-keys kbd { font: 600 10.5px/1 'Inter', sans-serif; padding: 2px 5px; border-radius: 4px; background: var(--team); color: var(--ink2); }

/* --- Desktop side rail ---------------------------------------------------------- */
.sup-side { padding-top: 6px; }
.sup-side h2 {
    margin: 0 0 10px; font-size: 11.5px; font-weight: 700; letter-spacing: .06em;
    text-transform: uppercase; color: var(--muted);
}
.sup-facts { margin: 0 0 30px; }
.sup-facts div { padding: 10px 0; border-bottom: 1px solid var(--line); }
.sup-facts div:first-child { border-top: 1px solid var(--line); }
.sup-facts dt { font-size: 12px; color: var(--muted); margin-bottom: 2px; }
.sup-facts dd { margin: 0; font-size: 14px; font-weight: 600; color: var(--ink); }
.sup-links { display: flex; flex-direction: column; }
.sup-links a {
    display: flex; flex-direction: column; gap: 1px;
    padding: 10px 12px; margin: 0 -12px; border-radius: 10px;
    text-decoration: none; color: inherit; transition: background-color .15s ease;
}
.sup-links a:hover { background: #F8FAFC; }
.sup-links b { font-size: 14px; font-weight: 600; color: var(--ink); }
.sup-links span { font-size: 12.5px; color: var(--ink2); }

@media (prefers-reduced-motion: reduce) {
    .sup-b.is-new, .sup-jump, .sup-send.is-fired svg { animation: none; }
}

/* --- Phone & tablet: the thread is the page ---------------------------------------- */
@media (max-width: 1024px) {
    body:has(.sup) main.container {
        width: 100% !important; max-width: none !important;
        margin: 0 !important; padding: 0 !important; background: #fff;
    }
    .sup { display: block; max-width: none; padding: 0; }
    .sup-side, .sup-keys { display: none; }
    body:has(.sup) .pagebar { display: none; }
    .sup-back { display: inline-flex; }
    .sup-chat {
        /* The whole screen: header pinned, list scrolls, composer on the keyboard.
           dvh tracks the on-screen keyboard's resize on Android. */
        height: 100vh; height: 100dvh;
        min-height: 0; max-height: none;
        border: 0; border-radius: 0;
    }
    .sup-head { position: sticky; top: 0; padding: 10px 14px 10px 12px; gap: 10px; }
    .sup-av { width: 36px; height: 36px; }
    .sup-av img { width: 18px; }
    .sup-scroll { padding: 12px 14px 4px; }
    .sup-b { max-width: 84%; }
    /* On a phone the status line needs the width; the topic is in the thread. */
    .sup-head__topic { display: none; }
    .sup-b--intro { max-width: 92%; }
    .sup-topics { max-width: none; }
    .sup-topic { padding: 13px 14px; }
    .sup-jump { bottom: 84px; }
    .sup-compose { padding: 8px 10px calc(10px + env(safe-area-inset-bottom, 0px)); }
    /* 16px keeps iOS Safari from zooming the page when the box takes focus. */
    .sup-box textarea { font-size: 16px; }
}
</style>

<script>
(() => {
    const root = document.getElementById('sup');
    if (!root) return;

    const scroller = document.getElementById('supScroll');
    const log      = document.getElementById('supLog');
    const form     = document.getElementById('supForm');
    const input    = document.getElementById('supInput');
    const send     = document.getElementById('supSend');
    const jump     = document.getElementById('supJump');
    const intro    = document.getElementById('supIntro');
    const topicIn  = document.getElementById('supTopic');
    const about    = document.getElementById('supAbout');
    const token    = form.querySelector('input[name=_token]').value;
    const coarse   = matchMedia('(pointer: coarse)').matches;

    let messages = JSON.parse(document.getElementById('supData').textContent).messages || [];
    let seen     = root.dataset.seen === '1';
    let lastId   = Number(root.dataset.last) || 0;
    let inFlight = 0;
    const pending = [];          // optimistic user messages not yet stored

    // --- Rendering ---------------------------------------------------------------
    const dayKey = (d) => `${d.getFullYear()}-${d.getMonth()}-${d.getDate()}`;
    const dayLabel = (d) => {
        const today = new Date(), y = new Date(); y.setDate(today.getDate() - 1);
        if (dayKey(d) === dayKey(today)) return 'Today';
        if (dayKey(d) === dayKey(y)) return 'Yesterday';
        return d.toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: d.getFullYear() === today.getFullYear() ? undefined : 'numeric' });
    };
    const clock = (d) => d.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
    const el = (tag, cls, text) => { const n = document.createElement(tag); if (cls) n.className = cls; if (text != null) n.textContent = text; return n; };

    function render(freshIds = new Set()) {
        const all = messages.concat(pending);
        const nearBottom = isNearBottom();
        log.replaceChildren();
        let prevDay = null;

        all.forEach((m, i) => {
            const d = m.ts ? new Date(m.ts) : new Date();
            const k = dayKey(d);
            if (k !== prevDay) {
                prevDay = k;
                const rule = el('div', 'sup-day'); rule.append(el('span', null, dayLabel(d)));
                log.append(rule);
            }
            const next = all[i + 1];
            const endOfRun = !next || next.from !== m.from || (next.ts && dayKey(new Date(next.ts)) !== k);
            const startOfRun = i === 0 || all[i - 1].from !== m.from;

            const b = el('div', `sup-b ${m.from === 'admin' ? 'sup-b--team' : 'sup-b--me'}`);
            if (endOfRun) b.classList.add('is-last');
            if (m.state) b.classList.add(`is-${m.state}`);
            if (freshIds.has(m.id)) b.classList.add('is-new');
            if (m.from === 'admin' && startOfRun && m.name) b.append(el('span', 'sup-b__from', `${m.name} · Haraan`));
            b.append(el('span', 'sup-b__txt', m.body));
            if (endOfRun) {
                const t = el('time', null, m.state === 'failed' ? 'Not sent · tap to retry' : (m.state === 'sending' ? '' : clock(d)));
                if (m.ts) t.dateTime = m.ts;
                if (t.textContent) b.append(t);
            }
            if (m.state === 'failed') b.addEventListener('click', () => retry(m));
            log.append(b);
        });

        // Receipt under the reader's own last line, only if it's the last thing said.
        const tail = all[all.length - 1];
        if (tail && tail.from === 'user' && tail.state !== 'failed') {
            const r = el('div', 'sup-receipt', tail.state === 'sending' ? 'Sending…' : (seen ? 'Seen' : 'Sent'));
            if (seen && tail.state !== 'sending') r.classList.add('is-seen');
            log.append(r);
        }

        if (nearBottom || freshIds.size && [...freshIds].some((id) => String(id).startsWith('tmp'))) toBottom();
    }

    // --- Scrolling ------------------------------------------------------------------
    const isNearBottom = () => scroller.scrollHeight - scroller.scrollTop - scroller.clientHeight < 80;
    const toBottom = (smooth) => {
        scroller.scrollTo({ top: scroller.scrollHeight, behavior: smooth ? 'smooth' : 'auto' });
        jump.hidden = true;
    };
    scroller.addEventListener('scroll', () => { if (isNearBottom()) jump.hidden = true; }, { passive: true });
    jump.addEventListener('click', () => toBottom(true));

    // --- Topic picking ----------------------------------------------------------------
    function pickTopic(id, label) {
        topicIn.value = id;
        intro?.classList.add('is-picked');
        if (label) {
            document.getElementById('supAboutLabel').textContent = label;
            about.hidden = false;
        } else {
            about.hidden = true;
        }
        input.focus({ preventScroll: true });
    }
    document.querySelectorAll('.sup-topic').forEach((btn) =>
        btn.addEventListener('click', () => { buzz(); pickTopic(btn.dataset.topic, btn.dataset.label); }));
    document.getElementById('supAboutChange')?.addEventListener('click', () => {
        topicIn.value = '';
        about.hidden = true;
        intro?.classList.remove('is-picked');
        scroller.scrollTo({ top: 0, behavior: 'smooth' });
    });

    // --- Composer -----------------------------------------------------------------------
    const buzz = () => { if (coarse && navigator.vibrate) try { navigator.vibrate(8); } catch (e) {} };
    function grow() {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 132) + 'px';
        send.disabled = input.value.trim() === '';
    }
    input.addEventListener('input', grow);
    // Desktop: Enter sends, Shift+Enter breaks the line. On a phone the keyboard's
    // return key is a new line, like every messaging app there.
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey && !coarse && !e.isComposing) {
            e.preventDefault();
            form.requestSubmit();
        }
    });

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const body = input.value.trim();
        if (!body) return;
        input.value = '';
        grow();
        send.classList.remove('is-fired'); void send.offsetWidth; send.classList.add('is-fired');
        buzz();
        const m = { id: 'tmp' + Date.now(), from: 'user', body, ts: new Date().toISOString(), state: 'sending', topic: topicIn.value };
        pending.push(m);
        deliver(m);
    });

    async function deliver(m) {
        inFlight++;
        seen = false;
        render(new Set([m.id]));
        try {
            const fd = new FormData();
            fd.append('_token', token);
            fd.append('body', m.body);
            if (m.topic) fd.append('category_id', m.topic);
            const r = await fetch(form.action, {
                method: 'POST', body: fd, credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!r.ok) throw new Error(String(r.status));
            const { message } = await r.json();
            pending.splice(pending.indexOf(m), 1);
            if (message && !messages.some((x) => x.id === message.id)) messages.push(message);
            if (message) lastId = Math.max(lastId, message.id);
            // The topic is set by the first message; after that it's the team's call.
            if (m.topic) settleTopic();
            intro?.remove();
            input.placeholder = 'Message';
        } catch (err) {
            m.state = 'failed';
        } finally {
            inFlight--;
            render();
        }
    }
    function retry(m) {
        m.state = 'sending';
        m.ts = new Date().toISOString();
        pending.splice(pending.indexOf(m), 1);
        pending.push(m);
        deliver(m);
    }
    function settleTopic() {
        const label = document.getElementById('supAboutLabel').textContent;
        about.hidden = true;
        topicIn.value = '';
        const fact = document.getElementById('supFactTopic');
        if (fact && label) fact.textContent = label;
    }

    // --- Live replies: same 4s cadence as the app; the site has no socket client yet.
    async function poll() {
        if (inFlight) return;
        try {
            const r = await fetch(`${root.dataset.poll}?after=${lastId}`, {
                headers: { 'Accept': 'application/json' }, credentials: 'same-origin',
            });
            if (!r.ok) return;
            const data = await r.json();
            const fresh = (data.messages || []).filter((m) => !messages.some((x) => x.id === m.id));
            const seenChanged = data.seen !== seen;
            seen = !!data.seen;
            if (!fresh.length && !seenChanged) return;
            const wasNear = isNearBottom();
            messages = messages.concat(fresh);
            fresh.forEach((m) => { lastId = Math.max(lastId, m.id); });
            if (fresh.length) intro?.remove();
            render(new Set(fresh.map((m) => m.id)));
            if (fresh.some((m) => m.from === 'admin')) {
                buzz();
                if (!wasNear) jump.hidden = false;
            }
        } catch (e) { /* a blip — the next tick retries */ }
    }
    setInterval(() => { if (!document.hidden) poll(); }, 4000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });

    // Back: pop history; a page opened directly has nothing to pop.
    document.getElementById('supBack')?.addEventListener('click', () => {
        if (history.length > 1) history.back(); else location.assign('/');
    });
    // Dates in the reader's own timezone, like the thread's times.
    const started = document.getElementById('supFactStarted');
    if (started?.dataset.ts) started.textContent = new Date(started.dataset.ts)
        .toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });

    render();
    toBottom();
    grow();
})();
</script>
@endsection
