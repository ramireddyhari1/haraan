@php
    $q = fn () => \App\Models\SupportThread::query();
    $waiting = $q()->where('admin_unread_count', '>', 0)->count();
    $open = $q()->where('status', 'open')->count();
    $mine = $q()->where('assigned_to', auth()->id())->where('status', '!=', 'closed')->count();
    $unassigned = $q()->whereNull('assigned_to')->where('status', '!=', 'closed')->count();
@endphp
<header class="stx-head">
    <div>
        <h1 class="stx-head__title">Support inbox</h1>
        <p class="stx-head__sub">
            @if ($waiting > 0)
                <strong>{{ $waiting }} {{ \Illuminate\Support\Str::plural('conversation', $waiting) }} waiting on a reply</strong>
            @else
                Nobody is waiting on a reply
            @endif
            @if ($open > 0) <span>·</span> {{ $open }} open @endif
            @if ($unassigned > 0) <span>·</span> {{ $unassigned }} unassigned @endif
            @if ($mine > 0) <span>·</span> {{ $mine }} with you @endif
        </p>
    </div>
</header>

<style>
    /* Support inbox — scoped to this page via .stx-* classes. */
    .stx-head { display:flex; align-items:flex-end; justify-content:space-between; gap:16px; padding:4px 2px 2px; }
    .stx-head__title { font-size:1.6rem; line-height:1.2; font-weight:700; letter-spacing:-.02em; color:var(--gray-950); }
    .stx-head__sub { margin-top:6px; font-size:.875rem; color:var(--gray-500); }
    .stx-head__sub strong { color:var(--gray-900); font-weight:600; }
    .stx-head__sub span { margin:0 4px; color:var(--gray-300); }
    .dark .stx-head__title, .dark .stx-head__sub strong { color:#fff; }
    .dark .stx-head__sub { color:var(--gray-400); }

    /* Table chrome: lighter header, tighter rows, hairline separators. */
    .stx-table .fi-ta-header-cell { background:transparent; font-size:.7rem; letter-spacing:.06em; color:var(--gray-400); padding-top:10px; padding-bottom:10px; }
    .stx-table .fi-ta-row { transition:background-color .12s ease; }
    .stx-table .fi-ta-row:hover { background:color-mix(in oklab, var(--primary-500) 4%, transparent); }
    .stx-table .fi-ta-cell { padding-top:12px; padding-bottom:12px; }
    .stx-table .fi-ta-row.stx-row--unread { background:color-mix(in oklab, var(--primary-500) 5%, transparent); box-shadow:inset 3px 0 0 var(--primary-500); }

    /* Conversation cell */
    .stx-conv { display:flex; align-items:center; gap:12px; min-width:260px; }
    .stx-conv__av { position:relative; flex:none; width:40px; height:40px; }
    .stx-conv__av img { width:40px; height:40px; border-radius:999px; object-fit:cover; }
    .stx-conv__count { position:absolute; top:-4px; right:-6px; min-width:18px; height:18px; padding:0 5px; border-radius:999px; background:var(--danger-600); color:#fff; font-size:.68rem; font-weight:700; line-height:18px; text-align:center; box-shadow:0 0 0 2px #fff; }
    .dark .stx-conv__count { box-shadow:0 0 0 2px var(--gray-900); }
    .stx-conv__text { display:flex; flex-direction:column; min-width:0; }
    .stx-conv__name { display:flex; align-items:center; gap:8px; font-size:.9rem; font-weight:500; color:var(--gray-900); white-space:nowrap; }
    .stx-conv__subject { margin-top:2px; font-size:.8rem; color:var(--gray-500); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:340px; }
    .stx-conv.is-unread .stx-conv__name { font-weight:700; }
    .stx-conv.is-unread .stx-conv__subject { color:var(--gray-700); }
    .dark .stx-conv__name { color:#fff; }
    .dark .stx-conv__subject { color:var(--gray-400); }
    .dark .stx-conv.is-unread .stx-conv__subject { color:var(--gray-300); }
    .stx-plan { font-size:.65rem; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:var(--warning-700); background:color-mix(in oklab, var(--warning-500) 14%, transparent); padding:2px 6px; border-radius:5px; }
    .dark .stx-plan { color:var(--warning-400); }

    /* Status: a dot and a word, not a loud pill. */
    .stx-status { display:inline-flex; align-items:center; gap:7px; font-size:.82rem; font-weight:500; color:var(--gray-700); }
    .stx-status i { width:7px; height:7px; border-radius:999px; background:var(--gray-400); }
    .stx-status--open i { background:var(--success-500); box-shadow:0 0 0 3px color-mix(in oklab, var(--success-500) 18%, transparent); }
    .stx-status--pending i { background:var(--warning-500); }
    .stx-status--closed { color:var(--gray-400); }
    .dark .stx-status { color:var(--gray-200); }
    .dark .stx-status--closed { color:var(--gray-500); }

    /* Owner */
    .stx-owner { display:inline-flex; align-items:center; gap:8px; font-size:.82rem; color:var(--gray-700); white-space:nowrap; }
    .stx-owner img { width:22px; height:22px; border-radius:999px; object-fit:cover; }
    .stx-owner--none { color:var(--gray-400); }
    .dark .stx-owner { color:var(--gray-200); }
    .dark .stx-owner--none { color:var(--gray-500); }

    .stx-topic { font-size:.8rem; color:var(--gray-600); }
    .dark .stx-topic { color:var(--gray-300); }

    @media (max-width: 640px) {
        .stx-conv { min-width:0; }
        .stx-conv__subject { max-width:200px; }
    }
</style>
