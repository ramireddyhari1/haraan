@php
    /** @var \App\Models\SupportThread $thread */
    $name = (string) ($thread->user?->name ?: 'User');
    $src = \App\Support\MediaUrl::resolve($thread->user?->avatar);
    $src = ($src !== null && $src !== '') ? $src : \App\Filament\Support\AvatarColumn::initials($name);
    $status = (string) $thread->status;
    $statusLabel = match ($status) { 'open' => 'Open', 'pending' => 'Waiting on user', 'closed' => 'Closed', default => ucfirst($status) };
@endphp
<header class="stc-head">
    <a href="{{ $backUrl }}" class="stc-head__back" wire:navigate>
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12.5 4.5 7 10l5.5 5.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Inbox
    </a>
    <div class="stc-head__who">
        <img src="{{ $src }}" alt="" class="stc-head__av">
        <div class="stc-head__text">
            <h1 class="stc-head__name">{{ $name }}</h1>
            <p class="stc-head__meta">
                <span class="stx-status stx-status--{{ $status }}"><i></i>{{ $statusLabel }}</span>
                <span class="stc-sep">·</span>
                <span>{{ $thread->subject ?: 'General support' }}</span>
                @if ($thread->category)
                    <span class="stc-sep">·</span>
                    <span>{{ $thread->category->label }}</span>
                @endif
            </p>
        </div>
    </div>
</header>

<style>
    /* ── Open chat page (.stc-*). Shares status dots with the inbox (.stx-status). ── */
    .stc-head { display:flex; flex-direction:column; gap:12px; padding:2px; }
    .stc-head__back { display:inline-flex; align-items:center; gap:4px; width:max-content; font-size:.82rem; font-weight:500; color:var(--gray-500); }
    .stc-head__back:hover { color:var(--primary-600); }
    .stc-head__back svg { width:16px; height:16px; }
    .stc-head__who { display:flex; align-items:center; gap:14px; min-width:0; }
    .stc-head__av { width:48px; height:48px; border-radius:999px; object-fit:cover; flex:none; }
    .stc-head__text { min-width:0; }
    .stc-head__name { font-size:1.45rem; line-height:1.2; font-weight:700; letter-spacing:-.02em; color:var(--gray-950); }
    .stc-head__meta { margin-top:4px; display:flex; flex-wrap:wrap; align-items:center; gap:6px; font-size:.85rem; color:var(--gray-500); }
    .stc-sep { color:var(--gray-300); }
    .dark .stc-head__name { color:#fff; }
    .dark .stc-head__meta { color:var(--gray-400); }

    .stx-status { display:inline-flex; align-items:center; gap:7px; font-size:.82rem; font-weight:500; color:var(--gray-700); }
    .stx-status i { width:7px; height:7px; border-radius:999px; background:var(--gray-400); }
    .stx-status--open i { background:var(--success-500); box-shadow:0 0 0 3px color-mix(in oklab, var(--success-500) 18%, transparent); }
    .stx-status--pending i { background:var(--warning-500); }
    .stx-status--closed { color:var(--gray-400); }
    .dark .stx-status { color:var(--gray-200); }

    .stc-layout { align-items:start; }
    @media (min-width:1024px) { .stc-side { position:sticky; top:88px; } }

    /* Hide the relation manager's own table chrome; the chat draws its own. */
    .stc-main .fi-ta-header-ctn, .stc-main .fi-ta-header-toolbar { display:none; }
    .stc-main .fi-ta-ctn { overflow:hidden; }
    .stc-main .fi-ta-content-ctn, .stc-main .fi-ta-content { padding:0 !important; }

    /* Messages */
    .stc-chat { height:min(62vh, 640px); overflow-y:auto; padding:20px 20px 8px; display:flex; flex-direction:column; gap:4px; background:var(--gray-50); scroll-behavior:smooth; }
    .dark .stc-chat { background:color-mix(in oklab, var(--gray-950) 60%, transparent); }
    .stc-day { align-self:center; margin:14px 0 8px; font-size:.72rem; font-weight:600; letter-spacing:.04em; color:var(--gray-500); background:#fff; border:1px solid var(--gray-200); padding:3px 10px; border-radius:999px; }
    .dark .stc-day { background:var(--gray-900); border-color:var(--gray-700); color:var(--gray-400); }
    .stc-msg { display:flex; align-items:flex-end; gap:8px; max-width:78%; }
    .stc-msg--user { align-self:flex-start; }
    .stc-msg--team { align-self:flex-end; flex-direction:row-reverse; }
    .stc-msg__av { width:28px; height:28px; border-radius:999px; object-fit:cover; flex:none; }
    .stc-msg__av--hidden { visibility:hidden; }
    .stc-msg__col { display:flex; flex-direction:column; min-width:0; }
    .stc-msg--team .stc-msg__col { align-items:flex-end; }
    .stc-bubble { padding:9px 13px; border-radius:16px; font-size:.9rem; line-height:1.45; white-space:pre-wrap; overflow-wrap:anywhere; }
    .stc-msg--user .stc-bubble { background:#fff; color:var(--gray-900); border:1px solid var(--gray-200); border-bottom-left-radius:5px; }
    .stc-msg--team .stc-bubble { background:var(--primary-600); color:#fff; border-bottom-right-radius:5px; }
    .dark .stc-msg--user .stc-bubble { background:var(--gray-800); border-color:var(--gray-700); color:var(--gray-100); }
    .stc-msg__meta { margin:3px 4px 8px; font-size:.7rem; color:var(--gray-400); }
    .stc-msg--grouped .stc-msg__meta { display:none; }
    .stc-empty { margin:auto; text-align:center; color:var(--gray-500); font-size:.88rem; }

    /* Composer */
    .stc-compose { border-top:1px solid var(--gray-200); padding:12px 14px 14px; background:#fff; }
    .dark .stc-compose { background:var(--gray-900); border-color:var(--gray-700); }
    .stc-quick { display:flex; gap:6px; overflow-x:auto; padding-bottom:10px; scrollbar-width:none; }
    .stc-quick::-webkit-scrollbar { display:none; }
    .stc-chip { flex:none; max-width:240px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:.76rem; color:var(--gray-700); background:var(--gray-100); border:1px solid transparent; padding:5px 10px; border-radius:999px; cursor:pointer; }
    .stc-chip:hover { border-color:var(--primary-300); color:var(--primary-700); background:color-mix(in oklab, var(--primary-500) 8%, transparent); }
    .dark .stc-chip { background:var(--gray-800); color:var(--gray-300); }
    .stc-box { display:flex; align-items:flex-end; gap:8px; border:1px solid var(--gray-300); border-radius:14px; padding:6px 6px 6px 12px; background:#fff; }
    .stc-box:focus-within { border-color:var(--primary-500); box-shadow:0 0 0 3px color-mix(in oklab, var(--primary-500) 18%, transparent); }
    .dark .stc-box { background:var(--gray-950); border-color:var(--gray-700); }
    .stc-box textarea { flex:1; border:0; outline:0; box-shadow:none; resize:none; background:transparent; font-size:.9rem; line-height:1.45; padding:6px 0; max-height:160px; color:var(--gray-900); }
    .stc-box textarea:focus { box-shadow:none; outline:0; }
    .dark .stc-box textarea { color:#fff; }
    .stc-send { flex:none; display:inline-flex; align-items:center; gap:6px; height:36px; padding:0 14px; border-radius:10px; background:var(--primary-600); color:#fff; font-size:.85rem; font-weight:600; }
    .stc-send:hover { background:var(--primary-700); }
    .stc-send:disabled { opacity:.5; cursor:default; }
    .stc-send svg { width:16px; height:16px; }
    .stc-hint { margin-top:6px; font-size:.7rem; color:var(--gray-400); }
    .stc-error { margin-top:6px; font-size:.78rem; color:var(--danger-600); }

    /* Side pane */
    .stc-user { display:flex; flex-direction:column; gap:14px; padding:18px; border-radius:14px; background:#fff; border:1px solid var(--gray-200); margin-bottom:16px; }
    .dark .stc-user { background:var(--gray-900); border-color:var(--gray-700); }
    .stc-user__top { display:flex; align-items:center; gap:12px; }
    .stc-user__av { width:44px; height:44px; border-radius:999px; object-fit:cover; }
    .stc-user__name { font-weight:600; color:var(--gray-900); }
    .dark .stc-user__name { color:#fff; }
    .stc-user__since { font-size:.78rem; color:var(--gray-500); }
    .stc-user__rows { display:flex; flex-direction:column; gap:8px; font-size:.84rem; }
    .stc-user__row { display:flex; justify-content:space-between; gap:12px; }
    .stc-user__row dt { color:var(--gray-500); }
    .stc-user__row dd { color:var(--gray-900); text-align:right; overflow-wrap:anywhere; }
    .dark .stc-user__row dd { color:var(--gray-100); }
    .stc-user__link { font-size:.82rem; font-weight:600; color:var(--primary-600); }
    .stc-user__link:hover { text-decoration:underline; }

    @media (max-width:640px) {
        .stc-chat { height:58vh; padding:14px 10px 6px; }
        .stc-msg { max-width:90%; }
        .stc-head__name { font-size:1.2rem; }
    }
</style>
