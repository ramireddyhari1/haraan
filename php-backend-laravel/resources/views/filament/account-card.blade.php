{{-- Sidebar footer identity card for /control.
     Linear / Stripe elevated SaaS design with session status and desktop collapsed rail support. --}}
@php
    $u = auth()->user();
    $name = $u?->name ?: 'Admin';
    $parts = preg_split('/\s+/', trim($name)) ?: [$name];
    $init = strtoupper(mb_substr($parts[0] ?? '', 0, 1) . (count($parts) > 1 ? mb_substr((string) end($parts), 0, 1) : ''));
    $init = $init !== '' ? $init : 'A';
    $hue = crc32($name) % 360;
    $role = strtolower((string) ($u?->role ?? ''));
    $roleLabel = match ($role) {
        'admin' => 'Super Admin',
        'coadmin' => 'Co-admin',
        'ops' => 'Operations',
        'finance' => 'Finance',
        'marketing' => 'Marketing',
        default => 'Staff',
    };
    $photo = \App\Support\MediaUrl::resolve($u?->avatar);
    $profileUrl = \Filament\Facades\Filament::getProfileUrl();
    $tag = $profileUrl ? 'a' : 'div';
@endphp
<div class="hrn-acct" x-bind:class="{ 'hrn-acct-collapsed': ! $store.sidebar.isOpen }">
    <{{ $tag }}
        @if ($profileUrl) href="{{ $profileUrl }}" @endif
        class="hrn-acct-link"
        x-data="{ tooltip: false }"
        x-effect="
            tooltip = $store.sidebar.isOpen
                ? false
                : {
                      content: @js($name . ' (' . $roleLabel . ')'),
                      placement: document.dir === 'rtl' ? 'left' : 'right',
                      theme: $store.theme,
                  }
        "
        x-tooltip.html="tooltip"
        title="View profile & account settings"
    >
        <div class="hrn-acct-av-wrapper">
            @if ($photo)
                <img src="{{ $photo }}" alt="{{ $name }}" class="hrn-acct-av hrn-acct-av-img">
            @else
                <span class="hrn-acct-av" style="background: hsl({{ $hue }} 52% 40%); color: #fff;">{{ $init }}</span>
            @endif
            <span class="hrn-live-dot hrn-acct-dot" title="Active Session"></span>
        </div>

        <div class="hrn-acct-meta" x-show="$store.sidebar.isOpen" x-transition:enter="hrn-fade-enter">
            <span class="hrn-acct-name">{{ $name }}</span>
            <span class="hrn-acct-lane">
                <span class="hrn-acct-role-dot"></span>
                {{ $roleLabel }}
            </span>
        </div>
    </{{ $tag }}>

    <form method="POST" action="{{ route('filament.control.auth.logout') }}" class="hrn-acct-form" x-show="$store.sidebar.isOpen" x-transition:enter="hrn-fade-enter">
        @csrf
        <button type="submit" class="hrn-acct-out" title="Sign out of console" aria-label="Sign out">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M15 17l5-5-5-5M20 12H9M9 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h4"/>
            </svg>
        </button>
    </form>
</div>
