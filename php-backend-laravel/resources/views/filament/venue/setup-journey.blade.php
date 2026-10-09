{{-- Setup journey for the venue form. $steps from App\Support\VenueSetupSteps::forVenue(), $venue (nullable). --}}
@php
    $required = collect($steps)->where('required', true);
    $doneReq = $required->where('done', true)->count();
    $live = collect($steps)->firstWhere('key', 'live')['done'] ?? false;
    $ready = $doneReq === $required->count();
    [$pillClass, $pillText] = $live ? ['live', 'Live — taking bookings'] : ($ready ? ['ready', 'Ready to go live'] : ['draft', 'Not live yet']);
    $paused = \App\Support\VenueSetupSteps::isPaused($venue);
    if ($paused) { [$pillClass, $pillText] = ['ready', 'Live — bookings paused']; }
    $next = collect($steps)->first(fn ($s) => $s['required'] && ! $s['done']);
@endphp

@include('filament.venue.vf-styles')

<div class="vf-journey" role="navigation" aria-label="Venue setup steps">
    <div class="vf-j-top">
        <div>
            <div class="vf-j-title">
                @if ($live)
                    This venue is live
                @elseif ($ready)
                    Everything is set — one switch left
                @else
                    {{ $doneReq }} of {{ $required->count() }} steps done
                @endif
            </div>
            <div class="vf-j-sub">
                @if ($next)
                    Next: {{ $next['hint'] }}. Tap a step to jump to it.
                @elseif (! $live && ! $paused)
                    {{ \App\Support\VenueSetupSteps::goLiveHint() }} in step 8, then save.
                @else
                    Tap a step to jump to it.
                @endif
            </div>
        </div>
        <span class="vf-pill {{ $pillClass }}"><i></i>{{ $pillText }}</span>
    </div>

    <div class="vf-track">
        @foreach ($steps as $s)
            @php $cls = $s['done'] ? 'done' : ($s['required'] ? 'todo' : 'opt'); @endphp
            <button type="button" class="vf-node {{ $cls }}" title="{{ $s['n'] }}. {{ $s['label'] }} — {{ $s['hint'] }}"
                @if ($s['anchor'])
                    onclick="document.querySelector('[data-step={{ $s['anchor'] }}]')?.scrollIntoView({behavior:'smooth', block:'start'})"
                @else
                    onclick="const t=[...document.querySelectorAll('.fi-tabs-item')].find(e=>e.textContent.trim().startsWith('Courts')); t?.click(); (t?.closest('.fi-sc-tabs')||t)?.scrollIntoView({behavior:'smooth', block:'start'})"
                @endif
            >
                <span class="vf-dot">
                    {!! \App\Support\VenueSetupSteps::icon($s['key'], 20) !!}
                    @if ($s['done'])
                        <span class="vf-tick"><svg width="9" height="9" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M2.5 6.3l2.3 2.3 4.7-5"/></svg></span>
                    @endif
                </span>
                <span class="l">{{ $s['label'] }}</span>
                <span class="h">{{ $s['hint'] }}</span>
            </button>
        @endforeach
    </div>
</div>
