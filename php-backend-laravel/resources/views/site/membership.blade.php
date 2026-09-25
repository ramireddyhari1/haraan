@extends('site.layout')

@section('body_class', 'theme-minimal membership-page')

@section('content')
{{-- Membership — the web home of Pro and Hero, and the only place they're sold. Same shape
     as the app's plan showcase: one switcher, one panel whose material changes per plan, and
     a checkout tray. Every name, figure and price comes from the member catalogue. --}}
@php
    $firstTerm = in_array($selectedTerm, $terms, true) ? $selectedTerm : ($terms[0] ?? null);
    $currentPlan = collect($plans)->firstWhere('is_current', true);
    $startPlan = collect($plans)->firstWhere('code', $selectedPlan)
        ?? ($user ? $currentPlan : collect($plans)->first(fn ($p) => ! $p['is_default'] && ! empty($p['prices'])))
        ?? $currentPlan
        ?? ($plans[0] ?? null);
    $termLabels = [
        'month' => 'Monthly',
        'quarter' => '3 months',
        'half_year' => '6 months',
        'year' => 'Yearly',
    ];
@endphp

<style>
    .mbr { --ink:#0F172A; --sub:#64748B; --mute:#94A3B8; --line:#E2E8F0; --hair:#EDF1F6; --blue:#2563EB;
           max-width: 560px; margin: 0 auto; padding: 28px 16px 140px; font-family: 'Plus Jakarta Sans', system-ui, sans-serif; color: var(--ink); }
    .mbr h1 { margin: 0; font-size: 30px; font-weight: 800; letter-spacing: -0.03em; }
    .mbr__lede { margin: 6px 0 22px; font-size: 15px; line-height: 1.5; color: var(--sub); }
    .mbr__flash { margin: 0 0 16px; padding: 12px 14px; border-radius: 14px; font-size: 14px; background: #EAF1FE; color: #1E3A8A; }
    .mbr__flash--err { background: #FDECEF; color: #B91C1C; }

    .mbr-status { display: flex; gap: 12px; align-items: center; margin: 0 0 22px; padding: 14px 16px; border: 1px solid var(--line); border-radius: 16px; background: #fff; }
    .mbr-status strong { display: block; font-size: 15px; }
    .mbr-status span { font-size: 13.5px; color: var(--sub); }
    .mbr-status--warn { border-color: #FCD34D; background: #FFFBEB; }
    .mbr-status--fail { border-color: #FCA5A5; background: #FEF2F2; }

    .mbr-terms { display: flex; gap: 6px; margin: 0 0 12px; padding: 4px; border-radius: 14px; background: #EEF2F7; overflow-x: auto; scrollbar-width: none; }
    .mbr-terms::-webkit-scrollbar { display: none; }
    .mbr-term { flex: 1 0 auto; border: 1px solid transparent; background: transparent; border-radius: 11px; padding: 8px 12px; font: 600 13px/1 inherit; color: var(--sub); cursor: pointer; white-space: nowrap; }
    .mbr-term[aria-checked="true"] { background: #fff; color: var(--blue); border-color: rgba(37,99,235,.35); }
    .mbr-term em { font-style: normal; font-weight: 700; color: #16A34A; margin-left: 4px; }

    .mbr-switch { position: relative; display: grid; grid-template-columns: repeat(var(--n), 1fr); padding: 4px; border: 1px solid var(--line); border-radius: 20px; background: #fff; box-shadow: 0 6px 18px rgba(15,23,42,.06); }
    .mbr-switch__pill { position: absolute; top: 4px; bottom: 4px; left: 4px; width: calc((100% - 8px) / var(--n)); border-radius: 16px;
                        transform: translateX(calc(var(--i) * 100%)); transition: transform .42s cubic-bezier(.2,.9,.25,1), background .35s; box-shadow: 0 8px 18px rgba(15,23,42,.22); }
    .mbr-switch__pill::after { content: ""; position: absolute; inset: 0 12% auto; height: 1px; background: linear-gradient(90deg, transparent, rgba(255,255,255,.35), transparent); }
    .mbr-seg { position: relative; z-index: 1; border: 0; background: none; padding: 10px 4px; border-radius: 16px; cursor: pointer; font: inherit; color: var(--ink); transition: color .25s, transform .12s; }
    .mbr-seg:active { transform: scale(.97); }
    .mbr-seg[aria-selected="true"] { color: #fff; }
    .mbr-seg__name { display: inline-flex; align-items: center; gap: 6px; font-size: 15px; font-weight: 700; }
    .mbr-seg__price { display: block; margin-top: 2px; font-size: 11.5px; opacity: .72; }
    .mbr-seg__dot { width: 5px; height: 5px; border-radius: 50%; background: var(--blue); }
    .mbr-seg[aria-selected="true"] .mbr-seg__dot { background: rgba(255,255,255,.85); }

    .mbr-panel { margin-top: 16px; border-radius: 22px; overflow: hidden; border: 1px solid var(--line); background: #fff; box-shadow: 0 18px 40px rgba(15,23,42,.08); animation: mbrIn .32s ease both; }
    .mbr-panel[hidden] { display: none; }
    @keyframes mbrIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
    .mbr-band { position: relative; display: flex; gap: 14px; align-items: center; padding: 20px; color: #fff; }
    .mbr-band::after { content: ""; position: absolute; left: 0; right: 0; top: 0; height: 1px; background: linear-gradient(90deg, transparent, rgba(255,255,255,.18), transparent); }
    .mbr-band h2 { margin: 0; font-size: 25px; font-weight: 800; letter-spacing: -0.02em; }
    .mbr-band p { margin: 3px 0 0; font-size: 13.5px; line-height: 1.4; color: rgba(255,255,255,.72); }
    .mbr-band__price { margin-left: auto; text-align: right; white-space: nowrap; }
    .mbr-band__price b { display: block; font-size: 22px; font-weight: 800; letter-spacing: -0.01em; }
    .mbr-band__price small { font-size: 12px; color: rgba(255,255,255,.64); }
    .mbr-band__pill { margin-left: auto; padding: 5px 11px; border-radius: 99px; font-size: 12px; font-weight: 600; background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.18); white-space: nowrap; }
    .mbr-sheet { padding: 4px 20px 14px; }
    .mbr-sheet h3 { margin: 18px 0 2px; font-size: 12.5px; font-weight: 600; color: var(--sub); }
    .mbr-row { display: flex; align-items: center; gap: 12px; min-height: 50px; padding: 9px 0; border-top: 1px solid var(--hair); }
    .mbr-sheet h3 + .mbr-row { border-top: 0; }
    .mbr-row__name { flex: 1; font-size: 14.5px; font-weight: 500; }
    .mbr-row__yours { display: block; margin-top: 1px; font-size: 12px; font-weight: 400; color: var(--sub); }
    .mbr-row__yours--gain { color: var(--accent); font-weight: 600; }
    .mbr-row__value { font-size: 13.5px; font-weight: 600; color: var(--accent); white-space: nowrap; }
    .mbr-tick { display: inline-grid; place-items: center; width: 22px; height: 22px; border-radius: 50%; background: color-mix(in srgb, var(--accent) 14%, transparent); flex-shrink: 0; }
    .mbr-tick svg { width: 13px; height: 13px; stroke: var(--accent); }
    .mbr-dash { display: inline-grid; place-items: center; width: 22px; height: 22px; flex-shrink: 0; }
    .mbr-dash::before { content: ""; width: 10px; height: 1.5px; border-radius: 2px; background: var(--muted); }
    .mbr-row--off .mbr-row__name { color: var(--muted); }

    .mbr-panel--free { --accent: #2563EB; --muted: #B4BFCC; } .mbr-panel--free .mbr-band { background: linear-gradient(135deg, #4A5A70, #2C3849 55%, #161D29); }
    .mbr-panel--pro { --accent: #2563EB; --muted: #B4BFCC; border-color: #C7D7FE; } .mbr-panel--pro .mbr-band { background: linear-gradient(135deg, #1C3F95, #102A66 55%, #081634); }
    .mbr-panel--hero { --accent: #D9AE55; --muted: #59534B; border-color: #4A3A1C; background: #14120E; color: #F5F2EC; box-shadow: 0 22px 44px rgba(0,0,0,.28); }
    .mbr-panel--hero .mbr-band { background: linear-gradient(135deg, #332B20, #1A1712 55%, #0A0908); }
    .mbr-panel--hero .mbr-sheet h3, .mbr-panel--hero .mbr-row__yours { color: #A39C92; }
    .mbr-panel--hero .mbr-row { border-top-color: rgba(255,255,255,.09); }

    .mbr-cancel { display: block; margin: 22px auto 0; border: 0; background: none; font: 600 14px/1 inherit; color: #DC2626; cursor: pointer; padding: 12px; }

    .mbr-tray { position: fixed; left: 0; right: 0; bottom: 0; z-index: 40; background: #fff; border-radius: 24px 24px 0 0; box-shadow: 0 -10px 30px rgba(15,23,42,.12); padding: 14px 16px calc(14px + env(safe-area-inset-bottom)); }
    .mbr-tray__in { max-width: 560px; margin: 0 auto; display: flex; align-items: center; gap: 12px; }
    .mbr-tray__price { flex: 1; min-width: 0; }
    .mbr-tray__price b { font-size: 22px; font-weight: 800; letter-spacing: -0.02em; }
    .mbr-tray__price small { font-size: 13px; color: var(--sub); font-weight: 500; }
    .mbr-tray__terms { display: block; margin-top: 1px; font-size: 12px; line-height: 1.35; color: var(--mute); }
    .mbr-cta { min-width: 132px; height: 52px; padding: 0 22px; border: 0; border-radius: 16px; font: 600 15.5px/1 inherit; color: #fff; text-decoration: none; display: inline-grid; place-items: center;
               background: linear-gradient(180deg, #3B7BF6, #2563EB); box-shadow: 0 10px 22px rgba(37,99,235,.32); cursor: pointer; transition: transform .12s, opacity .15s; }
    .mbr-cta:active { transform: scale(.97); }
    .mbr-cta[disabled] { opacity: .55; cursor: default; box-shadow: none; }
    .mbr-tray__msg { max-width: 560px; margin: 8px auto 0; font-size: 13px; font-weight: 600; color: #DC2626; }
    .mbr-tray__msg:empty { display: none; }
    .mbr-tray__msg.ok { color: #16A34A; }
</style>

<div class="mbr">
    <h1>{{ $headline }}</h1>
    <p class="mbr__lede">{{ $lede }}</p>

    @if(session('membership_notice'))<div class="mbr__flash">{{ session('membership_notice') }}</div>@endif
    @if(session('membership_error'))<div class="mbr__flash mbr__flash--err">{{ session('membership_error') }}</div>@endif

    @if($membership && ($membership['plan']['rank'] > 0 || $membership['attention']))
        @php
            $statusClass = match ($membership['attention']) { 'payment_failed' => ' mbr-status--fail', 'payment_retrying' => ' mbr-status--warn', default => '' };
            $statusTier = $membership['badge'] ?? null;
        @endphp
        <div class="mbr-status{{ $statusClass }}">
            @if(in_array($statusTier, ['pro', 'hero'], true))
                @include('site.partials.member-mark', ['tier' => $statusTier, 'size' => 40])
            @endif
            <div>
                <strong>{{ $membership['plan']['name'] }}</strong>
                <span>{{ $status }}</span>
            </div>
        </div>
    @endif

    @if(count($terms) > 1)
        <div class="mbr-terms" role="radiogroup" aria-label="Billing term">
            @foreach($terms as $term)
                <button type="button" class="mbr-term" role="radio" data-term="{{ $term }}" aria-checked="{{ $term === $firstTerm ? 'true' : 'false' }}">
                    {{ $termLabels[$term] ?? $term }}<em data-saving-for="{{ $term }}"></em>
                </button>
            @endforeach
        </div>
    @endif

    @if($startPlan)
        @php $startIndex = array_search($startPlan['code'], array_column($plans, 'code'), true); @endphp
        <div class="mbr-switch" role="tablist" aria-label="Plans" style="--n: {{ count($plans) }}; --i: {{ $startIndex }}">
            <span class="mbr-switch__pill" aria-hidden="true"></span>
            @foreach($plans as $i => $plan)
                <button type="button" class="mbr-seg" role="tab" data-plan="{{ $plan['code'] }}" data-index="{{ $i }}"
                        aria-selected="{{ $plan['code'] === $startPlan['code'] ? 'true' : 'false' }}"
                        aria-label="{{ $plan['name'] }}{{ $user && $plan['is_current'] ? ', your plan' : '' }}">
                    <span class="mbr-seg__name">
                        @if($plan['tier']) @include('site.partials.member-mark', ['tier' => $plan['tier'], 'size' => 17]) @endif
                        {{ $plan['name'] }}
                        @if($user && $plan['is_current'])<i class="mbr-seg__dot"></i>@endif
                    </span>
                    @if(count($terms) > 0)<span class="mbr-seg__price" data-seg-price="{{ $plan['code'] }}"></span>@endif
                </button>
            @endforeach
        </div>

        @foreach($plans as $plan)
            @php $look = $plan['tier'] ?? 'free'; @endphp
            <section class="mbr-panel mbr-panel--{{ $look }}" data-panel="{{ $plan['code'] }}" role="tabpanel" @if($plan['code'] !== $startPlan['code']) hidden @endif>
                <div class="mbr-band">
                    @if($plan['tier']) @include('site.partials.member-mark', ['tier' => $plan['tier'], 'size' => 46]) @endif
                    <div>
                        <h2>{{ $plan['name'] }}</h2>
                        @if($plan['tagline'])<p>{{ $plan['tagline'] }}</p>@endif
                    </div>
                    @if($user && $plan['is_current'])
                        <span class="mbr-band__pill">Your plan</span>
                    @elseif(! $plan['is_default'])
                        <span class="mbr-band__price" data-band-price="{{ $plan['code'] }}"></span>
                    @endif
                </div>
                <div class="mbr-sheet">
                    @foreach($plan['sections'] as $section)
                        <h3>{{ $section['title'] }}</h3>
                        @foreach($section['rows'] as $row)
                            <div class="mbr-row{{ $row['value'] === null ? ' mbr-row--off' : '' }}">
                                <span class="mbr-row__name">
                                    {{ $row['name'] }}
                                    @if($row['yours'])<span class="mbr-row__yours{{ $row['gain'] ? ' mbr-row__yours--gain' : '' }}">{{ $row['yours'] }}</span>@endif
                                </span>
                                @if($row['value'] !== null)
                                    @if($row['value'] !== 'Included')<span class="mbr-row__value">{{ $row['value'] }}</span>@endif
                                    <span class="mbr-tick" role="img" aria-label="Included"><svg viewBox="0 0 24 24" fill="none" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></span>
                                @else
                                    <span class="mbr-dash" role="img" aria-label="Not included"></span>
                                @endif
                            </div>
                        @endforeach
                    @endforeach
                </div>
            </section>
        @endforeach
    @endif

    @if($membership && ($membership['subscription']['can_cancel'] ?? false))
        <form method="POST" action="{{ route('site.membership.cancel') }}"
              onsubmit="return confirm('Cancel {{ e($membership['plan']['name']) }}? You keep everything until the end of this period and won\'t be charged again.')">
            @csrf
            <button type="submit" class="mbr-cancel">Cancel membership</button>
        </form>
    @endif
</div>

<div class="mbr-tray" id="mbrTray" hidden>
    <div class="mbr-tray__in">
        <div class="mbr-tray__price">
            <b id="mbrTrayAmount"></b><small id="mbrTrayPer"></small>
            <span class="mbr-tray__terms" id="mbrTrayTerms"></span>
        </div>
        @if($user)
            <button type="button" class="mbr-cta" id="mbrCta"></button>
        @else
            <a class="mbr-cta" href="{{ route('site.login') }}">Sign in</a>
        @endif
    </div>
    <div class="mbr-tray__msg" id="mbrMsg" role="status" aria-live="polite"></div>
</div>

<script src="https://checkout.razorpay.com/v1/checkout.js" defer></script>
<script>
(function () {
    const PLANS = {!! json_encode(collect($plans)->keyBy('code')->map(fn ($p) => ['name' => $p['name'], 'default' => $p['is_default'], 'prices' => (object) $p['prices']]), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};
    const SIGNED_IN = @json((bool) $user);
    const CSRF = @json(csrf_token());
    const URLS = { checkout: @json(route('site.membership.checkout')), verify: @json(route('site.membership.verify')), abandon: @json(route('site.membership.abandon')) };
    const PREFILL = @json($user ? ['name' => $user->name, 'email' => $user->email, 'contact' => $user->phone] : new stdClass());

    let plan = @json($startPlan['code'] ?? null);
    let term = @json($firstTerm);
    if (!plan) return;

    const $ = (s, r = document) => r.querySelector(s);
    const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
    const tray = $('#mbrTray'), cta = $('#mbrCta'), msg = $('#mbrMsg');
    const say = (t, ok) => { msg.textContent = t || ''; msg.className = 'mbr-tray__msg' + (ok ? ' ok' : ''); };
    const bandColors = { free: '#2C3849', pro: '#102A66', hero: '#1A1712' };

    function priceOf(code) { return (PLANS[code].prices || {})[term] || null; }

    function render() {
        $$('.mbr-seg').forEach(b => {
            const on = b.dataset.plan === plan;
            b.setAttribute('aria-selected', on ? 'true' : 'false');
            if (on) $('.mbr-switch').style.setProperty('--i', b.dataset.index);
        });
        const panel = $(`[data-panel="${plan}"]`);
        $$('.mbr-panel').forEach(p => { p.hidden = p !== panel; });
        const pill = $('.mbr-switch__pill');
        const look = panel.classList.contains('mbr-panel--hero') ? 'hero' : panel.classList.contains('mbr-panel--pro') ? 'pro' : 'free';
        pill.style.background = `linear-gradient(135deg, ${look === 'hero' ? '#332B20' : look === 'pro' ? '#1C3F95' : '#4A5A70'}, ${bandColors[look]})`;

        $$('[data-term]').forEach(b => b.setAttribute('aria-checked', b.dataset.term === term ? 'true' : 'false'));
        $$('[data-saving-for]').forEach(e => {
            const p = (PLANS[plan].prices || {})[e.dataset.savingFor];
            e.textContent = p && p.saving ? `−${p.saving}%` : '';
        });
        $$('[data-seg-price]').forEach(e => {
            const c = e.dataset.segPrice, p = priceOf(c);
            e.textContent = PLANS[c].default ? '₹0' : p ? p.amount + p.per : 'Not on sale';
        });
        $$('[data-band-price]').forEach(e => {
            const p = priceOf(e.dataset.bandPrice);
            e.innerHTML = p ? `<b>${p.amount}</b><small>${p.per}</small>` : '<small>Not on sale yet</small>';
        });

        const p = PLANS[plan].default ? null : priceOf(plan);
        const buyable = p && p.action.kind !== 'current';
        tray.hidden = !buyable;
        if (buyable) {
            $('#mbrTrayAmount').textContent = p.amount;
            $('#mbrTrayPer').textContent = p.per;
            $('#mbrTrayTerms').textContent = SIGNED_IN ? p.action.terms : 'Sign in to choose a plan';
            if (cta) { cta.textContent = p.action.label; cta.disabled = false; }
        }
        say('');
    }

    async function post(url, body) {
        const res = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF }, body: JSON.stringify(body) });
        return { ok: res.ok, data: await res.json().catch(() => ({})) };
    }

    async function buy() {
        const p = priceOf(plan);
        if (!p || typeof Razorpay === 'undefined') { say('Checkout is still loading. Try again in a moment.'); return; }
        cta.disabled = true; say('');
        const started = await post(URLS.checkout, { price_id: p.id });
        if (!started.ok) { cta.disabled = false; say(started.data.error || 'Could not start checkout.'); return; }
        const s = started.data.data;
        const rzp = new Razorpay({
            key: s.key, subscription_id: s.subscription_id, name: 'Haraan',
            description: `${PLANS[plan].name} membership`, prefill: PREFILL, theme: { color: '#2563EB' },
            handler: async (r) => {
                say('Verifying payment…', true);
                const v = await post(URLS.verify, r);
                if (v.ok) {
                    say(v.data.data && v.data.data.confirmed ? `You're on ${PLANS[plan].name}.` : 'Payment received — confirming with your bank. Your plan switches on shortly.', true);
                    setTimeout(() => location.reload(), 1600);
                } else { cta.disabled = false; say(v.data.error || 'Could not verify payment.'); }
            },
            modal: { ondismiss: () => { post(URLS.abandon, { subscription_id: s.subscription_id }); cta.disabled = false; } },
        });
        rzp.on('payment.failed', (e) => { cta.disabled = false; say((e.error && e.error.description) || 'Payment failed. Try again.'); });
        rzp.open();
    }

    $$('.mbr-seg').forEach(b => b.addEventListener('click', () => { plan = b.dataset.plan; render(); }));
    $$('[data-term]').forEach(b => b.addEventListener('click', () => { term = b.dataset.term; render(); }));
    if (cta) cta.addEventListener('click', buy);
    render();
})();
</script>
@endsection
