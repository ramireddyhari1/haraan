<x-filament-panels::page>
    @php
        $items = $this->items;
        $readIds = $this->readIds;
    @endphp

    <div class="pnot">
        @if ($items->isNotEmpty())
            <div class="pnot-head">
                <span class="pnot-count">{{ $items->count() }} {{ Str::plural('message', $items->count()) }}</span>
                <button type="button" class="pnot-mark" wire:click="markAllRead">Mark all as read</button>
            </div>
        @endif

        <div class="pnot-list">
            @forelse ($items as $item)
                @php $unread = ! in_array($item->id, $readIds, true); @endphp
                <div @class(['pnot-item', 'pnot-item-unread' => $unread])>
                    @if ($item->image_url)
                        <img src="{{ $item->image_url }}" alt="" class="pnot-img">
                    @else
                        <div class="pnot-ic"><x-filament::icon icon="heroicon-o-megaphone" /></div>
                    @endif

                    <div class="pnot-main">
                        <div class="pnot-title">
                            {{ $item->title }}
                            @if ($unread)<span class="pnot-dot" aria-label="Unread"></span>@endif
                        </div>
                        @if ($item->body)
                            <div class="pnot-body">{{ $item->body }}</div>
                        @endif
                        <div class="pnot-when">{{ ($item->sent_at ?? $item->created_at)?->diffForHumans() }}</div>
                    </div>
                </div>
            @empty
                <div class="pnot-empty">
                    <x-filament::icon icon="heroicon-o-bell" class="pnot-empty-ic" />
                    <div class="pnot-empty-t">No notifications yet</div>
                    <div class="pnot-empty-s">Announcements from the Haraan team will show up here.</div>
                </div>
            @endforelse
        </div>
    </div>

    <style>
        .pnot{display:flex;flex-direction:column;gap:12px;}
        .pnot-head{display:flex;align-items:center;justify-content:space-between;gap:12px;}
        .pnot-count{font-size:12.5px;font-weight:600;color:#6b7280;}
        .pnot-mark{font-size:12.5px;font-weight:700;color:#2563eb;background:none;border:0;cursor:pointer;padding:4px 2px;}
        .pnot-mark:hover{text-decoration:underline;}

        .pnot-list{display:flex;flex-direction:column;gap:10px;}
        .pnot-item{display:flex;gap:13px;padding:14px 16px;border-radius:14px;background:#fff;
            box-shadow:0 1px 3px rgba(11,18,32,.06),0 0 0 1px rgba(120,120,120,.11);}
        .pnot-item-unread{box-shadow:0 1px 3px rgba(11,18,32,.06),0 0 0 1px rgba(37,99,235,.35);}
        .pnot-img{width:46px;height:46px;border-radius:10px;object-fit:cover;flex:0 0 auto;}
        .pnot-ic{width:46px;height:46px;border-radius:10px;flex:0 0 auto;display:flex;align-items:center;
            justify-content:center;background:#eef2ff;color:#4f46e5;}
        .pnot-ic svg{width:22px;height:22px;}
        .pnot-main{min-width:0;}
        .pnot-title{display:flex;align-items:center;gap:7px;font-size:14px;font-weight:700;color:#111827;}
        .pnot-dot{width:7px;height:7px;border-radius:999px;background:#2563eb;flex:0 0 auto;}
        .pnot-body{font-size:13px;color:#4b5563;margin-top:2px;line-height:1.45;}
        .pnot-when{font-size:11.5px;color:#9ca3af;margin-top:5px;}

        .pnot-empty{text-align:center;color:#6b7280;padding:40px 8px;}
        .pnot-empty-ic{width:34px;height:34px;color:#c3c8d2;margin:0 auto 8px;}
        .pnot-empty-t{font-size:14px;font-weight:700;color:#374151;}
        .pnot-empty-s{font-size:12.5px;margin-top:2px;}

        .dark .pnot-item{background:#1a2130;box-shadow:0 0 0 1px rgba(255,255,255,.08);}
        .dark .pnot-item-unread{box-shadow:0 0 0 1px rgba(96,165,250,.45);}
        .dark .pnot-title{color:#f3f4f6;}
        .dark .pnot-body{color:#9aa4b5;}
        .dark .pnot-ic{background:#23273d;color:#a5b4fc;}
        .dark .pnot-empty-t{color:#e5e7eb;}
    </style>
</x-filament-panels::page>
