@php
    /** @var \App\Models\SupportThread|null $thread */
    $thread = $getRecord();
    $u = $thread?->user;
    $name = (string) ($u?->name ?: 'User');
    $src = \App\Support\MediaUrl::resolve($u?->avatar);
    $src = ($src !== null && $src !== '') ? $src : \App\Filament\Support\AvatarColumn::initials($name);
    $email = ($u && \App\Support\ContactPrefill::isRealEmail($u->email)) ? $u->email : null;
    $plan = null;
    if ($u !== null) {
        try {
            $ent = app(\App\Services\Membership\MemberEntitlements::class);
            $plan = $ent->allows($u, \App\Support\Membership\MemberFeature::SUPPORT_PRIORITY) ? $ent->for($u)->plan->name : null;
        } catch (\Throwable) {
            $plan = null;
        }
    }
    $profileUrl = null;
    if ($u !== null && \App\Filament\Resources\AppUsers\AppUserResource::canView($u)) {
        try {
            $profileUrl = \App\Filament\Resources\AppUsers\AppUserResource::getUrl('view', ['record' => $u]);
        } catch (\Throwable) {
            $profileUrl = null;
        }
    }
    $count = $thread?->messages()->count() ?? 0;
@endphp
<div class="stc-user">
    <div class="stc-user__top">
        <img src="{{ $src }}" alt="" class="stc-user__av">
        <div>
            <div class="stc-user__name">{{ $name }}</div>
            @if ($u?->created_at)
                <div class="stc-user__since">On Haraan since {{ $u->created_at->format('M Y') }}</div>
            @endif
        </div>
    </div>
    <dl class="stc-user__rows">
        @if ($u?->phone)
            <div class="stc-user__row"><dt>Phone</dt><dd>{{ $u->phone }}</dd></div>
        @endif
        @if ($email)
            <div class="stc-user__row"><dt>Email</dt><dd>{{ $email }}</dd></div>
        @endif
        @if ($plan)
            <div class="stc-user__row"><dt>Plan</dt><dd><span class="stx-plan">{{ $plan }}</span></dd></div>
        @endif
        <div class="stc-user__row"><dt>Messages</dt><dd>{{ $count }}</dd></div>
        @if ($thread?->created_at)
            <div class="stc-user__row"><dt>Started</dt><dd>{{ \App\Support\BusinessClock::local($thread->created_at)->format('j M Y, g:i A') }}</dd></div>
        @endif
    </dl>
    @if ($profileUrl)
        <a href="{{ $profileUrl }}" class="stc-user__link">View full profile →</a>
    @endif
</div>
<style>
    .stx-plan { font-size:.65rem; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:var(--warning-700); background:color-mix(in oklab, var(--warning-500) 14%, transparent); padding:2px 6px; border-radius:5px; }
</style>
