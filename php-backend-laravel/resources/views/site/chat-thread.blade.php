@extends('site.layout')

@section('content')
{{--
    One conversation — the app's ChatScreens thread, ported.

    The details here are the ones the app went through a whole pass to get right, so they
    are not decoration:
      · Day rules (Today / Yesterday / 12 Aug). Without them a thread is one endless run.
      · ONE timestamp per RUN, on its last bubble — four lines sent in a breath carried
        four near-identical times and read like a log file.
      · A "N new messages" rule where the reader left off, computed from the unread count
        captured BEFORE the thread was marked read (after that it is always zero).
      · Sending is optimistic with a visible failure: the bubble appears dimmed, then
        settles — or turns red with "Tap to retry". A send that shows nothing until the
        round trip returns is a send you press twice.
--}}
@php
    $prevDay = null;
    // Where the unread rule goes: N messages back from the end, and only if that message
    // is not the reader's own (a rule above your own line means nothing).
    $unreadAt = null;
    if ($unreadOnOpen > 0 && count($messages) >= $unreadOnOpen) {
        $candidate = count($messages) - $unreadOnOpen;
        if (!($messages[$candidate]['mine'] ?? true)) {
            $unreadAt = $candidate;
        }
    }
@endphp

<div class="hthr" data-thread-id="{{ $thread['id'] }}">
    <header class="hthr__bar">
        <a class="hthr__back" href="{{ route('site.chat') }}" aria-label="Back to chats">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        </a>
        <a class="hthr__who" href="{{ $thread['playerId'] !== '' ? '/player/' . $thread['playerId'] : '#' }}">
            <span class="hthr__av">
                @if ($thread['avatar'] !== '')
                    <img src="{{ $thread['avatar'] }}" alt="" onerror="this.remove()">
                @else
                    {{ mb_strtoupper(mb_substr($thread['name'], 0, 1)) }}
                @endif
            </span>
            <span class="hthr__names">
                <b>{{ $thread['name'] }}</b>
                @if ($thread['isGroup'])
                    <span>{{ $thread['memberCount'] }} members</span>
                @elseif ($thread['username'] !== '')
                    <span>{{ '@' . $thread['username'] }}</span>
                @endif
            </span>
        </a>
    </header>

    <div class="hthr__scroll" id="hthrScroll">
        @if (count($messages) === 0)
            <div class="hthr__empty">
                <span class="hthr__eav">
                    @if ($thread['avatar'] !== '')
                        <img src="{{ $thread['avatar'] }}" alt="" onerror="this.remove()">
                    @else
                        {{ mb_strtoupper(mb_substr($thread['name'], 0, 1)) }}
                    @endif
                </span>
                <b>Say hello to {{ $thread['name'] }}</b>
                <p>This is the beginning of your conversation.</p>
            </div>
        @endif

        <div class="hthr__list" id="hthrList">
            @foreach ($messages as $i => $m)
                @php
                    $sent = $m['sentAt'] ? \Illuminate\Support\Carbon::parse($m['sentAt']) : null;
                    $day = $sent?->format('Y-m-d');
                    // Last of a run = the next message is from the other speaker, or this
                    // is the last one. Only that bubble carries a time.
                    $endOfRun = !isset($messages[$i + 1]) || $messages[$i + 1]['mine'] !== $m['mine'];
                @endphp
                @if ($day !== null && $day !== $prevDay)
                    @php $prevDay = $day; @endphp
                    <div class="hthr__day"><span>
                        {{ $sent->isToday() ? 'Today' : ($sent->isYesterday() ? 'Yesterday' : $sent->format('j M Y')) }}
                    </span></div>
                @endif
                @if ($unreadAt === $i)
                    <div class="hthr__new"><span>{{ $unreadOnOpen }} new {{ $unreadOnOpen === 1 ? 'message' : 'messages' }}</span></div>
                @endif
                <div class="hthr__b {{ $m['mine'] ? 'is-mine' : '' }} {{ $endOfRun ? 'is-last' : '' }}" data-id="{{ $m['id'] }}">
                    @if ($thread['isGroup'] && !$m['mine'] && $endOfRun)
                        <span class="hthr__from">{{ $m['senderName'] }}</span>
                    @endif
                    <span class="hthr__txt {{ $m['unsent'] ? 'is-unsent' : '' }}">{{ $m['unsent'] ? 'This message was unsent' : $m['body'] }}</span>
                    @if ($endOfRun && $sent)
                        <time datetime="{{ $sent->toIso8601String() }}">{{ $sent->format('g:i A') }}</time>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <form class="hthr__composer" id="hthrForm" autocomplete="off">
        <input type="text" id="hthrInput" name="body" maxlength="2000" placeholder="Message…" required>
        <button type="submit" id="hthrSend" aria-label="Send">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M21.3 3 2.9 11.05l6.6 2.6 2.6 6.6z"/></svg>
        </button>
    </form>
</div>

@include('site.partials.mab-bottombar', ['active' => 'Chat'])

<style>
/* =============================================================================
   CHAT THREAD (.hthr) — port of the app's conversation screen.
   ========================================================================== */
@media (max-width: 720px) {
    body:has(.hthr) .topbar { display: none !important; }
    body:has(.hthr) main.container { padding-left: 0; padding-right: 0; max-width: none; }
}
.hthr {
    --ink: #0F172A; --ink2: #475569; --muted: #94A3B8; --line: #E7ECF3; --blue: #2563EB;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    max-width: 520px; margin: 0 auto; background: #fff; color: var(--ink);
    /* The thread owns the viewport: header pinned, list scrolls, composer sits above the
       floating bar. A page-scrolling chat puts the composer wherever the page happens to
       end, which is never where a thumb is. */
    display: flex; flex-direction: column; height: 100vh; height: 100dvh;
}
.hthr__bar {
    flex: 0 0 auto; display: flex; align-items: center; gap: 6px;
    padding: 10px 12px; border-bottom: 1px solid var(--line); background: #fff;
}
.hthr__back {
    flex: 0 0 auto; width: 38px; height: 38px; border-radius: 50%; color: var(--ink);
    display: inline-flex; align-items: center; justify-content: center; text-decoration: none;
}
.hthr__back svg { width: 21px; height: 21px; }
.hthr__who { display: flex; align-items: center; gap: 10px; min-width: 0; text-decoration: none; color: inherit; }
.hthr__av {
    position: relative; flex: 0 0 auto; width: 38px; height: 38px; border-radius: 50%;
    background: #F1F5F9; overflow: hidden; display: flex; align-items: center; justify-content: center;
    font-size: 15px; font-weight: 800; color: var(--ink2);
}
.hthr__av img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
.hthr__names { min-width: 0; display: flex; flex-direction: column; line-height: 1.25; }
.hthr__names b { font-size: 14.5px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.hthr__names span { font-size: 11.5px; color: var(--muted); }

.hthr__scroll { flex: 1; min-height: 0; overflow-y: auto; padding: 10px 12px 4px; background: #fff; }
.hthr__list { display: flex; flex-direction: column; }

/* Day rules and the unread marker are the same object: a centred label on a hairline. */
.hthr__day, .hthr__new { position: relative; text-align: center; margin: 12px 0 8px; }
.hthr__day::before, .hthr__new::before {
    content: ""; position: absolute; left: 0; right: 0; top: 50%; height: 1px; background: var(--line);
}
.hthr__day span, .hthr__new span {
    position: relative; background: #fff; padding: 0 10px;
    font-size: 11.5px; font-weight: 700; color: var(--muted);
}
.hthr__new span { color: var(--blue); }
.hthr__new::before { background: #DBEAFE; }

.hthr__b {
    align-self: flex-start; max-width: 78%; margin-bottom: 3px;
    background: #F1F5F9; color: var(--ink);
    border-radius: 16px 16px 16px 6px; padding: 8px 12px 6px;
    display: flex; flex-direction: column; gap: 2px;
}
/* A change of speaker opens the gap; a run from one speaker hugs. */
.hthr__b.is-last { margin-bottom: 10px; }
.hthr__b.is-mine { align-self: flex-end; background: var(--blue); color: #fff; border-radius: 16px 16px 6px 16px; }
.hthr__from { font-size: 11px; font-weight: 700; color: var(--blue); }
.hthr__txt { font-size: 14px; line-height: 1.4; white-space: pre-wrap; overflow-wrap: anywhere; }
.hthr__txt.is-unsent { font-style: italic; opacity: .7; }
.hthr__b time { align-self: flex-end; font-size: 10.5px; color: var(--muted); }
.hthr__b.is-mine time { color: rgba(255, 255, 255, .75); }
/* In flight, and failed. Both states are visible on the bubble itself. */
.hthr__b.is-sending { opacity: .55; }
.hthr__b.is-failed { background: #FEE2E2; color: #991B1B; cursor: pointer; }
.hthr__b.is-failed time { color: #B91C1C; }

.hthr__empty { text-align: center; padding: 48px 24px 24px; color: var(--ink2); }
.hthr__eav {
    position: relative; display: inline-flex; align-items: center; justify-content: center;
    width: 72px; height: 72px; border-radius: 50%; background: #F1F5F9; overflow: hidden;
    font-size: 26px; font-weight: 800; color: var(--ink2);
}
.hthr__eav img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
.hthr__empty b { display: block; margin-top: 12px; font-size: 16px; color: var(--ink); }
.hthr__empty p { margin: 4px 0 0; font-size: 13px; }

/* Composer: a filled pill, matching every other input in the app — never a boxed
   form field, which is what the app replaced. Sits clear of the floating bar. */
.hthr__composer {
    flex: 0 0 auto; display: flex; align-items: center; gap: 8px;
    padding: 8px 12px calc(96px + env(safe-area-inset-bottom, 0px));
    background: #fff; border-top: 1px solid var(--line);
}
.hthr__composer input {
    flex: 1; min-width: 0; height: 42px; padding: 0 16px; border-radius: 21px;
    border: 1px solid var(--line); background: #F8FAFC; font: inherit; font-size: 14px; color: var(--ink);
}
.hthr__composer input:focus { outline: none; border-color: #BFD3F5; background: #fff; }
.hthr__composer button {
    flex: 0 0 auto; width: 42px; height: 42px; border-radius: 50%; border: 0; cursor: pointer;
    background: var(--blue); color: #fff; display: inline-flex; align-items: center; justify-content: center;
    /* Springs to full size once there is something to send. */
    transform: scale(.86); opacity: .5; transition: transform .18s cubic-bezier(.2, 1.5, .5, 1), opacity .18s ease;
}
.hthr__composer.is-ready button { transform: scale(1); opacity: 1; }
.hthr__composer button svg { width: 20px; height: 20px; transform: rotate(-24deg); }
</style>

<script>
(function () {
    var root = document.querySelector('.hthr');
    if (!root) return;
    var id = root.dataset.threadId;
    var list = document.getElementById('hthrList');
    var scroll = document.getElementById('hthrScroll');
    var form = document.getElementById('hthrForm');
    var input = document.getElementById('hthrInput');
    var CSRF = document.querySelector('meta[name="csrf-token"]');
    CSRF = CSRF ? CSRF.content : '';

    // The newest id we hold. Everything after it is what the poll asks for.
    var lastId = 0;
    list.querySelectorAll('[data-id]').forEach(function (b) { lastId = Math.max(lastId, +b.dataset.id || 0); });
    var lastDay = null;
    var lastMine = null;
    // Held as a variable, NOT looked up with `.hthr__b:last-of-type`: day rules are divs
    // too, so that selector returns nothing whenever a rule was the last thing appended.
    var lastBubble = null;
    (function seed() {
        var bubbles = list.querySelectorAll('.hthr__b');
        if (!bubbles.length) return;
        lastBubble = bubbles[bubbles.length - 1];
        var t = lastBubble.querySelector('time');
        if (t) lastDay = new Date(t.dateTime).toDateString();
        lastMine = lastBubble.classList.contains('is-mine');
    })();

    // First paint jumps to the bottom; later arrivals animate. Animating through the
    // whole history on open is the thing that makes a thread feel slow.
    scroll.scrollTop = scroll.scrollHeight;

    input.addEventListener('input', function () {
        form.classList.toggle('is-ready', input.value.trim().length > 0);
    });

    function atBottom() { return scroll.scrollHeight - scroll.scrollTop - scroll.clientHeight < 80; }
    function toBottom(smooth) { scroll.scrollTo({ top: scroll.scrollHeight, behavior: smooth ? 'smooth' : 'auto' }); }

    function dayRule(when) {
        var d = when.toDateString();
        if (d === lastDay) return null;
        lastDay = d;
        var today = new Date().toDateString();
        var y = new Date(Date.now() - 86400000).toDateString();
        var label = d === today ? 'Today' : (d === y ? 'Yesterday'
            : when.toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' }));
        var el = document.createElement('div');
        el.className = 'hthr__day';
        var s = document.createElement('span');
        s.textContent = label;
        el.appendChild(s);
        return el;
    }

    function bubble(m) {
        var when = m.sentAt ? new Date(m.sentAt) : new Date();
        var rule = dayRule(when);
        if (rule) list.appendChild(rule);

        // The previous bubble is no longer the end of its run if this one matches it —
        // one timestamp per run, on the run's last line.
        if (lastBubble && lastMine === m.mine && !rule) {
            lastBubble.classList.remove('is-last');
            var pt = lastBubble.querySelector('time');
            if (pt) pt.remove();
        }
        lastMine = m.mine;

        var el = document.createElement('div');
        el.className = 'hthr__b is-last' + (m.mine ? ' is-mine' : '');
        if (m.id) el.dataset.id = m.id;
        var txt = document.createElement('span');
        txt.className = 'hthr__txt';
        txt.textContent = m.body;          // never innerHTML: this is someone's typing
        el.appendChild(txt);
        var t = document.createElement('time');
        t.dateTime = when.toISOString();
        t.textContent = when.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
        el.appendChild(t);
        list.appendChild(el);
        lastBubble = el;
        return el;
    }

    // ── Send, with a visible outbox. The same function serves the first attempt and
    // the retry tap, so the two can never drift apart.
    function sendWithOutbox(body, el) {
        if (!el) {
            el = bubble({ mine: true, body: body, sentAt: new Date().toISOString() });
            var empty = root.querySelector('.hthr__empty');
            if (empty) empty.remove();
        }
        el.classList.add('is-sending');
        el.classList.remove('is-failed');
        toBottom(true);

        fetch('/chat/' + id + '/messages', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': CSRF,
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: JSON.stringify({ body: body })
        }).then(function (r) { if (!r.ok) throw r; return r.json(); })
          .then(function (m) {
              el.classList.remove('is-sending');
              el.dataset.id = m.id;
              lastId = Math.max(lastId, m.id);
          })
          .catch(function () {
              el.classList.remove('is-sending');
              el.classList.add('is-failed');
              el.title = 'Tap to retry';
              var t = el.querySelector('time');
              if (t) t.textContent = 'Tap to retry';
              el.onclick = function () { el.onclick = null; sendWithOutbox(body, el); };
          });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var body = input.value.trim();
        if (!body) return;
        input.value = '';
        form.classList.remove('is-ready');
        sendWithOutbox(body, null);
    });

    // ── Poll for what arrived while the thread was open. Delta only (?since_id=), so a
    // quiet thread costs an empty array rather than the whole page every tick.
    setInterval(function () {
        if (document.hidden) return;
        fetch('/chat/' + id + '/poll?since_id=' + lastId, {
            headers: { 'Accept': 'application/json' }, credentials: 'same-origin'
        }).then(function (r) { return r.json(); })
          .then(function (j) {
              var rows = (j.results || []).filter(function (m) { return m.id > lastId; });
              if (!rows.length) return;
              var stick = atBottom();
              rows.forEach(function (m) {
                  bubble(m);
                  lastId = Math.max(lastId, m.id);
              });
              if (stick) toBottom(true);
          }).catch(function () { /* a dropped tick is not worth telling anyone about */ });
    }, 5000);
})();
</script>
@endsection
