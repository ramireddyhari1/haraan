{{-- Partner Payouts: balance hero → settlement destination → payout history.
     Self-contained markup + inline CSS (no Vite rebuild), matching the Earnings
     page's fintech-console language. --}}
@php
    $bal = $this->getBalance();
    $account = $this->account();
    $batches = $this->getBatches();
@endphp

<x-filament-panels::page>
    <div class="ppo">

        {{-- ---------- Balance hero ---------- --}}
        <section class="ppo-hero">
            <div class="ppo-hero-bg" aria-hidden="true"></div>
            <div class="ppo-hero-in">
                <span class="ppo-hero-lab">Available to settle</span>
                <div class="ppo-hero-val">{{ $bal['available'] }}</div>

                <div class="ppo-hero-meta">
                    <span class="ppo-chip">
                        <svg viewBox="0 0 24 24" fill="none" width="13" height="13" aria-hidden="true">
                            <rect x="3" y="5" width="18" height="16" rx="3" stroke="currentColor" stroke-width="2"/>
                            <path d="M3 10h18M8 3v4M16 3v4" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round"/>
                        </svg>
                        Next settlement {{ $bal['nextDate'] }}
                    </span>
                    <span class="ppo-chip">
                        <svg viewBox="0 0 24 24" fill="none" width="13" height="13" aria-hidden="true">
                            <path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2.4"
                                stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        {{ $bal['settled'] }} settled to date
                    </span>
                    @if ($bal['hasInFlight'])
                        {{-- Already batched by Haraan but not yet landed — held out of
                             "available" so the same money can't be settled twice. --}}
                        <span class="ppo-chip">
                            <svg viewBox="0 0 24 24" fill="none" width="13" height="13" aria-hidden="true">
                                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/>
                                <path d="M12 7v5l3 2" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            {{ $bal['inFlight'] }} on the way
                        </span>
                    @endif
                </div>
            </div>
        </section>

        {{-- ---------- Settlement destination ---------- --}}
        <section class="ppo-card">
            <div class="ppo-card-head">
                <div>
                    <h2 class="ppo-card-title">Settlement account</h2>
                    <p class="ppo-card-sub">Where Haraan sends your money.</p>
                </div>

                @if ($account && ! $this->editing)
                    <button type="button" class="ppo-btn ppo-btn-ghost" wire:click="startEditing">Change</button>
                @endif
            </div>

            @if ($account && ! $this->editing)
                {{-- Saved destination, numbers masked --}}
                <div class="ppo-acct">
                    <span class="ppo-acct-ic">
                        @if ($account->method === 'upi')
                            <svg viewBox="0 0 24 24" fill="none" width="18" height="18" aria-hidden="true">
                                <path d="M4 12h16M13 5l7 7-7 7" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        @else
                            <svg viewBox="0 0 24 24" fill="none" width="18" height="18" aria-hidden="true">
                                <path d="M3 10l9-5 9 5M5 10v8m14-8v8M3 20h18" stroke="currentColor"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        @endif
                    </span>
                    <span class="ppo-acct-meta">
                        <span class="ppo-acct-line">{{ $account->summaryLine() }}</span>
                        <span class="ppo-acct-sub">
                            {{ $account->account_holder }} · {{ $account->method === 'upi' ? 'UPI' : 'Bank transfer' }}
                        </span>
                    </span>
                    <span class="ppo-pill {{ $account->isVerified() ? 'tone-ok' : 'tone-warn' }}">
                        {{ $account->isVerified() ? 'Verified' : 'Verification pending' }}
                    </span>
                </div>
            @else
                {{-- Editor. The destination fields are never prefilled from a saved
                     account — a change means re-entering where the money goes. --}}
                <form wire:submit.prevent="save" class="ppo-form">
                    <div class="ppo-seg" role="tablist">
                        <button type="button" role="tab" wire:click="$set('method','upi')"
                            class="ppo-seg-btn {{ $this->method === 'upi' ? 'is-on' : '' }}">UPI</button>
                        <button type="button" role="tab" wire:click="$set('method','bank')"
                            class="ppo-seg-btn {{ $this->method === 'bank' ? 'is-on' : '' }}">Bank account</button>
                    </div>

                    <label class="ppo-field">
                        <span class="ppo-lab">Account holder name</span>
                        <input type="text" wire:model="account_holder" value="{{ $this->account_holder }}"
                            class="ppo-in" placeholder="As printed on the account" autocomplete="off">
                        @error('account_holder') <span class="ppo-err">{{ $message }}</span> @enderror
                    </label>

                    @if ($this->method === 'upi')
                        <label class="ppo-field">
                            <span class="ppo-lab">UPI ID</span>
                            <input type="text" wire:model="upi_vpa" value="{{ $this->upi_vpa }}"
                                class="ppo-in" placeholder="name@okhdfc" autocomplete="off" spellcheck="false">
                            @error('upi_vpa') <span class="ppo-err">{{ $message }}</span> @enderror
                        </label>
                    @else
                        <label class="ppo-field">
                            <span class="ppo-lab">Bank name</span>
                            <input type="text" wire:model="bank_name" value="{{ $this->bank_name }}"
                                class="ppo-in" placeholder="HDFC Bank" autocomplete="off">
                            @error('bank_name') <span class="ppo-err">{{ $message }}</span> @enderror
                        </label>

                        <div class="ppo-row">
                            <label class="ppo-field">
                                <span class="ppo-lab">Account number</span>
                                <input type="text" wire:model="account_number" value="{{ $this->account_number }}"
                                    class="ppo-in" inputmode="numeric" autocomplete="off" spellcheck="false">
                                @error('account_number') <span class="ppo-err">{{ $message }}</span> @enderror
                            </label>
                            <label class="ppo-field">
                                <span class="ppo-lab">IFSC code</span>
                                <input type="text" wire:model="ifsc_code" value="{{ $this->ifsc_code }}"
                                    class="ppo-in ppo-in-upper" placeholder="HDFC0001234"
                                    autocomplete="off" spellcheck="false">
                                @error('ifsc_code') <span class="ppo-err">{{ $message }}</span> @enderror
                            </label>
                        </div>
                    @endif

                    <div class="ppo-actions">
                        <button type="submit" class="ppo-btn ppo-btn-primary">
                            <span wire:loading.remove wire:target="save">Save account</span>
                            <span wire:loading wire:target="save">Saving…</span>
                        </button>
                        @if ($account)
                            <button type="button" class="ppo-btn ppo-btn-ghost" wire:click="cancelEditing">Cancel</button>
                        @endif
                    </div>

                    <p class="ppo-note">
                        @if ($account)
                            Re-enter the full destination to confirm the change.
                        @endif
                        Saving clears verification — Haraan re-checks the destination before the next payout.
                    </p>
                </form>
            @endif
        </section>

        {{-- ---------- Payout history ---------- --}}
        <section class="ppo-card">
            <div class="ppo-card-head">
                <div>
                    <h2 class="ppo-card-title">Payout history</h2>
                    <p class="ppo-card-sub">Settlements Haraan has sent you.</p>
                </div>
            </div>

            @forelse ($batches as $b)
                <div class="ppo-row-item">
                    <span class="ppo-row-ic tone-{{ $b['tone'] }}">
                        <svg viewBox="0 0 24 24" fill="none" width="16" height="16" aria-hidden="true">
                            <path d="M12 4v12m0 0l-5-5m5 5l5-5M4 20h16" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <span class="ppo-row-meta">
                        <span class="ppo-row-top">
                            <span class="ppo-row-amt">{{ $b['amount'] }}</span>
                            <span class="ppo-pill tone-{{ $b['tone'] }}">{{ $b['status'] }}</span>
                        </span>
                        <span class="ppo-row-sub">
                            {{ $b['date'] }}
                            @if ($b['period']) · {{ $b['period'] }} @endif
                            @if ($b['reference']) · Ref {{ $b['reference'] }} @endif
                        </span>
                    </span>
                </div>
            @empty
                <div class="ppo-empty">
                    <span class="ppo-empty-ic">
                        <svg viewBox="0 0 24 24" fill="none" width="22" height="22" aria-hidden="true">
                            <rect x="2" y="6" width="20" height="13" rx="3" stroke="currentColor" stroke-width="1.8"/>
                            <path d="M2 11h20" stroke="currentColor" stroke-width="1.8"/>
                        </svg>
                    </span>
                    <p class="ppo-empty-t">No payouts yet</p>
                    <p class="ppo-empty-s">
                        Once your first settlement is sent, every transfer shows up here with its reference.
                    </p>
                </div>
            @endforelse
        </section>
    </div>

    <style>
        .ppo{display:flex;flex-direction:column;gap:16px;}

        /* ---- hero ---- */
        .ppo-hero{position:relative;overflow:hidden;border-radius:20px;
            box-shadow:0 18px 40px -22px rgba(21,71,170,.55);
            animation:ppo-in .45s cubic-bezier(.22,.61,.36,1) both;}
        .ppo-hero-bg{position:absolute;inset:0;
            background:radial-gradient(120% 140% at 0% 0%,#3d9bff 0%,#2563eb 44%,#1a4fd0 100%);}
        .ppo-hero-bg::after{content:"";position:absolute;inset:0;
            background:radial-gradient(80% 120% at 100% 0%,rgba(255,255,255,.22),transparent 60%);}
        .ppo-hero-in{position:relative;padding:20px 22px 22px;color:#fff;}
        .ppo-hero-lab{font-size:12px;font-weight:700;letter-spacing:.09em;text-transform:uppercase;
            color:rgba(255,255,255,.82);}
        .ppo-hero-val{font-size:40px;font-weight:800;letter-spacing:-.035em;line-height:1.05;
            margin:10px 0 14px;font-variant-numeric:tabular-nums;}
        .ppo-hero-meta{display:flex;flex-wrap:wrap;gap:8px;}
        .ppo-chip{display:inline-flex;align-items:center;gap:5px;font-size:12px;font-weight:700;
            padding:4px 10px;border-radius:999px;background:rgba(255,255,255,.18);color:#fff;
            box-shadow:inset 0 0 0 1px rgba(255,255,255,.22);}

        /* ---- cards ---- */
        .ppo-card{background:#fff;border:1px solid #ebedf2;border-radius:16px;padding:16px 18px 18px;
            box-shadow:0 1px 2px rgba(11,18,32,.05);
            animation:ppo-in .45s cubic-bezier(.22,.61,.36,1) both;animation-delay:.06s;}
        .ppo-card-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;
            margin-bottom:14px;}
        .ppo-card-title{font-size:15px;font-weight:800;color:#0b1220;letter-spacing:-.01em;margin:0;}
        .ppo-card-sub{font-size:12.5px;color:#98a2b3;margin:3px 0 0;}

        /* ---- saved account ---- */
        .ppo-acct{display:flex;align-items:center;gap:12px;padding:13px 14px;border-radius:13px;
            background:#f6f8fc;box-shadow:inset 0 0 0 1px #e9edf4;}
        .ppo-acct-ic{display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;
            border-radius:11px;flex:none;color:#2563eb;background:linear-gradient(180deg,#eef4ff,#dbe8ff);
            box-shadow:inset 0 0 0 1px rgba(37,99,235,.16);}
        .ppo-acct-meta{display:flex;flex-direction:column;min-width:0;flex:1;}
        .ppo-acct-line{font-size:14.5px;font-weight:700;color:#0b1220;letter-spacing:.01em;
            font-variant-numeric:tabular-nums;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
        .ppo-acct-sub{font-size:12px;color:#7a8394;margin-top:2px;
            overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}

        /* ---- pills ---- */
        .ppo-pill{display:inline-flex;align-items:center;font-size:11px;font-weight:800;flex:none;
            padding:3px 9px;border-radius:999px;letter-spacing:.02em;}
        .ppo-pill.tone-ok{color:#0a7d4e;background:#e7f7ef;box-shadow:inset 0 0 0 1px rgba(16,140,86,.18);}
        .ppo-pill.tone-warn{color:#b06d09;background:#fff6e8;box-shadow:inset 0 0 0 1px rgba(194,121,10,.18);}
        .ppo-pill.tone-bad{color:#b42318;background:#fee9e7;box-shadow:inset 0 0 0 1px rgba(180,35,24,.18);}

        /* ---- form ---- */
        .ppo-form{display:flex;flex-direction:column;gap:13px;}
        .ppo-seg{display:inline-flex;padding:3px;border-radius:11px;background:#f1f4f9;
            box-shadow:inset 0 0 0 1px #e5e9f1;align-self:flex-start;}
        .ppo-seg-btn{border:0;background:transparent;cursor:pointer;font-size:13px;font-weight:700;
            color:#667085;padding:7px 15px;border-radius:9px;transition:background .15s,color .15s;}
        .ppo-seg-btn.is-on{background:#fff;color:#1e50e6;box-shadow:0 1px 2px rgba(11,18,32,.09);}
        .ppo-field{display:flex;flex-direction:column;gap:5px;flex:1;min-width:0;}
        .ppo-lab{font-size:12px;font-weight:700;color:#475467;}
        .ppo-in{width:100%;font-size:14px;color:#0b1220;padding:10px 12px;border-radius:11px;
            border:1px solid #dbe1ea;background:#fff;outline:none;transition:border-color .15s,box-shadow .15s;}
        .ppo-in:focus{border-color:#2f6bff;box-shadow:0 0 0 3px rgba(47,107,255,.15);}
        .ppo-in-upper{text-transform:uppercase;}
        .ppo-row{display:flex;gap:12px;}
        .ppo-err{font-size:11.5px;font-weight:600;color:#b42318;}
        .ppo-actions{display:flex;gap:9px;align-items:center;margin-top:2px;}
        .ppo-btn{border:0;cursor:pointer;font-size:13.5px;font-weight:700;padding:10px 18px;
            border-radius:11px;transition:filter .15s,background .15s,transform .05s;}
        .ppo-btn:active{transform:translateY(1px);}
        .ppo-btn-primary{background:linear-gradient(180deg,#2f6bff,#1e50e6);color:#fff;
            box-shadow:0 8px 18px -8px rgba(37,99,235,.6);}
        .ppo-btn-primary:hover{filter:brightness(1.06);}
        .ppo-btn-ghost{background:#f1f4f9;color:#475467;box-shadow:inset 0 0 0 1px #e5e9f1;}
        .ppo-btn-ghost:hover{background:#e7ebf2;}
        .ppo-note{font-size:11.5px;color:#98a2b3;margin:0;line-height:1.45;}

        /* ---- history rows ---- */
        .ppo-row-item{display:flex;align-items:center;gap:12px;padding:12px 4px;
            border-top:1px solid #f0f2f6;}
        .ppo-row-item:first-of-type{border-top:0;padding-top:2px;}
        .ppo-row-ic{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;
            border-radius:10px;flex:none;}
        .ppo-row-ic.tone-ok{color:#0a7d4e;background:#e7f7ef;}
        .ppo-row-ic.tone-warn{color:#b06d09;background:#fff6e8;}
        .ppo-row-ic.tone-bad{color:#b42318;background:#fee9e7;}
        .ppo-row-meta{display:flex;flex-direction:column;min-width:0;flex:1;}
        .ppo-row-top{display:flex;align-items:center;gap:9px;}
        .ppo-row-amt{font-size:15px;font-weight:800;color:#0b1220;font-variant-numeric:tabular-nums;}
        .ppo-row-sub{font-size:12px;color:#98a2b3;margin-top:2px;
            overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}

        /* ---- empty ---- */
        .ppo-empty{text-align:center;padding:22px 12px 8px;}
        .ppo-empty-ic{display:inline-flex;align-items:center;justify-content:center;width:46px;height:46px;
            border-radius:14px;color:#98a2b3;background:#f4f6fa;box-shadow:inset 0 0 0 1px #e9edf4;}
        .ppo-empty-t{font-size:14px;font-weight:800;color:#0b1220;margin:11px 0 0;}
        .ppo-empty-s{font-size:12.5px;color:#98a2b3;margin:4px auto 0;max-width:34ch;line-height:1.5;}

        @keyframes ppo-in{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
        @media (prefers-reduced-motion:reduce){.ppo-hero,.ppo-card{animation:none;}}
        @media (max-width:520px){
            .ppo-hero-val{font-size:34px;}
            .ppo-row{flex-direction:column;gap:13px;}
            .ppo-card-head{flex-direction:column;}
        }
    </style>
</x-filament-panels::page>
