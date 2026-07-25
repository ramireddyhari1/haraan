{{-- Sidebar footer identity card for /control.

     The partner console gained a footer "who am I + sign out" card that makes
     its shell read finished; /control's footer was empty. This is the control
     twin: same shape, but role-aware (Admin / Co-admin / Operations …) rather
     than partner's event/venue "lane", pointed at the control logout route,
     and fully dark-mode aware (partner is light-only). Styling lives in
     resources/views/filament/theme.blade.php (.hrn-acct*), which is injected
     on /control only. --}}
@php
    $u = auth()->user();
    $name = $u?->name ?: 'Admin';
    $parts = preg_split('/\s+/', trim($name)) ?: [$name];
    $init = strtoupper(mb_substr($parts[0] ?? '', 0, 1) . (count($parts) > 1 ? mb_substr((string) end($parts), 0, 1) : ''));
    $init = $init !== '' ? $init : 'A';
    $hue = crc32($name) % 360;
    $role = strtolower((string) ($u?->role ?? ''));
    $roleLabel = match ($role) {
        'admin' => 'Admin',
        'coadmin' => 'Co-admin',
        'ops' => 'Operations',
        'finance' => 'Finance',
        'marketing' => 'Marketing',
        default => 'Team',
    };
    $photo = \App\Support\MediaUrl::resolve($u?->avatar);
    $profileUrl = \Filament\Facades\Filament::getProfileUrl();
    $tag = $profileUrl ? 'a' : 'span';
@endphp
<div class="hrn-acct">
    <{{ $tag }} @if ($profileUrl) href="{{ $profileUrl }}" @endif class="hrn-acct-link" title="View profile">
        @if ($photo)
            <img src="{{ $photo }}" alt="{{ $name }}" class="hrn-acct-av hrn-acct-av-img">
        @else
            <span class="hrn-acct-av" style="background:hsl({{ $hue }} 52% 46%)">{{ $init }}</span>
        @endif
        <span class="hrn-acct-meta">
            <span class="hrn-acct-name">{{ $name }}</span>
            <span class="hrn-acct-lane">{{ $roleLabel }}</span>
        </span>
    </{{ $tag }}>
    <form method="POST" action="{{ route('filament.control.auth.logout') }}" class="hrn-acct-form">
        @csrf
        <button type="submit" class="hrn-acct-out" title="Sign out" aria-label="Sign out">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M15 17l5-5-5-5M20 12H9M9 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h4"/>
            </svg>
        </button>
    </form>
</div>
