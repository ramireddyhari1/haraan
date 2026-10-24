@php
    use App\Models\PartnerApplication as PA;

    $types = [
        'venue' => ['label' => 'Sports venue or turf', 'line' => 'Courts, nets or a ground you rent by the hour'],
        'academy' => ['label' => 'Academy or coaching', 'line' => 'Batches, coaches and monthly fees'],
        'cafe' => ['label' => 'Café or play lounge', 'line' => 'Tables, consoles or boards, plus events'],
        'event' => ['label' => 'Events and tournaments', 'line' => 'You sell tickets or team entries'],
    ];
    $config = [
        'storeUrl' => route('site.partner.apply.store'),
        'callbackHours' => $callbackHours,
        'source' => $source,
        'prefill' => $prefill,
        'labels' => [
            'types' => PA::BUSINESS_TYPES,
            'channels' => PA::BOOKING_CHANNELS,
            'monthly' => PA::MONTHLY_BOOKINGS,
            'goals' => PA::GOALS,
            'roles' => PA::CONTACT_ROLES,
            'callTimes' => PA::CALL_TIMES,
            'slots' => PA::SLOT_LENGTHS,
        ],
        'days' => PA::WEEKDAYS,
        'sports' => $sports,
        'amenities' => $amenities,
        'rules' => $rulePresets,
        'maxPhotos' => $maxPhotos,
        'photoCheck' => $photoCheck ?? null,
        'askPayouts' => $askPayouts,
        'banks' => $banks,
        'ifscBanks' => PA::IFSC_BANKS,
        'whatsappLive' => $whatsappLive,
    ];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#F4F7FB">
    <title>Become a Haraan partner — list your venue, academy or events</title>
    <meta name="description" content="Venue owners, academies, cafés and event hosts: apply to sell on Haraan. Online bookings, a desk for walk-ins and payouts to your bank.">
    <link rel="canonical" href="{{ route('site.partner.apply') }}">
    <link rel="icon" href="/favicon-32.png" sizes="32x32">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #0F172A; --ink-2: #334155; --mute: #64748B; --faint: #94A3B8;
            --line: #E2E8F0; --line-2: #CBD5E1; --page: #F4F7FB; --card: #FFFFFF;
            --blue: #2563EB; --blue-d: #1D4ED8; --blue-t: #EFF5FF; --blue-l: #BFD4FE;
            --red: #DC2626; --green: #16A34A;
            --ease: cubic-bezier(.2, .8, .2, 1); --spring: cubic-bezier(.34, 1.56, .64, 1);
            --r: 14px;
        }
        * { box-sizing: border-box; }
        [hidden] { display: none !important; }
        /* Drawn strokes stay hollow unless the shape names its own fill. */
        svg .st:not([fill]), svg .st-t:not([fill]) { fill: none; }
        html, body { margin: 0; }
        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, 'Segoe UI', sans-serif;
            background: var(--page); color: var(--ink); -webkit-font-smoothing: antialiased;
            overflow-x: clip; min-height: 100vh;
        }
        button, input, textarea, select { font: inherit; color: inherit; }
        a { color: var(--blue); }

        /* ── Top bar ──────────────────────────────────────────────────────────── */
        .bp-top { display: flex; align-items: center; justify-content: space-between; padding: 18px 28px; max-width: 1240px; margin: 0 auto; }
        .bp-brand { display: flex; align-items: center; gap: 10px; text-decoration: none; }
        .bp-brand img { height: 24px; width: auto; display: block; }
        .bp-brand span { font-size: 9.5px; font-weight: 800; letter-spacing: .3em; color: var(--mute); padding-left: 10px; border-left: 1px solid var(--line-2); }
        .bp-top__link { font-size: 13.5px; font-weight: 600; color: var(--ink-2); text-decoration: none; }
        .bp-top__link b { color: var(--blue); font-weight: 700; }

        /* ── Layout ───────────────────────────────────────────────────────────── */
        .bp { max-width: 1240px; margin: 0 auto; padding: 8px 28px 64px; display: grid; grid-template-columns: minmax(0, 1.05fr) minmax(0, 1fr); gap: 40px; align-items: start; }
        .bp-side { position: sticky; top: 20px; }
        .bp-intro h1 { font-size: 38px; line-height: 1.12; letter-spacing: -.025em; margin: 6px 0 12px; font-weight: 800; }
        .bp-intro p { font-size: 15.5px; line-height: 1.6; color: var(--ink-2); margin: 0; max-width: 34em; }
        .bp-eyebrow { font-size: 11px; font-weight: 800; letter-spacing: .22em; color: var(--blue); text-transform: uppercase; }

        /* The scene: a drawn place that fills in as the owner describes it. */
        .bp-scene { margin-top: 26px; border-radius: 22px; background: linear-gradient(180deg, #EAF1FC 0%, #F5F8FD 62%); border: 1px solid #DCE6F5; overflow: hidden; position: relative; }
        .bp-scene svg { display: block; width: 100%; height: auto; }
        .bp-scene .st { stroke: #1E293B; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
        .bp-scene .st-t { stroke: #1E293B; stroke-width: 1.4; stroke-linecap: round; stroke-linejoin: round; }
        .bp-scene .w { stroke: #fff; stroke-width: 2; fill: none; stroke-linecap: round; }
        .sc-g { transition: opacity .5s var(--ease), transform .6s var(--spring); transform-box: fill-box; transform-origin: 50% 100%; }
        .sc-g.is-off { opacity: 0; transform: translateY(14px) scale(.96); pointer-events: none; }
        .sc-board-name { font: 800 15px 'Plus Jakarta Sans', sans-serif; fill: var(--ink); letter-spacing: -.01em; }
        .sc-board-sub { font: 600 9.5px 'Plus Jakarta Sans', sans-serif; fill: var(--mute); letter-spacing: .04em; }
        .sc-phone-txt { font: 700 7px 'Plus Jakarta Sans', sans-serif; fill: #fff; }
        .sc-phone-num { font: 600 6px 'Plus Jakarta Sans', sans-serif; fill: #BFD4FE; }
        #scHands { transition: transform .8s var(--spring); transform-origin: 66px 74px; }
        #scHandH, #scHandM { transition: transform .8s var(--spring); transform-origin: 66px 74px; }
        .sc-ring { opacity: 0; }
        .is-ringing .sc-ring { animation: scRing 1.4s var(--ease) infinite; }
        .is-ringing #scPhone { animation: scBuzz 1.4s linear infinite; }
        .sc-ring:nth-of-type(2) { animation-delay: .18s !important; }
        @keyframes scRing { 0% { opacity: 0; transform: scale(.7); } 30% { opacity: 1; } 100% { opacity: 0; transform: scale(1.25); } }
        @keyframes scBuzz { 0%, 30%, 100% { transform: rotate(0); } 5% { transform: rotate(-4deg); } 10% { transform: rotate(4deg); } 15% { transform: rotate(-3deg); } 20% { transform: rotate(3deg); } 25% { transform: rotate(-1deg); } }
        #scPhone, .sc-ring { transform-box: fill-box; transform-origin: 50% 50%; }
        #scFlag { transition: transform 1.1s var(--spring); transform: translateY(58px); }
        .is-done #scFlag { transform: translateY(0); }
        .sc-flag-cloth { transform-box: fill-box; transform-origin: 0 50%; animation: scWave 2.6s ease-in-out infinite; }
        @keyframes scWave { 0%, 100% { transform: skewY(0) scaleX(1); } 50% { transform: skewY(-4deg) scaleX(.94); } }
        .sc-cloud { animation: scDrift 26s linear infinite alternate; }
        @keyframes scDrift { from { transform: translateX(-10px); } to { transform: translateX(16px); } }

        .bp-truths { list-style: none; padding: 0; margin: 22px 0 0; display: grid; grid-template-columns: repeat(3, 1fr); border-top: 1px solid var(--line); }
        .bp-truths li { padding: 14px 14px 0 0; font-size: 12.5px; line-height: 1.45; color: var(--ink-2); }
        .bp-truths li + li { padding-left: 14px; border-left: 1px solid var(--line); }
        .bp-truths svg { width: 20px; height: 20px; stroke: var(--blue); fill: none; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; display: block; margin-bottom: 8px; }
        .bp-truths b { display: block; color: var(--ink); font-size: 13px; margin-bottom: 2px; }

        /* ── Form card ────────────────────────────────────────────────────────── */
        .bp-card { background: var(--card); border-radius: 24px; border: 1px solid #E4EAF3; box-shadow: 0 1px 0 rgba(15,23,42,.03), 0 24px 60px -28px rgba(30,58,138,.28); padding: 28px 30px 26px; position: relative; overflow: clip; }
        .bp-rail { display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px; margin-bottom: 22px; }
        .bp-rail i { height: 4px; border-radius: 4px; background: #E8EEF6; position: relative; overflow: hidden; }
        .bp-rail i::after { content: ''; position: absolute; inset: 0; background: var(--blue); transform: scaleX(0); transform-origin: left; transition: transform .55s var(--ease); }
        .bp-rail i.is-on::after { transform: scaleX(1); }
        .bp-stepno { font-size: 12px; font-weight: 700; color: var(--mute); letter-spacing: .02em; display: flex; justify-content: space-between; align-items: center; }
        .bp-stepno .bp-saved { font-weight: 600; color: var(--faint); display: inline-flex; gap: 5px; align-items: center; opacity: 0; transition: opacity .3s; }
        .bp-stepno .bp-saved.is-on { opacity: 1; }
        .bp-saved svg { width: 13px; height: 13px; stroke: currentColor; fill: none; stroke-width: 2; }
        .bp-h { font-size: 24px; letter-spacing: -.02em; margin: 6px 0 4px; font-weight: 800; }
        .bp-sub { margin: 0 0 22px; color: var(--mute); font-size: 14px; line-height: 1.55; }

        .bp-steps { position: relative; }
        .bp-step { display: none; }
        .bp-step.is-on { display: block; animation: stepIn .45s var(--ease) both; }
        .bp-step.is-back { animation-name: stepInBack; }
        @keyframes stepIn { from { opacity: 0; transform: translateX(26px); } to { opacity: 1; transform: none; } }
        @keyframes stepInBack { from { opacity: 0; transform: translateX(-26px); } to { opacity: 1; transform: none; } }

        /* Type cards */
        .bp-types { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .bp-type { position: relative; text-align: left; border: 1.5px solid var(--line); background: #fff; border-radius: 18px; padding: 16px 16px 15px; cursor: pointer; transition: border-color .2s, background .2s, transform .18s var(--ease), box-shadow .25s; min-height: 148px; display: flex; flex-direction: column; }
        .bp-type:hover { border-color: var(--line-2); box-shadow: 0 10px 24px -18px rgba(30,58,138,.45); }
        .bp-type:active { transform: scale(.975); }
        .bp-type.is-on { border-color: var(--blue); background: var(--blue-t); }
        .bp-type__art { height: 62px; margin-bottom: 10px; }
        .bp-type__art svg { height: 62px; width: auto; display: block; }
        .bp-type__art .st { stroke: #1E293B; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
        .bp-type b { font-size: 14.5px; display: block; }
        .bp-type small { display: block; font-size: 12px; color: var(--mute); margin-top: 3px; line-height: 1.4; }
        .bp-check { position: absolute; top: 12px; right: 12px; width: 22px; height: 22px; border-radius: 50%; border: 1.5px solid var(--line-2); background: #fff; display: grid; place-items: center; transition: background .2s, border-color .2s; }
        .bp-check svg { width: 12px; height: 12px; stroke: #fff; stroke-width: 3; fill: none; stroke-linecap: round; stroke-linejoin: round; stroke-dasharray: 20; stroke-dashoffset: 20; transition: stroke-dashoffset .35s var(--ease) .05s; }
        .is-on > .bp-check { background: var(--blue); border-color: var(--blue); }
        .is-on > .bp-check svg { stroke-dashoffset: 0; }

        /* Fields */
        .bp-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .bp-f { margin-bottom: 16px; position: relative; }
        .bp-f > label, .bp-lbl { display: block; font-size: 13px; font-weight: 700; color: var(--ink-2); margin-bottom: 7px; }
        .bp-lbl em, .bp-f > label em { font-style: normal; font-weight: 500; color: var(--faint); margin-left: 4px; }
        .bp-in { width: 100%; height: 52px; border-radius: var(--r); border: 1.5px solid var(--line); background: #FBFCFE; padding: 0 15px; font-size: 15px; font-weight: 500; outline: none; transition: border-color .2s, background .2s, box-shadow .2s; }
        textarea.bp-in { height: 92px; padding: 13px 15px; resize: vertical; line-height: 1.5; }
        .bp-in::placeholder { color: #A8B3C4; font-weight: 400; }
        .bp-in:focus { border-color: var(--blue); background: #fff; box-shadow: 0 0 0 4px rgba(37,99,235,.12); }
        .bp-f.has-err .bp-in, .bp-f.has-err .bp-phone { border-color: var(--red); }
        .bp-f.has-err { animation: shake .4s; }
        .bp-err { color: var(--red); font-size: 12.5px; font-weight: 600; margin-top: 6px; display: none; }
        .bp-f.has-err .bp-err, .bp-group.has-err .bp-err { display: block; }
        @keyframes shake { 20%, 60% { transform: translateX(-5px); } 40%, 80% { transform: translateX(5px); } }
        .bp-hint { font-size: 12px; color: var(--faint); margin-top: 6px; }

        .bp-phone { display: flex; align-items: center; height: 52px; border-radius: var(--r); border: 1.5px solid var(--line); background: #FBFCFE; transition: border-color .2s, box-shadow .2s, background .2s; }
        .bp-phone:focus-within { border-color: var(--blue); background: #fff; box-shadow: 0 0 0 4px rgba(37,99,235,.12); }
        .bp-phone__cc { display: flex; align-items: center; gap: 7px; padding: 0 12px 0 14px; height: 26px; border-right: 1px solid var(--line); font-weight: 700; font-size: 15px; color: var(--ink-2); }
        .bp-phone__cc svg { width: 20px; height: 14px; border-radius: 2px; display: block; }
        .bp-phone input { border: 0; background: transparent; outline: none; flex: 1; height: 100%; padding: 0 14px; font-size: 16px; font-weight: 600; letter-spacing: .04em; min-width: 0; }
        .bp-phone__ok { width: 22px; height: 22px; margin-right: 13px; border-radius: 50%; background: var(--green); display: grid; place-items: center; transform: scale(0); transition: transform .35s var(--spring); }
        .bp-phone__ok svg { width: 12px; height: 12px; stroke: #fff; stroke-width: 3; fill: none; }
        .bp-phone.is-valid .bp-phone__ok { transform: scale(1); }

        /* Chips */
        .bp-group { margin-bottom: 18px; }
        .bp-chips { display: flex; flex-wrap: wrap; gap: 8px; }
        .bp-chip { height: 38px; padding: 0 14px; border-radius: 999px; border: 1.5px solid var(--line); background: #fff; font-size: 13.5px; font-weight: 600; color: var(--ink-2); cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: border-color .18s, background .18s, color .18s, transform .15s var(--ease); }
        .bp-chip:hover { border-color: var(--line-2); }
        .bp-chip:active { transform: scale(.95); }
        .bp-chip svg { width: 0; height: 12px; stroke: var(--blue); stroke-width: 3; fill: none; stroke-linecap: round; stroke-linejoin: round; transition: width .25s var(--ease); }
        .bp-chip.is-on { border-color: var(--blue); background: var(--blue-t); color: var(--blue-d); }
        .bp-chip.is-on svg { width: 12px; }
        .bp-chip--add { border-style: dashed; color: var(--mute); }
        .bp-addsport { display: none; gap: 8px; margin-top: 10px; }
        .bp-addsport.is-on { display: flex; }
        .bp-addsport .bp-in { height: 40px; font-size: 14px; }
        .bp-addsport button { height: 40px; border-radius: 12px; padding: 0 14px; border: 0; background: var(--blue); color: #fff; font-weight: 700; cursor: pointer; }

        /* Stepper */
        .bp-stepper { display: inline-flex; align-items: center; height: 52px; border: 1.5px solid var(--line); border-radius: var(--r); background: #FBFCFE; }
        .bp-stepper button { width: 48px; height: 100%; border: 0; background: transparent; cursor: pointer; display: grid; place-items: center; color: var(--ink-2); }
        .bp-stepper button:active svg { transform: scale(.82); }
        .bp-stepper svg { width: 18px; height: 18px; stroke: currentColor; stroke-width: 2.2; fill: none; stroke-linecap: round; transition: transform .12s; }
        .bp-stepper output { min-width: 46px; text-align: center; font-size: 18px; font-weight: 800; font-variant-numeric: tabular-nums; }
        .bp-stepper output.bump { animation: bump .3s var(--spring); }
        @keyframes bump { 40% { transform: scale(1.25); } }

        /* Buttons */
        .bp-nav { display: flex; gap: 10px; margin-top: 8px; align-items: center; }
        .bp-btn { height: 54px; border-radius: 16px; border: 0; padding: 0 22px; font-size: 15.5px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 8px; transition: transform .15s var(--ease), background .2s, box-shadow .2s, opacity .2s; -webkit-tap-highlight-color: transparent; }
        .bp-btn:active { transform: scale(.97); }
        .bp-btn--go { background: var(--blue); color: #fff; flex: 1; box-shadow: 0 10px 22px -12px rgba(37,99,235,.9), inset 0 -2px 0 rgba(0,0,0,.12); }
        .bp-btn--go:hover { background: var(--blue-d); }
        .bp-btn--go svg { width: 18px; height: 18px; stroke: currentColor; stroke-width: 2.4; fill: none; stroke-linecap: round; stroke-linejoin: round; transition: transform .2s var(--ease); }
        .bp-btn--go:hover svg { transform: translateX(3px); }
        .bp-btn--back { background: #F1F5F9; color: var(--ink-2); width: 54px; padding: 0; }
        .bp-btn--back svg { width: 20px; height: 20px; stroke: currentColor; stroke-width: 2.2; fill: none; stroke-linecap: round; stroke-linejoin: round; }
        .bp-btn[disabled] { opacity: .7; cursor: progress; }
        .bp-spin { width: 18px; height: 18px; border-radius: 50%; border: 2.5px solid rgba(255,255,255,.4); border-top-color: #fff; animation: spin .7s linear infinite; display: none; }
        .is-busy .bp-spin { display: block; }
        .is-busy .bp-btn__lbl, .is-busy > svg { display: none; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Review slip */
        .bp-slip { position: relative; background: #FFFDF8; border: 1px solid #EDE6D6; border-radius: 6px 6px 16px 16px; padding: 22px 20px 6px; margin-bottom: 16px; box-shadow: 0 14px 30px -24px rgba(120,90,30,.5); }
        .bp-slip::before { content: ''; position: absolute; left: 0; right: 0; top: -7px; height: 14px; background: radial-gradient(circle at 7px 0, var(--card) 6px, transparent 6.5px) 0 0 / 14px 14px repeat-x; }
        .bp-slip__head { display: flex; justify-content: space-between; align-items: baseline; border-bottom: 1.5px dashed #E4DAC4; padding-bottom: 12px; margin-bottom: 4px; }
        .bp-slip__head b { font-size: 16px; }
        .bp-slip__head span { font-size: 10.5px; font-weight: 800; letter-spacing: .2em; color: #A08A5C; }
        .bp-slip dl { margin: 0; }
        .bp-slip__sec { padding: 10px 0; border-bottom: 1px solid #F1EBDD; display: grid; grid-template-columns: 1fr auto; gap: 2px 12px; }
        .bp-slip__sec:last-child { border-bottom: 0; }
        .bp-slip dt { font-size: 10.5px; font-weight: 800; letter-spacing: .14em; color: #A08A5C; text-transform: uppercase; }
        .bp-slip dd { margin: 0; font-size: 14px; font-weight: 600; color: var(--ink); line-height: 1.5; grid-column: 1; }
        .bp-slip dd small { display: block; color: var(--mute); font-weight: 500; font-size: 12.5px; }
        .bp-slip__edit { grid-column: 2; grid-row: 1 / span 2; align-self: center; background: none; border: 0; color: var(--blue); font-weight: 700; font-size: 13px; cursor: pointer; padding: 6px 0 6px 8px; }
        .bp-stamp { position: absolute; right: 18px; top: 42px; border: 3px solid var(--blue); color: var(--blue); border-radius: 10px; padding: 6px 12px 5px; font-weight: 800; letter-spacing: .18em; font-size: 15px; text-align: center; line-height: 1.1; transform: rotate(-11deg) scale(2.2); opacity: 0; pointer-events: none; mix-blend-mode: multiply; background: rgba(239,245,255,.35); }
        .bp-stamp small { display: block; font-size: 9px; letter-spacing: .14em; margin-top: 2px; }
        .bp-stamp.is-down { animation: stamp .5s cubic-bezier(.5,0,.75,0) forwards; }
        @keyframes stamp { 0% { opacity: 0; transform: rotate(-11deg) scale(2.2); } 70% { opacity: 1; transform: rotate(-11deg) scale(.94); } 100% { opacity: .92; transform: rotate(-11deg) scale(1); } }
        .bp-slip.is-thud { animation: thud .35s .35s var(--ease); }
        @keyframes thud { 40% { transform: translateY(3px) scale(.995); } }

        .bp-consent { display: flex; gap: 12px; align-items: flex-start; font-size: 13px; color: var(--ink-2); line-height: 1.5; cursor: pointer; padding: 4px 0 10px; }
        .bp-consent input { position: absolute; opacity: 0; pointer-events: none; }
        .bp-box { flex: none; width: 22px; height: 22px; border-radius: 7px; border: 1.5px solid var(--line-2); display: grid; place-items: center; transition: background .2s, border-color .2s; margin-top: 1px; }
        .bp-box svg { width: 13px; height: 13px; stroke: #fff; stroke-width: 3; fill: none; stroke-linecap: round; stroke-linejoin: round; stroke-dasharray: 20; stroke-dashoffset: 20; transition: stroke-dashoffset .3s var(--ease); }
        .bp-consent input:checked + .bp-box { background: var(--blue); border-color: var(--blue); }
        .bp-consent input:checked + .bp-box svg { stroke-dashoffset: 0; }
        .bp-consent input:focus-visible + .bp-box { box-shadow: 0 0 0 4px rgba(37,99,235,.18); }
        .bp-group.has-err .bp-box { border-color: var(--red); }

        .bp-banner { display: none; background: #FEF2F2; color: #991B1B; border-radius: 12px; padding: 11px 14px; font-size: 13.5px; font-weight: 600; margin-bottom: 14px; }
        .bp-banner.is-on { display: block; }

        /* Done */
        .bp-done { display: none; text-align: center; padding: 6px 4px 2px; }
        .bp-done.is-on { display: block; animation: stepIn .5s var(--ease) both; }
        .bp-done__art { width: 150px; height: 120px; margin: 0 auto 6px; display: block; }
        .bp-done__art .st { stroke: #1E293B; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
        .bp-done__art .draw { stroke-dasharray: 260; stroke-dashoffset: 260; animation: draw 1s .2s var(--ease) forwards; }
        @keyframes draw { to { stroke-dashoffset: 0; } }
        .bp-done h2 { font-size: 26px; letter-spacing: -.02em; margin: 4px 0 6px; }
        .bp-done > p { color: var(--ink-2); font-size: 14.5px; line-height: 1.6; margin: 0 auto 18px; max-width: 30em; }
        .bp-ref { display: inline-flex; align-items: center; gap: 10px; background: var(--blue-t); border: 1px dashed var(--blue-l); padding: 10px 16px; border-radius: 12px; margin-bottom: 22px; }
        .bp-ref span { font-size: 10.5px; font-weight: 800; letter-spacing: .18em; color: var(--mute); }
        .bp-ref b { font-size: 17px; letter-spacing: .08em; color: var(--blue-d); font-variant-numeric: tabular-nums; }
        .bp-next { list-style: none; margin: 0 0 24px; padding: 0; text-align: left; position: relative; }
        .bp-next::before { content: ''; position: absolute; left: 17px; top: 18px; bottom: 18px; width: 2px; background: repeating-linear-gradient(to bottom, var(--line-2) 0 4px, transparent 4px 8px); }
        .bp-next li { display: flex; gap: 14px; align-items: flex-start; padding: 9px 0; position: relative; opacity: 0; animation: rise .5s var(--ease) forwards; }
        .bp-next li:nth-child(1) { animation-delay: .35s; } .bp-next li:nth-child(2) { animation-delay: .5s; } .bp-next li:nth-child(3) { animation-delay: .65s; }
        @keyframes rise { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }
        .bp-next i { flex: none; width: 36px; height: 36px; border-radius: 50%; background: #fff; border: 1.5px solid var(--line); display: grid; place-items: center; }
        .bp-next li:first-child i { border-color: var(--blue); background: var(--blue-t); }
        .bp-next i svg { width: 17px; height: 17px; stroke: var(--blue); stroke-width: 1.8; fill: none; stroke-linecap: round; stroke-linejoin: round; }
        .bp-next b { display: block; font-size: 14px; }
        .bp-next small { color: var(--mute); font-size: 12.5px; line-height: 1.45; }
        .bp-done .bp-nav { flex-direction: column; }
        .bp-done .bp-btn { width: 100%; flex: none; text-decoration: none; }
        .bp-btn--ghost { background: #F1F5F9; color: var(--ink-2); }

        .bp-closed { text-align: center; padding: 20px 6px 8px; }
        .bp-closed p { color: var(--ink-2); line-height: 1.6; }

        .bp-hp { position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden; }

        /* ── Phone ────────────────────────────────────────────────────────────── */
        @media (max-width: 960px) {
            .bp-top { padding: 14px 16px; }
            .bp-top__link { font-size: 12.5px; }
            .bp { grid-template-columns: 1fr; gap: 0; padding: 0 16px 40px; }
            .bp-side { position: static; }
            .bp-intro h1 { font-size: 27px; margin-top: 4px; }
            .bp-intro p { font-size: 14px; }
            .bp-scene { margin: 16px -16px 0; border-radius: 0; border-left: 0; border-right: 0; }
            .bp-scene svg { height: auto; }
            .bp-truths { display: none; }
            .bp-card { margin-top: -26px; border-radius: 22px; padding: 22px 18px 20px; animation: rise .55s .1s var(--ease) both; }
            .bp-h { font-size: 21px; }
            .bp-type { min-height: 0; padding: 13px 12px; }
            .bp-type__art, .bp-type__art svg { height: 46px; }
            .bp-type b { font-size: 13.5px; padding-right: 18px; }
            .bp-type small { font-size: 11.5px; }
            .bp-row { grid-template-columns: 1fr; gap: 0; }
            .bp-row--keep { grid-template-columns: 1fr 1fr; gap: 12px; }
            .bp-done .bp-nav { position: static; background: none; padding: 0; }
            .bp-saved__long { display: none; }
            .bp-nav { position: sticky; bottom: 0; background: linear-gradient(180deg, rgba(255,255,255,0), #fff 30%); padding: 14px 0 calc(4px + env(safe-area-inset-bottom)); margin: 0; z-index: 2; }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation-duration: .01ms !important; animation-iteration-count: 1 !important; transition-duration: .01ms !important; }
        }

        /* ── Venue-form steps ─────────────────────────────────────────────────── */
        .bp > section { min-width: 0; }
        .bp-stepno b { color: var(--ink-2); font-weight: 700; }
        .bp-chip .bp-main { font-size: 9.5px; font-weight: 800; letter-spacing: .12em; color: #fff; background: var(--blue); border-radius: 999px; padding: 2px 6px; margin-left: 2px; }
        .bp-adder { display: none; gap: 8px; margin-top: 10px; }
        .bp-adder.is-on { display: flex; }
        .bp-adder .bp-in { height: 40px; font-size: 14px; }
        .bp-adder button { height: 40px; border-radius: 12px; padding: 0 14px; border: 0; background: var(--blue); color: #fff; font-weight: 700; cursor: pointer; flex: none; }
        .bp-count { float: right; font-weight: 600; color: var(--faint); font-size: 12px; }

        .bp-locate { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border: 1.5px dashed var(--line-2); border-radius: var(--r); margin-bottom: 16px; }
        .bp-locate.is-set { border-style: solid; border-color: var(--blue-l); background: var(--blue-t); }
        .bp-locate__pin { width: 38px; height: 38px; flex: none; border-radius: 12px; background: #fff; border: 1.5px solid var(--line); display: grid; place-items: center; }
        .bp-locate__pin svg { width: 20px; height: 20px; stroke: var(--blue); stroke-width: 1.9; fill: none; stroke-linecap: round; stroke-linejoin: round; }
        .is-set .bp-locate__pin svg { animation: pinDrop .5s var(--spring); }
        @keyframes pinDrop { from { transform: translateY(-10px); opacity: 0; } }
        .bp-locate__txt { flex: 1; min-width: 0; font-size: 13px; color: var(--ink-2); line-height: 1.45; }
        .bp-locate__txt b { display: block; font-size: 13.5px; color: var(--ink); }
        .bp-mini { height: 36px; border-radius: 10px; border: 1.5px solid var(--line); background: #fff; padding: 0 12px; font-size: 13px; font-weight: 700; color: var(--blue-d); cursor: pointer; flex: none; transition: transform .15s var(--ease); }
        .bp-mini:active { transform: scale(.95); }

        .bp-court { border: 1.5px solid var(--line); border-radius: 16px; padding: 12px 12px 12px 14px; margin-bottom: 10px; background: #fff; animation: rise .35s var(--ease) both; }
        .bp-court__top { display: flex; gap: 10px; align-items: center; }
        .bp-court__no { width: 30px; height: 30px; flex: none; border-radius: 9px; background: #DDEFE3; color: #166534; font-weight: 800; font-size: 13px; display: grid; place-items: center; }
        .bp-court__name { flex: 1; min-width: 0; height: 40px; border: 0; border-bottom: 1.5px dashed var(--line-2); background: transparent; font-size: 15px; font-weight: 700; outline: none; padding: 0 2px; }
        .bp-court__name:focus { border-bottom-color: var(--blue); }
        .bp-court__price { display: flex; align-items: center; height: 40px; border: 1.5px solid var(--line); border-radius: 11px; background: #FBFCFE; width: 132px; flex: none; }
        .bp-court__price:focus-within { border-color: var(--blue); background: #fff; }
        .bp-court__price span { padding: 0 2px 0 10px; font-weight: 700; color: var(--mute); }
        .bp-court__price input { border: 0; background: transparent; outline: none; width: 100%; min-width: 0; font-weight: 700; font-size: 15px; padding: 0 4px; font-variant-numeric: tabular-nums; }
        .bp-court__price small { padding-right: 9px; color: var(--faint); font-weight: 600; font-size: 12px; }
        .bp-court__del { width: 36px; height: 36px; flex: none; border: 0; border-radius: 10px; background: transparent; color: var(--faint); cursor: pointer; display: grid; place-items: center; }
        .bp-court__del:hover { background: #FEF2F2; color: var(--red); }
        .bp-court__del svg { width: 18px; height: 18px; stroke: currentColor; stroke-width: 2; fill: none; stroke-linecap: round; stroke-linejoin: round; }
        .bp-court .bp-chips { margin: 10px 0 0 40px; gap: 6px; }
        .bp-court .bp-chip { height: 30px; font-size: 12.5px; padding: 0 10px; }
        .bp-addcourt { width: 100%; height: 48px; border: 1.5px dashed var(--line-2); border-radius: 14px; background: transparent; color: var(--blue-d); font-weight: 700; font-size: 14px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: border-color .2s, background .2s, transform .15s var(--ease); }
        .bp-addcourt:hover { border-color: var(--blue); background: var(--blue-t); }
        .bp-addcourt:active { transform: scale(.98); }
        .bp-addcourt svg { width: 18px; height: 18px; stroke: currentColor; stroke-width: 2.2; fill: none; stroke-linecap: round; }
        .bp-from { margin: 12px 0 18px; font-size: 13px; color: var(--mute); }
        .bp-from b { color: var(--ink); }

        .bp-days { border: 1.5px solid var(--line); border-radius: 16px; overflow: hidden; margin-bottom: 8px; }
        .bp-day { display: grid; grid-template-columns: 74px 1fr 1fr; gap: 10px; align-items: center; padding: 8px 12px; border-top: 1px solid #EEF2F7; transition: background .2s; }
        .bp-day:first-child { border-top: 0; }
        .bp-day.is-closed { background: #FAFBFC; }
        .bp-day.is-closed .bp-day__t { opacity: .35; pointer-events: none; }
        .bp-sw { display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 800; font-size: 13px; color: var(--ink-2); background: none; border: 0; padding: 0; }
        .bp-sw i { width: 30px; height: 18px; border-radius: 999px; background: var(--line-2); position: relative; transition: background .2s; flex: none; }
        .bp-sw i::after { content: ''; position: absolute; top: 2px; left: 2px; width: 14px; height: 14px; border-radius: 50%; background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.2); transition: transform .25s var(--spring); }
        .bp-sw[aria-checked="true"] i { background: var(--blue); }
        .bp-sw[aria-checked="true"] i::after { transform: translateX(12px); }
        .bp-day__t { height: 40px; border-radius: 11px; font-size: 14px; padding: 0 10px; }
        .bp-day__closed { grid-column: 2 / span 2; font-size: 13px; color: var(--faint); font-weight: 600; display: none; }
        .bp-day.is-closed .bp-day__closed { display: block; }
        .bp-day.is-closed .bp-day__t { display: none; }
        .bp-linkbtn { background: none; border: 0; padding: 4px 0; color: var(--blue); font-weight: 700; font-size: 13px; cursor: pointer; margin-bottom: 16px; }

        .bp-drop { border: 1.5px dashed var(--line-2); border-radius: 18px; padding: 22px 16px; text-align: center; cursor: pointer; transition: border-color .2s, background .2s, transform .2s var(--ease); background: #FBFCFE; display: block; }
        .bp-drop:hover, .bp-drop.is-over { border-color: var(--blue); background: var(--blue-t); }
        .bp-drop.is-over { transform: scale(1.01); }
        .bp-drop svg { width: 64px; height: 50px; display: block; margin: 0 auto 8px; }
        .bp-drop b { display: block; font-size: 14.5px; }
        .bp-drop small { color: var(--mute); font-size: 12.5px; }
        .bp-drop input { position: absolute; opacity: 0; pointer-events: none; width: 1px; }
        .bp-photos { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-top: 12px; }
        .bp-photo { position: relative; aspect-ratio: 1; border-radius: 12px; overflow: hidden; background: #EEF2F7; animation: polaroid .45s var(--spring) both; }
        @keyframes polaroid { from { opacity: 0; transform: translateY(10px) rotate(-4deg) scale(.9); } }
        .bp-photo img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .bp-photo.is-busy::after { content: ''; position: absolute; inset: 0; background: rgba(255,255,255,.55); }
        .bp-photo__cover { position: absolute; left: 6px; bottom: 6px; font-size: 9.5px; font-weight: 800; letter-spacing: .12em; background: var(--blue); color: #fff; border-radius: 6px; padding: 3px 6px; }
        .bp-photo__make { position: absolute; left: 6px; bottom: 6px; font-size: 10.5px; font-weight: 700; background: rgba(15,23,42,.66); color: #fff; border: 0; border-radius: 6px; padding: 3px 6px; cursor: pointer; }
        .bp-photo__x { position: absolute; top: 6px; right: 6px; width: 24px; height: 24px; border-radius: 50%; border: 0; background: rgba(15,23,42,.66); color: #fff; cursor: pointer; display: grid; place-items: center; }
        .bp-photo__flag { position: absolute; left: 6px; top: 6px; font-size: 9.5px; font-weight: 800; letter-spacing: .08em; background: #F59E0B; color: #fff; border-radius: 6px; padding: 3px 6px; }
        .bp-photo.is-weak img { outline: 2px solid #F59E0B; outline-offset: -2px; }
        .bp-photo-warn { margin-top: 10px; padding: 10px 12px; border-radius: 12px; background: #FFFBEB; border: 1px solid #FDE68A; color: #92400E; font-size: 13px; line-height: 1.45; }
        .bp-photo__x svg { width: 12px; height: 12px; stroke: #fff; stroke-width: 2.6; fill: none; stroke-linecap: round; }

        .bp-slip__thumbs { display: flex; gap: 5px; margin-top: 6px; }
        .bp-slip__thumbs img { width: 38px; height: 38px; object-fit: cover; border-radius: 6px; border: 1px solid #EDE6D6; }

        @media (max-width: 960px) {
            .bp-court__top { flex-wrap: wrap; }
            .bp-court__name { flex-basis: calc(100% - 90px); }
            .bp-court__price { order: 3; margin-left: 40px; flex: 1; width: auto; }
            .bp-court .bp-chips { margin-left: 40px; }
            .bp-photos { grid-template-columns: repeat(3, 1fr); }
            .bp-day { grid-template-columns: 70px 1fr 1fr; gap: 8px; padding: 8px 10px; }
            .bp-day__t { padding: 0 6px; font-size: 13.5px; }
        }

        /* ── Payouts: a cheque leaf that fills in ─────────────────────────────── */
        .bp-cheque { position: relative; margin: 4px 0 18px; padding: 16px 18px 14px; border-radius: 14px; border: 1px solid #CFDBEF; overflow: hidden;
            background:
                repeating-radial-gradient(circle at 85% 120%, transparent 0 9px, rgba(37,99,235,.06) 9px 10px),
                repeating-linear-gradient(115deg, transparent 0 7px, rgba(37,99,235,.035) 7px 8px),
                linear-gradient(135deg, #F6F9FF, #EDF3FD);
            box-shadow: 0 16px 30px -24px rgba(30,58,138,.55); transition: transform .4s var(--spring), box-shadow .3s; }
        .bp-cheque.is-ready { box-shadow: 0 18px 34px -22px rgba(30,58,138,.7); }
        .bp-cheque.is-flash { animation: chequeFlip .5s var(--spring); }
        @keyframes chequeFlip { 40% { transform: rotateX(10deg) translateY(-3px); } }
        .bp-cheque__top { display: flex; justify-content: space-between; align-items: center; gap: 10px; }
        .bp-cheque__bank { display: flex; gap: 8px; align-items: center; min-width: 0; }
        .bp-cheque__bank svg { width: 20px; height: 20px; flex: none; stroke: var(--blue-d); stroke-width: 1.7; fill: none; stroke-linecap: round; stroke-linejoin: round; }
        .bp-cheque__bank b { font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .bp-cheque__ifsc { font-size: 10px; font-weight: 800; letter-spacing: .14em; color: var(--mute); white-space: nowrap; }
        .bp-cheque__ifsc b { font-family: ui-monospace, 'Cascadia Mono', Consolas, monospace; font-size: 12.5px; letter-spacing: .08em; color: var(--ink); margin-left: 4px; }
        .bp-cheque__branch { font-size: 11.5px; color: var(--mute); min-height: 15px; margin: 2px 0 8px 28px; }
        .bp-cheque__pay { display: flex; align-items: baseline; gap: 10px; border-bottom: 1.5px solid #B9C8E2; padding-bottom: 4px; margin-bottom: 12px; }
        .bp-cheque small { font-size: 9.5px; font-weight: 800; letter-spacing: .16em; color: #7C8DAA; flex: none; }
        .bp-cheque__pay span { font-size: 15.5px; font-weight: 700; color: var(--ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .bp-cheque__pay span.is-empty, .bp-cheque b.is-empty { color: #A9B6CC; font-weight: 600; }
        .bp-cheque__row { display: flex; justify-content: space-between; gap: 14px; align-items: flex-end; }
        .bp-cheque__ac, .bp-cheque__upi { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
        .bp-cheque__ac b { font-family: ui-monospace, 'Cascadia Mono', Consolas, monospace; font-size: 16px; letter-spacing: .1em; }
        .bp-cheque__upi { text-align: right; align-items: flex-end; }
        .bp-cheque__upi b { font-size: 12.5px; max-width: 170px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .bp-cheque__ok { position: absolute; right: 14px; top: 40px; border: 2.5px solid var(--green); color: var(--green); border-radius: 8px; padding: 2px 8px; font-weight: 800; font-size: 12px; letter-spacing: .18em; transform: rotate(-9deg) scale(1.8); opacity: 0; transition: opacity .25s, transform .35s cubic-bezier(.5,0,.75,0); pointer-events: none; }
        .bp-cheque.is-ready .bp-cheque__ok { opacity: .9; transform: rotate(-9deg) scale(1); }
        .bp-cheque.is-later { opacity: .45; filter: grayscale(.6); }
        .bp-cheque__ac b { white-space: nowrap; }
        @media (max-width: 960px) {
            .bp-cheque { padding: 14px 14px 12px; }
            .bp-cheque__ac b { font-size: 14px; letter-spacing: .06em; }
            /* Account number over UPI; the stamp takes the corner they leave free. */
            .bp-cheque__row { flex-direction: column; align-items: flex-start; gap: 8px; }
            .bp-cheque__upi { text-align: left; align-items: flex-start; }
            .bp-cheque__upi b { max-width: 200px; }
            .bp-cheque__ok { top: auto; bottom: 16px; right: 14px; }
        }
        .bp-in--mono { font-family: ui-monospace, 'Cascadia Mono', Consolas, monospace; letter-spacing: .08em; }
        .bp-select { appearance: none; -webkit-appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2364748B' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 14px center; background-size: 18px; padding-right: 42px; cursor: pointer; }
        .bp-later { display: none; gap: 12px; align-items: flex-start; padding: 14px; border-radius: 14px; background: #F8FAFC; border: 1.5px dashed var(--line-2); font-size: 13.5px; color: var(--ink-2); line-height: 1.5; margin-bottom: 14px; }
        .bp-later svg { width: 20px; height: 20px; flex: none; stroke: var(--blue); stroke-width: 1.8; fill: none; stroke-linecap: round; stroke-linejoin: round; margin-top: 1px; }
        .bp-later.is-on { display: flex; }
        .bp-secure { display: flex; gap: 6px; align-items: center; }
        .bp-secure svg { width: 14px; height: 14px; stroke: currentColor; stroke-width: 2; fill: none; flex: none; }
        #scBank { transition: opacity .5s var(--ease), transform .6s var(--spring); }
        .sc-coin { opacity: 0; }
        .is-paid .sc-coin { animation: coinArc 2.4s var(--ease) infinite; }
        .is-paid .sc-coin:nth-of-type(2) { animation-delay: .8s; }
        .is-paid .sc-coin:nth-of-type(3) { animation-delay: 1.6s; }
        @keyframes coinArc { 0% { opacity: 0; offset-distance: 0%; } 15% { opacity: 1; } 85% { opacity: 1; } 100% { opacity: 0; offset-distance: 100%; } }

        /* ── WhatsApp number ─────────────────────────────────────────────────── */
        .bp-wa { border: 1.5px solid var(--line); border-radius: 16px; padding: 14px; margin-bottom: 16px; background: #FBFCFE; }
        .bp-wa__head { display: flex; gap: 10px; align-items: flex-start; margin-bottom: 12px; }
        .bp-wa__icon { width: 34px; height: 34px; flex: none; border-radius: 10px; background: #E7F7EE; display: grid; place-items: center; }
        .bp-wa__icon svg { width: 19px; height: 19px; stroke: #15803D; stroke-width: 1.7; fill: none; stroke-linecap: round; stroke-linejoin: round; }
        .bp-wa__head b { display: block; font-size: 14px; }
        .bp-wa__head small { color: var(--mute); font-size: 12.5px; line-height: 1.45; }
        .bp-wa .bp-phone { margin-top: 10px; background: #fff; }
        .bp-wa .bp-chip { background: #fff; }
        #scChat { transition: opacity .4s var(--ease), transform .5s var(--spring); }
    </style>
</head>
<body>
<header class="bp-top">
    <a class="bp-brand" href="{{ url('/') }}">
        <img src="{{ asset('images/haraan-logo-blue.png') }}" alt="haraan" width="1680" height="445">
        <span>PARTNERS</span>
    </a>
    <a class="bp-top__link" href="{{ url('/partner/login') }}">Already a partner? <b>Sign in</b></a>
</header>

<main class="bp" id="bp">
    <section class="bp-side">
        <div class="bp-intro">
            <div class="bp-eyebrow">Become a partner</div>
            <h1>{{ $headline }}</h1>
            <p>{{ $subline }}</p>
        </div>

        {{-- The place being described, drawn. Every group is driven by the form below. --}}
        <div class="bp-scene" id="scene" aria-hidden="true">
            <svg viewBox="0 0 520 330" preserveAspectRatio="xMidYMid slice">
                <g class="sc-cloud"><path class="st-t" d="M150 64c2-10 16-12 21-4 4-8 18-7 19 3 8 0 10 11 1 12h-40c-8 0-9-10-1-11z" fill="#fff"/></g>
                <g class="sc-cloud" style="animation-duration:34s"><path class="st-t" d="M318 44c2-8 13-10 17-3 3-6 14-5 15 2 6 0 8 9 1 9h-32c-6 0-7-8-1-8z" fill="#fff"/></g>
                <circle cx="452" cy="58" r="22" fill="#FDE9B0"/>

                {{-- Clock: hands go to the opening time. --}}
                <g id="scClock" class="sc-g is-off">
                    <circle cx="66" cy="74" r="27" fill="#fff" class="st"/>
                    <g class="st-t"><path d="M66 50v4M66 94v4M42 74h4M86 74h4"/></g>
                    <g id="scHandH"><path class="st" d="M66 74V60" stroke-width="2.6"/></g>
                    <g id="scHandM"><path class="st" d="M66 74V54" stroke="#2563EB"/></g>
                    <circle cx="66" cy="74" r="2.6" fill="#1E293B"/>
                </g>

                {{-- Ground --}}
                <path d="M0 252 C 120 240, 380 240, 520 250 L520 330 L0 330 Z" fill="#DDEFE3"/>
                <path class="st-t" d="M0 252 C 120 240, 380 240, 520 250" opacity=".55"/>

                {{-- Centrepieces, one per business type --}}
                <g id="scVenue" class="sc-g">
                    <path d="M196 252 L478 252 L446 192 L226 192 Z" fill="#9ED3B1"/>
                    <path class="st" d="M196 252 L478 252 L446 192 L226 192 Z"/>
                    <path class="w" d="M337 192 V252 M211 222 H462"/>
                    <ellipse class="w" cx="337" cy="222" rx="24" ry="8"/>
                    <path class="st" d="M337 186 V230" />
                    <path class="st-t" d="M318 190 h38 v14 h-38z" fill="#fff" opacity=".9"/>
                    <path class="st-t" d="M322 190 v14 M328 190 v14 M334 190 v14 M340 190 v14 M346 190 v14 M352 190 v14"/>
                    <path class="st" d="M210 192 V118"/><path class="st" d="M200 110 h22 v10 h-22z" fill="#fff"/>
                    <path class="st" d="M462 192 V118"/><path class="st" d="M452 110 h22 v10 h-22z" fill="#fff"/>
                    <g id="scBeams" style="transition:opacity .6s;opacity:0"><path d="M206 120 L180 196 L238 196 Z M466 120 L440 196 L498 196 Z" fill="#FEF3C7" opacity=".8"/></g>
                </g>
                <g id="scAcademy" class="sc-g is-off">
                    <path d="M200 252 L470 252 L446 204 L226 204 Z" fill="#B7E0C5"/>
                    <path class="st" d="M200 252 L470 252 L446 204 L226 204 Z"/>
                    <path class="st" d="M300 204 V158 H378 V204" /><path class="st-t" d="M300 166 H378 M300 178 H378 M300 190 H378 M313 158 V204 M326 158 V204 M339 158 V204 M352 158 V204 M365 158 V204" opacity=".6"/>
                    <g fill="#FDBA74"><path class="st" d="M236 246 l9-20 9 20z"/><path class="st" d="M282 246 l9-20 9 20z"/><path class="st" d="M410 246 l9-20 9 20z"/></g>
                    <circle class="st" cx="352" cy="236" r="9" fill="#fff"/><path class="st-t" d="M346 231 l5 4 6-3 M348 242 l4-6"/>
                </g>
                <g id="scCafe" class="sc-g is-off">
                    <path class="st" d="M300 120 V138"/><path class="st" d="M286 138 h28 l-5 12 h-18z" fill="#FDE68A"/>
                    <path d="M278 152 L322 152 L344 226 L256 226 Z" fill="#FEF3C7" opacity=".6"/>
                    <ellipse class="st" cx="300" cy="212" rx="78" ry="16" fill="#E9D5B5"/>
                    <path class="st" d="M300 228 V252 M276 252 h48"/>
                    <circle class="st" cx="270" cy="204" r="13" fill="#2563EB"/><path class="st" d="M279 213 l10 9" stroke-width="4"/>
                    <path class="st" d="M318 196 h18 v14 a9 9 0 0 1-18 0z" fill="#fff"/><path class="st-t" d="M336 200 a5 5 0 0 1 0 8"/>
                    <path class="st-t" d="M324 190 c-3-5 3-7 0-12 M330 190 c-3-5 3-7 0-12"/>
                    <circle class="st" cx="352" cy="207" r="4" fill="#fff"/>
                </g>
                <g id="scEvent" class="sc-g is-off">
                    <path d="M214 214 h236 v38 h-236z" fill="#C7D7F5"/><path class="st" d="M214 214 h236 v38 h-236z"/>
                    <path class="st" d="M222 214 V128 M442 214 V128 M214 128 H450"/>
                    <path class="st-t" d="M222 132 q14 22 28 0 q14 22 28 0 q14 22 28 0 q14 22 28 0 q14 22 28 0 q14 22 28 0 q14 22 28 0 q14 22 28 0"/>
                    <g><path d="M226 134 l7 12 7-12z" fill="#2563EB"/><path d="M254 134 l7 12 7-12z" fill="#F59E0B"/><path d="M282 134 l7 12 7-12z" fill="#2563EB"/><path d="M310 134 l7 12 7-12z" fill="#F59E0B"/><path d="M338 134 l7 12 7-12z" fill="#2563EB"/><path d="M366 134 l7 12 7-12z" fill="#F59E0B"/><path d="M394 134 l7 12 7-12z" fill="#2563EB"/><path d="M422 134 l7 12 7-12z" fill="#F59E0B"/></g>
                    <path d="M332 150 L300 214 L364 214 Z" fill="#FEF3C7" opacity=".7"/>
                    <path class="st" d="M332 214 V176"/><circle class="st" cx="332" cy="171" r="5" fill="#fff"/><path class="st" d="M322 214 h20"/>
                </g>

                {{-- People appear once they say how bookings come in. --}}
                <g id="scPeople" class="sc-g is-off">
                    <g transform="translate(262 206)"><circle class="st" cx="0" cy="0" r="7" fill="#FCD9B6"/><path class="st" d="M-9 30 v-12 a9 9 0 0 1 18 0 v12" fill="#2563EB"/><path class="st" d="M-5 30 v12 M5 30 v12"/></g>
                    <g transform="translate(404 206)"><circle class="st" cx="0" cy="0" r="7" fill="#E8B892"/><path class="st" d="M-9 30 v-12 a9 9 0 0 1 18 0 v12" fill="#F59E0B"/><path class="st" d="M-5 30 v12 M5 30 v12"/><path class="st" d="M9 20 l12 -10" /><ellipse class="st-t" cx="24" cy="7" rx="5" ry="6" fill="#fff"/></g>
                </g>

                {{-- Courts strip — one tile per court. --}}
                <g transform="translate(196 276)"><g id="scCourts" class="sc-g is-off"></g></g>
                <text id="scCourtsMore" x="0" y="292" class="sc-board-sub" style="font-size:11px;fill:#334155"></text>

                {{-- Signboard with the name they type. --}}
                <path class="st" d="M96 300 V170"/>
                <path class="st" d="M150 300 V170"/>
                <g id="scFlag"><path class="st" d="M96 170 V118"/><path class="sc-flag-cloth" d="M96 120 h34 l-8 10 8 10 h-34z" fill="#2563EB"/></g>
                <rect x="26" y="150" width="194" height="58" rx="10" fill="#fff" class="st"/>
                <rect x="32" y="156" width="182" height="46" rx="6" fill="none" stroke="#E2E8F0" stroke-width="1.2"/>
                <text id="scName" x="123" y="179" text-anchor="middle" class="sc-board-name">Your place</text>
                <text id="scCity" x="123" y="195" text-anchor="middle" class="sc-board-sub">ON HARAAN</text>

                <g id="scPin" class="sc-g is-off"><path class="st" d="M208 152 c0 0-10-9-10-17 a10 10 0 0 1 20 0 c0 8-10 17-10 17z" fill="#2563EB"/><circle cx="208" cy="135" r="3.4" fill="#fff"/></g>
                <g id="scTag" class="sc-g is-off"><path class="st-t" d="M110 208 V216 M136 208 V216"/><rect class="st-t" x="89" y="216" width="68" height="20" rx="5" fill="#FEF3C7"/><text id="scTagTxt" x="123" y="230" text-anchor="middle" class="sc-board-sub" style="font-size:9.5px;fill:#0F172A;font-weight:800">from ₹0</text></g>
                <g id="scPhoto" class="sc-g is-off"><g transform="rotate(-7 50 255)"><rect class="st-t" x="16" y="224" width="62" height="66" rx="3" fill="#fff"/><clipPath id="scClip"><rect x="21" y="229" width="52" height="45"/></clipPath><rect x="21" y="229" width="52" height="45" fill="#E2E8F0"/><image id="scPhotoImg" x="21" y="229" width="52" height="45" preserveAspectRatio="xMidYMid slice" clip-path="url(#scClip)"/></g></g>
                <g id="scParking" class="sc-g is-off"><path class="st" d="M176 262 V236"/><rect class="st-t" x="164" y="214" width="24" height="24" rx="4" fill="#2563EB"/><text x="176" y="231" text-anchor="middle" style="font:800 14px 'Plus Jakarta Sans',sans-serif;fill:#fff">P</text></g>

                <g id="scBank" class="sc-g is-off">
                    <path d="M236 74 L262 58 L288 74 Z" fill="#DBEAFE" class="st-t"/>
                    <rect x="240" y="74" width="44" height="4" fill="#fff" class="st-t"/>
                    <path class="st-t" d="M246 78 V96 M256 78 V96 M268 78 V96 M278 78 V96"/>
                    <rect x="236" y="96" width="52" height="5" fill="#fff" class="st-t"/>
                    <circle cx="262" cy="67" r="2.4" fill="#2563EB"/>
                    <path d="M123 150 Q 180 52 250 92" fill="none" stroke="#94A3B8" stroke-width="1.2" stroke-dasharray="3 4"/>
                    <circle class="sc-coin" r="4.5" fill="#FBBF24" stroke="#B45309" stroke-width="1" style="offset-path: path('M123 150 Q 180 52 250 92')"/>
                    <circle class="sc-coin" r="4.5" fill="#FBBF24" stroke="#B45309" stroke-width="1" style="offset-path: path('M123 150 Q 180 52 250 92')"/>
                    <circle class="sc-coin" r="4.5" fill="#FBBF24" stroke="#B45309" stroke-width="1" style="offset-path: path('M123 150 Q 180 52 250 92')"/>
                </g>

                <g id="scChat" class="sc-g is-off">
                    <path class="st-t" d="M372 196 h44 a6 6 0 0 1 6 6 v16 a6 6 0 0 1-6 6 h-30 l-10 8 v-8 h-4 a6 6 0 0 1-6-6 v-16 a6 6 0 0 1 6-6z" fill="#DCF8E7"/>
                    <path d="M381 206 h26 M381 213 h18" stroke="#15803D" stroke-width="2" stroke-linecap="round"/>
                    <path d="M405 218 l3 3 6-7" fill="none" stroke="#2563EB" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </g>

                {{-- The phone we'll ring. --}}
                <g id="scCall" class="sc-g is-off">
                    <path class="st sc-ring" d="M488 230 a20 20 0 0 1 0 30" />
                    <path class="st sc-ring" d="M497 222 a32 32 0 0 1 0 46" />
                    <g id="scPhone">
                        <rect x="436" y="214" width="42" height="76" rx="9" fill="#1E293B"/>
                        <rect x="440" y="222" width="34" height="58" rx="4" fill="#2563EB"/>
                        <circle cx="457" cy="240" r="7" fill="#fff" opacity=".9"/>
                        <text x="457" y="258" text-anchor="middle" class="sc-phone-txt">Haraan</text>
                        <text id="scPhoneNum" x="457" y="268" text-anchor="middle" class="sc-phone-num">calling…</text>
                    </g>
                </g>
            </svg>
        </div>

        <ul class="bp-truths">
            <li><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="15" rx="2"/><path d="M3 10h18M8 3v4M16 3v4M8 14h3"/></svg><b>Online bookings</b>Players book and pay by UPI on the app and website.</li>
            <li><svg viewBox="0 0 24 24"><path d="M4 7h16v10H4z"/><path d="M8 17v3h8v-3M7 11h4"/></svg><b>A desk for walk-ins</b>Bill walk-ins and cash at the counter, on one board.</li>
            <li><svg viewBox="0 0 24 24"><path d="M3 10l9-6 9 6M5 10v8M19 10v8M9 10v8M15 10v8M3 20h18"/></svg><b>Payouts to your bank</b>Statements and tax invoices for every settlement.</li>
        </ul>
    </section>

    <section>
        <div class="bp-card" id="card">
            @if (! $open)
                <div class="bp-closed">
                    <svg class="bp-done__art" viewBox="0 0 150 120" aria-hidden="true"><path class="st" d="M75 14 L45 44 M75 14 L105 44"/><circle cx="75" cy="12" r="4" fill="#1E293B"/><rect class="st" x="25" y="44" width="100" height="44" rx="8" fill="#FEF3C7"/><text x="75" y="72" text-anchor="middle" style="font:800 15px 'Plus Jakarta Sans';letter-spacing:.2em;fill:#0F172A">CLOSED</text></svg>
                    <h2 class="bp-h">Not taking applications right now</h2>
                    <p>{{ $closedMessage }}</p>
                    <a class="bp-btn bp-btn--go" href="{{ url('/') }}" style="text-decoration:none;width:100%"><span class="bp-btn__lbl">Back to Haraan</span></a>
                </div>
            @else
            <form id="bpForm" novalidate>
                <div id="bpHead">
                    <div class="bp-rail" id="bpRail" aria-hidden="true"></div>
                    <div class="bp-stepno"><span id="bpStepNo">Step 1</span><span class="bp-saved" id="bpSaved"><svg viewBox="0 0 24 24"><path d="M5 12l5 5 9-10"/></svg>Saved<span class="bp-saved__long">on this device</span></span></div>
                    <h2 class="bp-h" id="bpTitle">What do you run?</h2>
                    <p class="bp-sub" id="bpSub"></p>
                </div>
                <div class="bp-banner" id="bpBanner" role="alert"></div>

                <div class="bp-hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

                <div class="bp-steps">
                    {{-- What they run --}}
                    <div class="bp-step" data-key="type" data-label="What you run" data-title="What do you run?" data-sub="Pick the closest one. If you run more than one, tell us in the notes later.">
                        <div class="bp-group" data-field="business_type">
                            <div class="bp-types" role="radiogroup" aria-label="What you run">
                                @foreach ($types as $key => $t)
                                    <button type="button" class="bp-type" role="radio" aria-checked="false" data-type="{{ $key }}">
                                        <span class="bp-check"><svg viewBox="0 0 24 24"><path d="M5 12l5 5 9-10"/></svg></span>
                                        <span class="bp-type__art">
                                            @switch($key)
                                                @case('venue')
                                                    <svg viewBox="0 0 90 62"><path d="M6 56 L84 56 L74 26 L16 26 Z" fill="#9ED3B1"/><path class="st" d="M6 56 L84 56 L74 26 L16 26 Z"/><path d="M45 26 V56 M11 41 H79" stroke="#fff" stroke-width="1.8"/><path class="st" d="M45 22 V44"/><path class="st" d="M18 26 V6 M13 3 h10 v5 h-10z M72 26 V6 M67 3 h10 v5 h-10z"/></svg>
                                                    @break
                                                @case('academy')
                                                    <svg viewBox="0 0 90 62"><path class="st" d="M30 52 V14 H74 V52"/><path class="st" d="M30 24 H74 M30 34 H74 M30 44 H74 M41 14 V52 M52 14 V52 M63 14 V52" stroke-width="1" opacity=".55"/><path class="st" d="M8 56 l7-15 7 15z" fill="#FDBA74"/><circle class="st" cx="58" cy="50" r="7" fill="#fff"/><path class="st" d="M4 57 H86"/></svg>
                                                    @break
                                                @case('cafe')
                                                    <svg viewBox="0 0 90 62"><ellipse class="st" cx="45" cy="38" rx="38" ry="9" fill="#E9D5B5"/><path class="st" d="M45 47 V60 M33 60 h24"/><circle class="st" cx="28" cy="30" r="8" fill="#2563EB"/><path class="st" d="M33 36 l6 6" stroke-width="3"/><path class="st" d="M52 22 h12 v9 a6 6 0 0 1-12 0z" fill="#fff"/><path class="st" d="M56 17 c-2-3 2-5 0-8 M60 17 c-2-3 2-5 0-8" stroke-width="1.2"/></svg>
                                                    @break
                                                @case('event')
                                                    <svg viewBox="0 0 90 62"><path d="M6 40 h78 v18 h-78z" fill="#C7D7F5"/><path class="st" d="M6 40 h78 v18 h-78z M10 40 V6 M80 40 V6 M6 6 H84"/><path d="M12 8 l5 9 5-9z M30 8 l5 9 5-9z M48 8 l5 9 5-9z M66 8 l5 9 5-9z" fill="#2563EB"/><path d="M21 8 l5 9 5-9z M39 8 l5 9 5-9z M57 8 l5 9 5-9z" fill="#F59E0B"/><path class="st" d="M45 40 V26"/><circle class="st" cx="45" cy="23" r="3.5" fill="#fff"/></svg>
                                                    @break
                                            @endswitch
                                        </span>
                                        <b>{{ $t['label'] }}</b>
                                        <small>{{ $t['line'] }}</small>
                                    </button>
                                @endforeach
                            </div>
                            <div class="bp-err">Pick what you run.</div>
                        </div>
                    </div>

                    {{-- Basics — /control "Basics" --}}
                    <div class="bp-step" data-key="basics" data-label="Basics" data-title="The basics" data-sub="What players see first: the name, the sports and a one-line pitch.">
                        <div class="bp-f" data-field="business_name">
                            <label for="bpName" id="bpNameLbl">Name of your place</label>
                            <input class="bp-in" id="bpName" name="business_name" maxlength="120" autocomplete="organization" placeholder="e.g. Green Field Sports Arena">
                            <div class="bp-err"></div>
                        </div>
                        <div class="bp-group" data-field="sports">
                            <div class="bp-lbl" id="bpSportsLbl">Sports played here <em>the first one you pick is the main sport</em></div>
                            <div class="bp-chips" id="bpSports"></div>
                            <div class="bp-adder" id="bpSportsAdder"><input class="bp-in" maxlength="40" placeholder="Which sport?"><button type="button">Add</button></div>
                            <div class="bp-err"></div>
                        </div>
                        <div class="bp-f" data-field="tagline">
                            <label for="bpTagline">One-liner <em>optional · under the name on the card</em></label>
                            <input class="bp-in" id="bpTagline" name="tagline" maxlength="120" placeholder="e.g. 6 wooden indoor courts">
                            <div class="bp-err"></div>
                        </div>
                        <div class="bp-f" data-field="about">
                            <label for="bpAbout" id="bpAboutLbl">About this venue <em>optional</em></label>
                            <textarea class="bp-in" id="bpAbout" name="about" maxlength="2000" placeholder="What makes it good to play at — surface, size, coaching, parking, anything a player would ask."></textarea>
                            <div class="bp-err"></div>
                        </div>
                    </div>

                    {{-- Location — /control "Location" --}}
                    <div class="bp-step" data-key="location" data-label="Location" data-title="Where is it?" data-sub="The pin powers the “X km away” players see, and directions on the venue page.">
                        <div class="bp-row">
                            <div class="bp-f" data-field="area">
                                <label for="bpArea" id="bpAreaLbl">Area or locality</label>
                                <input class="bp-in" id="bpArea" name="area" maxlength="120" placeholder="e.g. Bandra West">
                                <div class="bp-err"></div>
                            </div>
                            <div class="bp-f" data-field="city">
                                <label for="bpCity">City</label>
                                <input class="bp-in" id="bpCity" name="city" maxlength="80" autocomplete="address-level2" placeholder="e.g. Kadapa">
                                <div class="bp-err"></div>
                            </div>
                        </div>
                        <div class="bp-f" data-field="address">
                            <label for="bpAddress">Full address <em>optional</em></label>
                            <input class="bp-in" id="bpAddress" name="address" maxlength="255" autocomplete="street-address" placeholder="Street, colony, city, state, PIN">
                            <div class="bp-err"></div>
                        </div>
                        <div class="bp-locate" id="bpLocate" data-field="latitude">
                            <span class="bp-locate__pin"><svg viewBox="0 0 24 24"><path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></svg></span>
                            <span class="bp-locate__txt" id="bpLocateTxt"><b>Standing at the venue?</b>Drop the pin from your phone’s location.</span>
                            <button type="button" class="bp-mini" id="bpLocateBtn">Use my location</button>
                        </div>
                        <div class="bp-f" data-field="map_link">
                            <label for="bpMap">Google Maps link <em>optional · Maps → Share → Copy link</em></label>
                            <input class="bp-in" id="bpMap" name="map_link" maxlength="600" inputmode="url" autocapitalize="off" spellcheck="false" placeholder="https://maps.app.goo.gl/…">
                            <div class="bp-err"></div>
                        </div>
                    </div>

                    {{-- Courts & pricing — /control "Pricing" + Courts & slots --}}
                    <div class="bp-step" data-key="courts" data-label="Courts & pricing" data-title="Courts and prices" data-sub="One row per court, pitch or table players can book, with its price for an hour.">
                        <div class="bp-group" data-field="courts_detail">
                            <div id="bpCourtList"></div>
                            <button type="button" class="bp-addcourt" id="bpAddCourt"><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg><span id="bpAddCourtLbl">Add a court</span></button>
                            <div class="bp-err"></div>
                        </div>
                        <p class="bp-from" id="bpFrom"></p>
                    </div>

                    {{-- Hours & cancellation — /control "Hours & slots" + "Cancellation policy" --}}
                    <div class="bp-step" data-key="hours" data-label="Hours & cancellation" data-title="When are you open?" data-sub="Bookable start-times are made from these hours. Open past midnight? Set the real closing time.">
                        <div class="bp-group" data-field="hours_json">
                            <div class="bp-days" id="bpDays"></div>
                            <button type="button" class="bp-linkbtn" id="bpCopyMon">Use Monday’s hours for every open day</button>
                            <div class="bp-err"></div>
                        </div>
                        <div class="bp-group" data-field="slot_minutes">
                            <div class="bp-lbl">Players book in blocks of</div>
                            <div class="bp-chips" data-single="slot_minutes" data-num>
                                @foreach (PA::SLOT_LENGTHS as $k => $l)
                                    <button type="button" class="bp-chip" data-val="{{ $k }}"><svg viewBox="0 0 24 24"><path d="M5 12l5 5 9-10"/></svg>{{ $l }}</button>
                                @endforeach
                            </div>
                        </div>
                        <div class="bp-group" data-field="cancel_free_hours">
                            <div class="bp-lbl">Free cancellation <em>shown in “Good to know”</em></div>
                            <div class="bp-chips" data-single="cancel_free_hours" data-num data-keep>
                                <button type="button" class="bp-chip" data-val=""><svg viewBox="0 0 24 24"><path d="M5 12l5 5 9-10"/></svg>No refunds</button>
                                @foreach ([2, 6, 12, 24, 48] as $h)
                                    <button type="button" class="bp-chip" data-val="{{ $h }}"><svg viewBox="0 0 24 24"><path d="M5 12l5 5 9-10"/></svg>Up to {{ $h }} h before</button>
                                @endforeach
                            </div>
                        </div>
                        <div class="bp-group" data-field="cancel_refund_percent" id="bpRefundGroup">
                            <div class="bp-lbl">Cancelled later than that, they get back</div>
                            <div class="bp-chips" data-single="cancel_refund_percent" data-num data-keep>
                                @foreach ([0, 25, 50, 75] as $p)
                                    <button type="button" class="bp-chip" data-val="{{ $p }}"><svg viewBox="0 0 24 24"><path d="M5 12l5 5 9-10"/></svg>{{ $p === 0 ? 'Nothing' : $p . '%' }}</button>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- Photos — /control "Photos" --}}
                    <div class="bp-step" data-key="photos" data-label="Photos" data-title="Show the place" data-sub="The first photo is the cover. Daylight shots of the courts work best — a venue goes live with at least one.">
                        <div class="bp-group" data-field="photos">
                            <label class="bp-drop" id="bpDrop">
                                <svg viewBox="0 0 64 50" aria-hidden="true"><rect x="10" y="6" width="44" height="36" rx="3" fill="#fff" stroke="#1E293B" stroke-width="1.8" transform="rotate(-6 32 24)"/><rect x="14" y="9" width="40" height="34" rx="3" fill="#fff" stroke="#1E293B" stroke-width="1.8"/><path d="M18 36 l9-10 7 7 5-5 11 8z" fill="#9ED3B1"/><circle cx="44" cy="17" r="3.5" fill="#FDE68A"/><path d="M14 36 h40" stroke="#1E293B" stroke-width="1.4"/></svg>
                                <b>Add photos</b>
                                <small>Tap to pick, or drop them here · up to {{ $maxPhotos }}, JPG, PNG or WebP</small>
                                <input type="file" id="bpPhotoIn" accept="image/jpeg,image/png,image/webp" multiple>
                            </label>
                            <div class="bp-photos" id="bpPhotos"></div>
                            <div class="bp-photo-warn" id="bpPhotoWarn" hidden></div>
                            <div class="bp-hint" id="bpPhotoHint">Photos stay on this page only until you send — if you reload, add them again.</div>
                            <div class="bp-err"></div>
                        </div>
                    </div>

                    {{-- Amenities & rules — /control "Amenities" + "Good to know" --}}
                    <div class="bp-step" data-key="extras" data-label="Amenities & rules" data-title="Amenities and house rules" data-sub="Each amenity gets its own icon on the venue page; each rule becomes a line in “Good to know”.">
                        <div class="bp-group" data-field="amenities">
                            <div class="bp-lbl">What you have <em>pick all</em></div>
                            <div class="bp-chips" id="bpAmenities"></div>
                            <div class="bp-adder" id="bpAmenitiesAdder"><input class="bp-in" maxlength="40" placeholder="Anything not listed"><button type="button">Add</button></div>
                        </div>
                        <div class="bp-group" data-field="rules">
                            <div class="bp-lbl">Good to know <em>house rules</em></div>
                            <div class="bp-chips" id="bpRules"></div>
                            <div class="bp-adder" id="bpRulesAdder"><input class="bp-in" maxlength="120" placeholder="A rule specific to your place"><button type="button">Add</button></div>
                        </div>
                    </div>

                    {{-- How it runs today --}}
                    <div class="bp-step" data-key="today" data-label="Today" data-title="How do bookings come in today?" data-sub="No wrong answers — this tells us what to set up first for you.">
                        <div class="bp-group" data-field="booking_channels">
                            <div class="bp-lbl">Where bookings come from <em>pick all</em></div>
                            <div class="bp-chips" data-multi="booking_channels">
                                @foreach (PA::BOOKING_CHANNELS as $k => $l)
                                    <button type="button" class="bp-chip" data-val="{{ $k }}"><svg viewBox="0 0 24 24"><path d="M5 12l5 5 9-10"/></svg>{{ $l }}</button>
                                @endforeach
                            </div>
                        </div>
                        <div class="bp-group" data-field="monthly_bookings">
                            <div class="bp-lbl" id="bpMonthlyLbl">Bookings in a usual month</div>
                            <div class="bp-chips" data-single="monthly_bookings">
                                @foreach (PA::MONTHLY_BOOKINGS as $k => $l)
                                    <button type="button" class="bp-chip" data-val="{{ $k }}"><svg viewBox="0 0 24 24"><path d="M5 12l5 5 9-10"/></svg>{{ $l }}</button>
                                @endforeach
                            </div>
                        </div>
                        <div class="bp-group" data-field="goals">
                            <div class="bp-lbl">What would help most <em>pick up to three</em></div>
                            <div class="bp-chips" data-multi="goals" data-max="3">
                                @foreach (PA::GOALS as $k => $l)
                                    <button type="button" class="bp-chip" data-val="{{ $k }}"><svg viewBox="0 0 24 24"><path d="M5 12l5 5 9-10"/></svg>{{ $l }}</button>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- Who to call --}}
                    <div class="bp-step" data-key="contact" data-label="Contact" data-title="Who should we call?" data-sub="A person from Haraan calls you within {{ $callbackHours }} hours and puts your listing live with you.">
                        <div class="bp-f" data-field="contact_name">
                            <label for="bpContact">Your name</label>
                            <input class="bp-in" id="bpContact" name="contact_name" maxlength="100" autocomplete="name" placeholder="Full name">
                            <div class="bp-err"></div>
                        </div>
                        <div class="bp-group" data-field="contact_role">
                            <div class="bp-lbl">You are the</div>
                            <div class="bp-chips" data-single="contact_role">
                                @foreach (PA::CONTACT_ROLES as $k => $l)
                                    <button type="button" class="bp-chip" data-val="{{ $k }}"><svg viewBox="0 0 24 24"><path d="M5 12l5 5 9-10"/></svg>{{ $l }}</button>
                                @endforeach
                            </div>
                        </div>
                        <div class="bp-f" data-field="phone">
                            <label for="bpPhone">Mobile number</label>
                            <div class="bp-phone" id="bpPhoneWrap">
                                <span class="bp-phone__cc"><svg viewBox="0 0 30 20"><rect width="30" height="20" fill="#fff"/><rect width="30" height="6.67" fill="#FF9933"/><rect y="13.33" width="30" height="6.67" fill="#138808"/><circle cx="15" cy="10" r="2.6" fill="none" stroke="#000080" stroke-width=".8"/></svg>+91</span>
                                <input id="bpPhone" name="phone" inputmode="tel" autocomplete="tel-national" placeholder="98765 43210" maxlength="16">
                                <span class="bp-phone__ok"><svg viewBox="0 0 24 24"><path d="M5 12l5 5 9-10"/></svg></span>
                            </div>
                            <div class="bp-hint">We call this number only about your listing.</div>
                            <div class="bp-err"></div>
                        </div>
                        <div class="bp-wa bp-group" data-field="whatsapp_phone">
                            <div class="bp-wa__head">
                                <span class="bp-wa__icon"><svg viewBox="0 0 24 24"><path d="M4 20l1.3-3.9A8 8 0 1 1 8 18.8z"/><path d="M9 9.5c.3 1.8 2.6 4.3 4.6 4.9l1.1-1.2 1.8.9c-.2.9-1 1.7-1.9 1.7-3 0-6.7-3.6-6.7-6.6 0-.9.8-1.7 1.6-1.9l.9 1.8z"/></svg></span>
                                <span><b>WhatsApp updates</b><small id="bpWaSub">Your reference number and every update from Haraan about your listing.</small></span>
                            </div>
                            <div class="bp-chips" data-single="whatsapp_same" data-keep data-bool>
                                <button type="button" class="bp-chip" data-val="1"><svg viewBox="0 0 24 24"><path d="M5 12l5 5 9-10"/></svg>On my mobile number</button>
                                <button type="button" class="bp-chip" data-val="0"><svg viewBox="0 0 24 24"><path d="M5 12l5 5 9-10"/></svg>On another number</button>
                            </div>
                            <div class="bp-phone" id="bpWaWrap" hidden>
                                <span class="bp-phone__cc"><svg viewBox="0 0 30 20"><rect width="30" height="20" fill="#fff"/><rect width="30" height="6.67" fill="#FF9933"/><rect y="13.33" width="30" height="6.67" fill="#138808"/><circle cx="15" cy="10" r="2.6" fill="none" stroke="#000080" stroke-width=".8"/></svg>+91</span>
                                <input id="bpWaPhone" name="whatsapp_phone" inputmode="tel" autocomplete="off" placeholder="WhatsApp number" maxlength="16" aria-label="WhatsApp number">
                                <span class="bp-phone__ok"><svg viewBox="0 0 24 24"><path d="M5 12l5 5 9-10"/></svg></span>
                            </div>
                            <div class="bp-err"></div>
                        </div>
                        <div class="bp-f" data-field="email">
                            <label for="bpEmail">Email <em>optional — for your invoices later</em></label>
                            <input class="bp-in" type="email" id="bpEmail" name="email" maxlength="160" autocomplete="email" autocapitalize="off" spellcheck="false" placeholder="you@example.com">
                            <div class="bp-err"></div>
                        </div>
                        <div class="bp-group" data-field="call_time">
                            <div class="bp-lbl">Best time to call</div>
                            <div class="bp-chips" data-single="call_time">
                                @foreach (PA::CALL_TIMES as $k => $l)
                                    <button type="button" class="bp-chip" data-val="{{ $k }}"><svg viewBox="0 0 24 24"><path d="M5 12l5 5 9-10"/></svg>{{ $l }}</button>
                                @endforeach
                            </div>
                        </div>
                        <div class="bp-f" data-field="gstin">
                            <label for="bpGst">GSTIN <em>optional</em></label>
                            <input class="bp-in" id="bpGst" name="gstin" maxlength="15" autocapitalize="characters" spellcheck="false" placeholder="37ABCDE1234F1Z5" style="text-transform:uppercase;letter-spacing:.06em">
                            <div class="bp-err"></div>
                        </div>
                        <div class="bp-f" data-field="note">
                            <label for="bpNote">Anything else <em>optional</em></label>
                            <textarea class="bp-in" id="bpNote" name="note" maxlength="1000" placeholder="Second branch, coaching batches, anything we should know"></textarea>
                            <div class="bp-err"></div>
                        </div>
                    </div>

                    {{-- Payouts — becomes the partner's settlement account (Finance verifies it). --}}
                    <div class="bp-step" data-key="payouts" data-label="Payouts" data-title="Where should we send your money?" data-sub="Online bookings are settled to this account. Haraan finance checks it before the first transfer.">
                        <div class="bp-group" data-field="payout_method">
                            <div class="bp-chips" data-single="payout_method" data-keep>
                                @foreach (PA::PAYOUT_METHODS as $k => $l)
                                    <button type="button" class="bp-chip" data-val="{{ $k }}"><svg viewBox="0 0 24 24"><path d="M5 12l5 5 9-10"/></svg>{{ $l }}</button>
                                @endforeach
                            </div>
                        </div>

                        {{-- The cheque leaf fills in as they type. --}}
                        <div class="bp-cheque" id="bpCheque" aria-hidden="true">
                            <div class="bp-cheque__top">
                                <span class="bp-cheque__bank"><svg viewBox="0 0 24 24"><path d="M3 10l9-6 9 6M5 10v8M19 10v8M9 10v8M15 10v8M3 20h18"/></svg><b id="chBank">Your bank</b></span>
                                <span class="bp-cheque__ifsc">IFSC <b id="chIfsc">— — — —</b></span>
                            </div>
                            <div class="bp-cheque__branch" id="chBranch"></div>
                            <div class="bp-cheque__pay"><small>PAY TO</small><span id="chName">Account holder’s name</span></div>
                            <div class="bp-cheque__row">
                                <div class="bp-cheque__ac"><small>A/C NO.</small><b id="chAc">•••• •••• ••••</b></div>
                                <div class="bp-cheque__upi" id="chUpiWrap"><small>UPI</small><b id="chUpi">—</b></div>
                            </div>
                            <div class="bp-cheque__ok" id="chOk">READY</div>
                        </div>

                        <div id="bpBankFields">
                            <div class="bp-f" data-field="ifsc_code">
                                <label for="bpIfsc">IFSC code <em>printed on your cheque book or passbook</em></label>
                                <input class="bp-in bp-in--mono" id="bpIfsc" name="ifsc_code" maxlength="11" autocapitalize="characters" spellcheck="false" autocomplete="off" placeholder="HDFC0001234">
                                <div class="bp-hint" id="bpIfscHint">We fill in the bank from the IFSC.</div>
                                <div class="bp-err"></div>
                            </div>
                            <div class="bp-f" data-field="bank_name">
                                <label for="bpBank">Bank</label>
                                <select class="bp-in bp-select" id="bpBank" name="bank_name">
                                    <option value="">Pick your bank</option>
                                    @foreach ($banks as $b)
                                        <option value="{{ $b }}">{{ $b }}</option>
                                    @endforeach
                                    <option value="__other">Another bank…</option>
                                </select>
                                <input class="bp-in" id="bpBankOther" maxlength="80" placeholder="Name of your bank" style="margin-top:8px" hidden>
                                <div class="bp-err"></div>
                            </div>
                        </div>
                        <div class="bp-f" data-field="account_holder" id="bpHolderField">
                            <label for="bpHolder">Account holder’s name <em>exactly as the bank has it</em></label>
                            <input class="bp-in" id="bpHolder" name="account_holder" maxlength="100" autocomplete="off" placeholder="e.g. Green Field Sports LLP">
                            <div class="bp-err"></div>
                        </div>
                        <div class="bp-row" id="bpAcRow">
                            <div class="bp-f" data-field="account_number">
                                <label for="bpAc">Account number</label>
                                <input class="bp-in bp-in--mono" id="bpAc" name="account_number" inputmode="numeric" maxlength="22" autocomplete="off" placeholder="5010 0012 3443 21">
                                <div class="bp-err"></div>
                            </div>
                            <div class="bp-f" data-field="account_number_confirmation">
                                <label for="bpAc2">Type it again</label>
                                <input class="bp-in bp-in--mono" id="bpAc2" name="account_number_confirmation" inputmode="numeric" maxlength="22" autocomplete="off" placeholder="Same number">
                                <div class="bp-err"></div>
                            </div>
                        </div>
                        <div class="bp-f" data-field="upi_vpa" id="bpUpiField">
                            <label for="bpUpi" id="bpUpiLbl">UPI ID <em>optional</em></label>
                            <input class="bp-in" id="bpUpi" name="upi_vpa" maxlength="120" inputmode="email" autocapitalize="off" spellcheck="false" autocomplete="off" placeholder="name@okhdfcbank">
                            <div class="bp-err"></div>
                        </div>
                        <div class="bp-later" id="bpLater">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/></svg>
                            <span>No problem — we’ll take your bank or UPI details on the call. Bookings can’t be paid out until they’re added.</span>
                        </div>
                        <p class="bp-hint bp-secure"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>Stored encrypted. Only Haraan finance sees the full number.</p>
                    </div>

                    {{-- Review the slip, then stamp it. --}}
                    <div class="bp-step" data-key="review" data-label="Review" data-title="Check your application" data-sub="This is exactly what reaches our partner team — and what your venue page starts from.">
                        <div class="bp-slip" id="bpSlip">
                            <div class="bp-slip__head"><b id="slName">—</b><span>APPLICATION</span></div>
                            <dl id="slBody"></dl>
                            <div class="bp-stamp" id="bpStamp">RECEIVED<small id="bpStampRef">HARAAN</small></div>
                        </div>
                        <div class="bp-group" data-field="consent">
                            <label class="bp-consent">
                                <input type="checkbox" name="consent" id="bpConsent" value="1">
                                <span class="bp-box"><svg viewBox="0 0 24 24"><path d="M5 12l5 5 9-10"/></svg></span>
                                <span>Haraan may call and WhatsApp me about listing this business, and use these details and photos for its listing. I agree to the <a href="{{ route('site.legal', 'terms') }}" target="_blank" rel="noopener">Terms</a> and <a href="{{ route('site.legal', 'privacy') }}" target="_blank" rel="noopener">Privacy Policy</a>.</span>
                            </label>
                            <div class="bp-err">Tick the box so we can call you.</div>
                        </div>
                    </div>
                </div>

                <div class="bp-nav">
                    <button type="button" class="bp-btn bp-btn--back" id="bpBack" aria-label="Back" hidden><svg viewBox="0 0 24 24"><path d="M15 6l-6 6 6 6"/></svg></button>
                    <button type="submit" class="bp-btn bp-btn--go" id="bpNext"><span class="bp-btn__lbl">Continue</span><svg viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg><span class="bp-spin"></span></button>
                </div>
            </form>

            <div class="bp-done" id="bpDone" aria-live="polite">
                <svg class="bp-done__art" viewBox="0 0 150 120" aria-hidden="true">
                    <rect x="22" y="40" width="106" height="66" rx="8" fill="#EFF5FF"/>
                    <path class="st draw" d="M22 48 a8 8 0 0 1 8-8 h90 a8 8 0 0 1 8 8 v50 a8 8 0 0 1-8 8 h-90 a8 8 0 0 1-8-8z"/>
                    <path class="st draw" d="M24 46 L75 80 L126 46" style="animation-delay:.45s"/>
                    <circle cx="112" cy="36" r="17" fill="#2563EB"/>
                    <path d="M104 36 l6 6 11-12" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="draw" style="animation-delay:.8s;stroke-dasharray:40;stroke-dashoffset:40"/>
                </svg>
                <h2>{{ $successTitle }}</h2>
                <p>{{ $successBody }}</p>
                <div class="bp-ref"><span>REFERENCE</span><b id="bpRef">—</b></div>
                <ol class="bp-next">
                    <li><i><svg viewBox="0 0 24 24"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/></svg></i><span><b>We call you</b><small id="bpNextCall">Within {{ $callbackHours }} hours, from a Haraan number.</small></span></li>
                    <li><i><svg viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M8 9h8M8 13h5"/></svg></i><span><b>We build your page from these answers</b><small>Courts, prices, hours and photos — you check it with us on the call.</small></span></li>
                    <li><i><svg viewBox="0 0 24 24"><path d="M5 12l5 5 9-10"/></svg></i><span><b>You sign in and take bookings</b><small>Your partner sign-in goes to the number you gave us.</small></span></li>
                </ol>
                <div class="bp-nav">
                    <a class="bp-btn bp-btn--go" href="{{ url('/') }}"><span class="bp-btn__lbl">Back to Haraan</span></a>
                    <a class="bp-btn bp-btn--ghost" href="{{ url('/partner/login') }}">Partner sign in</a>
                </div>
            </div>
            @endif
        </div>
    </section>
</main>

@if ($open)
<script>
(() => {
    const CFG = @json($config);
    const DRAFT_KEY = 'hrn.partnerApply.v2';
    const $ = (s, r = document) => r.querySelector(s);
    const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
    const form = $('#bpForm');
    const scene = $('#scene');
    const buzz = (p) => { try { navigator.vibrate && navigator.vibrate(p); } catch (e) {} };
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const DAYS = CFG.days; // { Mon: 'Monday', … }

    // Steps follow the /control venue form, top to bottom. Event hosts have no place to list.
    const ORDER = $$('.bp-step', form).map((s) => s.dataset.key);
    const PLACE_ONLY = ['courts', 'hours', 'photos', 'extras'];
    const isPlace = () => state.business_type !== 'event';
    const flow = () => ORDER.filter((k) => (isPlace() || !PLACE_ONLY.includes(k)) && (CFG.askPayouts || k !== 'payouts'));
    const reached = (k) => ORDER.indexOf(step) >= ORDER.indexOf(k);

    const unit = () => (state.business_type === 'cafe' ? 'Table' : 'Court');
    const blankHours = () => Object.fromEntries(Object.keys(DAYS).map((d) => [d, { on: true, open: '06:00', close: '23:00' }]));
    const state = {
        business_type: '', business_name: '', sports: [], tagline: '', about: '',
        area: '', city: '', address: '', map_link: '', latitude: null, longitude: null,
        courts: [{ name: 'Court 1', sports: [], price: '' }],
        hours: blankHours(), slot_minutes: 60, cancel_free_hours: null, cancel_refund_percent: null,
        amenities: [], rules: [],
        booking_channels: [], monthly_bookings: '', goals: [],
        contact_name: CFG.prefill.name || '', contact_role: '', phone: (CFG.prefill.phone || '').replace(/^\+91/, ''),
        email: CFG.prefill.email || '', call_time: '', gstin: '', note: '',
        whatsapp_same: true, whatsapp_phone: '',
        payout_method: 'bank', bank_name: '', account_holder: '', account_number: '', account_number_confirmation: '',
        ifsc_code: '', upi_vpa: '',
    };
    let photos = []; // [{ blob, url }] — memory only, never in the draft
    let step = 'type';

    // ── Draft: survives a refresh or a dropped connection on a phone ──────────────
    try {
        const d = JSON.parse(localStorage.getItem(DRAFT_KEY) || 'null');
        if (d && typeof d === 'object' && d.state) {
            Object.assign(state, d.state);
            if (ORDER.includes(d.step)) step = d.step;
        }
    } catch (e) {}
    let saveT;
    const save = () => {
        clearTimeout(saveT);
        saveT = setTimeout(() => {
            try {
                const { account_number, account_number_confirmation, ...keep } = state;
                localStorage.setItem(DRAFT_KEY, JSON.stringify({ state: keep, step }));
                const s = $('#bpSaved'); s.classList.add('is-on'); clearTimeout(s._t); s._t = setTimeout(() => s.classList.remove('is-on'), 1600);
            } catch (e) {}
        }, 350);
    };

    const digits = (v) => (v || '').replace(/\D/g, '');
    const phoneOk = () => /^[6-9]\d{9}$/.test(digits(state.phone).replace(/^(91|0)(?=\d{10}$)/, ''));
    const fmtPhone = (v) => { const d = digits(v).slice(0, 10); return d.length > 5 ? d.slice(0, 5) + ' ' + d.slice(5) : d; };
    const fmtTime = (t) => { if (!t) return ''; const [h, m] = t.split(':').map(Number); return ((h % 12) || 12) + ':' + String(m).padStart(2, '0') + ' ' + (h >= 12 ? 'PM' : 'AM'); };
    const rupees = (n) => '₹' + Number(n).toLocaleString('en-IN');
    const minPrice = () => { const p = state.courts.map((c) => +c.price).filter((n) => n > 0); return p.length ? Math.min(...p) : 0; };
    const CHECK = '<svg viewBox="0 0 24 24"><path d="M5 12l5 5 9-10"/></svg>';

    // ── Plain inputs ─────────────────────────────────────────────────────────────
    ['business_name', 'tagline', 'about', 'area', 'city', 'address', 'map_link', 'contact_name', 'email', 'gstin', 'note', 'account_holder', 'ifsc_code', 'upi_vpa'].forEach((n) => {
        const el = form.elements[n]; if (el) el.value = state[n] || '';
    });
    $('#bpPhone').value = fmtPhone(state.phone);

    // ── Pickable lists that can grow: sports, amenities, rules ───────────────────
    // The options are the /control venue form's own lists; anything typed joins them.
    const lists = {
        sports: { box: '#bpSports', adder: '#bpSportsAdder', options: CFG.sports, main: true },
        amenities: { box: '#bpAmenities', adder: '#bpAmenitiesAdder', options: CFG.amenities },
        rules: { box: '#bpRules', adder: '#bpRulesAdder', options: CFG.rules },
    };
    const paintList = (key) => {
        const L = lists[key];
        const arr = state[key];
        const extra = arr.filter((v) => !L.options.some((o) => o.toLowerCase() === v.toLowerCase()));
        $(L.box).innerHTML = [...L.options, ...extra].map((o) => {
            const on = arr.includes(o);
            const main = L.main && on && arr[0] === o && arr.length > 1 ? '<span class="bp-main">MAIN</span>' : '';
            return `<button type="button" class="bp-chip${on ? ' is-on' : ''}" data-v="${esc(o)}">${CHECK}${esc(o)}${main}</button>`;
        }).join('') + '<button type="button" class="bp-chip bp-chip--add" data-add>+ Other</button>';
    };
    Object.entries(lists).forEach(([key, L]) => {
        $(L.box).addEventListener('click', (e) => {
            const c = e.target.closest('button'); if (!c) return;
            if (c.hasAttribute('data-add')) { $(L.adder).classList.add('is-on'); $('input', $(L.adder)).focus(); return; }
            const v = c.dataset.v; const arr = state[key]; const i = arr.indexOf(v);
            i >= 0 ? arr.splice(i, 1) : arr.push(v);
            buzz(6); setErr(key, ''); paintList(key);
            if (key === 'sports') paintCourts();
            paintScene(); save();
        });
        const add = () => {
            const inp = $('input', $(L.adder));
            const v = inp.value.trim().replace(/\s+/g, ' ');
            if (!v) return;
            const known = [...L.options, ...state[key]].find((o) => o.toLowerCase() === v.toLowerCase()) || v;
            if (!state[key].includes(known)) state[key].push(known);
            inp.value = ''; $(L.adder).classList.remove('is-on');
            buzz(8); paintList(key); if (key === 'sports') paintCourts(); paintScene(); save();
        };
        $('button', $(L.adder)).addEventListener('click', add);
        $('input', $(L.adder)).addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); add(); } });
        paintList(key);
    });

    // ── Location pin ─────────────────────────────────────────────────────────────
    const paintPin = () => {
        const set = state.latitude !== null && state.longitude !== null;
        $('#bpLocate').classList.toggle('is-set', set);
        $('#bpLocateTxt').innerHTML = set
            ? `<b>Pinned</b>${(+state.latitude).toFixed(5)}, ${(+state.longitude).toFixed(5)}`
            : '<b>Standing at the venue?</b>Drop the pin from your phone’s location.';
        $('#bpLocateBtn').textContent = set ? 'Clear' : 'Use my location';
    };
    $('#bpLocateBtn').addEventListener('click', () => {
        const btn = $('#bpLocateBtn');
        if (state.latitude !== null) { state.latitude = state.longitude = null; paintPin(); paintScene(); save(); return; }
        if (!navigator.geolocation) { setErr('latitude', ''); $('#bpLocateTxt').innerHTML = '<b>Location isn’t available here</b>Paste the Google Maps link below instead.'; return; }
        btn.textContent = 'Locating…'; btn.disabled = true;
        navigator.geolocation.getCurrentPosition((pos) => {
            state.latitude = +pos.coords.latitude.toFixed(7); state.longitude = +pos.coords.longitude.toFixed(7);
            btn.disabled = false; buzz([8, 30, 14]); paintPin(); paintScene(); save();
        }, () => {
            btn.disabled = false; btn.textContent = 'Try again';
            $('#bpLocateTxt').innerHTML = '<b>Couldn’t read your location</b>Allow location, or paste the Google Maps link below.';
        }, { enableHighAccuracy: true, timeout: 12000, maximumAge: 60000 });
    });
    // A full Maps link carries the coordinates; short share links don't, and that's fine.
    const pinFromLink = (link) => {
        const m = link.match(/@(-?\d{1,2}\.\d+),(-?\d{1,3}\.\d+)/) || link.match(/[?&](?:q|ll|query|destination)=(-?\d{1,2}\.\d+)(?:,|%2C)(-?\d{1,3}\.\d+)/i);
        if (m && state.latitude === null) { state.latitude = +m[1]; state.longitude = +m[2]; paintPin(); }
    };

    // ── Courts & prices ──────────────────────────────────────────────────────────
    const paintCourts = () => {
        const many = state.sports.length > 1;
        $('#bpCourtList').innerHTML = state.courts.map((c, i) => `
            <div class="bp-court" data-i="${i}">
                <div class="bp-court__top">
                    <span class="bp-court__no">${i + 1}</span>
                    <input class="bp-court__name" data-k="name" value="${esc(c.name)}" maxlength="40" aria-label="Name of ${esc(unit().toLowerCase())} ${i + 1}">
                    <label class="bp-court__price"><span>₹</span><input data-k="price" inputmode="numeric" value="${esc(c.price)}" placeholder="0" aria-label="Price for an hour"><small>/hr</small></label>
                    ${state.courts.length > 1 ? `<button type="button" class="bp-court__del" data-del aria-label="Remove"><svg viewBox="0 0 24 24"><path d="M5 7h14M10 11v6M14 11v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3"/></svg></button>` : ''}
                </div>
                ${many ? `<div class="bp-chips">${state.sports.map((s) => `<button type="button" class="bp-chip${!c.sports.length || c.sports.includes(s) ? ' is-on' : ''}" data-cs="${esc(s)}">${CHECK}${esc(s)}</button>`).join('')}</div>` : ''}
            </div>`).join('');
        $('#bpAddCourtLbl').textContent = 'Add a ' + unit().toLowerCase();
        paintFrom();
    };
    const paintFrom = () => {
        const p = minPrice();
        $('#bpFrom').innerHTML = p ? `Players will see <b>from ${rupees(p)} an hour</b> on your card.` : (state.business_type === 'academy' ? 'Coaching fees are set up with you on the call — hourly prices are only for courts you rent out.' : 'Add the price for an hour on each one.');
    };
    $('#bpCourtList').addEventListener('input', (e) => {
        const row = e.target.closest('[data-i]'); if (!row) return;
        const c = state.courts[+row.dataset.i];
        if (e.target.dataset.k === 'price') { e.target.value = digits(e.target.value).slice(0, 6); c.price = e.target.value; paintFrom(); }
        else c.name = e.target.value;
        setErr('courts_detail', ''); paintScene(); save();
        e.stopPropagation();
    });
    $('#bpCourtList').addEventListener('click', (e) => {
        const row = e.target.closest('[data-i]'); if (!row) return;
        const i = +row.dataset.i;
        if (e.target.closest('[data-del]')) {
            state.courts.splice(i, 1); buzz([6, 20, 6]);
        } else if (e.target.closest('[data-cs]')) {
            const s = e.target.closest('[data-cs]').dataset.cs;
            // No pick yet means "every sport here"; the first tap narrows from that.
            if (!state.courts[i].sports.length) state.courts[i].sports = [...state.sports];
            const arr = state.courts[i].sports; const j = arr.indexOf(s);
            j >= 0 ? arr.splice(j, 1) : arr.push(s); buzz(5);
        } else return;
        paintCourts(); paintScene(); save();
    });
    $('#bpAddCourt').addEventListener('click', () => {
        if (state.courts.length >= 50) return;
        const last = state.courts[state.courts.length - 1] || { sports: [], price: '' };
        state.courts.push({ name: unit() + ' ' + (state.courts.length + 1), sports: [...last.sports], price: last.price });
        buzz(8); paintCourts(); paintScene(); save();
        const rows = $$('.bp-court__name'); rows[rows.length - 1]?.select();
    });

    // ── Hours ────────────────────────────────────────────────────────────────────
    $('#bpDays').innerHTML = Object.entries(DAYS).map(([k, full]) => `
        <div class="bp-day" data-day="${k}">
            <button type="button" class="bp-sw" role="switch" aria-checked="true" aria-label="Open on ${full}"><i></i>${k}</button>
            <input type="time" class="bp-in bp-day__t" data-t="open" step="900" aria-label="${full} opens">
            <input type="time" class="bp-in bp-day__t" data-t="close" step="900" aria-label="${full} closes">
            <span class="bp-day__closed">Closed</span>
        </div>`).join('');
    const paintHours = () => {
        $$('.bp-day').forEach((row) => {
            const h = state.hours[row.dataset.day];
            row.classList.toggle('is-closed', !h.on);
            $('.bp-sw', row).setAttribute('aria-checked', h.on);
            $('[data-t=open]', row).value = h.open; $('[data-t=close]', row).value = h.close;
        });
        $('#bpRefundGroup').hidden = state.cancel_free_hours === null;
    };
    $('#bpDays').addEventListener('click', (e) => {
        const sw = e.target.closest('.bp-sw'); if (!sw) return;
        const h = state.hours[sw.closest('[data-day]').dataset.day]; h.on = !h.on;
        buzz(6); setErr('hours_json', ''); paintHours(); paintScene(); save();
    });
    $('#bpDays').addEventListener('input', (e) => {
        const t = e.target.dataset.t; if (!t) return;
        state.hours[e.target.closest('[data-day]').dataset.day][t] = e.target.value;
        setErr('hours_json', ''); paintScene(); save(); e.stopPropagation();
    });
    $('#bpCopyMon').addEventListener('click', () => {
        const m = state.hours.Mon;
        Object.values(state.hours).forEach((h) => { if (h.on) { h.open = m.open; h.close = m.close; } });
        buzz(8); paintHours(); paintScene(); save();
    });

    // ── Photos ───────────────────────────────────────────────────────────────────
    // Shrunk on the phone before upload: a 12 MP shot becomes a ~300 KB JPEG.
    // The size check reads the photo as picked, before shrinking.
    const photoIssue = (w, h) => {
        const ck = CFG.photoCheck;
        if (!ck) return null;
        if (ck.landscape && w <= h) return 'tall';
        if (w < ck.w || h < ck.h) return 'small';
        return null;
    };
    const shrink = (file) => new Promise((resolve) => {
        const img = new Image(); const url = URL.createObjectURL(file);
        img.onload = () => {
            const issue = photoIssue(img.naturalWidth, img.naturalHeight);
            const max = 1600; const k = Math.min(1, max / Math.max(img.naturalWidth, img.naturalHeight));
            const c = document.createElement('canvas'); c.width = Math.round(img.naturalWidth * k); c.height = Math.round(img.naturalHeight * k);
            c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
            URL.revokeObjectURL(url);
            c.toBlob((b) => resolve({ blob: b || file, issue }), 'image/jpeg', 0.84);
        };
        img.onerror = () => { URL.revokeObjectURL(url); resolve(null); };
        img.src = url;
    });
    const addPhotos = async (files) => {
        setErr('photos', '');
        const room = CFG.maxPhotos - photos.length;
        const list = Array.from(files).filter((f) => /^image\/(jpeg|png|webp)$/.test(f.type));
        if (list.length < files.length) setErr('photos', 'Only JPG, PNG or WebP photos.');
        if (list.length > room) setErr('photos', `Up to ${CFG.maxPhotos} photos — the first ${room || 'ones'} were kept.`);
        for (const f of list.slice(0, Math.max(0, room))) {
            const shot = await shrink(f);
            if (!shot) continue;
            photos.push({ blob: shot.blob, issue: shot.issue, url: URL.createObjectURL(shot.blob) });
            buzz(6); paintPhotos(); paintScene();
        }
    };
    const paintPhotos = () => {
        $('#bpPhotos').innerHTML = photos.map((p, i) => `
            <div class="bp-photo${p.issue ? ' is-weak' : ''}" data-i="${i}"><img src="${p.url}" alt="">
                ${p.issue ? `<span class="bp-photo__flag">${p.issue === 'tall' ? 'TALL' : 'SMALL'}</span>` : ''}
                ${i === 0 ? '<span class="bp-photo__cover">COVER</span>' : '<button type="button" class="bp-photo__make" data-cover>Make cover</button>'}
                <button type="button" class="bp-photo__x" data-x aria-label="Remove photo"><svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
            </div>`).join('');
        $('#bpDrop').hidden = photos.length >= CFG.maxPhotos;
        const tall = photos.some((p) => p.issue === 'tall'), small = photos.some((p) => p.issue === 'small');
        const warn = $('#bpPhotoWarn');
        warn.hidden = !(tall || small);
        warn.textContent = [
            tall ? 'Photos marked TALL are portrait. Customers see venue photos wide, so these get cropped to a thin strip. Turn the phone sideways and take them again.' : '',
            small ? `Photos marked SMALL are under ${CFG.photoCheck.w}×${CFG.photoCheck.h} px and will look blurry. Photos forwarded on WhatsApp get shrunk, so use the originals from your camera.` : '',
        ].filter(Boolean).join(' ') + ' You can still send them, and we can sort photos out on the call.';
    };
    $('#bpPhotoIn').addEventListener('change', (e) => { addPhotos(e.target.files); e.target.value = ''; });
    const drop = $('#bpDrop');
    ['dragenter', 'dragover'].forEach((t) => drop.addEventListener(t, (e) => { e.preventDefault(); drop.classList.add('is-over'); }));
    ['dragleave', 'drop'].forEach((t) => drop.addEventListener(t, (e) => { e.preventDefault(); drop.classList.remove('is-over'); }));
    drop.addEventListener('drop', (e) => addPhotos(e.dataTransfer.files));
    $('#bpPhotos').addEventListener('click', (e) => {
        const tile = e.target.closest('[data-i]'); if (!tile) return;
        const i = +tile.dataset.i;
        if (e.target.closest('[data-x]')) { URL.revokeObjectURL(photos[i].url); photos.splice(i, 1); buzz([6, 20, 6]); }
        else if (e.target.closest('[data-cover]')) { photos.unshift(photos.splice(i, 1)[0]); buzz(8); }
        else return;
        paintPhotos(); paintScene();
    });

    // ── Single / multi chip groups ───────────────────────────────────────────────
    const paintChoices = () => {
        $$('.bp-type').forEach((b) => { const on = b.dataset.type === state.business_type; b.classList.toggle('is-on', on); b.setAttribute('aria-checked', on); });
        $$('[data-multi]').forEach((g) => $$('[data-val]', g).forEach((c) => c.classList.toggle('is-on', state[g.dataset.multi].includes(c.dataset.val))));
        $$('[data-single]').forEach((g) => $$('[data-val]', g).forEach((c) => {
            const v = state[g.dataset.single];
            c.classList.toggle('is-on', g.hasAttribute('data-bool') ? (v ? '1' : '0') === c.dataset.val : String(v ?? '') === c.dataset.val);
        }));
    };
    $$('[data-multi]').forEach((g) => g.addEventListener('click', (e) => {
        const c = e.target.closest('[data-val]'); if (!c) return;
        const arr = state[g.dataset.multi]; const v = c.dataset.val; const i = arr.indexOf(v);
        if (i >= 0) arr.splice(i, 1);
        else {
            // "Not open yet" means none of the others.
            if (v === 'none') arr.length = 0; else { const j = arr.indexOf('none'); if (j >= 0) arr.splice(j, 1); }
            if (arr.length >= (+g.dataset.max || 99)) arr.shift();
            arr.push(v);
        }
        buzz(6); paintChoices(); paintScene(); save();
    }));
    $$('[data-single]').forEach((g) => g.addEventListener('click', (e) => {
        const c = e.target.closest('[data-val]'); if (!c) return;
        const k = g.dataset.single;
        let v = c.dataset.val;
        if (g.hasAttribute('data-num')) v = v === '' ? null : +v;
        if (g.hasAttribute('data-bool')) v = v === '1';
        // Groups marked data-keep always hold an answer; the rest toggle off on a second tap.
        state[k] = !g.hasAttribute('data-keep') && state[k] === v ? (g.hasAttribute('data-num') ? null : '') : v;
        if (k === 'cancel_free_hours' && state.cancel_free_hours !== null && state.cancel_refund_percent === null) state.cancel_refund_percent = 0;
        if (k === 'whatsapp_same') { setErr('whatsapp_phone', ''); if (!state.whatsapp_same) setTimeout(() => $('#bpWaPhone').focus(), 50); }
        buzz(6); paintChoices(); paintHours(); paintPayout(); paintWa(); paintScene(); save();
    }));

    // Copy that depends on what they run.
    const paintTypeCopy = () => {
        const t = state.business_type;
        $('#bpNameLbl').textContent = t === 'event' ? 'Your organisation or brand' : t === 'academy' ? 'Name of your academy' : 'Name of your place';
        $('#bpName').placeholder = { venue: 'e.g. Green Field Sports Arena', academy: 'e.g. Rayalaseema Cricket Academy', cafe: 'e.g. The Rally Café', event: 'e.g. Kadapa Premier League' }[t] || 'e.g. Green Field Sports Arena';
        $('#bpSportsLbl').innerHTML = (t === 'event' ? 'Sports or kinds of events' : 'Sports played here') + ' <em>the first one you pick is the main sport</em>';
        $('#bpAboutLbl').innerHTML = (t === 'event' ? 'About your events' : t === 'academy' ? 'About your academy' : 'About this venue') + ' <em>optional</em>';
        $('#bpAreaLbl').innerHTML = t === 'event' ? 'Area <em>optional</em>' : 'Area or locality';
        $('#bpMonthlyLbl').textContent = t === 'event' ? 'Tickets or entries in a usual month' : 'Bookings in a usual month';
        // Default court names follow the unit ("Court 2" → "Table 2").
        state.courts.forEach((c, i) => { if (/^(Court|Table) \d+$/.test(c.name)) c.name = unit() + ' ' + (i + 1); });
    };

    // ── WhatsApp number ──────────────────────────────────────────────────────────
    const waOk = () => /^[6-9]\d{9}$/.test(digits(state.whatsapp_phone));
    const waNumber = () => (state.whatsapp_same ? digits(state.phone).slice(-10) : digits(state.whatsapp_phone));
    const paintWa = () => {
        $('#bpWaWrap').hidden = state.whatsapp_same;
        $('#bpWaPhone').value = fmtPhone(state.whatsapp_phone);
        $('#bpWaWrap').classList.toggle('is-valid', waOk());
        $('#bpWaSub').textContent = CFG.whatsappLive
            ? 'Your reference number arrives here as soon as you send, then every update about your listing.'
            : 'Every update from Haraan about your listing comes here.';
    };

    // ── Payouts: the cheque leaf ─────────────────────────────────────────────────
    const IFSC_RE = /^[A-Z]{4}0[A-Z0-9]{6}$/;
    const UPI_RE = /^[a-zA-Z0-9.\-_]{2,256}@[a-zA-Z][a-zA-Z0-9]{1,63}$/;
    const groupAc = (v) => digits(v).slice(0, 18).replace(/(\d{4})(?=\d)/g, '$1 ');
    const bankSel = $('#bpBank');
    const setBank = (name) => {
        if (!name) return;
        const known = Array.from(bankSel.options).find((o) => o.value.toLowerCase() === name.toLowerCase());
        if (known) { bankSel.value = known.value; $('#bpBankOther').hidden = true; state.bank_name = known.value; }
        else { bankSel.value = '__other'; $('#bpBankOther').hidden = false; $('#bpBankOther').value = name; state.bank_name = name; }
    };
    if (state.bank_name) setBank(state.bank_name);
    let branchFor = '';
    const lookupBranch = async (code) => {
        if (branchFor === code) return;
        branchFor = code;
        // Public IFSC directory — only the IFSC is sent. Quietly does nothing offline.
        try {
            const r = await fetch('https://ifsc.razorpay.com/' + encodeURIComponent(code));
            if (!r.ok || branchFor !== code) { if (r.status === 404) $('#bpIfscHint').textContent = 'We couldn’t find that IFSC — check it on your cheque book.'; return; }
            const d = await r.json();
            if (branchFor !== code) return;
            if (d.BANK && !state.bank_name) setBank(d.BANK);
            $('#chBranch').textContent = [d.BRANCH, d.CITY].filter(Boolean).join(', ');
            $('#bpIfscHint').textContent = (d.BANK || '') + (d.BRANCH ? ' · ' + d.BRANCH : '');
            paintPayout();
        } catch (e) {}
    };
    const payoutReady = () => {
        const m = state.payout_method;
        if (m === 'upi') return state.account_holder.trim().length >= 2 && UPI_RE.test(state.upi_vpa.trim());
        if (m === 'bank') return !!state.bank_name && state.account_holder.trim().length >= 2 && /^\d{9,18}$/.test(digits(state.account_number))
            && digits(state.account_number) === digits(state.account_number_confirmation) && IFSC_RE.test(state.ifsc_code)
            && (!state.upi_vpa.trim() || UPI_RE.test(state.upi_vpa.trim()));
        return false;
    };
    const paintPayout = () => {
        const m = state.payout_method;
        $('#bpBankFields').hidden = m !== 'bank';
        $('#bpAcRow').hidden = m !== 'bank';
        $('#bpHolderField').hidden = m === 'later';
        $('#bpUpiField').hidden = m === 'later';
        $('#bpLater').classList.toggle('is-on', m === 'later');
        $('#bpUpiLbl').innerHTML = m === 'upi' ? 'UPI ID' : 'UPI ID <em>optional</em>';
        const ch = $('#bpCheque');
        ch.classList.toggle('is-later', m === 'later');
        const put = (id, text, empty) => { const el = $(id); el.textContent = text || empty; el.classList.toggle('is-empty', !text); };
        put('#chBank', m === 'upi' ? 'UPI' : state.bank_name, 'Your bank');
        put('#chIfsc', m === 'bank' ? state.ifsc_code : '', '— — — —');
        put('#chName', state.account_holder.trim().toUpperCase(), 'Account holder’s name');
        put('#chAc', m === 'bank' ? groupAc(state.account_number) : '', '•••• •••• ••••');
        put('#chUpi', state.upi_vpa.trim().toLowerCase(), '—');
        $('#chUpiWrap').hidden = m === 'bank' && !state.upi_vpa.trim();
        if (m !== 'bank') $('#chBranch').textContent = '';
        const ready = payoutReady();
        if (ready && !ch.classList.contains('is-ready')) { ch.classList.remove('is-flash'); void ch.offsetWidth; ch.classList.add('is-flash'); buzz([10, 30, 16]); }
        ch.classList.toggle('is-ready', ready);
    };
    bankSel.addEventListener('change', () => {
        const other = bankSel.value === '__other';
        $('#bpBankOther').hidden = !other;
        state.bank_name = other ? $('#bpBankOther').value.trim() : bankSel.value;
        if (other) $('#bpBankOther').focus();
        setErr('bank_name', ''); paintPayout(); paintScene(); save();
    });
    $('#bpBankOther').addEventListener('input', (e) => { state.bank_name = e.target.value.trim(); paintPayout(); save(); e.stopPropagation(); });

    // ── The scene ────────────────────────────────────────────────────────────────
    const show = (id, on) => $(id).classList.toggle('is-off', !on);
    const fit = (s, n) => (s.length > n ? s.slice(0, n - 1).trimEnd() + '…' : s);
    const paintScene = () => {
        const t = state.business_type || 'venue';
        show('#scVenue', t === 'venue'); show('#scAcademy', t === 'academy'); show('#scCafe', t === 'cafe'); show('#scEvent', t === 'event');
        $('#scName').textContent = fit(state.business_name.trim() || 'Your place', 20);
        const where = [state.area.trim(), state.city.trim()].filter(Boolean).join(', ');
        $('#scCity').textContent = fit(where ? where.toUpperCase() : (state.tagline.trim() || 'ON HARAAN').toUpperCase(), 30);
        show('#scPin', state.latitude !== null || /^https:\/\//.test(state.map_link));

        // Courts: one tile each, eight at most, the rest as a count.
        const g = $('#scCourts');
        const n = isPlace() && reached('courts') ? state.courts.length : 0;
        const shown = Math.min(n, 8);
        if (g.childElementCount !== shown || g.dataset.t !== t) {
            g.dataset.t = t; g.innerHTML = '';
            for (let i = 0; i < shown; i++) {
                const x = i * 33;
                g.insertAdjacentHTML('beforeend', `<g style="opacity:0;animation:rise .4s ${i * 0.05}s var(--ease) forwards"><rect x="${x}" y="0" width="28" height="18" rx="2" fill="${t === 'cafe' ? '#E9D5B5' : '#9ED3B1'}" class="st-t"/><path d="M${x + 14} 1v16" stroke="#fff" stroke-width="1.4"/></g>`);
            }
        }
        const more = $('#scCourtsMore');
        more.textContent = n > 8 ? '+' + (n - 8) : '';
        more.setAttribute('x', 196 + 8 * 33 + 2);
        show('#scCourts', n > 0);
        const p = minPrice();
        show('#scTag', isPlace() && reached('courts') && p > 0);
        $('#scTagTxt').textContent = 'from ' + rupees(p);

        // Clock: hands go to the first open day's opening time.
        const first = Object.values(state.hours).find((h) => h.on && h.open);
        const showClock = isPlace() && reached('hours') && !!first;
        show('#scClock', showClock);
        if (showClock) {
            const [h, m] = first.open.split(':').map(Number);
            $('#scHandH').style.transform = `rotate(${(h % 12) * 30 + m * 0.5}deg)`;
            $('#scHandM').style.transform = `rotate(${m * 6}deg)`;
        }

        show('#scPhoto', photos.length > 0);
        if (photos.length) $('#scPhotoImg').setAttribute('href', photos[0].url);
        show('#scParking', isPlace() && state.amenities.includes('Parking'));
        $('#scBeams').style.opacity = state.amenities.includes('Floodlights') ? '1' : '0';

        const paying = reached('payouts') && CFG.askPayouts && state.payout_method !== 'later';
        show('#scBank', paying);
        scene.classList.toggle('is-paid', paying && payoutReady());

        show('#scPeople', reached('today') && (state.booking_channels.length > 0 || state.monthly_bookings !== ''));
        show('#scCall', reached('contact'));
        show('#scChat', reached('contact') && (state.whatsapp_same ? phoneOk() : waOk()));
        const ringing = reached('contact') && phoneOk();
        scene.classList.toggle('is-ringing', ringing);
        $('#scPhoneNum').textContent = ringing ? fmtPhone(state.phone) : 'calling…';
    };

    // ── Steps ────────────────────────────────────────────────────────────────────
    let booted = false;
    const goTo = (key, back = false) => {
        const keys = flow();
        if (!keys.includes(key)) key = keys[0];
        step = key;
        $$('.bp-step', form).forEach((s) => { const on = s.dataset.key === key; s.classList.toggle('is-on', on); s.classList.toggle('is-back', on && back); });
        const cur = $(`.bp-step[data-key="${key}"]`, form);
        const counted = keys.filter((k) => k !== 'review');
        const n = counted.indexOf(key) + 1;
        $('#bpRail').style.gridTemplateColumns = `repeat(${counted.length}, 1fr)`;
        $('#bpRail').innerHTML = counted.map((_, i) => `<i class="${key === 'review' || i < n ? 'is-on' : ''}"></i>`).join('');
        $('#bpTitle').textContent = cur.dataset.title;
        $('#bpSub').textContent = cur.dataset.sub;
        $('#bpStepNo').innerHTML = key === 'review' ? 'Last look' : `Step ${n} of ${counted.length} · <b>${esc(cur.dataset.label)}</b>`;
        $('#bpBack').hidden = key === keys[0];
        const nextKey = keys[keys.indexOf(key) + 1];
        $('.bp-btn__lbl', $('#bpNext')).textContent = key === 'review' ? 'Send application' : nextKey === 'review' ? 'Review' : 'Continue';
        $('#bpBanner').classList.remove('is-on');
        if (key === 'review') paintSlip();
        paintScene();
        save();
        if (booted && window.innerWidth <= 960) $('#card').scrollIntoView({ behavior: 'smooth', block: 'start' });
        booted = true;
    };
    const next = () => { const k = flow(); return k[k.indexOf(step) + 1]; };
    const prev = () => { const k = flow(); return k[k.indexOf(step) - 1]; };

    const setErr = (name, msg) => {
        const box = $(`[data-field="${name}"]`, form);
        if (!box) return false;
        box.classList.remove('has-err'); void box.offsetWidth;
        if (msg) {
            box.classList.add('has-err');
            let e = $(':scope > .bp-err', box);
            if (!e) { e = document.createElement('div'); e.className = 'bp-err'; box.append(e); }
            e.textContent = msg;
        }
        return !!msg;
    };
    const clearErrs = () => $$('.has-err', form).forEach((b) => b.classList.remove('has-err'));

    const validate = (key) => {
        clearErrs();
        const bad = [];
        const need = (cond, name, msg) => { if (!cond) { setErr(name, msg); bad.push(name); } };
        const t = state.business_type;
        if (key === 'type') need(!!t, 'business_type', 'Pick what you run.');
        if (key === 'basics') {
            need(state.business_name.trim().length >= 2, 'business_name', 'What is your place called?');
            need(t === 'event' || t === 'cafe' || state.sports.length > 0, 'sports', 'Pick at least one sport.');
        }
        if (key === 'location') {
            need(!isPlace() || state.area.trim().length >= 2, 'area', 'Which area or locality is it in?');
            need(state.city.trim().length >= 2, 'city', 'Which city are you in?');
            need(!state.map_link || /^https:\/\/\S+$/.test(state.map_link.trim()), 'map_link', 'Paste the link from Google Maps → Share → Copy link.');
        }
        if (key === 'courts') {
            const noName = state.courts.some((c) => !c.name.trim());
            const noPrice = t !== 'academy' && state.courts.some((c) => !(+c.price > 0));
            need(state.courts.length > 0 && !noName && !noPrice, 'courts_detail',
                !state.courts.length ? 'Add at least one ' + unit().toLowerCase() + '.' : noName ? 'Give every ' + unit().toLowerCase() + ' a name.' : 'Add the price for an hour on every ' + unit().toLowerCase() + '.');
        }
        if (key === 'hours') {
            const open = Object.entries(state.hours).filter(([, h]) => h.on);
            const same = open.find(([, h]) => !h.open || !h.close || h.open === h.close);
            need(open.length > 0 && !same, 'hours_json', !open.length ? 'Open at least one day.' : same ? `${DAYS[same[0]]}: closing time can’t be the same as opening time.` : '');
        }
        if (key === 'contact') {
            need(state.contact_name.trim().length >= 2, 'contact_name', 'Who should we ask for?');
            need(phoneOk(), 'phone', state.phone ? 'Enter a 10-digit mobile number.' : 'We need a number to call you on.');
            need(state.whatsapp_same || waOk(), 'whatsapp_phone', state.whatsapp_phone ? 'Enter a 10-digit WhatsApp number.' : 'Enter the number you use on WhatsApp.');
            need(!state.email || /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(state.email), 'email', 'That email doesn’t look right.');
            need(!state.gstin || /^\d{2}[A-Z]{5}\d{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/i.test(state.gstin), 'gstin', 'A GSTIN is 15 characters, like 37ABCDE1234F1Z5. Leave it empty if you don’t have one.');
        }
        if (key === 'payouts') {
            const m = state.payout_method;
            const ac = digits(state.account_number);
            if (m === 'bank') {
                need(IFSC_RE.test(state.ifsc_code), 'ifsc_code', state.ifsc_code ? 'An IFSC is 11 characters, like HDFC0001234 (the fifth is a zero).' : 'Enter the IFSC — it is printed on your cheque book.');
                need(!!state.bank_name, 'bank_name', 'Pick your bank.');
            }
            if (m !== 'later') need(state.account_holder.trim().length >= 2, 'account_holder', 'Whose name is the account in?');
            if (m === 'bank') {
                need(/^\d{9,18}$/.test(ac), 'account_number', ac ? 'An account number is 9 to 18 digits.' : 'Enter the account number.');
                need(!ac || ac === digits(state.account_number_confirmation), 'account_number_confirmation', 'The two account numbers don’t match.');
            }
            if (m === 'upi' || state.upi_vpa.trim()) need(UPI_RE.test(state.upi_vpa.trim()), 'upi_vpa', state.upi_vpa.trim() ? 'A UPI ID looks like name@okhdfcbank.' : 'Enter your UPI ID.');
        }
        if (key === 'review') need($('#bpConsent').checked, 'consent', 'Tick the box so we can call you.');
        if (bad.length) {
            buzz([10, 40, 10]);
            const box = $(`[data-field="${bad[0]}"]`, form);
            const f = box && $('input, textarea', box);
            f && f.type !== 'file' && f.type !== 'checkbox' ? f.focus({ preventScroll: true }) : null;
            box && box.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        return bad.length === 0;
    };

    // ── Review slip ──────────────────────────────────────────────────────────────
    const L = CFG.labels;
    const words = (keys, vocab) => keys.map((k) => vocab[k] || k).join(', ');
    const hoursLine = () => {
        const groups = {};
        Object.entries(state.hours).forEach(([d, h]) => { const w = h.on ? fmtTime(h.open) + ' – ' + fmtTime(h.close) : 'Closed'; (groups[w] = groups[w] || []).push(d); });
        return Object.entries(groups).map(([w, ds]) => (ds.length === 7 ? 'Every day' : ds.join(', ')) + ': ' + w).join(' · ');
    };
    const paintSlip = () => {
        $('#slName').textContent = state.business_name.trim() || '—';
        const where = [state.area.trim(), state.city.trim()].filter(Boolean).join(', ');
        const rows = [
            ['Basics', esc(L.types[state.business_type] || '—') + (state.sports.length ? ' · ' + esc(state.sports.join(', ')) : ''), esc(state.tagline.trim()), 'basics'],
            ['Location', esc(where || '—'), esc([state.address.trim(), state.latitude !== null ? 'Pinned on the map' : (state.map_link ? 'Maps link added' : '')].filter(Boolean).join(' · ')), 'location'],
        ];
        if (isPlace()) {
            const p = minPrice();
            rows.push(
                ['Courts & pricing', esc(state.courts.length + ' ' + (state.courts.length === 1 ? unit().toLowerCase() : unit().toLowerCase() + 's') + (p ? ' · from ' + rupees(p) + '/hr' : '')), esc(state.courts.map((c) => c.name + (+c.price > 0 ? ' ' + rupees(c.price) : '')).join(', ')), 'courts'],
                ['Hours', esc(hoursLine()), esc([L.slots[state.slot_minutes] + ' slots', state.cancel_free_hours === null ? 'No refunds' : `Free cancellation up to ${state.cancel_free_hours} h, then ${state.cancel_refund_percent ?? 0}% back`].join(' · ')), 'hours'],
                ['Photos', photos.length ? photos.length + (photos.length === 1 ? ' photo' : ' photos') : 'None yet — add one, or send them on the call', photos.length ? `<span class="bp-slip__thumbs">${photos.slice(0, 6).map((ph) => `<img src="${ph.url}" alt="">`).join('')}</span>` : '', 'photos'],
                ['Amenities & rules', esc(state.amenities.join(', ') || 'None picked'), esc(state.rules.join(' · ')), 'extras'],
            );
        }
        rows.push(
            ['Today', esc(words(state.booking_channels, L.channels) || 'Not said'), esc([L.monthly[state.monthly_bookings] ? L.monthly[state.monthly_bookings] + ' a month' : '', words(state.goals, L.goals)].filter(Boolean).join(' · ')), 'today'],
            ...(CFG.askPayouts ? [['Payouts', esc(state.payout_method === 'later' ? 'On the call' : state.payout_method === 'upi' ? 'UPI · ' + state.upi_vpa.trim().toLowerCase() : (state.bank_name || 'Bank') + ' · •••• ' + digits(state.account_number).slice(-4)),
                esc(state.payout_method === 'later' ? 'We’ll take the details when we call' : [state.account_holder.trim(), state.payout_method === 'bank' ? 'IFSC ' + state.ifsc_code : '', state.payout_method === 'bank' && state.upi_vpa.trim() ? 'UPI ' + state.upi_vpa.trim().toLowerCase() : ''].filter(Boolean).join(' · ')), 'payouts']] : []),
            ['Call', esc(state.contact_name.trim() + (state.contact_role ? ' · ' + L.roles[state.contact_role] : '')), esc(['+91 ' + fmtPhone(state.phone), 'WhatsApp ' + (state.whatsapp_same ? 'same number' : '+91 ' + fmtPhone(state.whatsapp_phone)), state.call_time ? L.callTimes[state.call_time] : '', state.email].filter(Boolean).join(' · ')), 'contact'],
        );
        $('#slBody').innerHTML = rows.map(([k, v, sub, s]) => `<div class="bp-slip__sec"><dt>${k}</dt><dd>${v}${sub ? `<small>${sub}</small>` : ''}</dd><button type="button" class="bp-slip__edit" data-goto="${s}">Edit</button></div>`).join('');
    };
    $('#slBody').addEventListener('click', (e) => { const b = e.target.closest('[data-goto]'); if (b) { buzz(5); goTo(b.dataset.goto, true); } });

    // ── Typing ───────────────────────────────────────────────────────────────────
    form.addEventListener('input', (e) => {
        const el = e.target;
        if (el.id === 'bpPhone') {
            const pos = el.selectionStart, before = el.value.length;
            state.phone = digits(el.value).replace(/^(91|0)(?=\d{10}$)/, '');
            el.value = fmtPhone(state.phone);
            const p = Math.max(0, pos + (el.value.length - before)); el.setSelectionRange(p, p);
            const was = $('#bpPhoneWrap').classList.contains('is-valid');
            $('#bpPhoneWrap').classList.toggle('is-valid', phoneOk());
            if (!was && phoneOk()) buzz(12);
        } else if (el.id === 'bpWaPhone') {
            state.whatsapp_phone = digits(el.value).replace(/^(91|0)(?=\d{10}$)/, '');
            el.value = fmtPhone(state.whatsapp_phone);
            const was = $('#bpWaWrap').classList.contains('is-valid');
            $('#bpWaWrap').classList.toggle('is-valid', waOk());
            if (!was && waOk()) buzz(12);
        } else if (el.name === 'account_number' || el.name === 'account_number_confirmation') {
            el.value = groupAc(el.value);
            state[el.name] = digits(el.value);
        } else if (el.name === 'ifsc_code') {
            el.value = el.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 11);
            state.ifsc_code = el.value;
            const bank = CFG.ifscBanks[el.value.slice(0, 4)];
            if (bank && !state.bank_name) setBank(bank);
            if (IFSC_RE.test(el.value)) lookupBranch(el.value);
            else { $('#chBranch').textContent = ''; $('#bpIfscHint').textContent = 'We fill in the bank from the IFSC.'; }
        } else if (el.name && el.name in state && el.name !== 'consent' && el.name !== 'bank_name') {
            state[el.name] = el.name === 'gstin' ? el.value.toUpperCase().replace(/\s/g, '') : el.value;
            if (el.name === 'map_link') pinFromLink(el.value);
        }
        if (step === 'payouts') paintPayout();
        setErr(el.closest('[data-field]')?.dataset.field, '');
        paintScene(); save();
    });
    $('#bpPhoneWrap').classList.toggle('is-valid', phoneOk());

    $$('.bp-type').forEach((b) => b.addEventListener('click', () => {
        const first = !state.business_type;
        state.business_type = b.dataset.type; buzz(8);
        setErr('business_type', ''); paintChoices(); paintTypeCopy(); paintCourts(); paintScene(); goTo('type'); save();
        if (first) setTimeout(() => step === 'type' && goTo(next()), 420);
    }));
    $('#bpBack').addEventListener('click', () => { buzz(5); goTo(prev(), true); });

    // ── Send ─────────────────────────────────────────────────────────────────────
    const FIELD_STEP = {
        business_type: 'type', business_name: 'basics', sports: 'basics', tagline: 'basics', about: 'basics',
        area: 'location', city: 'location', address: 'location', map_link: 'location', latitude: 'location', longitude: 'location',
        courts_detail: 'courts', hours_json: 'hours', slot_minutes: 'hours', cancel_free_hours: 'hours', cancel_refund_percent: 'hours',
        photos: 'photos', amenities: 'extras', rules: 'extras',
        booking_channels: 'today', monthly_bookings: 'today', goals: 'today',
        contact_name: 'contact', contact_role: 'contact', phone: 'contact', whatsapp_phone: 'contact', whatsapp_same: 'contact', email: 'contact', call_time: 'contact', gstin: 'contact', note: 'contact',
        payout_method: 'payouts', bank_name: 'payouts', account_holder: 'payouts', account_number: 'payouts',
        account_number_confirmation: 'payouts', ifsc_code: 'payouts', upi_vpa: 'payouts',
        consent: 'review',
    };
    const payload = () => {
        const place = isPlace();
        return {
            business_type: state.business_type, business_name: state.business_name, sports: state.sports,
            tagline: state.tagline, about: state.about,
            area: state.area, city: state.city, address: state.address, map_link: state.map_link.trim(),
            latitude: state.latitude, longitude: state.longitude,
            courts_detail: place ? state.courts.map((c) => ({ name: c.name.trim(), sports: c.sports, price: +c.price > 0 ? +c.price : null })) : null,
            hours_json: place ? Object.fromEntries(Object.entries(state.hours).filter(([, h]) => h.on).map(([d, h]) => [d, { open: h.open, close: h.close }])) : null,
            slot_minutes: place ? state.slot_minutes : null,
            cancel_free_hours: place ? state.cancel_free_hours : null,
            cancel_refund_percent: place && state.cancel_free_hours !== null ? state.cancel_refund_percent : null,
            amenities: place ? state.amenities : [], rules: place ? state.rules : [],
            booking_channels: state.booking_channels, monthly_bookings: state.monthly_bookings || null, goals: state.goals,
            contact_name: state.contact_name, contact_role: state.contact_role || null, phone: digits(state.phone),
            email: state.email || null, call_time: state.call_time || null, gstin: state.gstin || null, note: state.note,
            consent: true, source: CFG.source || null,
            whatsapp_same: !!state.whatsapp_same, whatsapp_phone: state.whatsapp_same ? null : digits(state.whatsapp_phone),
            payout_method: CFG.askPayouts ? state.payout_method : 'later',
            bank_name: state.bank_name || null, account_holder: state.account_holder.trim() || null,
            account_number: digits(state.account_number) || null, account_number_confirmation: digits(state.account_number_confirmation) || null,
            ifsc_code: state.ifsc_code || null, upi_vpa: state.upi_vpa.trim() || null,
        };
    };

    let sending = false;
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (sending) return;
        if (!validate(step)) return;
        buzz(8);
        if (step !== 'review') { goTo(next()); return; }

        sending = true;
        const btn = $('#bpNext'); btn.disabled = true; btn.classList.add('is-busy');
        const fd = new FormData();
        fd.append('payload', JSON.stringify(payload()));
        fd.append('website', form.elements.website.value);
        if (isPlace()) photos.forEach((p, i) => fd.append('photos[]', p.blob, `photo-${i + 1}.jpg`));
        try {
            const res = await fetch(CFG.storeUrl, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                body: fd,
                credentials: 'same-origin',
            });
            const data = await res.json().catch(() => ({}));
            if (res.ok && data.reference) return stampAndFinish(data.reference, !!data.duplicate);
            if (res.status === 422 && data.errors) {
                const first = Object.keys(data.errors)[0].split('.')[0];
                const to = FIELD_STEP[first] || 'review';
                if (to !== step) goTo(to, true);
                setTimeout(() => Object.entries(data.errors).forEach(([k, v]) => setErr(k.split('.')[0], v[0])), 60);
            } else {
                const b = $('#bpBanner');
                b.textContent = res.status === 429 ? 'Too many tries from this connection. Wait a few minutes and send again — your answers are saved.'
                    : res.status === 413 ? 'The photos are too large to send together. Remove one or two and send again.'
                    : (data.message || 'That didn’t go through. Check your connection and send again — your answers are saved.');
                b.classList.add('is-on');
            }
        } catch (err) {
            const b = $('#bpBanner'); b.textContent = 'No connection. Your answers are saved on this device — send again when you’re back online.'; b.classList.add('is-on');
        }
        buzz([10, 40, 10]);
        sending = false; btn.disabled = false; btn.classList.remove('is-busy');
    });

    // The slip gets stamped, then the card turns into the thank-you.
    const stampAndFinish = (ref, duplicate) => {
        try { localStorage.removeItem(DRAFT_KEY); } catch (e) {}
        $('#bpStampRef').textContent = ref;
        $('#bpStamp').classList.add('is-down');
        $('#bpSlip').classList.add('is-thud');
        setTimeout(() => buzz([18, 30, 26]), 340);
        setTimeout(() => {
            form.style.display = 'none';
            $('#bpRef').textContent = ref;
            if (duplicate) $('#bpNextCall').textContent = 'We already have your application from this number — no need to send it again.';
            else if (CFG.whatsappLive) $('#bpNextCall').textContent = `Within ${CFG.callbackHours} hours. Your reference is on its way to WhatsApp at +91 ${fmtPhone(waNumber())}.`;
            $('#bpDone').classList.add('is-on');
            scene.classList.add('is-done');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }, 1250);
    };

    paintChoices(); paintTypeCopy(); paintPin(); paintCourts(); paintHours(); paintPhotos(); paintPayout(); paintWa(); goTo(step);
})();
</script>
@endif
</body>
</html>
