@extends('site.layout')

@section('content')
{{-- The web twin of the app's SupportChatScreen: one conversation with the Haraan
     team, admin replies arriving from the Filament "Support" resource. --}}
<div class="support-page">
    <header class="support-page__head">
        <h1 class="support-page__title">Support</h1>
        <p class="support-page__sub">
            @if($thread->assigned_to)
                {{ $thread->assignee?->name }} is looking after this conversation.
            @else
                The Haraan team usually replies within a day.
            @endif
            @if($thread->category)
                <span class="support-topic-tag">{{ $thread->category->label }}</span>
            @endif
        </p>
    </header>

    <div class="support-thread" id="supportThread" data-poll="{{ route('site.support.poll') }}" data-last="{{ $messages->last()?->id ?? 0 }}">
        @forelse($messages as $message)
            <div class="support-msg support-msg--{{ $message->sender_type === 'admin' ? 'admin' : 'user' }}">
                <div class="support-msg__bubble">{{ $message->body }}</div>
                <time class="support-msg__time">{{ $message->created_at?->format('g:i A') }}</time>
            </div>
        @empty
            <div class="support-empty">
                <h2>How can we help?</h2>
                <p>Send a message and the team will pick it up here. Your replies show up in the app too.</p>
            </div>
        @endforelse
        <span id="latest"></span>
    </div>

    <form class="support-compose" method="POST" action="{{ route('site.support.send') }}">
        @csrf

        @if($categories->isNotEmpty())
            {{-- Topic picker, shown only until the thread has one — after that the
                 admin owns the classification and the user can't overwrite it. --}}
            <label class="support-compose__topic">
                <span>Topic</span>
                <select name="category_id">
                    <option value="">Choose a topic (optional)</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->label }}</option>
                    @endforeach
                </select>
            </label>
        @endif

        @error('body')
            <p class="support-compose__error">{{ $message }}</p>
        @enderror

        <div class="support-compose__row">
            <input type="text" name="body" class="support-compose__input" placeholder="Write a message…" maxlength="4000" autocomplete="off" required>
            <button type="submit" class="support-compose__send" aria-label="Send">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
            </button>
        </div>
    </form>
</div>

<script>
    // Poll for the admin's replies while the chat is open — same 4s cadence the
    // app uses. The site has no socket client yet, so this is the live channel.
    (() => {
        const thread = document.getElementById('supportThread');
        if (!thread) return;

        const escape = (s) => { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; };

        async function poll() {
            try {
                const r = await fetch(`${thread.dataset.poll}?after=${thread.dataset.last}`, {
                    headers: {'Accept': 'application/json'},
                    credentials: 'same-origin',
                });
                if (!r.ok) return;
                const {messages} = await r.json();
                if (!messages?.length) return;

                document.querySelector('.support-empty')?.remove();
                const anchor = document.getElementById('latest');
                for (const m of messages) {
                    const el = document.createElement('div');
                    el.className = `support-msg support-msg--${m.from === 'admin' ? 'admin' : 'user'}`;
                    el.innerHTML = `<div class="support-msg__bubble">${escape(m.body)}</div>`
                        + `<time class="support-msg__time">${escape(m.at ?? '')}</time>`;
                    thread.insertBefore(el, anchor);
                    thread.dataset.last = m.id;
                }
                thread.scrollTop = thread.scrollHeight;
            } catch (e) { /* transient network blip — the next tick retries */ }
        }

        // Don't poll a backgrounded tab, but catch up the moment it comes back
        // rather than making the reader wait out the interval.
        setInterval(() => { if (!document.hidden) poll(); }, 4000);
        document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });

        thread.scrollTop = thread.scrollHeight;
    })();
</script>
@endsection
