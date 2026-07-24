{{-- Identity card at the top of /partner/profile: who this account is, at a
     glance. The editable form sits underneath — read first, edit if you want. --}}
@php
    /** @var \App\Models\User $user */
    $name = $user->name ?: 'Partner';
    $parts = preg_split('/\s+/', trim($name)) ?: [$name];
    $initials = strtoupper(mb_substr($parts[0] ?? '', 0, 1) . (count($parts) > 1 ? mb_substr((string) end($parts), 0, 1) : ''));
    $initials = $initials !== '' ? $initials : 'P';
    $hue = crc32($name) % 360;
    $photo = \App\Support\MediaUrl::resolve($user->avatar);

    $lane = $user->isDeskStaff()
        ? 'Team member'
        : (($user->partner_type === 'event') ? 'Event organiser' : 'Venue owner');

    $supportUrl = \App\Filament\Pages\Partner\PartnerSupport::canAccess()
        ? \App\Filament\Pages\Partner\PartnerSupport::getUrl()
        : null;

    // "locked" marks the fields only the Haraan team can change.
    $facts = [
        ['icon' => 'heroicon-o-identification', 'label' => 'Member ID', 'value' => \App\Models\User::memberId($user->id), 'locked' => false],
        ['icon' => 'heroicon-o-user', 'label' => 'Name', 'value' => $name, 'locked' => true],
        ['icon' => 'heroicon-o-envelope', 'label' => 'Email', 'value' => $user->email, 'locked' => true],
        ['icon' => 'heroicon-o-phone', 'label' => 'Phone', 'value' => $user->phone ?: 'Not on file', 'locked' => true],
        ['icon' => 'heroicon-o-calendar-days', 'label' => 'Partner since', 'value' => optional($user->created_at)->format('d M Y') ?: '—', 'locked' => false],
    ];
@endphp

<section class="hpp">
    <div class="hpp-id">
        @if ($photo)
            <img src="{{ $photo }}" alt="{{ $name }}" class="hpp-av hpp-av-img">
        @else
            <span class="hpp-av" style="background:hsl({{ $hue }} 52% 46%)">{{ $initials }}</span>
        @endif

        <div class="hpp-meta">
            <h1 class="hpp-name">{{ $name }}</h1>
            <div class="hpp-badges">
                <span class="hpp-badge hpp-badge-lane">{{ $lane }}</span>
                @if ($user->email_verified_at)
                    <span class="hpp-badge hpp-badge-ok">
                        <x-filament::icon icon="heroicon-m-check-badge" class="hpp-badge-ico" />
                        Email verified
                    </span>
                @endif
            </div>
        </div>
    </div>

    <dl class="hpp-facts">
        @foreach ($facts as $fact)
            <div class="hpp-fact">
                <x-filament::icon :icon="$fact['icon']" class="hpp-fact-ico" />
                <div class="hpp-fact-body">
                    <dt>
                        {{ $fact['label'] }}
                        @if ($fact['locked'])
                            <x-filament::icon icon="heroicon-m-lock-closed" class="hpp-lock"
                                title="Only the Haraan team can change this" />
                        @endif
                    </dt>
                    <dd>{{ $fact['value'] }}</dd>
                </div>
            </div>
        @endforeach
    </dl>

    <p class="hpp-note">
        <x-filament::icon icon="heroicon-o-lock-closed" class="hpp-note-ico" />
        <span>
            Your name, email and phone are held by the Haraan team — they're tied to your
            payouts and tickets.
            @if ($supportUrl)
                Need a change? <a href="{{ $supportUrl }}" class="hpp-note-link">Ask support</a>.
            @else
                Contact support to have them changed.
            @endif
            Your photo and password are yours to change below.
        </span>
    </p>
</section>

<style>
    .hpp{border-radius:18px;padding:20px 22px;background:linear-gradient(180deg,#eef4ff 0%,#f7faff 60%,#fff 100%);
        box-shadow:0 1px 2px rgba(11,18,32,.05),0 0 0 1px #e3e9f5 inset;}
    .hpp-id{display:flex;align-items:center;gap:16px;}
    .hpp-av{width:66px;height:66px;border-radius:50%;flex:none;display:flex;align-items:center;
        justify-content:center;color:#fff;font-weight:800;font-size:22px;letter-spacing:.02em;
        overflow:hidden;box-shadow:0 6px 16px -8px rgba(11,18,32,.45);}
    .hpp-av-img{object-fit:cover;display:block;}
    .hpp-meta{min-width:0;}
    .hpp-name{font-size:24px;font-weight:800;letter-spacing:-.03em;color:#0b1220;line-height:1.15;margin:0;
        white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .hpp-badges{display:flex;flex-wrap:wrap;gap:6px;margin-top:7px;}
    .hpp-badge{display:inline-flex;align-items:center;gap:4px;font-size:11.5px;font-weight:700;
        padding:3px 9px;border-radius:999px;letter-spacing:.01em;}
    .hpp-badge-lane{background:rgba(47,107,255,.12);color:#1e50e6;}
    .hpp-badge-ok{background:rgba(16,185,129,.13);color:#047857;}
    .hpp-badge-ico{width:13px;height:13px;}

    .hpp-facts{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:10px;
        margin:18px 0 0;padding:0;}
    .hpp-fact{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:12px;
        background:#fff;box-shadow:0 0 0 1px #e7ecf6 inset;}
    .hpp-fact-ico{width:17px;height:17px;color:#2f6bff;flex:none;}
    .hpp-fact-body{min-width:0;}
    .hpp-fact dt{display:flex;align-items:center;gap:4px;font-size:10.5px;font-weight:700;
        letter-spacing:.08em;text-transform:uppercase;color:#7a8394;}
    .hpp-lock{width:11px;height:11px;color:#9aa2b1;flex:none;}
    .hpp-fact dd{margin:2px 0 0;font-size:13.5px;font-weight:600;color:#0b1220;
        white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}

    .hpp-note{display:flex;align-items:flex-start;gap:7px;margin:14px 0 0;font-size:12.5px;
        line-height:1.5;color:#5b6474;}
    .hpp-note-ico{width:15px;height:15px;color:#7a8394;flex:none;margin-top:2px;}
    .hpp-note-link{color:#1e50e6;font-weight:600;text-decoration:none;}
    .hpp-note-link:hover{text-decoration:underline;}

    @media (max-width:640px){
        .hpp{padding:16px;}
        .hpp-av{width:54px;height:54px;font-size:18px;}
        .hpp-name{font-size:20px;white-space:normal;}
        .hpp-facts{grid-template-columns:1fr;}
    }
</style>
