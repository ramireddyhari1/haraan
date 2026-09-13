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
    $h = (int) now()->format('G');
    $greeting = $h < 12 ? 'Good morning' : ($h < 17 ? 'Good afternoon' : 'Good evening');
@endphp
<div class="hrn-hero hrn-dash-hero">
    <div class="hrn-hero-wash"></div>
    <div class="hrn-dash-hero-in">
        <p class="hrn-dash-hero-eyebrow">{{ now()->format('l, j F') }}</p>
        <h1 class="hrn-dash-hero-h">{{ $greeting }}, {{ $first }}</h1>
        <p class="hrn-dash-hero-sub">Here's what's happening across Haraan today.</p>
    </div>
</div>
