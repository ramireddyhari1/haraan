{{--
    Ticket Studio — a bespoke, premium ticket-configuration workspace (not a stacked
    admin form). Custom Blade + Alpine, fully owned here, so it can be crafted to a
    Stripe/Linear/BookMyShow bar. State is entangled to the Filament field and
    reconciled into the event's ticket_types on save (see CreateEvent).
--}}
<div
    x-data="ticketStudio($wire.$entangle('{{ $getStatePath() }}'))"
    x-cloak
    class="tk"
    wire:ignore
>
    <style>
        .tk{
            --tk-ink:#0b1220;--tk-ink2:#334155;--tk-mut:#667085;--tk-faint:#98a2b3;
            --tk-line:#e9edf3;--tk-line2:#f0f3f8;--tk-bg:#f8fafc;--tk-card:#fff;
            --tk-blue:#2563eb;--tk-blue-d:#1d4ed8;--tk-blue-soft:#eef4ff;
            --tk-green:#12a150;--tk-green-soft:#e9faf1;--tk-amber:#f59e0b;
            --tk-r:16px;--tk-r-sm:11px;
            --tk-sh:0 1px 2px rgba(16,24,40,.05),0 14px 30px -22px rgba(16,24,40,.28);
            --tk-sh-lift:0 1px 2px rgba(16,24,40,.06),0 22px 44px -24px rgba(37,99,235,.32);
            color:var(--tk-ink);font-size:14px;-webkit-font-smoothing:antialiased;
        }
        .tk *{box-sizing:border-box}
        .tk [x-cloak]{display:none!important}

        /* layout */
        .tk-stack{display:flex;flex-direction:column;gap:16px}
        .tk-row{display:flex;align-items:center;gap:12px}
        .tk-grid2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
        @media (max-width:620px){.tk-grid2{grid-template-columns:1fr}}

        /* config cards (seating / phases) */
        .tk-cfg{display:grid;grid-template-columns:1fr 1fr;gap:12px}
        @media (max-width:720px){.tk-cfg{grid-template-columns:1fr}}
        .tk-cfg-card{
            display:flex;align-items:center;gap:13px;background:var(--tk-card);
            border:1px solid var(--tk-line);border-radius:var(--tk-r);padding:15px 16px;
            box-shadow:var(--tk-sh);transition:box-shadow .2s,border-color .2s;
        }
        .tk-cfg-card:hover{border-color:#dbe3ef}
        .tk-cfg-card.on{border-color:#c9dbff;background:linear-gradient(180deg,#fff,var(--tk-blue-soft))}
        .tk-ic{
            width:38px;height:38px;border-radius:10px;flex:none;display:flex;align-items:center;
            justify-content:center;background:var(--tk-blue-soft);color:var(--tk-blue);font-size:18px;
        }
        .tk-ic.g{background:var(--tk-green-soft);color:var(--tk-green)}
        .tk-ttl{font-weight:680;font-size:14px;letter-spacing:-.01em;line-height:1.25}
        .tk-sub{font-size:12px;color:var(--tk-mut);line-height:1.4;margin-top:2px}
        .tk-spacer{margin-left:auto}

        /* switch */
        .tk-sw{width:42px;height:24px;border-radius:999px;background:#d2d8e3;position:relative;cursor:pointer;border:none;padding:0;flex:none;transition:background .18s}
        .tk-sw b{position:absolute;top:2px;left:2px;width:20px;height:20px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(16,24,40,.3);transition:left .18s}
        .tk-sw.on{background:var(--tk-blue)} .tk-sw.on b{left:20px}
        .tk-sw.g.on{background:var(--tk-green)}
        .tk-sw.sm{width:36px;height:21px} .tk-sw.sm b{width:17px;height:17px} .tk-sw.sm.on b{left:17px}

        /* buttons */
        .tk-btn{display:inline-flex;align-items:center;gap:7px;border-radius:10px;font-weight:650;font-size:13px;padding:9px 15px;cursor:pointer;border:1px solid var(--tk-line);background:#fff;color:var(--tk-ink2);transition:all .16s}
        .tk-btn:hover{background:#f8fafc;border-color:#dbe3ef}
        .tk-btn.pri{background:linear-gradient(135deg,#3b82f6,#2563eb);border-color:transparent;color:#fff;box-shadow:0 10px 22px -12px rgba(37,99,235,.7)}
        .tk-btn.pri:hover{filter:brightness(1.05)}

        /* tickets-required banner */
        .tk-req{display:flex;gap:11px;align-items:flex-start;background:linear-gradient(180deg,#fff,#fff5f5);border:1px solid #fbd5d5;border-radius:var(--tk-r);padding:14px 16px}
        .tk-req .tk-ic{background:#fdecec;color:#e5484d}

        /* mode selector */
        .tk-mode{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;background:var(--tk-bg);border:1px solid var(--tk-line);border-radius:14px;padding:8px}
        .tk-opt{display:flex;gap:11px;align-items:flex-start;padding:12px 13px;border-radius:11px;cursor:pointer;border:1.5px solid transparent;transition:all .15s}
        .tk-opt:hover{background:#fff}
        .tk-opt.sel{background:#fff;border-color:#c9dbff;box-shadow:0 8px 20px -16px rgba(37,99,235,.6)}
        .tk-opt .tk-ic{width:32px;height:32px;font-size:15px;background:#eef2f7;color:var(--tk-mut)}
        .tk-opt.sel .tk-ic{background:var(--tk-blue-soft);color:var(--tk-blue)}
        .tk-opt-t{font-weight:650;font-size:13.5px} .tk-opt.sel .tk-opt-t{color:var(--tk-blue-d)}

        /* ── ticket card ── */
        .tk-card{background:var(--tk-card);border:1px solid var(--tk-line);border-radius:var(--tk-r);box-shadow:var(--tk-sh);transition:box-shadow .22s,border-color .22s,transform .22s;overflow:hidden}
        .tk-card:hover{box-shadow:var(--tk-sh-lift);border-color:#dbe3ef}
        .tk-card.dim{opacity:.62}
        .tk-head{display:flex;align-items:center;gap:12px;padding:14px 16px;border-bottom:1px solid var(--tk-line2);background:linear-gradient(180deg,#fff,#fcfdff)}
        .tk-grip{color:#cbd2e0;cursor:grab;font-size:15px;line-height:1;letter-spacing:-2px}
        .tk-avatar{width:38px;height:38px;border-radius:11px;flex:none;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#eff4ff,#e0ebff);color:var(--tk-blue);font-size:17px}
        .tk-head-main{min-width:0;flex:1}
        .tk-name-live{font-weight:700;font-size:14.5px;letter-spacing:-.01em;color:var(--tk-ink);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .tk-name-live.ph{color:var(--tk-faint);font-weight:600}
        .tk-chips{display:flex;gap:6px;margin-top:5px;flex-wrap:wrap}
        .tk-chip{display:inline-flex;align-items:center;gap:4px;font-size:11.5px;font-weight:650;padding:2px 8px;border-radius:7px;background:#f1f5f9;color:var(--tk-ink2)}
        .tk-chip.price{background:var(--tk-blue-soft);color:var(--tk-blue-d)}
        .tk-chip.free{background:var(--tk-green-soft);color:var(--tk-green)}
        .tk-chip.seats{background:#f4f6fa;color:var(--tk-mut)}
        .tk-vis{display:flex;align-items:center;gap:7px;font-size:12px;font-weight:600;color:var(--tk-green)}
        .tk-vis.off{color:var(--tk-faint)}
        .tk-del{width:32px;height:32px;border-radius:9px;border:none;background:transparent;color:#b6bdca;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:15px;transition:all .15s}
        .tk-del:hover{background:#fdecec;color:#e5484d}

        .tk-body{padding:18px 16px;display:flex;flex-direction:column;gap:16px}
        .tk-lbl{display:block;font-size:12px;font-weight:650;color:var(--tk-ink2);margin-bottom:7px;letter-spacing:.005em}
        .tk-lbl .req{color:#e5484d;margin-left:2px}
        .tk-in{width:100%;border:1.5px solid var(--tk-line);border-radius:10px;padding:10px 12px;font-size:14px;color:var(--tk-ink);background:#fff;outline:none;transition:border-color .15s,box-shadow .15s;font-family:inherit}
        .tk-in:hover{border-color:#d5dce7}
        .tk-in:focus{border-color:var(--tk-blue);box-shadow:0 0 0 4px rgba(37,99,235,.12)}
        .tk-in::placeholder{color:#aab4c4}
        textarea.tk-in{resize:vertical;min-height:44px;line-height:1.5}
        .tk-in-money{position:relative}
        .tk-in-money .cur{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--tk-mut);font-weight:600;font-size:14px}
        .tk-in-money .tk-in{padding-left:26px}
        .tk-err{color:#e5484d;font-size:11.5px;margin-top:6px;display:flex;gap:5px;align-items:center}

        /* pricing / bulk / phase rows inside card */
        .tk-cellhead{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px}
        .tk-inline{display:flex;align-items:center;gap:9px;font-size:13px;color:var(--tk-mut);font-weight:600}
        .tk-mini{display:flex;align-items:center;justify-content:space-between;gap:12px;background:var(--tk-bg);border:1px solid var(--tk-line2);border-radius:11px;padding:11px 13px}
        .tk-mini-t{font-weight:640;font-size:13px;color:var(--tk-ink2)}
        .tk-mini-s{font-size:11.5px;color:var(--tk-mut);margin-top:1px}
        select.tk-in{max-width:230px;appearance:auto}

        /* add ticket CTA */
        .tk-add{display:flex;align-items:center;justify-content:center;gap:10px;width:100%;background:linear-gradient(180deg,#fff,#f7faff);border:1.5px dashed #c3d3ee;border-radius:var(--tk-r);padding:16px;cursor:pointer;color:var(--tk-blue-d);font-weight:700;font-size:14px;transition:all .18s}
        .tk-add:hover{border-color:var(--tk-blue);background:var(--tk-blue-soft);transform:translateY(-1px);box-shadow:0 14px 30px -20px rgba(37,99,235,.5)}
        .tk-add .plus{width:26px;height:26px;border-radius:8px;background:var(--tk-blue);color:#fff;display:flex;align-items:center;justify-content:center;font-size:16px;line-height:1}

        /* modal */
        .tk-modal-bg{position:fixed;inset:0;background:rgba(11,18,32,.5);backdrop-filter:blur(2px);display:flex;align-items:center;justify-content:center;z-index:70;padding:20px}
        .tk-modal{background:#fff;border-radius:18px;padding:22px;width:100%;max-width:460px;box-shadow:0 30px 70px -20px rgba(0,0,0,.45)}
        .tk-phase-row{display:flex;align-items:center;gap:11px;border:1.5px solid #c7f0d7;background:var(--tk-green-soft);border-radius:12px;padding:10px 12px;margin-top:10px}
        .tk-phase-num{width:26px;height:26px;border-radius:8px;background:var(--tk-green);color:#fff;display:flex;align-items:center;justify-content:center;font-size:12.5px;font-weight:700;flex:none}
        .tk-onsale{font-size:11px;font-weight:650;color:var(--tk-green);background:#d6f5e3;border-radius:999px;padding:3px 9px;white-space:nowrap}
    </style>

    <div class="tk-stack">
        {{-- Seating + Release phases config cards --}}
        <div class="tk-cfg">
            <div class="tk-cfg-card" :class="{on:state.seating}">
                <span class="tk-ic g">▦</span>
                <div style="min-width:0">
                    <div class="tk-ttl">Seating Layout</div>
                    <div class="tk-sub">Let attendees pick a seat before booking.</div>
                </div>
                <button type="button" class="tk-sw g tk-spacer" :class="{on:state.seating}" @click="state.seating=!state.seating"><b></b></button>
            </div>
            <div class="tk-cfg-card" :class="{on:state.phases.length}">
                <span class="tk-ic">⚡</span>
                <div style="min-width:0">
                    <div class="tk-ttl" x-text="state.phases.length ? (state.phases.length + ' release phase' + (state.phases.length>1?'s':'')) : 'Release in phases?'"></div>
                    <div class="tk-sub" x-text="state.phases.length ? 'Assign each ticket to a phase below.' : 'Sell Early Bird first, open more when it sells out.'"></div>
                </div>
                <button type="button" class="tk-btn tk-spacer" @click="phasesOpen=true"><span x-text="state.phases.length ? 'Edit' : 'Set Up'"></span></button>
            </div>
        </div>

        {{-- Tickets required --}}
        <div class="tk-req" x-show="state.tickets.length===0">
            <span class="tk-ic">!</span>
            <div><div class="tk-ttl" style="color:#c4373b">Add your first ticket</div><div class="tk-sub">Every event needs at least one ticket type to publish.</div></div>
        </div>

        {{-- Mode selector --}}
        <div>
            <div class="tk-lbl" style="margin-bottom:9px">Ticket configuration</div>
            <div class="tk-mode">
                <div class="tk-opt" :class="{sel:state.mode==='unified'}" @click="state.mode='unified'">
                    <span class="tk-ic">▦</span>
                    <div><div class="tk-opt-t">Same for all sessions</div><div class="tk-sub">One set of tickets everywhere.</div></div>
                </div>
                <div class="tk-opt" :class="{sel:state.mode==='per_slot'}" @click="state.mode='per_slot'">
                    <span class="tk-ic">🏷</span>
                    <div><div class="tk-opt-t">Customize per session</div><div class="tk-sub">Different tickets per session.</div></div>
                </div>
            </div>
            <div class="tk-sub" x-show="state.mode==='per_slot'" style="margin-top:8px;padding-left:2px">Assign tickets to specific sessions after saving.</div>
        </div>

        {{-- Ticket cards --}}
        <template x-for="(t,i) in state.tickets" :key="t.key">
            <div class="tk-card" :class="{dim:!t.visible}">
                <div class="tk-head">
                    <span class="tk-grip">⋮⋮</span>
                    <span class="tk-avatar" x-text="t.free ? '🎟' : '🎫'"></span>
                    <div class="tk-head-main">
                        <div class="tk-name-live" :class="{ph:!t.name}" x-text="t.name || 'Untitled ticket'"></div>
                        <div class="tk-chips">
                            <span class="tk-chip" :class="t.free ? 'free' : 'price'" x-text="t.free ? 'Free' : ('₹' + (Number(t.price)||0).toLocaleString('en-IN'))"></span>
                            <span class="tk-chip seats" x-text="(t.seats<0 || t.seats==='' ? 'Unlimited seats' : (t.seats + ' seats'))"></span>
                            <span class="tk-chip" x-show="state.phases.length>0 && state.phases[t.phase]" x-text="state.phases[t.phase] ? state.phases[t.phase].name : ''"></span>
                        </div>
                    </div>
                    <label class="tk-vis" :class="{off:!t.visible}">
                        <button type="button" class="tk-sw sm" :class="{on:t.visible}" @click="t.visible=!t.visible"><b></b></button>
                        <span x-text="t.visible?'Visible':'Hidden'"></span>
                    </label>
                    <button type="button" class="tk-del" @click="remove(i)" title="Remove">🗑</button>
                </div>

                <div class="tk-body">
                    <div>
                        <label class="tk-lbl">Ticket name<span class="req">*</span></label>
                        <input class="tk-in" type="text" x-model="t.name" placeholder="e.g. General Admission, VIP, Early Bird">
                        <div class="tk-err" x-show="!t.name"><span>⚠</span> Give this ticket a name</div>
                    </div>

                    <div>
                        <label class="tk-lbl">Description <span style="color:var(--tk-faint);font-weight:500">(optional)</span></label>
                        <textarea class="tk-in" rows="2" x-model="t.description" placeholder="e.g. Includes entry, welcome drink and reserved seating"></textarea>
                    </div>

                    <div class="tk-grid2">
                        <div>
                            <div class="tk-cellhead">
                                <label class="tk-lbl" style="margin:0">Price</label>
                                <label class="tk-inline"><span>Free</span><button type="button" class="tk-sw sm g" :class="{on:t.free}" @click="toggleFree(t)"><b></b></button></label>
                            </div>
                            <div class="tk-in-money" x-show="!t.free">
                                <span class="cur">₹</span>
                                <input class="tk-in" type="number" min="0" x-model.number="t.price" placeholder="0">
                            </div>
                            <div class="tk-mini" x-show="t.free" style="justify-content:flex-start;gap:8px">
                                <span class="tk-ic g" style="width:28px;height:28px;font-size:13px">✓</span>
                                <span class="tk-mini-t" style="color:var(--tk-green)">This ticket is free</span>
                            </div>
                        </div>
                        <div>
                            <label class="tk-lbl">Total seats <span style="color:var(--tk-faint);font-weight:500">(-1 = unlimited)</span></label>
                            <input class="tk-in" type="number" min="-1" x-model.number="t.seats">
                        </div>
                    </div>

                    <div class="tk-mini" x-show="!t.free">
                        <div><div class="tk-mini-t">Bulk booking</div><div class="tk-mini-s">Let buyers grab several at once</div></div>
                        <button type="button" class="tk-sw sm" :class="{on:t.bulk}" @click="t.bulk=!t.bulk"><b></b></button>
                    </div>
                    <div class="tk-grid2" x-show="!t.free && t.bulk">
                        <div><label class="tk-lbl">Min per order</label><input class="tk-in" type="number" min="1" x-model.number="t.minPer"></div>
                        <div><label class="tk-lbl">Max per order</label><input class="tk-in" type="number" min="1" x-model.number="t.maxPer" placeholder="No limit"></div>
                    </div>

                    <div class="tk-mini" x-show="state.phases.length>0">
                        <div><div class="tk-mini-t">Release phase</div><div class="tk-mini-s">When this ticket goes on sale</div></div>
                        <select class="tk-in" x-model.number="t.phase" style="width:auto;min-width:170px">
                            <template x-for="(p,pi) in state.phases" :key="pi">
                                <option :value="pi" x-text="p.name + (pi===0 ? ' · on sale now' : '')"></option>
                            </template>
                        </select>
                    </div>
                    {{-- A later phase only opens once the earlier phase sells out, so an
                         earlier phase with no seat limit (or no tickets) opens this one
                         immediately — which reads to the host as "phases don't work". --}}
                    <div class="tk-err" x-show="state.phases.length>0 && phaseNote(t)" style="margin-top:0">
                        <span>⚠</span> <span x-text="phaseNote(t)"></span>
                    </div>
                </div>
            </div>
        </template>

        {{-- Add ticket --}}
        <button type="button" class="tk-add" @click="add()"><span class="plus">+</span> Add ticket type</button>
    </div>

    {{-- Release Phases modal --}}
    <div x-show="phasesOpen" class="tk-modal-bg" @click.self="phasesOpen=false" style="display:none">
        <div class="tk-modal">
            <div class="tk-row">
                <span class="tk-ic">⚡</span>
                <div style="flex:1"><div class="tk-ttl" style="font-size:16px">Release phases</div><div class="tk-sub">Phase 1 sells now; later phases open as each sells out.</div></div>
                <button type="button" class="tk-del" @click="phasesOpen=false">✕</button>
            </div>
            <template x-for="(p,i) in state.phases" :key="i">
                <div class="tk-phase-row">
                    <span class="tk-phase-num" x-text="i+1"></span>
                    <input class="tk-in" x-model="p.name" placeholder="Phase name" style="border:none;background:transparent;padding:4px 2px;box-shadow:none">
                    <span class="tk-onsale" x-show="i===0">On sale now</span>
                    <button type="button" class="tk-del" @click="removePhase(i)">🗑</button>
                </div>
            </template>
            <button type="button" class="tk-add" @click="addPhase()" style="margin-top:12px;padding:12px"><span class="plus">+</span> Add phase</button>
            <div class="tk-row" style="margin-top:16px">
                <button type="button" @click="state.phases=[]" style="color:#e5484d;background:none;border:none;cursor:pointer;font-weight:650;font-size:13px" x-show="state.phases.length">Remove all</button>
                <button type="button" class="tk-btn pri tk-spacer" @click="phasesOpen=false">Done</button>
            </div>
        </div>
    </div>
</div>

<script>
    function ticketStudio(state) {
        return {
            state,
            phasesOpen: false,
            init() {
                if (!this.state || typeof this.state !== 'object') {
                    this.state = { mode: 'unified', seating: false, slotCount: 1, tickets: [], phases: [] };
                }
                if (!Array.isArray(this.state.tickets)) this.state.tickets = [];
                if (!Array.isArray(this.state.phases)) this.state.phases = [];
                this.state.tickets.forEach(t => { if (!t.key) t.key = this.uid(); if (t.phase == null) t.phase = 0; });
            },
            uid() {
                return (crypto.randomUUID ? crypto.randomUUID().replace(/-/g, '') : Math.random().toString(16).slice(2).padEnd(16, '0')).slice(0, 12);
            },
            add() {
                this.state.tickets.push({
                    key: this.uid(), id: null, name: '', seats: -1, description: '',
                    price: 0, free: true, visible: true, bulk: false, minPer: 1, maxPer: null, phase: 0,
                });
            },
            remove(i) { this.state.tickets.splice(i, 1); },
            toggleFree(t) { t.free = !t.free; if (t.free) { t.price = 0; t.bulk = false; } },
            addPhase() {
                if (this.state.phases.length === 0) this.state.phases.push({ name: 'Early Bird' });
                this.state.phases.push({ name: 'Phase ' + (this.state.phases.length + 1) });
            },
            /**
             * Why a ticket's release phase won't behave the way the host expects, or ''
             * when it's fine. Mirrors Event::phaseReleased(): a phase opens once every
             * capacity-bearing ticket in the earlier phases has sold out, and an
             * unlimited ticket never sells out (so it's skipped, not treated as a block).
             */
            phaseNote(t) {
                const pi = Number(t.phase) || 0;
                if (pi <= 0 || !this.state.phases.length) return '';

                const earlier = this.state.tickets.filter(x => (Number(x.phase) || 0) < pi && String(x.name || '').trim() !== '');
                const prev = (this.state.phases[pi - 1] || {}).name || 'the earlier phase';

                if (!earlier.length) {
                    return 'No ticket sells in ' + prev + ' yet, so this one goes on sale straight away. Move a ticket into ' + prev + '.';
                }
                const noCap = earlier.every(x => x.seats === '' || x.seats === null || Number(x.seats) < 0);
                if (noCap) {
                    return prev + ' has unlimited seats, so it never sells out and this ticket opens immediately. Give those tickets a seat limit.';
                }

                return '';
            },
            removePhase(i) {
                this.state.phases.splice(i, 1);
                this.state.tickets.forEach(t => { if (t.phase >= this.state.phases.length) t.phase = Math.max(0, this.state.phases.length - 1); });
            },
        };
    }
</script>
