<x-filament-panels::page>
    {{-- Chat transcript + composer. Polls so an admin reply appears without a
         manual refresh, mirroring the in-app support screen. --}}
    <div class="psup" wire:poll.5s="refreshThread">
        <div class="psup-thread">
            @forelse ($this->messages as $message)
                @php $fromAdmin = $message->sender_type === 'admin'; @endphp
                <div @class(['psup-row', 'psup-row-me' => ! $fromAdmin])>
                    <div @class(['psup-bubble', 'psup-bubble-me' => ! $fromAdmin])>
                        <div class="psup-who">
                            {{ $fromAdmin ? ($message->sender?->name ?: 'Haraan team') : 'You' }}
                        </div>
                        <div class="psup-body">{{ $message->body }}</div>
                        <div class="psup-time">{{ $message->created_at?->diffForHumans() }}</div>
                    </div>
                </div>
            @empty
                <div class="psup-empty">
                    <x-filament::icon icon="heroicon-o-chat-bubble-left-right" class="psup-empty-ic" />
                    <div class="psup-empty-t">No messages yet</div>
                    <div class="psup-empty-s">Ask us anything about your events, venues, bookings or payouts.</div>
                </div>
            @endforelse
        </div>

        <form class="psup-compose" wire:submit.prevent="send">
            <textarea
                class="psup-input"
                rows="2"
                maxlength="4000"
                placeholder="Type your message…"
                wire:model="body"
            ></textarea>
            <button type="submit" class="psup-send" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="send">Send</span>
                <span wire:loading wire:target="send">Sending…</span>
            </button>
        </form>
    </div>

    <style>
        .psup{display:flex;flex-direction:column;gap:14px;}
        .psup-thread{display:flex;flex-direction:column;gap:10px;min-height:280px;max-height:56vh;
            overflow-y:auto;padding:16px;border-radius:16px;background:#fff;
            box-shadow:0 1px 3px rgba(11,18,32,.06),0 0 0 1px rgba(120,120,120,.11);}
        .psup-row{display:flex;justify-content:flex-start;}
        .psup-row-me{justify-content:flex-end;}
        .psup-bubble{max-width:min(560px,82%);padding:9px 13px;border-radius:14px;
            background:#f3f4f6;box-shadow:inset 0 0 0 1px rgba(120,120,120,.12);}
        .psup-bubble-me{background:#2563eb;box-shadow:none;}
        .psup-who{font-size:11px;font-weight:700;letter-spacing:.02em;color:#6b7280;margin-bottom:2px;}
        .psup-bubble-me .psup-who{color:rgba(255,255,255,.78);}
        .psup-body{font-size:14px;line-height:1.45;color:#111827;white-space:pre-wrap;word-break:break-word;}
        .psup-bubble-me .psup-body{color:#fff;}
        .psup-time{font-size:10.5px;color:#9ca3af;margin-top:3px;}
        .psup-bubble-me .psup-time{color:rgba(255,255,255,.7);}

        .psup-empty{margin:auto;text-align:center;color:#6b7280;padding:24px 8px;}
        .psup-empty-ic{width:34px;height:34px;color:#c3c8d2;margin:0 auto 8px;}
        .psup-empty-t{font-size:14px;font-weight:700;color:#374151;}
        .psup-empty-s{font-size:12.5px;margin-top:2px;}

        .psup-compose{display:flex;gap:10px;align-items:flex-end;}
        .psup-input{flex:1 1 auto;resize:vertical;font-size:14px;padding:10px 12px;border-radius:12px;
            background:#fff;color:#111827;border:0;box-shadow:inset 0 0 0 1px rgba(120,120,120,.22);}
        .psup-input:focus{outline:none;box-shadow:inset 0 0 0 2px #2563eb;}
        .psup-send{flex:0 0 auto;font-size:13.5px;font-weight:700;color:#fff;background:#2563eb;
            padding:11px 20px;border-radius:12px;border:0;cursor:pointer;}
        .psup-send:hover{background:#1d4ed8;}
        .psup-send:disabled{opacity:.6;cursor:default;}

        .dark .psup-thread{background:#1a2130;box-shadow:0 0 0 1px rgba(255,255,255,.08);}
        .dark .psup-bubble{background:#232c3d;box-shadow:inset 0 0 0 1px rgba(255,255,255,.08);}
        .dark .psup-body{color:#e5e7eb;}
        .dark .psup-who{color:#9aa4b5;}
        .dark .psup-empty-t{color:#e5e7eb;}
        .dark .psup-input{background:#1a2130;color:#e5e7eb;box-shadow:inset 0 0 0 1px rgba(255,255,255,.14);}

        @media (max-width:640px){
            .psup-thread{padding:12px;max-height:52vh;}
            .psup-bubble{max-width:88%;}
        }
    </style>
</x-filament-panels::page>
