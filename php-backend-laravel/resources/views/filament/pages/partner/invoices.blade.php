{{-- Partner Invoices: billed-to details → filter → settlements by month with their
     documents → TDS by quarter. Documents open in a new tab, ready to save as PDF. --}}
@php
    $docs = $this->documents();
    $profile = $this->profile();
    $m = fn ($v) => \App\Filament\Pages\Partner\PartnerInvoices::money((float) $v);
    $rows = collect($docs['settlements'])->filter(fn ($s) => $this->filter === 'all' || in_array($this->filter, $s['docs'], true));
    $labels = ['settlement' => 'Statement', 'invoice' => 'Tax invoice', 'tds' => 'TDS certificate', 'csv' => 'Bookings CSV'];
    $partnerId = auth()->user()->effectivePartnerId();
@endphp

<x-filament-panels::page>
    <div class="piv">

        {{-- ---------- Billed to ---------- --}}
        <section class="piv-card">
            <div class="piv-head">
                <div>
                    <span class="piv-eyebrow">Billed to</span>
                    <h2 class="piv-title">{{ $profile?->legal_name ?: 'Your business name' }}</h2>
                </div>
                @if ($this->canEditTax() && ! $this->editingTax)
                    <button type="button" class="piv-btn piv-btn-soft" wire:click="$set('editingTax', true)">{{ $profile?->pan || $profile?->gstin ? 'Edit' : 'Add tax details' }}</button>
                @endif
            </div>

            @if ($this->editingTax)
                <form wire:submit="saveTax" class="piv-form">
                    <label class="piv-field"><span>Legal business name</span>
                        <input class="piv-in" type="text" wire:model="legal_name" value="{{ $legal_name }}" maxlength="160">
                        @error('legal_name')<em>{{ $message }}</em>@enderror
                    </label>
                    <div class="piv-row">
                        <label class="piv-field"><span>GSTIN (if registered)</span>
                            <input class="piv-in piv-up" type="text" wire:model="gstin" value="{{ $gstin }}" maxlength="15" placeholder="37ABCDE1234F1Z5">
                            @error('gstin')<em>{{ $message }}</em>@enderror
                        </label>
                        <label class="piv-field"><span>PAN</span>
                            <input class="piv-in piv-up" type="text" wire:model="pan" value="{{ $pan }}" maxlength="10" placeholder="ABCDE1234F">
                            @error('pan')<em>{{ $message }}</em>@enderror
                        </label>
                    </div>
                    <label class="piv-field"><span>State</span>
                        <select class="piv-in" wire:model="state_code">
                            <option value="">Choose your state</option>
                            @foreach ($this->states() as $code => $name)
                                <option value="{{ $code }}" @selected($state_code === $code)>{{ $name }}</option>
                            @endforeach
                        </select>
                        <small>Taken from your GSTIN when you have one.</small>
                    </label>
                    <label class="piv-field"><span>Billing address</span>
                        <textarea class="piv-in" rows="2" wire:model="address" maxlength="300">{{ $address }}</textarea>
                    </label>
                    <div class="piv-actions">
                        <button type="submit" class="piv-btn piv-btn-primary">Save</button>
                        <button type="button" class="piv-btn piv-btn-ghost" wire:click="$set('editingTax', false)">Cancel</button>
                    </div>
                </form>
            @else
                <dl class="piv-tax">
                    <dt>PAN</dt><dd class="{{ $profile?->pan ? '' : 'is-warn' }}">{{ $profile?->pan ?: 'Not added — TDS is withheld at a higher rate' }}</dd>
                    <dt>GSTIN</dt><dd class="{{ $profile?->gstin ? '' : 'is-faint' }}">{{ $profile?->gstin ?: 'Not registered' }}</dd>
                    @if ($profile?->state_code)<dt>State</dt><dd>{{ \App\Support\GstStates::name($profile->state_code) }}</dd>@endif
                </dl>
                @unless ($this->canEditTax())<p class="piv-note">Only the venue owner can change these.</p>@endunless
            @endif
        </section>

        {{-- ---------- Filter ---------- --}}
        <div class="piv-chips" role="tablist">
            @foreach (['all' => 'All', 'invoice' => 'Tax invoices', 'tds' => 'TDS certificates', 'settlement' => 'Statements'] as $k => $label)
                <button type="button" role="tab" aria-selected="{{ $this->filter === $k ? 'true' : 'false' }}" class="piv-chip {{ $this->filter === $k ? 'is-on' : '' }}" wire:click="setFilter('{{ $k }}')">{{ $label }}</button>
            @endforeach
        </div>

        {{-- ---------- Settlements by month ---------- --}}
        @if (empty($docs['settlements']))
            <section class="piv-card piv-empty">
                <svg viewBox="0 0 92 72" width="92" height="72" aria-hidden="true">
                    <rect x="14" y="8" width="40" height="58" rx="4" transform="rotate(-12 34 66)" fill="#f1f5f9" stroke="#94a3b8" stroke-width="1.6"/>
                    <rect x="38" y="8" width="40" height="58" rx="4" transform="rotate(10 58 66)" fill="#f1f5f9" stroke="#94a3b8" stroke-width="1.6"/>
                    <rect x="26" y="5" width="40" height="60" rx="4" fill="#fff" stroke="#334155" stroke-width="1.6"/>
                    <path d="M33 18h26M33 26h26M33 34h15" stroke="#94a3b8" stroke-width="1.6" stroke-linecap="round"/>
                    <circle cx="53" cy="50" r="7" fill="none" stroke="#2563eb" stroke-width="1.6"/>
                </svg>
                <h3>No invoices yet</h3>
                <p>Every settlement Haraan sends you comes with a statement here — plus a tax invoice for any commission, and a TDS certificate when tax is withheld.</p>
            </section>
        @elseif ($rows->isEmpty())
            <p class="piv-note">{{ $this->filter === 'invoice' ? 'No tax invoices yet. One is issued whenever Haraan keeps a commission from a settlement.' : ($this->filter === 'tds' ? 'No tax has been withheld from your settlements.' : 'Nothing here yet.') }}</p>
        @endif

        @foreach ($rows->groupBy('month') as $month => $list)
            <div class="piv-month">{{ $month }}</div>
            <section class="piv-card piv-list">
                @foreach ($list as $s)
                    @php $tone = $s['status'] === 'Paid' ? 'ok' : ($s['status'] === 'Cancelled' ? 'bad' : 'warn'); $ded = $s['commission'] + $s['commission_gst'] + $s['tds'] + $s['tcs']; @endphp
                    <article class="piv-item">
                        <div class="piv-date"><b>{{ \Illuminate\Support\Carbon::parse($s['date'])->format('j') }}</b><span>{{ \Illuminate\Support\Carbon::parse($s['date'])->format('M') }}</span></div>
                        <div class="piv-body">
                            <div class="piv-line">
                                <strong>
                                    @if ($this->filter === 'invoice'){{ $s['invoice_number'] ?? 'Tax invoice' }}
                                    @elseif ($this->filter === 'tds')TDS {{ $m($s['tds'] + $s['tcs']) }}
                                    @else{{ $m($s['net']) }}@endif
                                </strong>
                                <span class="piv-pill tone-{{ $tone }}">{{ $s['status'] }}</span>
                            </div>
                            <div class="piv-sub">
                                @if ($this->filter === 'invoice')Commission {{ $m($s['commission']) }}@if ($s['commission_gst'] > 0) + GST {{ $m($s['commission_gst']) }}@endif
                                @elseif ($this->filter === 'tds'){{ collect([$s['tds'] > 0 ? '194-O '.$m($s['tds']) : null, $s['tcs'] > 0 ? 'GST TCS '.$m($s['tcs']) : null])->filter()->implode(' · ') }}
                                @else{{ $ded > 0 ? 'of '.$m($s['gross']).' · '.$m($ded).' deducted' : 'Nothing deducted' }}@endif
                                @if ($s['period']) <span class="piv-faint">· {{ $s['period'] }}</span>@endif
                                @if ($s['reference']) <span class="piv-faint">· UTR {{ $s['reference'] }}</span>@endif
                            </div>
                            <div class="piv-docs">
                                @foreach (($this->filter === 'all' ? $s['docs'] : array_intersect([$this->filter], $s['docs'])) as $d)
                                    <a class="piv-doc" href="{{ route('partner.docs.batch', ['id' => $s['id'], 'doc' => $d]) }}" @if ($d !== 'csv') target="_blank" rel="noopener" @endif>
                                        @if ($d === 'csv')
                                            <svg viewBox="0 0 13 16" width="13" height="16" aria-hidden="true"><rect x=".7" y=".7" width="11.6" height="14.6" rx="2" fill="#fff" stroke="currentColor" stroke-width="1.4"/><path d="M.7 5.6h11.6M.7 10.4h11.6M5.5.7v14.6" stroke="currentColor" stroke-width="1"/></svg>
                                        @else
                                            <svg viewBox="0 0 13 16" width="13" height="16" aria-hidden="true"><path d="M.7.7h7l4.6 4.6v10H.7z" fill="#fff" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M7.7.7v4.6h4.6M3 8.8h7M3 12h5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
                                        @endif
                                        {{ $labels[$d] }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </article>
                @endforeach
            </section>
        @endforeach

        {{-- ---------- TDS by quarter ---------- --}}
        @if (! empty($docs['quarters']) && in_array($this->filter, ['all', 'tds'], true))
            <div class="piv-month">TDS by quarter</div>
            <section class="piv-card piv-list">
                @foreach ($docs['quarters'] as $q)
                    <article class="piv-item piv-q">
                        <div class="piv-body">
                            <div class="piv-line"><strong>{{ $q['label'] }}</strong></div>
                            <div class="piv-sub">TDS {{ $m($q['tds']) }}@if ($q['tcs'] > 0) · GST TCS {{ $m($q['tcs']) }}@endif · {{ $q['settlements'] }} {{ \Illuminate\Support\Str::plural('settlement', $q['settlements']) }}</div>
                            <div class="piv-docs">
                                <a class="piv-doc" target="_blank" rel="noopener" href="{{ route('partner.docs.quarter', ['partner' => $partnerId, 'fy' => $q['financial_year'], 'q' => $q['quarter']]) }}">Summary</a>
                                @if ($q['form16a'])
                                    <a class="piv-doc" href="{{ route('partner.docs.form16a', $q['form16a']['id']) }}">Form 16A{{ $q['form16a']['certificate_no'] ? ' · '.$q['form16a']['certificate_no'] : '' }}</a>
                                @else
                                    <span class="piv-faint">Form 16A after the quarter is filed</span>
                                @endif
                            </div>
                        </div>
                    </article>
                @endforeach
            </section>
        @endif
    </div>

    <style>
        .piv{display:flex;flex-direction:column;gap:14px;max-width:820px}
        .piv-card{background:#fff;border:1px solid #ebedf2;border-radius:16px;padding:16px 18px 18px;box-shadow:0 1px 2px rgba(11,18,32,.05)}
        .piv-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px}
        .piv-eyebrow{font-size:11px;font-weight:800;letter-spacing:.09em;text-transform:uppercase;color:#64748b}
        .piv-title{margin:3px 0 0;font-size:17px;font-weight:800;color:#0b1220;letter-spacing:-.01em}
        .piv-tax{display:grid;grid-template-columns:70px 1fr;gap:6px 10px;margin:12px 0 0;font-size:13.5px}
        .piv-tax dt{color:#64748b}
        .piv-tax dd{margin:0;font-weight:600;color:#0b1220;letter-spacing:.02em;font-variant-numeric:tabular-nums}
        .piv-tax dd.is-warn{color:#b45309;font-weight:500;letter-spacing:0}
        .piv-tax dd.is-faint{color:#94a3b8;font-weight:500;letter-spacing:0}
        .piv-note{font-size:12.5px;color:#64748b;margin:10px 2px 0}
        .piv-form{display:flex;flex-direction:column;gap:12px;margin-top:14px}
        .piv-row{display:flex;gap:12px;flex-wrap:wrap}
        .piv-row .piv-field{flex:1;min-width:180px}
        .piv-field{display:flex;flex-direction:column;gap:5px;font-size:12px;font-weight:700;color:#475467}
        .piv-field small{font-weight:500;color:#94a3b8}
        .piv-field em{font-style:normal;font-weight:600;color:#b42318;font-size:11.5px}
        .piv-in{width:100%;font-size:14px;font-weight:500;color:#0b1220;padding:10px 12px;border-radius:11px;border:1px solid #dbe1ea;background:#fff;outline:none}
        .piv-in:focus{border-color:#2f6bff;box-shadow:0 0 0 3px rgba(47,107,255,.15)}
        .piv-up{text-transform:uppercase;letter-spacing:.04em}
        .piv-actions{display:flex;gap:9px}
        .piv-btn{border:0;cursor:pointer;font-size:13.5px;font-weight:700;padding:9px 16px;border-radius:11px}
        .piv-btn-primary{background:#2563eb;color:#fff}
        .piv-btn-soft{background:#eef3fe;color:#2563eb}
        .piv-btn-ghost{background:transparent;color:#475467}
        .piv-chips{display:flex;gap:8px;overflow-x:auto;padding-bottom:2px}
        .piv-chip{flex:none;border:1px solid rgba(15,23,42,.12);background:#fff;color:#334155;font-size:13px;font-weight:600;padding:7px 14px;border-radius:999px;cursor:pointer}
        .piv-chip.is-on{background:#2563eb;border-color:#2563eb;color:#fff}
        .piv-month{font-size:11px;font-weight:800;letter-spacing:.09em;text-transform:uppercase;color:#64748b;margin:6px 2px -4px}
        .piv-list{padding:0}
        .piv-item{display:flex;gap:14px;padding:14px 18px}
        .piv-item + .piv-item{border-top:1px solid #eef0f4}
        .piv-date{width:38px;flex:none;text-align:center;display:flex;flex-direction:column}
        .piv-date b{font-size:19px;line-height:1.1;color:#0b1220;font-variant-numeric:tabular-nums}
        .piv-date span{font-size:11px;font-weight:700;color:#64748b}
        .piv-body{flex:1;min-width:0}
        .piv-line{display:flex;align-items:center;gap:8px}
        .piv-line strong{font-size:15.5px;color:#0b1220;font-variant-numeric:tabular-nums}
        .piv-sub{font-size:12.5px;color:#64748b;margin-top:2px}
        .piv-faint{color:#94a3b8}
        .piv-pill{font-size:11px;font-weight:800;padding:2px 8px;border-radius:999px}
        .piv-pill.tone-ok{color:#0a7d4e;background:#e7f7ef}
        .piv-pill.tone-warn{color:#b06d09;background:#fff6e8}
        .piv-pill.tone-bad{color:#b42318;background:#fee9e7}
        .piv-docs{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px;align-items:center}
        .piv-doc{display:inline-flex;align-items:center;gap:7px;font-size:13px;font-weight:600;color:#2563eb;background:#eef3fe;padding:7px 12px;border-radius:10px;text-decoration:none}
        .piv-doc:hover{background:#e0e9fd}
        .piv-empty{text-align:center;padding:26px 22px}
        .piv-empty h3{margin:10px 0 4px;font-size:15px;font-weight:800;color:#0b1220}
        .piv-empty p{margin:0 auto;max-width:420px;font-size:13px;color:#64748b}
        .dark .piv-card{background:#111827;border-color:#1f2937}
        .dark .piv-title,.dark .piv-line strong,.dark .piv-date b,.dark .piv-tax dd{color:#f1f5f9}
        .dark .piv-chip{background:#111827;color:#cbd5e1;border-color:#334155}
        .dark .piv-doc,.dark .piv-btn-soft{background:rgba(37,99,235,.16);color:#93b4ff}
        .dark .piv-item + .piv-item{border-top-color:#1f2937}
        .dark .piv-in{background:#0b1220;border-color:#334155;color:#f1f5f9}
    </style>
</x-filament-panels::page>
