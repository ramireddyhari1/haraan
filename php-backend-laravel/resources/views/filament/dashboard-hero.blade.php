{{-- /control Dashboard hero band.

     The partner console leads its dashboard with a launchpad card and hides the
     redundant "Dashboard" H1 so the page opens on something, not a title. This
     is the control twin for the limited-staff dashboard (marketing / ops —
     Command Center users are redirected away in Dashboard::mount(), so they
     never see this): a time-aware greeting band using the shared .hrn-hero
     style, plus the same page-title suppression.

     Injected via a PAGE_START render hook scoped to the Dashboard page on the
     control panel (App\Providers\Filament\AdminPanelProvider). --}}
@php
    $u = auth()->user();
    $first = trim((string) \Illuminate\Support\Str::before((string) ($u?->name ?? ''), ' ')) ?: 'there';
    // The business day (IST). The app clock is UTC, so now() said "Good morning" at 2 PM.
    $now = \App\Support\BusinessClock::now();
    $h = (int) $now->format('G');
    $greeting = $h < 12 ? 'Good morning' : ($h < 17 ? 'Good afternoon' : 'Good evening');
@endphp
<div class="hrn-hero hrn-dash-hero">
    <div class="hrn-hero-wash"></div>
    <div class="hrn-dash-hero-in">
        <p class="hrn-dash-hero-eyebrow">{{ $now->format('l, j F') }}</p>
        <h1 class="hrn-dash-hero-h">{{ $greeting }}, {{ $first }}</h1>
        <p class="hrn-dash-hero-sub">Here's what's happening across Haraan today.</p>
    </div>
    {{-- Floodlights over a pitch, drawn in white on the band. --}}
    <x-hrn-illo name="stadium-lights" :size="168" class="hrn-dash-hero-art"
        style="--illo-ink: rgba(255,255,255,.88); --illo-accent: #ffffff; --illo-soft: rgba(255,255,255,.12); --illo-paper: rgba(255,255,255,.16)" />
</div>
<style>
    .hrn-dash-hero { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
    .hrn-dash-hero-art { position: relative; margin: -12px 0 -18px; }
    @media (max-width: 640px) { .hrn-dash-hero-art { display: none; } }
    /* The greeting is the heading; the stock "Dashboard" H1 under it repeated the page name. */
    .fi-header { display: none; }
</style>
