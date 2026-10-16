{{-- A to-scale, top-down plan of a court: white lines on the playing surface,
     dimensions in ink outside it. plan: 'box' | 'badminton' | 'football'.
     Used by the city venue pages and Pulse; the styles ship once per page. --}}
@php $plan = $plan ?? 'box'; @endphp
@once
<style>
    .kv-plan {
        --plan-ink: #0F172A; --plan-ink-2: #334155; --plan-ink-3: #64748B; --plan-rule: #E3E8EF;
        --turf: #3F7D4E; --turf-2: #4A8B5A; --court: #2F6E5A;
    }
    /* The plan: a scaled, top-down drawing of the court, white lines on the
       playing surface, dimensions in ink outside it — like a groundsman's sheet. */
    .kv-plan { margin: 0; }
    .kv-plan__sheet {
        position: relative; background: #fff; border: 1px solid var(--plan-rule); border-radius: 14px;
        padding: 18px 18px 14px;
        box-shadow: 0 1px 1px rgba(15, 23, 42, .04), 0 10px 28px -12px rgba(15, 23, 42, .18);
    }
    .kv-plan svg { display: block; width: 100%; height: auto; }
    .kv-plan .surface { fill: var(--turf); }
    .kv-plan .surface--court { fill: var(--court); }
    .kv-plan .run { fill: var(--turf-2); }
    .kv-plan .mow { fill: #fff; opacity: .055; }
    .kv-plan .line { fill: none; stroke: #fff; stroke-width: 2; stroke-linecap: square; }
    .kv-plan .net { fill: none; stroke: rgba(15, 23, 42, .6); stroke-width: 2; stroke-dasharray: 4 3; }
    .kv-plan .dim { fill: none; stroke: var(--plan-ink-3); stroke-width: 1; }
    .kv-plan .dim-txt { fill: var(--plan-ink-2); font: 600 11px 'Plus Jakarta Sans', system-ui, sans-serif; }
    .kv-plan .note { fill: var(--plan-ink-3); font: 500 10.5px 'Plus Jakarta Sans', system-ui, sans-serif; }
    .kv-plan .stump { fill: #fff; }
    .kv-plan figcaption { display: flex; justify-content: space-between; gap: 12px; margin-top: 10px; font-size: 12.5px; color: var(--plan-ink-3); }
    .kv-plan figcaption b { color: var(--plan-ink); font-weight: 700; }
    @media (prefers-reduced-motion: no-preference) {
        .kv-plan .line { stroke-dasharray: 1200; stroke-dashoffset: 1200; animation: kv-draw 1.1s cubic-bezier(.3,.7,.2,1) .15s forwards; }

        @keyframes kv-draw { to { stroke-dashoffset: 0; } }
        .kv-plan .net { stroke-dasharray: 4 3; stroke-dashoffset: 0; animation: kv-fade .4s ease .9s both; }
        @keyframes kv-fade { from { opacity: 0; } }
    }
    @media (max-width: 600px) {
        .kv-plan__sheet { padding: 12px 10px 10px; }
        .kv-plan figcaption { flex-direction: column; gap: 2px; padding: 0 4px; }
    }
</style>
@endonce
<figure class="kv-plan" aria-label="Plan of a {{ $plan === 'box' ? 'box cricket arena' : ($plan === 'badminton' ? 'badminton court' : 'five-a-side football pitch') }}">
    <div class="kv-plan__sheet">
        @if($plan === 'badminton')
            {{-- BWF doubles court, to scale: 13.40 × 6.10 m (26.87 px/m). --}}
            <svg viewBox="0 0 440 262" role="img" aria-hidden="true">
                <rect class="surface surface--court" x="28" y="42" width="384" height="196" rx="2"/>
                <g class="line">
                    <rect x="40" y="56" width="360" height="164"/>
                    <line x1="40" y1="68.4" x2="400" y2="68.4"/>
                    <line x1="40" y1="207.6" x2="400" y2="207.6"/>
                    <line x1="60.4" y1="56" x2="60.4" y2="220"/>
                    <line x1="379.6" y1="56" x2="379.6" y2="220"/>
                    <line x1="166.8" y1="56" x2="166.8" y2="220"/>
                    <line x1="273.2" y1="56" x2="273.2" y2="220"/>
                    <line x1="40" y1="138" x2="166.8" y2="138"/>
                    <line x1="273.2" y1="138" x2="400" y2="138"/>
                </g>
                <line class="net" x1="220" y1="48" x2="220" y2="228"/>
                <g class="dim">
                    <path d="M40 24v10M400 24v10M40 29h360"/>
                    <path d="M418 56h10M418 220h10M423 56v164"/>
                    <path d="M166.8 250v-8M220 250v-8M168 247h50"/>
                </g>
                <text class="dim-txt" x="220" y="20" text-anchor="middle">13.40 m</text>
                <text class="dim-txt" x="432" y="142" text-anchor="middle" transform="rotate(90 432 142)">6.10 m doubles</text>
                <text class="note" x="193" y="259" text-anchor="middle">1.98 m</text>
                <text class="note" x="232" y="251">net 1.55 m</text>
            </svg>
            <figcaption><span><b>Badminton court</b> · doubles lines shown</span><span>BWF size, to scale</span></figcaption>
        @elseif($plan === 'football')
            {{-- Five-a-side: sizes vary by ground; drawn at 36 × 20 m. --}}
            <svg viewBox="0 0 452 262" role="img" aria-hidden="true">
                <rect class="surface" x="22" y="40" width="396" height="198" rx="3"/>
                <rect class="mow" x="22" y="40" width="36" height="198"/><rect class="mow" x="94" y="40" width="36" height="198"/><rect class="mow" x="166" y="40" width="36" height="198"/><rect class="mow" x="238" y="40" width="36" height="198"/><rect class="mow" x="310" y="40" width="36" height="198"/><rect class="mow" x="382" y="40" width="36" height="198"/>
                <g class="line">
                    <rect x="40" y="52" width="360" height="174" rx="1"/>
                    <line x1="220" y1="52" x2="220" y2="226"/>
                    <circle cx="220" cy="139" r="27"/>
                    <path d="M40 94a48 48 0 0 1 0 90"/>
                    <path d="M400 94a48 48 0 0 0 0 90"/>
                    <rect x="31" y="121" width="9" height="36"/>
                    <rect x="400" y="121" width="9" height="36"/>
                </g>
                <circle cx="220" cy="139" r="2.5" fill="#fff"/>
                <g class="dim">
                    <path d="M40 22v10M400 22v10M40 27h360"/>
                    <path d="M424 52h8M424 226h8M428 52v174"/>
                </g>
                <text class="dim-txt" x="220" y="18" text-anchor="middle">25–40 m</text>
                <text class="dim-txt" x="443" y="139" text-anchor="middle" transform="rotate(90 443 139)">16–25 m</text>
                <text class="note" x="220" y="256" text-anchor="middle">Rebound boards or nets on all sides</text>
            </svg>
            <figcaption><span><b>Five-a-side pitch</b> · size varies by ground</span><span>Check before booking</span></figcaption>
        @else
            {{-- Box cricket: a netted cage, usually 90–110 ft long, with a short pitch. --}}
            <svg viewBox="0 0 440 262" role="img" aria-hidden="true">
                <rect class="surface" x="40" y="48" width="360" height="176" rx="3"/>
                <rect class="mow" x="40" y="48" width="40" height="176"/><rect class="mow" x="120" y="48" width="40" height="176"/><rect class="mow" x="200" y="48" width="40" height="176"/><rect class="mow" x="280" y="48" width="40" height="176"/><rect class="mow" x="360" y="48" width="40" height="176"/>
                <rect class="run" x="140" y="121" width="160" height="30" rx="1"/>
                <g class="line">
                    <line x1="154" y1="121" x2="154" y2="151"/>
                    <line x1="286" y1="121" x2="286" y2="151"/>
                    <line x1="146" y1="126" x2="162" y2="126"/>
                    <line x1="146" y1="146" x2="162" y2="146"/>
                    <line x1="278" y1="126" x2="294" y2="126"/>
                    <line x1="278" y1="146" x2="294" y2="146"/>
                </g>
                <rect class="stump" x="146" y="132.5" width="3" height="7" rx="1"/>
                <rect class="stump" x="291" y="132.5" width="3" height="7" rx="1"/>
                <rect class="net" x="40" y="48" width="360" height="176" rx="3"/>
                <g class="dim">
                    <path d="M40 24v10M400 24v10M40 29h360"/>
                    <path d="M418 48h10M418 224h10M423 48v176"/>
                </g>
                <text class="dim-txt" x="220" y="20" text-anchor="middle">90–110 ft</text>
                <text class="dim-txt" x="433" y="136" text-anchor="middle" transform="rotate(90 433 136)">50–70 ft</text>
                <text class="note" x="220" y="113" text-anchor="middle" fill="#fff" style="fill:#fff;opacity:.85">pitch</text>
                <text class="note" x="40" y="246">- - -  netted on all sides and overhead</text>
            </svg>
            <figcaption><span><b>Box cricket arena</b> · 6 to 8 a side</span><span>Typical size; grounds vary</span></figcaption>
        @endif
    </div>
</figure>
