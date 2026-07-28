{{--
    Coupon Studio — a bespoke, premium coupon builder for the event create/edit wizard.
    Custom Blade + Alpine, state entangled to the Filament field and reconciled into the
    event's coupons on save (see CreateEvent / EditEvent). "Create Coupon" opens a 6-step
    modal wizard (Code · Discount · Eligibility · Limits · Restrictions · Review).
--}}
<div x-data="couponStudio($wire.$entangle('{{ $getStatePath() }}'))" x-cloak class="cp" wire:ignore>
    <style>
        .cp{--cp-ink:#0b1220;--cp-ink2:#334155;--cp-mut:#667085;--cp-faint:#94a3b8;
            --cp-line:#e6ebf3;--cp-line2:#eef2f8;--cp-bg:#f7f9fc;--cp-card:#fff;
            --cp-blue:#2563eb;--cp-blue-d:#1d4ed8;--cp-blue-soft:#eff4ff;--cp-blue2:#3b5bdb;
            --cp-green:#12a150;--cp-green-soft:#e9faf1;
            --cp-r:16px;--cp-r-sm:11px;color:var(--cp-ink);font-size:14px;-webkit-font-smoothing:antialiased;}
        .cp *{box-sizing:border-box}
        .cp [x-cloak]{display:none!important}

        /* ---- list of created coupons ---- */
        .cp-list{display:flex;flex-direction:column;gap:12px}
        .cp-empty{display:flex;gap:13px;align-items:flex-start;background:linear-gradient(180deg,#fff,var(--cp-blue-soft));
            border:1px dashed #c3d3ee;border-radius:var(--cp-r);padding:18px}
        .cp-empty .cp-ic{width:40px;height:40px;border-radius:11px;background:var(--cp-blue-soft);color:var(--cp-blue);
            display:flex;align-items:center;justify-content:center;font-size:19px;flex:none}
        .cp-ttl{font-weight:680;letter-spacing:-.01em;line-height:1.25}
        .cp-sub{font-size:12.5px;color:var(--cp-mut);line-height:1.45;margin-top:2px}

        .cp-coup{display:flex;align-items:center;gap:14px;background:var(--cp-card);border:1px solid var(--cp-line);
            border-radius:var(--cp-r);padding:14px 16px;box-shadow:0 1px 2px rgba(16,24,40,.05),0 16px 34px -26px rgba(16,24,40,.3)}
        .cp-tag{width:44px;height:44px;border-radius:12px;flex:none;display:flex;align-items:center;justify-content:center;
            background:linear-gradient(135deg,#eff4ff,#dbe7ff);color:var(--cp-blue);font-size:19px}
        .cp-coup-main{min-width:0;flex:1}
        .cp-code{font-weight:750;letter-spacing:.02em;font-size:14.5px;text-transform:uppercase}
        .cp-chips{display:flex;gap:6px;margin-top:5px;flex-wrap:wrap}
        .cp-chip{display:inline-flex;align-items:center;gap:4px;font-size:11.5px;font-weight:650;padding:2px 8px;border-radius:7px;background:#f1f5f9;color:var(--cp-ink2)}
        .cp-chip.blue{background:var(--cp-blue-soft);color:var(--cp-blue-d)}
        .cp-chip.off{background:#fff1f2;color:#e5484d;background:#fef2f2}
        .cp-iconbtn{width:34px;height:34px;border-radius:9px;border:1px solid var(--cp-line);background:#fff;color:var(--cp-mut);
            cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:15px;transition:all .15s}
        .cp-iconbtn:hover{background:#f8fafc;border-color:#dbe3ef;color:var(--cp-ink)}
        .cp-iconbtn.del:hover{background:#fdecec;color:#e5484d;border-color:#f6cccc}

        .cp-add{display:flex;align-items:center;justify-content:center;gap:10px;width:100%;
            background:linear-gradient(180deg,#fff,#f6f9ff);border:1.5px dashed #c3d3ee;border-radius:var(--cp-r);
            padding:15px;cursor:pointer;color:var(--cp-blue-d);font-weight:700;font-size:14px;transition:all .18s}
        .cp-add:hover{border-color:var(--cp-blue);background:var(--cp-blue-soft);transform:translateY(-1px);
            box-shadow:0 14px 30px -20px rgba(37,99,235,.5)}
        .cp-add .plus{width:26px;height:26px;border-radius:8px;background:var(--cp-blue);color:#fff;display:flex;align-items:center;justify-content:center;font-size:16px;line-height:1}

        /* ---- wizard modal ---- */
        .cp-bg{position:fixed;inset:0;background:rgba(11,18,32,.55);backdrop-filter:blur(3px);display:flex;
            align-items:center;justify-content:center;z-index:80;padding:18px}
        .cp-modal{background:#fff;border-radius:20px;width:100%;max-width:430px;box-shadow:0 30px 80px -18px rgba(0,0,0,.5);
            overflow:hidden;display:flex;flex-direction:column;max-height:92vh}
        .cp-mhead{padding:18px 20px 14px;display:flex;align-items:flex-start;gap:12px}
        .cp-mtitle{font-size:17px;font-weight:750;letter-spacing:-.01em}
        .cp-mstep{font-size:12px;color:var(--cp-mut);margin-top:2px;font-weight:600}
        .cp-mbody{padding:2px 20px 8px;overflow-y:auto}
        .cp-mfoot{padding:14px 20px 18px;display:flex;align-items:center;justify-content:space-between;gap:12px;border-top:1px solid var(--cp-line2)}

        /* active toggle */
        .cp-sw{width:44px;height:25px;border-radius:999px;background:#d2d8e3;position:relative;cursor:pointer;border:none;padding:0;flex:none;transition:background .18s}
        .cp-sw b{position:absolute;top:2px;left:2px;width:21px;height:21px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(16,24,40,.3);transition:left .18s}
        .cp-sw.on{background:var(--cp-blue)} .cp-sw.on b{left:21px}
        .cp-sw.sm{width:38px;height:22px} .cp-sw.sm b{width:18px;height:18px} .cp-sw.sm.on b{left:18px}
        .cp-sw.g.on{background:var(--cp-green)}

        /* stepper */
        .cp-steps{display:flex;align-items:center;padding:4px 20px 14px}
        .cp-st{display:flex;flex-direction:column;align-items:center;gap:5px;flex:none;width:34px}
        .cp-st .dot{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;
            font-size:12.5px;font-weight:700;background:#eef2f7;color:var(--cp-faint);transition:all .2s}
        .cp-st.on .dot{background:var(--cp-blue);color:#fff;box-shadow:0 6px 14px -6px rgba(37,99,235,.7)}
        .cp-st.done .dot{background:var(--cp-blue);color:#fff}
        .cp-st .lb{font-size:9px;font-weight:650;color:var(--cp-faint);letter-spacing:.01em;white-space:nowrap}
        .cp-st.on .lb,.cp-st.done .lb{color:var(--cp-blue-d)}
        .cp-bar{height:2px;flex:1;background:#e6ebf3;margin:0 -3px;position:relative;top:-16px;border-radius:2px}
        .cp-bar.fill{background:var(--cp-blue)}

        /* form bits */
        .cp-lbl{display:block;font-size:12px;font-weight:650;color:var(--cp-ink2);margin-bottom:7px}
        .cp-lbl .opt{color:var(--cp-faint);font-weight:500}
        .cp-in{width:100%;border:1.5px solid var(--cp-line);border-radius:10px;padding:11px 12px;font-size:14px;color:var(--cp-ink);
            background:#fff;outline:none;transition:border-color .15s,box-shadow .15s;font-family:inherit}
        .cp-in:hover{border-color:#d5dce7}
        .cp-in:focus{border-color:var(--cp-blue);box-shadow:0 0 0 4px rgba(37,99,235,.12)}
        .cp-in.big{font-size:17px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;padding:14px}
        .cp-hint{font-size:11.5px;color:var(--cp-faint);margin-top:6px}
        .cp-err{color:#e5484d;font-size:11.5px;margin-top:6px}
        .cp-grid2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
        .cp-grid3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px}
        .cp-money{position:relative}.cp-money .cur{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--cp-mut);font-weight:600}
        .cp-money .cp-in{padding-left:26px}
        .cp-suf{position:relative}.cp-suf .s{position:absolute;right:12px;top:50%;transform:translateY(-50%);color:var(--cp-mut);font-weight:600}

        /* choice cards */
        .cp-choice{display:grid;grid-template-columns:1fr 1fr;gap:10px}
        .cp-opt{display:flex;gap:11px;align-items:flex-start;padding:14px;border-radius:12px;cursor:pointer;border:1.5px solid var(--cp-line);background:#fff;transition:all .15s}
        .cp-opt:hover{border-color:#c9d6ea}
        .cp-opt.sel{border-color:var(--cp-blue);background:var(--cp-blue-soft);box-shadow:0 8px 20px -16px rgba(37,99,235,.7)}
        .cp-opt .ic{width:34px;height:34px;border-radius:9px;background:#eef2f7;color:var(--cp-mut);display:flex;align-items:center;justify-content:center;font-size:16px;flex:none}
        .cp-opt.sel .ic{background:#dbe7ff;color:var(--cp-blue)}
        .cp-opt-t{font-weight:680;font-size:13.5px}
        .cp-opt.full{grid-column:1/-1}
        .cp-opt .tick{margin-left:auto;color:var(--cp-blue);font-weight:800}

        .cp-preview{background:var(--cp-blue-soft);border:1px solid #d6e2fb;border-radius:12px;padding:12px 14px;margin-top:14px}
        .cp-preview .k{font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--cp-blue-d)}
        .cp-preview .v{font-weight:700;margin-top:3px}

        /* restriction toggle rows */
        .cp-rrow{display:flex;align-items:center;gap:13px;background:#fff;border:1px solid var(--cp-line);border-radius:12px;padding:14px 15px;margin-bottom:10px}
        .cp-rrow .ic{width:36px;height:36px;border-radius:10px;background:var(--cp-blue-soft);color:var(--cp-blue);display:flex;align-items:center;justify-content:center;font-size:16px;flex:none}
        .cp-tokens{display:flex;flex-wrap:wrap;gap:7px;margin-top:9px}
        .cp-token{display:inline-flex;align-items:center;gap:6px;background:#f1f5f9;border-radius:8px;padding:5px 9px;font-size:12.5px;font-weight:600}
        .cp-token button{border:none;background:none;color:#94a3b8;cursor:pointer;font-size:13px;line-height:1}
        .cp-tokrow{display:flex;gap:8px;margin-top:9px}

        /* review card */
        .cp-rev{border-radius:16px;padding:18px;color:#fff;background:linear-gradient(135deg,#2f4bd8,#2036a8)}
        .cp-rev-code{font-size:20px;font-weight:800;letter-spacing:.03em;text-transform:uppercase}
        .cp-rev-off{opacity:.92;margin-top:2px;font-weight:600}
        .cp-rev-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:15px}
        .cp-rev-cell{background:rgba(255,255,255,.12);border-radius:11px;padding:11px 12px}
        .cp-rev-k{font-size:10.5px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;opacity:.8}
        .cp-rev-v{font-weight:700;margin-top:3px}

        /* buttons */
        .cp-btn{display:inline-flex;align-items:center;gap:7px;border-radius:10px;font-weight:700;font-size:13.5px;padding:11px 18px;cursor:pointer;border:1px solid var(--cp-line);background:#fff;color:var(--cp-ink2);transition:all .16s}
        .cp-btn:hover{background:#f8fafc;border-color:#dbe3ef}
        .cp-btn.ghost{border:none;background:none;color:var(--cp-mut);padding-left:6px}
        .cp-btn.pri{background:linear-gradient(135deg,#3b82f6,#2563eb);border-color:transparent;color:#fff;box-shadow:0 12px 24px -12px rgba(37,99,235,.75)}
        .cp-btn.pri:hover{filter:brightness(1.05)}
        .cp-btn:disabled{opacity:.5;cursor:not-allowed}
    </style>

    {{-- ── Coupon list ── --}}
    <div class="cp-list">
        <template x-if="state.coupons.length===0">
            <div class="cp-empty">
                <span class="cp-ic">🎟</span>
                <div>
                    <div class="cp-ttl">No coupons yet</div>
                    <div class="cp-sub">Offer discounts to boost ticket sales and reward loyal customers. Buyers enter the code at checkout.</div>
                </div>
            </div>
        </template>

        <template x-for="(c,i) in state.coupons" :key="c.key">
            <div class="cp-coup">
                <span class="cp-tag">🏷</span>
                <div class="cp-coup-main">
                    <div class="cp-code" x-text="c.code || 'UNNAMED'"></div>
                    <div class="cp-chips">
                        <span class="cp-chip blue" x-text="summary(c)"></span>
                        <span class="cp-chip" x-show="c.expiresAt" x-text="'Expires ' + fmtDate(c.expiresAt)"></span>
                        <span class="cp-chip" x-text="c.eligibility==='phones' ? 'Selected numbers' : 'Everyone'"></span>
                        <span class="cp-chip off" x-show="!c.active">Inactive</span>
                    </div>
                </div>
                <button type="button" class="cp-iconbtn" @click="openEdit(i)" title="Edit">✎</button>
                <button type="button" class="cp-iconbtn del" @click="remove(i)" title="Remove">🗑</button>
            </div>
        </template>

        <button type="button" class="cp-add" @click="openNew()"><span class="plus">+</span> Create Coupon</button>
    </div>

    {{-- ── Wizard modal ── --}}
    <template x-if="draft">
        <div class="cp-bg" @click.self="cancel()">
            <div class="cp-modal">
                <div class="cp-mhead">
                    <div style="flex:1">
                        <div class="cp-mtitle" x-text="editing>=0 ? 'Edit Coupon' : 'Create Coupon'"></div>
                        <div class="cp-mstep" x-text="'Step ' + step + ' of 6'"></div>
                    </div>
                    <label style="display:flex;align-items:center;gap:8px;font-size:12px;font-weight:650;color:var(--cp-mut)">
                        <span x-text="draft.active ? 'Active' : 'Inactive'"></span>
                        <button type="button" class="cp-sw" :class="{on:draft.active}" @click="draft.active=!draft.active"><b></b></button>
                    </label>
                </div>

                {{-- stepper --}}
                <div class="cp-steps">
                    <template x-for="(lbl,si) in stepLabels" :key="si">
                        <div style="display:flex;align-items:center;flex:none" :style="si<5 ? 'flex:1' : ''">
                            <div class="cp-st" :class="{on:step===si+1, done:step>si+1}">
                                <div class="dot" x-text="step>si+1 ? '✓' : (si+1)"></div>
                                <div class="lb" x-text="lbl"></div>
                            </div>
                            <div class="cp-bar" :class="{fill:step>si+1}" x-show="si<5"></div>
                        </div>
                    </template>
                </div>

                <div class="cp-mbody">
                    {{-- Step 1: Code --}}
                    <div x-show="step===1">
                        <label class="cp-lbl">Coupon Code</label>
                        <div class="cp-hint" style="margin:-2px 0 8px">The code customers will enter at checkout</div>
                        <input class="cp-in big" type="text" x-model="draft.code" placeholder="e.g. WELCOME20" @input="draft.code=draft.code.toUpperCase().replace(/[^A-Z0-9]/g,'')">
                        <div class="cp-hint">Letters and numbers only. Keep it short and memorable.</div>
                        <div class="cp-err" x-show="step1Tried && !draft.code">Enter a code.</div>
                    </div>

                    {{-- Step 2: Discount --}}
                    <div x-show="step===2">
                        <label class="cp-lbl">Discount Type</label>
                        <div class="cp-choice" style="margin-bottom:14px">
                            <div class="cp-opt" :class="{sel:draft.type==='percent'}" @click="draft.type='percent'">
                                <span class="ic">%</span><div><div class="cp-opt-t">Percentage</div><div class="cp-sub">e.g. 20% off</div></div>
                            </div>
                            <div class="cp-opt" :class="{sel:draft.type==='fixed'}" @click="draft.type='fixed'">
                                <span class="ic">₹</span><div><div class="cp-opt-t">Fixed Amount</div><div class="cp-sub">e.g. ₹100 off</div></div>
                            </div>
                        </div>
                        <div class="cp-grid3">
                            <div>
                                <label class="cp-lbl" x-text="draft.type==='percent' ? 'Discount %' : 'Amount (₹)'"></label>
                                <div class="cp-suf" x-show="draft.type==='percent'"><span class="s">%</span><input class="cp-in" type="number" min="0" x-model.number="draft.discount"></div>
                                <div class="cp-money" x-show="draft.type==='fixed'"><span class="cur">₹</span><input class="cp-in" type="number" min="0" x-model.number="draft.discount"></div>
                            </div>
                            <div x-show="draft.type==='percent'">
                                <label class="cp-lbl">Max Discount Cap</label>
                                <div class="cp-money"><span class="cur">₹</span><input class="cp-in" type="number" min="0" x-model.number="draft.maxCap" placeholder="—"></div>
                            </div>
                            <div>
                                <label class="cp-lbl">Min Order Value</label>
                                <div class="cp-money"><span class="cur">₹</span><input class="cp-in" type="number" min="0" x-model.number="draft.minOrder" placeholder="0"></div>
                            </div>
                        </div>
                        <div class="cp-preview">
                            <div class="k">Preview</div>
                            <div class="v" x-text="summary(draft)"></div>
                        </div>
                    </div>

                    {{-- Step 3: Eligibility --}}
                    <div x-show="step===3">
                        <label class="cp-lbl">Who can use this coupon?</label>
                        <div class="cp-hint" style="margin:-2px 0 10px">Select who is eligible to redeem this code</div>
                        <div class="cp-choice" style="grid-template-columns:1fr">
                            <div class="cp-opt full" :class="{sel:draft.eligibility==='all'}" @click="draft.eligibility='all'">
                                <span class="ic">🌐</span><div style="flex:1"><div class="cp-opt-t">Everyone</div><div class="cp-sub">Anyone with the code</div></div>
                                <span class="tick" x-show="draft.eligibility==='all'">✓</span>
                            </div>
                            <div class="cp-opt full" :class="{sel:draft.eligibility==='phones'}" @click="draft.eligibility='phones'">
                                <span class="ic">📞</span><div style="flex:1"><div class="cp-opt-t">Phone Numbers</div><div class="cp-sub">Specific phone numbers</div></div>
                                <span class="tick" x-show="draft.eligibility==='phones'">✓</span>
                            </div>
                        </div>
                        <div x-show="draft.eligibility==='phones'" style="margin-top:12px">
                            <div class="cp-tokrow">
                                <input class="cp-in" type="tel" x-model="phoneInput" placeholder="Add a phone number" @keydown.enter.prevent="addPhone()">
                                <button type="button" class="cp-btn" @click="addPhone()">Add</button>
                            </div>
                            <div class="cp-tokens">
                                <template x-for="(p,pi) in draft.phones" :key="pi"><span class="cp-token" x-text="p"><button type="button" @click="draft.phones.splice(pi,1)">✕</button></span></template>
                            </div>
                            <div class="cp-hint">Coming soon: enforced at checkout. Saved with the coupon now.</div>
                        </div>
                    </div>

                    {{-- Step 4: Limits --}}
                    <div x-show="step===4">
                        <div class="cp-grid2">
                            <div>
                                <label class="cp-lbl">Usage Limit (per customer)</label>
                                <select class="cp-in" x-model="draft.perCustomer">
                                    <option value="">Unlimited</option>
                                    <option value="1">1 per customer</option>
                                    <option value="2">2 per customer</option>
                                    <option value="3">3 per customer</option>
                                    <option value="5">5 per customer</option>
                                </select>
                            </div>
                            <div>
                                <label class="cp-lbl">Expiry Date</label>
                                <input class="cp-in" type="date" x-model="draft.expiresAt">
                            </div>
                        </div>
                        <div class="cp-rrow" style="margin-top:14px">
                            <span class="ic">🧾</span>
                            <div style="flex:1"><div class="cp-opt-t">Allow Multiple Bookings</div><div class="cp-sub">Apply to multiple events in one order</div></div>
                            <button type="button" class="cp-sw sm" :class="{on:draft.multiEvent}" @click="draft.multiEvent=!draft.multiEvent"><b></b></button>
                        </div>
                    </div>

                    {{-- Step 5: Restrictions --}}
                    <div x-show="step===5">
                        <label class="cp-lbl">Date &amp; Time Restrictions</label>
                        <div class="cp-hint" style="margin:-2px 0 12px">Optionally limit this coupon to specific dates or timeslots</div>
                        <div class="cp-rrow">
                            <span class="ic">📅</span>
                            <div style="flex:1"><div class="cp-opt-t">Restrict to specific dates</div><div class="cp-sub">Only valid on selected dates</div></div>
                            <button type="button" class="cp-sw sm" :class="{on:draft.restrictDates}" @click="draft.restrictDates=!draft.restrictDates"><b></b></button>
                        </div>
                        <div x-show="draft.restrictDates" style="margin:-4px 0 12px">
                            <div class="cp-tokrow"><input class="cp-in" type="date" x-model="dateInput"><button type="button" class="cp-btn" @click="addDate()">Add date</button></div>
                            <div class="cp-tokens"><template x-for="(d,di) in draft.dates" :key="di"><span class="cp-token" x-text="fmtDate(d)"><button type="button" @click="draft.dates.splice(di,1)">✕</button></span></template></div>
                        </div>
                        <div class="cp-rrow">
                            <span class="ic">🕐</span>
                            <div style="flex:1"><div class="cp-opt-t">Restrict to specific timeslots</div><div class="cp-sub">Only valid for selected times</div></div>
                            <button type="button" class="cp-sw sm" :class="{on:draft.restrictTimes}" @click="draft.restrictTimes=!draft.restrictTimes"><b></b></button>
                        </div>
                        <div x-show="draft.restrictTimes">
                            <div class="cp-tokrow"><input class="cp-in" type="time" x-model="timeInput"><button type="button" class="cp-btn" @click="addTime()">Add time</button></div>
                            <div class="cp-tokens"><template x-for="(t,ti) in draft.times" :key="ti"><span class="cp-token" x-text="t"><button type="button" @click="draft.times.splice(ti,1)">✕</button></span></template></div>
                        </div>
                        <div class="cp-hint" style="margin-top:6px">Coming soon: enforced at checkout. Saved with the coupon now.</div>
                    </div>

                    {{-- Step 6: Review --}}
                    <div x-show="step===6">
                        <div class="cp-lbl" style="font-size:14px">Review &amp; Confirm</div>
                        <div class="cp-hint" style="margin:-3px 0 12px">Verify your coupon settings before saving</div>
                        <div class="cp-rev">
                            <div class="cp-rev-code" x-text="draft.code || 'CODE'"></div>
                            <div class="cp-rev-off" x-text="summary(draft)"></div>
                            <div class="cp-rev-grid">
                                <div class="cp-rev-cell"><div class="cp-rev-k">Eligibility</div><div class="cp-rev-v" x-text="draft.eligibility==='phones' ? 'Selected numbers' : 'Everyone'"></div></div>
                                <div class="cp-rev-cell"><div class="cp-rev-k">Expires</div><div class="cp-rev-v" x-text="draft.expiresAt ? fmtDate(draft.expiresAt) : 'Never'"></div></div>
                                <div class="cp-rev-cell"><div class="cp-rev-k">Usage Limit</div><div class="cp-rev-v" x-text="draft.perCustomer ? (draft.perCustomer + ' / customer') : 'Unlimited'"></div></div>
                                <div class="cp-rev-cell"><div class="cp-rev-k">Min Order</div><div class="cp-rev-v" x-text="draft.minOrder>0 ? ('₹'+draft.minOrder) : 'None'"></div></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="cp-mfoot">
                    <button type="button" class="cp-btn ghost" @click="step===1 ? cancel() : back()" x-text="step===1 ? '‹ Cancel' : '‹ Back'"></button>
                    <button type="button" class="cp-btn pri" x-show="step<6" @click="next()">Next ›</button>
                    <button type="button" class="cp-btn pri" x-show="step===6" @click="saveDraft()">✓ <span x-text="editing>=0 ? 'Save Coupon' : 'Create Coupon'"></span></button>
                </div>
            </div>
        </div>
    </template>
</div>

<script>
    function couponStudio(state) {
        return {
            state,
            stepLabels: ['Code', 'Discount', 'Eligibility', 'Limits', 'Restrictions', 'Review'],
            draft: null,
            editing: -1,
            step: 1,
            step1Tried: false,
            phoneInput: '', dateInput: '', timeInput: '',
            init() {
                if (!this.state || typeof this.state !== 'object') this.state = { coupons: [] };
                if (!Array.isArray(this.state.coupons)) this.state.coupons = [];
                this.state.coupons.forEach(c => { if (!c.key) c.key = this.uid(); });
            },
            uid() { return (crypto.randomUUID ? crypto.randomUUID().replace(/-/g, '') : Math.random().toString(16).slice(2)).slice(0, 12); },
            blank() {
                return { key: this.uid(), id: null, active: true, code: '', type: 'percent', discount: 10, maxCap: null,
                    minOrder: 0, eligibility: 'all', phones: [], perCustomer: '', expiresAt: '', multiEvent: false,
                    restrictDates: false, dates: [], restrictTimes: false, times: [] };
            },
            openNew() { this.draft = this.blank(); this.editing = -1; this.step = 1; this.step1Tried = false; },
            openEdit(i) { this.draft = JSON.parse(JSON.stringify(this.state.coupons[i])); this.editing = i; this.step = 1; this.step1Tried = false; },
            cancel() { this.draft = null; },
            remove(i) { this.state.coupons.splice(i, 1); },
            next() {
                if (this.step === 1) { this.step1Tried = true; if (!this.draft.code) return; }
                if (this.step < 6) this.step++;
            },
            back() { if (this.step > 1) this.step--; },
            saveDraft() {
                if (!this.draft.code) { this.step = 1; this.step1Tried = true; return; }
                const clean = JSON.parse(JSON.stringify(this.draft));
                if (this.editing >= 0) this.state.coupons.splice(this.editing, 1, clean);
                else this.state.coupons.push(clean);
                this.draft = null;
            },
            addPhone() { const v = (this.phoneInput || '').trim(); if (v) { this.draft.phones.push(v); this.phoneInput = ''; } },
            addDate() { const v = (this.dateInput || '').trim(); if (v && !this.draft.dates.includes(v)) { this.draft.dates.push(v); this.dateInput = ''; } },
            addTime() { const v = (this.timeInput || '').trim(); if (v && !this.draft.times.includes(v)) { this.draft.times.push(v); this.timeInput = ''; } },
            summary(c) {
                if (!c) return '';
                const cap = (c.type === 'percent' && c.maxCap) ? (' (max ₹' + c.maxCap + ')') : '';
                const base = c.type === 'percent' ? ((Number(c.discount) || 0) + '% off') : ('₹' + (Number(c.discount) || 0) + ' off');
                return base + cap;
            },
            fmtDate(s) {
                if (!s) return '';
                const d = new Date(s + 'T00:00:00');
                if (isNaN(d)) return s;
                return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
            },
        };
    }
</script>
