@php
    /** @var \App\Models\SupportThread $thread */
    $userName = (string) ($thread->user?->name ?: 'User');
    $userAv = \App\Support\MediaUrl::resolve($thread->user?->avatar);
    $userAv = ($userAv !== null && $userAv !== '') ? $userAv : \App\Filament\Support\AvatarColumn::initials($userName);
    $today = \App\Support\BusinessClock::now()->startOfDay();
    $list = collect($records instanceof \Illuminate\Contracts\Pagination\Paginator ? $records->items() : $records)->values();
    $lastDay = null;
@endphp
<div class="stc-chat"
     x-data="{ stick: true }"
     x-init="$el.scrollTop = $el.scrollHeight; new MutationObserver(() => { if (stick) $el.scrollTop = $el.scrollHeight }).observe($el, { childList: true, subtree: true })"
     x-on:scroll.passive="stick = ($el.scrollHeight - $el.scrollTop - $el.clientHeight) < 80"
     wire:key="stc-chat-{{ $thread->getKey() }}">
    @forelse ($list as $i => $m)
        @php
            $at = \App\Support\BusinessClock::local($m->created_at);
            $day = $at->toDateString();
            $team = $m->sender_type === 'admin';
            $next = $list[$i + 1] ?? null;
            // Consecutive messages from the same side within 3 minutes read as one group.
            $grouped = $next !== null
                && $next->sender_type === $m->sender_type
                && ($team ? $next->sender_id === $m->sender_id : true)
                && $next->created_at->diffInMinutes($m->created_at, true) < 3;
            $daysAgo = (int) $at->copy()->startOfDay()->diffInDays($today, true);
            $dayLabel = match (true) { $daysAgo === 0 => 'Today', $daysAgo === 1 => 'Yesterday', $daysAgo < 7 => $at->format('l'), default => $at->format('j M Y') };
            $agent = $team ? ($m->sender_id === auth()->id() ? 'You' : ($m->sender?->name ?: 'Haraan team')) : null;
            $agentAv = null;
            if ($team) {
                $agentAv = \App\Support\MediaUrl::resolve($m->sender?->avatar);
                $agentAv = ($agentAv !== null && $agentAv !== '') ? $agentAv : \App\Filament\Support\AvatarColumn::initials((string) ($m->sender?->name ?: 'Haraan'));
            }
        @endphp
        @if ($day !== $lastDay)
            <div class="stc-day">{{ $dayLabel }}</div>
            @php $lastDay = $day; @endphp
        @endif
        <div class="stc-msg {{ $team ? 'stc-msg--team' : 'stc-msg--user' }} {{ $grouped ? 'stc-msg--grouped' : '' }}" wire:key="stc-m-{{ $m->getKey() }}">
            <img src="{{ $team ? $agentAv : $userAv }}" alt="" class="stc-msg__av {{ $grouped ? 'stc-msg__av--hidden' : '' }}" loading="lazy">
            <div class="stc-msg__col">
                <div class="stc-bubble">{{ $m->body }}</div>
                <div class="stc-msg__meta" title="{{ $at->format('j M Y, g:i A') }}">
                    {{ $team ? $agent.' · ' : '' }}{{ $at->format('g:i A') }}
                </div>
            </div>
        </div>
    @empty
        <p class="stc-empty">No messages yet.</p>
    @endforelse
</div>
