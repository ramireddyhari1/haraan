<x-filament-panels::page>
    {{-- Haraan Partner support center — production-grade, minimal, action-first.
         A compact Ask-Haraan composer up top, category chips, an inline AI
         conversation, real FAQ cards, a clean team-chat surface, and a
         persistent floating chat action. Self-contained markup + inline CSS,
         theme-aware, no Vite rebuild. Backend/AI wiring unchanged. --}}
    @php
        $topics = [
            ['label' => 'Events',      'q' => 'How do I create or edit an event?'],
            ['label' => 'Bookings',    'q' => 'Where do I see and manage my bookings?'],
            ['label' => 'Payments',    'q' => 'How do payments and refunds work on Haraan?'],
            ['label' => 'Settlements', 'q' => 'When and how do I get my settlement / payout?'],
            ['label' => 'Staff',       'q' => 'How do I add my team and set their permissions?'],
        ];
        $faqs = [
            ['q' => 'When will I get paid?', 'a' => 'Money you collect is settled to you by Haraan. Track it under Earnings — the Pending tile shows what’s awaiting settlement, and each ledger row shows “Settled” or “Awaiting payout”, with the payout date once it’s processed.'],
            ['q' => 'Why does a booking show “Unsettled”?', 'a' => 'It means the money was collected but hasn’t been paid out to you yet. It moves to “Settled” once its payout is processed.'],
            ['q' => 'How do I create or edit an event?', 'a' => 'Open Events → Create to add one, or tap any event to edit its details, ticket tiers and capacity. Each event also has its own Analytics.'],
            ['q' => 'Can I take a walk-in booking?', 'a' => 'Yes — on a venue’s day slot-grid you can add an offline/walk-in booking, and close a day when you’re fully booked.'],
            ['q' => 'How do I scan tickets at the door?', 'a' => 'Use the QR ticket scanner in the console for check-in. Staff need the check-in permission to use it.'],
            ['q' => 'Can I add my team?', 'a' => 'Yes — invite staff and set their roles and permissions from People / Staff. Each role controls what they can see and do.'],
        ];
    @endphp

    <div class="hs">

        {{-- ── Composer + status ── --}}
        <div class="hs-top">
            <div class="hs-status"><span class="hs-status-dot"></span>Support online · typically replies within a few hours</div>

            <form class="hs-ask" wire:submit.prevent="askAi">
                <span class="hs-ask-ic" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" width="18" height="18">
                        <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.8"/>
                        <path d="M20 20l-3.2-3.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    </svg>
                </span>
                <input
                    class="hs-ask-input"
                    type="text"
                    maxlength="2000"
                    placeholder="Ask Haraan or search for help…"
                    wire:model="aiInput"
                    wire:loading.attr="disabled" wire:target="askAi"
                    x-on:focus-ai.window="$el.focus()"
                    autocomplete="off"
                />
                <button type="submit" class="hs-ask-go" wire:loading.attr="disabled" wire:target="askAi" aria-label="Ask">
                    <span wire:loading.remove wire:target="askAi">
                        <svg viewBox="0 0 24 24" fill="none" width="17" height="17" aria-hidden="true">
                            <path d="M5 12h13M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <span wire:loading wire:target="askAi" class="hs-spin" aria-hidden="true"></span>
                </button>
            </form>

            <div class="hs-cats">
                @foreach ($topics as $t)
                    <button type="button" class="hs-cat" wire:click="askQuick(@js($t['q']))"
                        wire:loading.attr="disabled" wire:target="askAi,askQuick">{{ $t['label'] }}</button>
                @endforeach
            </div>
        </div>

        {{-- ── AI answer (inline) ── --}}
        @if (! empty($aiMessages))
            <section class="hs-ai"
                x-data="{ scroll(){ this.$nextTick(() => { const t = this.$refs.aithread; if (t) t.scrollTop = t.scrollHeight; }); } }"
                x-init="scroll()" x-on:ai-answered.window="scroll()">
                <div class="hs-ai-cap"><span class="hs-ai-badge">Haraan AI</span>Instant answers, based on how the console works</div>
                <div class="hs-ai-thread" x-ref="aithread">
                    @foreach ($aiMessages as $i => $m)
                        <div @class(['hs-ai-row', 'is-me' => ($m['role'] ?? '') === 'user']) wire:key="ai-{{ $i }}">
                            <div class="hs-ai-bubble">
                                <div class="hs-ai-body">{{ $m['text'] }}</div>
                                @if (! empty($m['handoff']))
                                    <button type="button" class="hs-ai-handoff" wire:click="escalateToHuman">
                                        Talk to a human <span aria-hidden="true">→</span>
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                    <div class="hs-ai-typing" wire:loading.flex wire:target="askAi,askQuick"><span></span><span></span><span></span></div>
                </div>
            </section>
        @endif

        {{-- ── FAQ cards ── --}}
        <section class="hs-faq">
            <div class="hs-sec">Answers</div>
            <div class="hs-faq-grid">
                @foreach ($faqs as $i => $f)
                    <div class="hs-faq-card" x-data="{ open: false }" :class="{ 'is-open': open }" wire:key="faq-{{ $i }}">
                        <button type="button" class="hs-faq-q" x-on:click="open = !open" :aria-expanded="open">
                            <span>{{ $f['q'] }}</span>
                            <svg class="hs-faq-chev" viewBox="0 0 24 24" fill="none" width="17" height="17" aria-hidden="true">
                                <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>
                        <div class="hs-faq-a" x-show="open" x-collapse x-cloak>
                            <p>{{ $f['a'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- ── Team chat ── --}}
        <section id="hs-chat" class="hs-chat" wire:poll.5s="refreshThread"
            x-data="{ scroll(){ this.$nextTick(() => { const t = this.$refs.hthread; if (t) t.scrollTop = t.scrollHeight; }); } }"
            x-init="scroll()">
            <div class="hs-chat-head">
                <span class="hs-chat-ava" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" width="17" height="17">
                        <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </span>
                <div>
                    <div class="hs-chat-title">Chat with the Haraan team</div>
                    <div class="hs-chat-sub"><span class="hs-live-dot"></span>Live · a real person replies here</div>
                </div>
            </div>

            <div class="hs-chat-thread" x-ref="hthread">
                @forelse ($this->messages as $message)
                    @php $fromAdmin = $message->sender_type === 'admin'; @endphp
                    <div @class(['hs-h-row', 'is-me' => ! $fromAdmin]) wire:key="hmsg-{{ $message->id }}">
                        @if ($fromAdmin)<span class="hs-h-ava" aria-hidden="true">H</span>@endif
                        <div class="hs-h-bubble">
                            <div class="hs-h-who">{{ $fromAdmin ? ($message->sender?->name ?: 'Haraan team') : 'You' }}</div>
                            <div class="hs-h-body">{{ $message->body }}</div>
                            <div class="hs-h-time">{{ $message->created_at?->diffForHumans() }}</div>
                        </div>
                    </div>
                @empty
                    <div class="hs-h-empty">No messages yet — send one and the Haraan team will pick it up.</div>
                @endforelse
            </div>

            <form class="hs-h-compose" wire:submit.prevent="send" x-on:submit="scroll()">
                <textarea
                    class="hs-h-input" rows="1" maxlength="4000"
                    placeholder="Message the Haraan team…"
                    wire:model="body"
                    x-on:focus-composer.window="$el.focus(); $el.setSelectionRange($el.value.length, $el.value.length)"
                    x-on:keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); $wire.send() }"
                ></textarea>
                <button type="submit" class="hs-h-send" wire:loading.attr="disabled" wire:target="send" aria-label="Send">
                    <span wire:loading.remove wire:target="send">
                        <svg viewBox="0 0 24 24" fill="none" width="18" height="18" aria-hidden="true">
                            <path d="M4 12l16-8-6 16-3-7-7-1z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <span wire:loading wire:target="send" class="hs-spin" aria-hidden="true"></span>
                </button>
            </form>
        </section>
    </div>

    {{-- ── Persistent floating chat action ── --}}
    <button type="button" class="hs-fab" aria-label="Message the Haraan team"
        x-data
        x-on:click="document.getElementById('hs-chat')?.scrollIntoView({ behavior: 'smooth', block: 'start' }); $dispatch('focus-composer')">
        <svg viewBox="0 0 24 24" fill="none" width="22" height="22" aria-hidden="true">
            <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <span class="hs-fab-dot" aria-hidden="true"></span>
    </button>

    <style>
        [x-cloak]{display:none !important;}
        .hs{--ink:#0c1424;--ink-2:#5a6472;--line:#e8eaf0;--brand:#2563eb;
            display:flex;flex-direction:column;gap:22px;}
        .hs-sec{font-size:12px;font-weight:700;letter-spacing:.09em;text-transform:uppercase;color:#9aa2b1;
            margin:0 0 11px 2px;}

        /* ── Top: status + composer ── */
        .hs-top{display:flex;flex-direction:column;gap:12px;}
        .hs-status{display:inline-flex;align-items:center;gap:7px;font-size:12.5px;font-weight:500;color:var(--ink-2);}
        .hs-status-dot{width:7px;height:7px;border-radius:50%;background:#12b76a;flex:none;
            box-shadow:0 0 0 3px rgba(18,183,106,.15);}
        .hs-ask{display:flex;align-items:center;gap:10px;padding:6px 6px 6px 14px;background:#fff;
            border:1px solid var(--line);border-radius:14px;box-shadow:0 1px 2px rgba(11,18,32,.05);
            transition:border-color .15s,box-shadow .15s;}
        .hs-ask:focus-within{border-color:#bcd0fb;box-shadow:0 0 0 3px rgba(37,99,235,.1);}
        .hs-ask-ic{display:inline-flex;color:#98a2b3;flex:none;}
        .hs-ask-input{flex:1;border:0;outline:0;background:transparent;font-size:14.5px;color:var(--ink);
            padding:8px 0;min-width:0;}
        .hs-ask-input::placeholder{color:#98a2b3;}
        .hs-ask-go{flex:none;display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;
            border:0;border-radius:10px;cursor:pointer;color:#fff;background:var(--brand);
            transition:background .13s,transform .1s;}
        .hs-ask-go:hover{background:#1d4ed8;}
        .hs-ask-go:active{transform:scale(.94);}
        .hs-ask-go:disabled{opacity:.6;cursor:default;}

        .hs-cats{display:flex;flex-wrap:wrap;gap:7px;}
        .hs-cat{font-size:12.5px;font-weight:600;color:#475467;background:#f4f6fa;border:1px solid transparent;
            border-radius:9px;padding:7px 12px;cursor:pointer;transition:background .13s,color .13s,border-color .13s;}
        .hs-cat:hover{background:#eaf0fb;color:var(--brand);border-color:#d7e3fb;}
        .hs-cat:disabled{opacity:.5;cursor:default;}

        /* ── AI answer ── */
        .hs-ai{background:#fff;border:1px solid var(--line);border-radius:16px;overflow:hidden;
            box-shadow:0 1px 2px rgba(11,18,32,.05);}
        .hs-ai-cap{display:flex;align-items:center;gap:8px;padding:11px 14px;border-bottom:1px solid #f1f3f7;
            font-size:11.5px;color:#8a93a3;font-weight:500;}
        .hs-ai-badge{font-size:11px;font-weight:700;color:var(--brand);background:#eaf0fb;
            padding:3px 8px;border-radius:7px;}
        .hs-ai-thread{display:flex;flex-direction:column;gap:11px;max-height:44vh;overflow-y:auto;padding:14px;
            scroll-behavior:smooth;}
        .hs-ai-row{display:flex;justify-content:flex-start;}
        .hs-ai-row.is-me{justify-content:flex-end;}
        .hs-ai-bubble{max-width:min(600px,88%);padding:10px 13px;border-radius:14px 14px 14px 4px;
            background:#f5f7fa;box-shadow:inset 0 0 0 1px rgba(120,120,120,.09);}
        .hs-ai-row.is-me .hs-ai-bubble{border-radius:14px 14px 4px 14px;background:var(--brand);color:#fff;box-shadow:none;}
        .hs-ai-body{font-size:14px;line-height:1.55;color:#1a2230;white-space:pre-wrap;word-break:break-word;}
        .hs-ai-row.is-me .hs-ai-body{color:#fff;}
        .hs-ai-handoff{margin-top:9px;display:inline-flex;align-items:center;gap:5px;font-size:12px;font-weight:700;
            color:var(--brand);background:#eaf0fb;border:0;border-radius:8px;padding:6px 11px;cursor:pointer;
            transition:background .12s;}
        .hs-ai-handoff:hover{background:#dce8fb;}
        .hs-ai-typing{display:none;align-items:center;gap:5px;padding:2px 4px;}
        .hs-ai-typing span{width:6px;height:6px;border-radius:50%;background:#9fb6e8;animation:hs-bounce 1s infinite;}
        .hs-ai-typing span:nth-child(2){animation-delay:.15s;}
        .hs-ai-typing span:nth-child(3){animation-delay:.3s;}
        @keyframes hs-bounce{0%,60%,100%{transform:translateY(0);opacity:.5}30%{transform:translateY(-4px);opacity:1}}

        /* ── FAQ cards ── */
        .hs-faq-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
        .hs-faq-card{background:#fff;border:1px solid var(--line);border-radius:14px;overflow:hidden;
            box-shadow:0 1px 2px rgba(11,18,32,.04);transition:border-color .14s,box-shadow .14s;}
        .hs-faq-card:hover{border-color:#dbe0ea;box-shadow:0 6px 18px -12px rgba(11,18,32,.22);}
        .hs-faq-card.is-open{border-color:#cddcfa;}
        .hs-faq-q{width:100%;display:flex;align-items:center;justify-content:space-between;gap:12px;
            padding:14px 15px;background:none;border:0;cursor:pointer;text-align:left;
            font-size:13.5px;font-weight:600;color:var(--ink);}
        .hs-faq-chev{color:#98a2b3;flex:none;transition:transform .2s,color .2s;}
        .hs-faq-card.is-open .hs-faq-chev{transform:rotate(180deg);color:var(--brand);}
        .hs-faq-a{padding:0 15px 14px;}
        .hs-faq-a p{margin:0;font-size:12.5px;line-height:1.55;color:var(--ink-2);}

        /* ── Team chat ── */
        .hs-chat{background:#fff;border:1px solid var(--line);border-radius:16px;overflow:hidden;
            box-shadow:0 1px 2px rgba(11,18,32,.05);}
        .hs-chat-head{display:flex;align-items:center;gap:10px;padding:13px 15px;border-bottom:1px solid #f1f3f7;}
        .hs-chat-ava{display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;flex:none;
            border-radius:10px;color:#fff;background:var(--brand);}
        .hs-chat-title{font-size:13.5px;font-weight:700;color:var(--ink);letter-spacing:-.01em;}
        .hs-chat-sub{display:flex;align-items:center;gap:6px;font-size:11.5px;color:#12b76a;font-weight:600;margin-top:1px;}
        .hs-live-dot{width:6px;height:6px;border-radius:50%;background:#12b76a;box-shadow:0 0 0 3px rgba(18,183,106,.15);}
        .hs-chat-thread{display:flex;flex-direction:column;gap:11px;min-height:130px;max-height:40vh;overflow-y:auto;
            padding:15px;scroll-behavior:smooth;}
        .hs-h-row{display:flex;justify-content:flex-start;align-items:flex-end;gap:8px;}
        .hs-h-row.is-me{justify-content:flex-end;}
        .hs-h-ava{width:26px;height:26px;flex:none;border-radius:50%;display:flex;align-items:center;justify-content:center;
            font-size:12px;font-weight:800;color:#fff;background:var(--brand);}
        .hs-h-bubble{max-width:min(520px,82%);padding:9px 13px;border-radius:14px 14px 14px 4px;
            background:#f5f7fa;box-shadow:inset 0 0 0 1px rgba(120,120,120,.09);}
        .hs-h-row.is-me .hs-h-bubble{border-radius:14px 14px 4px 14px;background:var(--brand);color:#fff;box-shadow:none;}
        .hs-h-who{font-size:10.5px;font-weight:700;color:#667085;margin-bottom:2px;}
        .hs-h-row.is-me .hs-h-who{color:rgba(255,255,255,.8);}
        .hs-h-body{font-size:13.5px;line-height:1.45;color:#111827;white-space:pre-wrap;word-break:break-word;}
        .hs-h-row.is-me .hs-h-body{color:#fff;}
        .hs-h-time{font-size:10px;color:#9ca3af;margin-top:3px;}
        .hs-h-row.is-me .hs-h-time{color:rgba(255,255,255,.7);}
        .hs-h-empty{margin:auto;text-align:center;font-size:12.5px;color:#8a93a3;max-width:30ch;line-height:1.5;padding:16px;}
        .hs-h-compose{display:flex;gap:9px;align-items:flex-end;padding:12px 14px 14px;border-top:1px solid #f1f3f7;}
        .hs-h-input{flex:1;resize:none;font-size:13.5px;line-height:1.4;padding:10px 13px;border-radius:12px;
            max-height:120px;background:#f5f7fa;color:#111827;border:0;box-shadow:inset 0 0 0 1px rgba(120,120,120,.14);
            transition:box-shadow .12s;}
        .hs-h-input:focus{outline:none;background:#fff;box-shadow:inset 0 0 0 2px var(--brand);}
        .hs-h-send{flex:none;display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;
            color:#fff;background:var(--brand);border-radius:12px;border:0;cursor:pointer;transition:background .13s,transform .1s;}
        .hs-h-send:hover{background:#1d4ed8;}
        .hs-h-send:active{transform:scale(.93);}
        .hs-h-send:disabled{opacity:.65;cursor:default;}

        .hs-spin{width:15px;height:15px;border-radius:50%;border:2px solid rgba(255,255,255,.4);
            border-top-color:#fff;animation:hs-spin .7s linear infinite;}
        @keyframes hs-spin{to{transform:rotate(360deg)}}

        /* ── Floating chat action ── */
        .hs-fab{position:fixed;right:22px;bottom:22px;z-index:40;width:54px;height:54px;border-radius:16px;border:0;
            cursor:pointer;color:#fff;background:var(--brand);display:inline-flex;align-items:center;justify-content:center;
            box-shadow:0 12px 26px -8px rgba(37,99,235,.55),0 2px 6px rgba(11,18,32,.2);
            transition:transform .15s cubic-bezier(.22,.61,.36,1),background .13s;}
        .hs-fab:hover{transform:translateY(-2px) scale(1.03);background:#1d4ed8;}
        .hs-fab:active{transform:scale(.95);}
        .hs-fab-dot{position:absolute;top:11px;right:11px;width:9px;height:9px;border-radius:50%;background:#12b76a;
            border:2px solid var(--brand);}

        /* ── Dark ── */
        .dark .hs{--ink:#eef1f6;--ink-2:#8b94a5;--line:#1e2633;}
        .dark .hs-ask{background:#111722;border-color:#28303d;box-shadow:0 1px 2px rgba(0,0,0,.4);}
        .dark .hs-ask:focus-within{border-color:#33518f;box-shadow:0 0 0 3px rgba(37,99,235,.16);}
        .dark .hs-ask-input{color:#eef1f6;}
        .dark .hs-ask-input::placeholder{color:#6b7382;}
        .dark .hs-cat{background:#171f2b;color:#aab3c2;}
        .dark .hs-cat:hover{background:#1c2941;color:#7fb0ff;border-color:#2b3f63;}
        .dark .hs-ai{background:#111722;border-color:#1e2633;}
        .dark .hs-ai-cap{border-color:#1e2633;color:#8b94a5;}
        .dark .hs-ai-badge{color:#7fb0ff;background:#16233b;}
        .dark .hs-ai-bubble{background:#1a2230;box-shadow:inset 0 0 0 1px rgba(255,255,255,.06);}
        .dark .hs-ai-body{color:#e5e7eb;}
        .dark .hs-ai-handoff{color:#7fb0ff;background:#16233b;}
        .dark .hs-faq-card{background:#111722;border-color:#1e2633;}
        .dark .hs-faq-card:hover{border-color:#2a3444;}
        .dark .hs-faq-card.is-open{border-color:#33518f;}
        .dark .hs-faq-a p{color:#8b94a5;}
        .dark .hs-chat{background:#111722;border-color:#1e2633;}
        .dark .hs-chat-head,.dark .hs-h-compose{border-color:#1e2633;}
        .dark .hs-h-bubble{background:#1a2230;box-shadow:inset 0 0 0 1px rgba(255,255,255,.06);}
        .dark .hs-h-body{color:#e5e7eb;}
        .dark .hs-h-who{color:#8b94a5;}
        .dark .hs-h-empty{color:#6b7382;}
        .dark .hs-h-input{background:#141b26;color:#e5e7eb;box-shadow:inset 0 0 0 1px rgba(255,255,255,.12);}
        .dark .hs-h-input:focus{background:#141b26;box-shadow:inset 0 0 0 2px var(--brand);}
        .dark .hs-fab-dot{border-color:#111722;}

        @media (max-width:640px){.hs-faq-grid{grid-template-columns:1fr;}}
    </style>
</x-filament-panels::page>
