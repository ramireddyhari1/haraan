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
<style>
    /* Drop the redundant "Dashboard" page title so the hero leads the page.
       Scoped: this hook only injects on the control Dashboard page. */
    .fi-header{display:none!important;}

    .hrn-dash-hero{margin-bottom:1.35rem;}
    .hrn-dash-hero-in{position:relative;z-index:1;}
    .hrn-dash-hero-eyebrow{margin:0;font-size:12px;font-weight:600;letter-spacing:.06em;
        text-transform:uppercase;color:rgba(255,255,255,.82);}
    .hrn-dash-hero-h{margin:6px 0 0;font-size:26px;line-height:1.15;font-weight:800;
        letter-spacing:-.02em;color:#fff;}
    .hrn-dash-hero-sub{margin:7px 0 0;font-size:13.5px;color:rgba(255,255,255,.9);}
    @media (max-width:640px){.hrn-dash-hero-h{font-size:22px;}}
</style>
