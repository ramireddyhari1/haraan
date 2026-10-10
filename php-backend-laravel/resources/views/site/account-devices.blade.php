@extends('site.layout')

@push('head')
<style>
    .devx-meter { display:flex; align-items:center; gap:12px; margin-top:16px; padding:14px 16px; border:1px solid #E5E9F0; border-radius:16px; background:#fff; }
    .devx-meter__slots { display:flex; gap:6px; }
    .devx-meter__slot { width:22px; height:8px; border-radius:4px; background:#E5E9F0; }
    .devx-meter__slot.is-used { background:#2563EB; }
    .devx-meter__text { flex:1; font-size:14px; color:#475569; }
    .devx-meter__text b { color:#0F172A; }
    .devx-held { margin-top:16px; padding:16px; border-radius:16px; background:#EFF4FF; border:1px solid #C7D7FE; }
    .devx-held h2 { margin:0 0 4px; font-size:16px; font-weight:700; color:#0F172A; }
    .devx-held p { margin:0; font-size:14px; line-height:1.5; color:#334155; }
    .devx-row { display:flex; align-items:center; gap:12px; padding:14px 16px; }
    .devx-row__icon { width:40px; height:40px; flex:0 0 40px; display:grid; place-items:center; border-radius:12px; background:#F1F5F9; color:#334155; }
    .devx-row__icon svg { width:20px; height:20px; }
    .devx-row__body { flex:1; min-width:0; }
    .devx-row__name { display:block; font-size:15px; font-weight:600; color:#0F172A; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .devx-row__meta { display:block; margin-top:2px; font-size:13px; color:#64748B; }
    .devx-row__meta.is-here { color:#2563EB; font-weight:600; }
    .devx-chip { display:inline-block; margin-left:6px; padding:1px 8px; border-radius:999px; background:#F1F5F9; color:#475569; font-size:11px; font-weight:700; vertical-align:middle; }
    .devx-out { flex:0 0 auto; padding:8px 14px; border-radius:10px; border:1px solid #C7D7FE; background:#fff; color:#2563EB; font:inherit; font-size:13px; font-weight:700; cursor:pointer; }
    .devx-out:hover { background:#EFF4FF; }
    .devx-upgrade { display:flex; align-items:center; gap:12px; margin-top:16px; padding:16px; border-radius:16px; border:1px solid #E5E9F0; background:#fff; }
    .devx-upgrade p { flex:1; margin:0; font-size:14px; line-height:1.45; color:#334155; }
    .devx-upgrade p b { color:#0F172A; }
    .devx-cta { flex:0 0 auto; padding:10px 16px; border-radius:12px; background:#2563EB; color:#fff; font-size:14px; font-weight:700; text-decoration:none; }
    .devx-cta:hover { background:#1D4ED8; color:#fff; }
    .devx-note { margin-top:14px; font-size:13px; line-height:1.5; color:#64748B; }
</style>
@endpush

@section('content')
{{-- Signed-in devices — the web twin of the app's DevicesScreen. A browser past the plan's
     device limit is held here by EnforceMemberDevice until a slot frees. --}}
@php
    $limit = $state['limit'];
    $used = (int) $state['used'];
    $phone = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="7" y="2" width="10" height="20" rx="2"></rect><line x1="11" y1="18" x2="13" y2="18"></line></svg>';
    $laptop = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="4" y="4" width="16" height="11" rx="1.5"></rect><path d="M2 19h20"></path></svg>';
@endphp
<div class="aprof">
    <h1 class="aprof-doc__title">Signed-in devices</h1>
    <p class="aprof-doc__lede">Phones and browsers where you’re signed in to Haraan.</p>

    @if(session('success'))
        <div class="aprof-flash">{{ session('success') }}</div>
    @endif

    @if($state['blocked'])
        <div class="devx-held" role="alert">
            <h2>{{ $state['title'] }}</h2>
            <p>{{ $state['message'] }}</p>
        </div>
    @endif

    @if($state['enforced'] && $limit !== null)
        <div class="devx-meter">
            <div class="devx-meter__slots" aria-hidden="true">
                @for($i = 0; $i < $limit; $i++)
                    <span class="devx-meter__slot {{ $i < $used ? 'is-used' : '' }}"></span>
                @endfor
            </div>
            <span class="devx-meter__text"><b>{{ min($used, $limit) }} of {{ $limit }}</b> in use on {{ $state['plan']['name'] }}</span>
        </div>
    @endif

    <h2 class="aprof-heading">Devices</h2>
    <div class="aprof-card">
        @forelse($state['devices'] as $i => $device)
            @if($i > 0)<i class="aprof-hr" style="margin-left:68px"></i>@endif
            <div class="devx-row">
                <span class="devx-row__icon">{!! $device['surface'] === 'web' ? $laptop : $phone !!}</span>
                <span class="devx-row__body">
                    <span class="devx-row__name">{{ $device['name'] }}@if($device['status'] === 'pending' && ! $device['this_device'])<span class="devx-chip">Waiting</span>@endif</span>
                    <span class="devx-row__meta {{ $device['this_device'] ? 'is-here' : '' }}">{{ $device['this_device'] ? 'This browser' : $device['last_active_label'] }}</span>
                </span>
                <form method="POST" action="{{ route('site.account.devices.remove', ['id' => $device['id']]) }}">
                    @csrf
                    <button type="submit" class="devx-out">{{ $device['this_device'] ? 'Sign out here' : 'Sign out' }}</button>
                </form>
            </div>
        @empty
            <div class="devx-row"><span class="devx-row__meta">No devices yet.</span></div>
        @endforelse
    </div>

    @if($state['upgrade'] && $state['enforced'])
        <div class="devx-upgrade">
            <p><b>{{ $state['upgrade']['name'] }}</b> keeps you signed in on {{ $state['upgrade']['limit_label'] }} at once.</p>
            <a class="devx-cta" href="{{ route('site.membership') }}">See {{ $state['upgrade']['name'] }}</a>
        </div>
    @endif

    <p class="devx-note">Signing a device out ends its session straight away. A device you haven’t used for a while signs itself out and frees its place.</p>

    @unless($state['blocked'])
        <p class="aprof-doc__back"><a href="{{ route('site.profile') }}">← Account</a></p>
    @endunless
</div>
@endsection
