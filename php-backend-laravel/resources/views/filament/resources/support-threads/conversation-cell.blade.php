@php
    /** @var \App\Models\SupportThread $t */
    $t = $getRecord();
    $name = (string) ($t->user?->name ?: 'User');
    $avatar = \App\Support\MediaUrl::resolve($t->user?->avatar);
    $avatar = ($avatar !== null && $avatar !== '') ? $avatar : \App\Filament\Support\AvatarColumn::initials($name);
    $unread = (int) $t->admin_unread_count;
    $plan = $getPlan($t);
@endphp
<div class="stx-conv {{ $unread > 0 ? 'is-unread' : '' }}">
    <span class="stx-conv__av">
        <img src="{{ $avatar }}" alt="" loading="lazy">
        @if ($unread > 0)
            <span class="stx-conv__count" title="{{ $unread }} new {{ \Illuminate\Support\Str::plural('message', $unread) }}">{{ $unread > 99 ? '99+' : $unread }}</span>
        @endif
    </span>
    <span class="stx-conv__text">
        <span class="stx-conv__name">
            {{ $name }}
            @if ($plan)
                <span class="stx-plan">{{ $plan }}</span>
            @endif
        </span>
        <span class="stx-conv__subject">{{ $t->subject ?: 'General support' }}</span>
    </span>
</div>
