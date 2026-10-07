{{--
    "Get the Partner app" footer under the partner sign-in form. Rendered from the
    FOOTER hook (PartnerPanelProvider, scoped to PartnerLogin), so it sits OUTSIDE the
    Livewire login component — no single-root rule, never morphed by Livewire.

    Desktop: a scan-to-phone QR "pass" + Android / iPhone buttons, pinned to the
    bottom of the form column. Phone: an OS-aware strip — Android gets the download,
    iPhone gets the Add-to-Home-Screen steps in a sheet you can drag away. The QR
    opens this same page with #get-app, which jumps straight to that strip.

    Every word and the Android link come from /control → Platform rules → Partner
    web app. Self-contained (markup + inline CSS/JS), no theme rebuild.
--}}
@php
    use App\Support\PlatformRules;

    $androidUrl = PlatformRules::string('partner_web_app.android_url');
    $androidUrl = str_starts_with($androidUrl, 'https://') ? $androidUrl : '';
    $androidNote = PlatformRules::string('partner_web_app.android_note');
    $title = PlatformRules::string('partner_web_app.download_title');
    $sub = PlatformRules::string('partner_web_app.download_sub');
    $isApk = $androidUrl !== '' && str_ends_with(strtolower(parse_url($androidUrl, PHP_URL_PATH) ?? ''), '.apk');
    $scanUrl = url('/partner/login') . '#get-app';
    $icon = asset('partner-app/icon-180.png');
@endphp

<footer class="hga" id="get-app" data-android="{{ $androidUrl !== '' ? '1' : '0' }}" data-scan="{{ $scanUrl }}">
    <div class="hga__in">

        {{-- ── Desktop: QR pass + buttons ─────────────────────────────────── --}}
        <div class="hga__desk">
            <div class="hga__pass" aria-hidden="true">
                <div class="hga__qr" id="hgaQr"></div>
                <span class="hga__passlbl">
                    <svg viewBox="0 0 24 24"><path d="M4 8V6a2 2 0 0 1 2-2h2M16 4h2a2 2 0 0 1 2 2v2M20 16v2a2 2 0 0 1-2 2h-2M8 20H6a2 2 0 0 1-2-2v-2"/><path d="M4 12h16"/></svg>
                    Point your phone camera
                </span>
            </div>

            <div class="hga__copy">
                <p class="hga__eyebrow">Haraan Partner app</p>
                <p class="hga__title">{{ $title }}</p>
                <p class="hga__sub">{{ $sub }}</p>

                <div class="hga__btns">
                    @if ($androidUrl !== '')
                        <a class="hga-key hga-key--blue js-hga-android" href="{{ $androidUrl }}" @if ($isApk) download @else target="_blank" rel="noopener" @endif>
                            <svg class="hga-key__ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 10.5h10v6.2a1.3 1.3 0 0 1-1.3 1.3H8.3A1.3 1.3 0 0 1 7 16.7z"/><path d="M7 9.4a5 5 0 0 1 10 0z"/><path d="M8.6 4.6l1.1 1.8M15.4 4.6l-1.1 1.8M5 11v4.4M19 11v4.4M10 18v2.2M14 18v2.2"/></svg>
                            <span class="hga-key__t"><small>{{ $isApk ? 'Download for' : 'Get it for' }}</small>Android</span>
                        </a>
                    @endif
                    <button type="button" class="hga-key hga-key--plain js-hga-ios">
                        <svg class="hga-key__ic" viewBox="0 0 24 24" aria-hidden="true"><rect x="6.5" y="2.8" width="11" height="18.4" rx="2.6"/><path d="M10.6 5.4h2.8"/><path d="M12 10.2v5.2M9.4 12.8h5.2"/></svg>
                        <span class="hga-key__t"><small>Add to Home Screen</small>iPhone</span>
                    </button>
                </div>
                @if ($androidUrl !== '' && $androidNote !== '')
                    <p class="hga__note">{{ $androidNote }}</p>
                @endif
            </div>
        </div>

        {{-- ── Phone: one strip, the right action for this phone ─────────────── --}}
        <div class="hga__mob">
            <img class="hga__appicon" src="{{ $icon }}" alt="" width="56" height="56" loading="lazy">
            <div class="hga__mcopy">
                <p class="hga__mtitle">Haraan Partner</p>
                <p class="hga__msub">{{ $title }}</p>
                @if ($androidUrl !== '' && $androidNote !== '')
                    <p class="hga__mnote js-hga-anote">{{ $androidNote }}</p>
                @endif
            </div>
            @if ($androidUrl !== '')
                <a class="hga-get js-hga-android js-hga-mandroid" href="{{ $androidUrl }}" @if ($isApk) download @else target="_blank" rel="noopener" @endif>Get</a>
            @endif
            <button type="button" class="hga-get js-hga-ios js-hga-mios">Install</button>
        </div>

        <nav class="hga__legal" aria-label="Legal">
            <span>© {{ now()->year }} Haraan</span>
            <a href="{{ route('site.legal', 'terms') }}">Terms</a>
            <a href="{{ route('site.legal', 'privacy') }}">Privacy</a>
            <a href="{{ url('/') }}">haraan.app</a>
        </nav>
    </div>

    {{-- iPhone steps — a sheet on phones, a popover card on desktop. --}}
    <div class="hga-sheet" id="hgaSheet" role="dialog" aria-modal="true" aria-labelledby="hgaSheetTitle" hidden>
        <div class="hga-sheet__scrim js-hga-close"></div>
        <div class="hga-sheet__panel" id="hgaPanel">
            <span class="hga-sheet__grab" aria-hidden="true"></span>
            <div class="hga-sheet__head">
                <img src="{{ $icon }}" alt="" width="48" height="48">
                <div>
                    <p class="hga-sheet__title" id="hgaSheetTitle">Put Haraan Partner on your Home Screen</p>
                    <p class="hga-sheet__sub">Opens full screen like an app and stays signed in. Use Safari.</p>
                </div>
            </div>
            <ol class="hga-steps">
                <li><span class="hga-steps__n">1</span><span>Tap <b class="hga-steps__key"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12M8 7l4-4 4 4"/><path d="M7 11H6a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2h-1"/></svg>Share</b> in the browser bar. On newer iPhones it is inside the <b>•••</b> menu.</span></li>
                <li><span class="hga-steps__n">2</span><span>Choose <b class="hga-steps__key"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="4"/><path d="M12 8.5v7M8.5 12h7"/></svg>Add to Home Screen</b>, then tap <b>Add</b>.</span></li>
                <li><span class="hga-steps__n">3</span><span>Open <b>Haraan Partner</b> from your Home Screen and sign in once.</span></li>
            </ol>
            <p class="hga-sheet__desk">On a computer? Open <b>{{ parse_url(url('/'), PHP_URL_HOST) }}/partner</b> in Safari on your iPhone, or scan the code.</p>
            <button type="button" class="hga-sheet__done js-hga-close">Got it</button>
        </div>
    </div>
</footer>

<style>
    .hga { --hga-ink: #0F172A; --hga-mute: #64748B; --hga-line: #E3E8F0; --hga-card: #fff; --hga-blue: #2563EB; --hga-ease: cubic-bezier(.2, .8, .2, 1); --hga-spring: cubic-bezier(.16, 1, .3, 1); font-family: 'Inter', system-ui, sans-serif; color: var(--hga-ink); }
    .dark .hga { --hga-ink: #F1F5F9; --hga-mute: #94A3B8; --hga-line: rgba(255,255,255,.09); --hga-card: #151c2c; }
    .hga p { margin: 0; }
    .hga svg { fill: none; stroke: currentColor; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }

    /* Desktop: the brand panel spans both rows; the form fills row 1 of the right
       column and this footer settles at the bottom of it. */
    @media (min-width: 1024px) {
        .fi-simple-layout:has(.hrn-authbrand) { grid-template-rows: 1fr auto; }
        .fi-simple-layout:has(.hrn-authbrand) > .hrn-authbrand { grid-row: 1 / span 2; grid-column: 1; }
        .fi-simple-layout:has(.hrn-authbrand) > .fi-simple-main-ctn { grid-row: 1; grid-column: 2; }
        .fi-simple-layout:has(.hrn-authbrand) > .hga { grid-row: 2; grid-column: 2; }
        .hga__mob { display: none !important; }
    }
    @media (max-width: 1023px) { .hga__desk { display: none !important; } }

    .hga__in { width: 100%; max-width: 31rem; margin: 0 auto; padding: 0 1.5rem 1.4rem; box-sizing: border-box; }

    /* ── Desktop pass ─────────────────────────────────────────────────────── */
    .hga__desk { display: flex; align-items: center; gap: 1.35rem; padding: 1.25rem 0 1.15rem; border-top: 1px solid var(--hga-line); opacity: 0; transform: translate3d(0, 10px, 0); animation: hga-rise .7s var(--hga-spring) .45s forwards; }
    @keyframes hga-rise { to { opacity: 1; transform: none; } }

    .hga__pass { position: relative; flex: none; width: 8.4rem; padding: .7rem .7rem .55rem; background: #fff; border-radius: 1rem; border: 1px solid rgba(15,23,42,.07); box-shadow: 0 1px 1px rgba(15,23,42,.04), 0 6px 14px -8px rgba(15,23,42,.22), 0 22px 34px -26px rgba(37,99,235,.55); transform-style: preserve-3d; transition: transform .5s var(--hga-spring), box-shadow .5s var(--hga-spring); will-change: transform; }
    /* Ticket notches where the code meets the caption — reads as a pass, not a tile. */
    .hga__pass::before, .hga__pass::after { content: ''; position: absolute; top: calc(100% - 1.95rem); width: .7rem; height: .7rem; border-radius: 50%; background: var(--hrn-form-bg, #f7f8fb); box-shadow: inset 0 0 0 1px rgba(15,23,42,.07); }
    .hga__pass::before { left: -.36rem; } .hga__pass::after { right: -.36rem; }
    .hga__qr { position: relative; width: 7rem; height: 7rem; margin: 0 auto; overflow: hidden; border-radius: .35rem; }
    .hga__qr img, .hga__qr canvas { display: block; width: 100% !important; height: 100% !important; image-rendering: pixelated; }
    /* One sweep when it lands — the code "reads" once, then stays still. */
    .hga__qr::after { content: ''; position: absolute; left: 0; right: 0; height: 30%; top: -30%; background: linear-gradient(180deg, transparent, rgba(37,99,235,.18) 70%, rgba(37,99,235,.55) 100%); border-bottom: 1.5px solid rgba(37,99,235,.8); opacity: 0; }
    .hga.is-seen .hga__qr::after { animation: hga-sweep 1.3s var(--hga-ease) .3s 1; }
    @keyframes hga-sweep { 0% { top: -30%; opacity: 0; } 15% { opacity: 1; } 85% { opacity: 1; } 100% { top: 100%; opacity: 0; } }
    .hga__passlbl { display: flex; align-items: center; justify-content: center; gap: .3rem; margin-top: .6rem; padding-top: .5rem; border-top: 1px dashed #D5DCE7; font-size: .62rem; font-weight: 700; color: #475569; letter-spacing: .01em; white-space: nowrap; }
    .hga__passlbl svg { width: .8rem; height: .8rem; color: var(--hga-blue); }

    .hga__copy { min-width: 0; }
    .hga__eyebrow { font-size: .62rem; font-weight: 800; letter-spacing: .2em; text-transform: uppercase; color: var(--hga-blue); }
    .hga__title { margin-top: .3rem !important; font-size: 1.02rem; font-weight: 800; letter-spacing: -.02em; line-height: 1.25; }
    .hga__sub { margin-top: .3rem !important; font-size: .8rem; line-height: 1.5; color: var(--hga-mute); font-weight: 500; }
    .hga__btns { display: flex; flex-wrap: wrap; gap: .55rem; margin-top: .85rem; }
    .hga__note { margin-top: .45rem !important; font-size: .68rem; color: var(--hga-mute); font-variant-numeric: tabular-nums; }

    /* Keycap buttons: a real lower edge that the press pushes the cap down into. */
    .hga-key { --edge: #1D4ED8; position: relative; display: inline-flex; align-items: center; gap: .55rem; height: 2.85rem; padding: 0 1rem 0 .8rem; border-radius: .8rem; border: 0; cursor: pointer; text-decoration: none; font: inherit; transform: translateY(0); transition: transform .12s var(--hga-ease), box-shadow .12s var(--hga-ease), filter .15s; -webkit-tap-highlight-color: transparent; user-select: none; }
    .hga-key--blue { color: #fff; background: linear-gradient(180deg, #3B7BFF 0%, #2563EB 100%); box-shadow: 0 3px 0 var(--edge), 0 8px 16px -8px rgba(37,99,235,.7), inset 0 1px 0 rgba(255,255,255,.22); }
    .hga-key--plain { --edge: #CBD3DF; color: var(--hga-ink); background: var(--hga-card); box-shadow: 0 3px 0 var(--edge), 0 8px 16px -10px rgba(15,23,42,.25), inset 0 0 0 1px rgba(15,23,42,.08); }
    .dark .hga-key--plain { --edge: #0b1020; box-shadow: 0 3px 0 var(--edge), inset 0 0 0 1px rgba(255,255,255,.1); }
    .hga-key:hover { filter: brightness(1.04); transform: translateY(-1px); }
    .hga-key--blue:hover { box-shadow: 0 4px 0 var(--edge), 0 12px 20px -10px rgba(37,99,235,.75), inset 0 1px 0 rgba(255,255,255,.22); }
    .hga-key--plain:hover { box-shadow: 0 4px 0 var(--edge), 0 12px 20px -12px rgba(15,23,42,.3), inset 0 0 0 1px rgba(15,23,42,.1); }
    .hga-key:active, .hga-key.is-pressed { transform: translateY(3px); transition-duration: .04s; }
    .hga-key--blue:active, .hga-key--blue.is-pressed { box-shadow: 0 0 0 var(--edge), 0 2px 6px -3px rgba(37,99,235,.6), inset 0 1px 0 rgba(255,255,255,.15); }
    .hga-key--plain:active, .hga-key--plain.is-pressed { box-shadow: 0 0 0 var(--edge), 0 1px 3px rgba(15,23,42,.12), inset 0 0 0 1px rgba(15,23,42,.12); }
    .hga-key:focus-visible { outline: 3px solid rgba(37,99,235,.35); outline-offset: 3px; }
    .hga-key__ic { width: 1.35rem; height: 1.35rem; flex: none; }
    .hga-key__t { display: flex; flex-direction: column; align-items: flex-start; line-height: 1.05; font-size: .92rem; font-weight: 800; letter-spacing: -.01em; }
    .hga-key__t small { font-size: .58rem; font-weight: 600; letter-spacing: .02em; opacity: .78; margin-bottom: .12rem; }

    /* ── Phone strip ──────────────────────────────────────────────────────── */
    .hga__mob { display: flex; align-items: center; gap: .85rem; margin-top: .25rem; padding: .8rem .8rem .8rem .75rem; background: var(--hga-card); border-radius: 1.15rem; border: 1px solid rgba(15,23,42,.06); box-shadow: 0 1px 2px rgba(15,23,42,.04), 0 10px 24px -16px rgba(10,23,56,.35); transition: box-shadow .4s var(--hga-ease), transform .4s var(--hga-spring); opacity: 0; transform: translate3d(0, 14px, 0); animation: hga-rise .7s var(--hga-spring) .55s forwards; }
    .dark .hga__mob { border-color: rgba(255,255,255,.08); }
    .hga__mob.is-flash { animation: hga-flash 1.1s var(--hga-ease); opacity: 1; transform: none; }
    @keyframes hga-flash { 0% { box-shadow: 0 0 0 0 rgba(37,99,235,.55), 0 10px 24px -16px rgba(10,23,56,.35); } 70% { box-shadow: 0 0 0 10px rgba(37,99,235,0), 0 10px 24px -16px rgba(10,23,56,.35); } 100% { box-shadow: 0 1px 2px rgba(15,23,42,.04), 0 10px 24px -16px rgba(10,23,56,.35); } }
    .hga__appicon { flex: none; width: 3.5rem; height: 3.5rem; border-radius: 22.5%; box-shadow: 0 0 0 1px rgba(15,23,42,.06), 0 6px 12px -6px rgba(10,23,56,.45); }
    .hga__mcopy { flex: 1; min-width: 0; }
    .hga__mtitle { font-size: .95rem; font-weight: 800; letter-spacing: -.015em; }
    .hga__msub { margin-top: .12rem !important; font-size: .76rem; line-height: 1.35; color: var(--hga-mute); font-weight: 500; }
    .hga__mnote { margin-top: .2rem !important; font-size: .66rem; color: var(--hga-mute); font-variant-numeric: tabular-nums; }
    /* App-store "Get" pill: squashes under the thumb, springs back on release. */
    .hga-get { flex: none; display: inline-grid; place-items: center; min-width: 4.6rem; height: 2.2rem; padding: 0 1rem; border-radius: 999px; border: 0; cursor: pointer; text-decoration: none; font: inherit; font-size: .86rem; font-weight: 800; color: #fff; background: var(--hga-blue); box-shadow: 0 2px 0 #1D4ED8, 0 6px 12px -6px rgba(37,99,235,.7); transition: transform .35s var(--hga-spring), box-shadow .2s var(--hga-ease); -webkit-tap-highlight-color: transparent; user-select: none; }
    .hga-get:active, .hga-get.is-pressed { transform: translateY(2px) scale(.94); box-shadow: 0 0 0 #1D4ED8, 0 2px 4px -2px rgba(37,99,235,.6); transition-duration: .06s; }
    .hga-get[hidden] { display: none; }

    .hga__legal { display: flex; flex-wrap: wrap; justify-content: center; align-items: center; gap: .3rem .95rem; padding-top: 1rem; font-size: .7rem; color: var(--hga-mute); }
    .hga__legal a { color: inherit; text-decoration: none; font-weight: 600; }
    .hga__legal a:hover { color: var(--hga-blue); }
    @media (min-width: 1024px) { .hga__legal { justify-content: flex-start; padding-top: 0; } }

    /* ── iPhone steps sheet ──────────────────────────────────────────────── */
    .hga-sheet { position: fixed; inset: 0; z-index: 60; display: flex; align-items: flex-end; justify-content: center; }
    .hga-sheet[hidden] { display: none; }
    .hga-sheet__scrim { position: absolute; inset: 0; background: rgba(8,15,32,.45); opacity: 0; transition: opacity .3s var(--hga-ease); }
    .hga-sheet.is-open .hga-sheet__scrim { opacity: 1; }
    .hga-sheet__panel { position: relative; width: 100%; max-width: 30rem; box-sizing: border-box; background: var(--hga-card); color: var(--hga-ink); border-radius: 1.5rem 1.5rem 0 0; padding: .6rem 1.35rem calc(1.2rem + env(safe-area-inset-bottom)); box-shadow: 0 -20px 50px -20px rgba(8,15,32,.5); max-height: 92dvh; overflow-y: auto; transform: translate3d(0, 105%, 0); transition: transform .5s var(--hga-spring); touch-action: none; }
    .hga-sheet.is-open .hga-sheet__panel { transform: translate3d(0, var(--hga-drag, 0px), 0); }
    .hga-sheet.is-dragging .hga-sheet__panel { transition: none; }
    .hga-sheet__grab { display: block; width: 2.4rem; height: .3rem; margin: 0 auto .9rem; border-radius: 999px; background: #CBD5E1; cursor: grab; }
    .hga-sheet__head { display: flex; gap: .85rem; align-items: center; }
    .hga-sheet__head img { flex: none; width: 3rem; height: 3rem; border-radius: 22.5%; box-shadow: 0 0 0 1px rgba(15,23,42,.06); }
    .hga-sheet__title { font-size: 1rem; font-weight: 800; letter-spacing: -.02em; line-height: 1.25; }
    .hga-sheet__sub { margin-top: .2rem !important; font-size: .78rem; color: var(--hga-mute); }
    .hga-steps { list-style: none; margin: 1.1rem 0 0; padding: 0; display: grid; gap: .8rem; }
    .hga-steps li { display: flex; gap: .75rem; align-items: flex-start; font-size: .86rem; line-height: 1.5; }
    .hga-steps__n { flex: none; display: grid; place-items: center; width: 1.55rem; height: 1.55rem; border-radius: 50%; background: #EEF3FF; color: var(--hga-blue); font-size: .75rem; font-weight: 800; }
    .dark .hga-steps__n { background: rgba(37,99,235,.18); }
    .hga-steps__key { display: inline-flex; align-items: center; gap: .25rem; padding: .05rem .4rem; border-radius: .4rem; background: #F1F5F9; font-weight: 700; white-space: nowrap; }
    .dark .hga-steps__key { background: rgba(255,255,255,.08); }
    .hga-steps__key svg { width: .95rem; height: .95rem; color: var(--hga-blue); }
    .hga-sheet__desk { display: none; margin-top: 1rem !important; font-size: .76rem; color: var(--hga-mute); }
    .hga-sheet__done { display: block; width: 100%; height: 3rem; margin-top: 1.15rem; border: 0; border-radius: .9rem; cursor: pointer; font: inherit; font-size: .95rem; font-weight: 800; color: #fff; background: var(--hga-blue); box-shadow: 0 3px 0 #1D4ED8; transition: transform .1s var(--hga-ease), box-shadow .1s; }
    .hga-sheet__done:active { transform: translateY(3px); box-shadow: 0 0 0 #1D4ED8; }
    /* On a computer the steps float as a centred card instead of a bottom sheet. */
    @media (min-width: 1024px) {
        .hga-sheet { align-items: center; }
        .hga-sheet__panel { border-radius: 1.3rem; padding: 1.4rem 1.5rem 1.3rem; transform: translate3d(0, 16px, 0) scale(.97); opacity: 0; transition: transform .45s var(--hga-spring), opacity .25s var(--hga-ease); }
        .hga-sheet.is-open .hga-sheet__panel { transform: none; opacity: 1; }
        .hga-sheet__grab { display: none; }
        .hga-sheet__desk { display: block; }
    }

    @media (prefers-reduced-motion: reduce) {
        .hga__desk, .hga__mob { animation: none; opacity: 1; transform: none; }
        .hga.is-seen .hga__qr::after { animation: none; }
        .hga__pass, .hga-sheet__panel, .hga-key, .hga-get { transition-duration: .001s; }
    }
</style>

<script>
(function () {
    var root = document.getElementById('get-app');
    if (!root || root.dataset.wired) return;
    root.dataset.wired = '1';

    var ua = navigator.userAgent || '';
    var isIOS = /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    var isAndroid = /Android/i.test(ua);
    var standalone = window.navigator.standalone === true
        || (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches);
    var hasAndroid = root.dataset.android === '1';
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function tick(ms) { try { if (navigator.vibrate) navigator.vibrate(ms || 8); } catch (e) {} }

    /* Phone strip: one action, the right one for this phone. Already running as
       the installed app → nothing to sell, drop the strip. */
    var mob = root.querySelector('.hga__mob');
    var mAndroid = root.querySelector('.js-hga-mandroid');
    var mIos = root.querySelector('.js-hga-mios');
    var aNote = root.querySelector('.js-hga-anote');
    if (standalone) {
        mob.remove();
    } else if (isAndroid) {
        if (mIos) mIos.hidden = true;
        if (!hasAndroid) mob.remove();
    } else if (isIOS) {
        if (mAndroid) mAndroid.hidden = true;
        if (aNote) aNote.remove();
    } else if (hasAndroid && mIos) {
        mIos.hidden = true;             // other phones/tablets: Android build is the closer fit
    }

    /* Press feel for touch: :active is unreliable on iOS without a touch listener,
       so mirror it with a class + a short tick on Android. */
    root.querySelectorAll('.hga-key, .hga-get').forEach(function (b) {
        b.addEventListener('pointerdown', function () { b.classList.add('is-pressed'); tick(8); });
        ['pointerup', 'pointercancel', 'pointerleave'].forEach(function (t) {
            b.addEventListener(t, function () { b.classList.remove('is-pressed'); });
        });
    });
    root.querySelectorAll('.js-hga-android').forEach(function (a) {
        a.addEventListener('click', function () { tick([10, 40, 18]); });
    });

    /* iPhone steps sheet — drag the panel down to dismiss, it springs back if not far enough. */
    var sheet = document.getElementById('hgaSheet');
    var panel = document.getElementById('hgaPanel');
    var lastFocus = null;
    function openSheet() {
        lastFocus = document.activeElement;
        sheet.hidden = false;
        sheet.style.removeProperty('--hga-drag');
        requestAnimationFrame(function () { requestAnimationFrame(function () { sheet.classList.add('is-open'); }); });
        setTimeout(function () { var d = sheet.querySelector('.hga-sheet__done'); if (d) d.focus({ preventScroll: true }); }, 60);
    }
    function closeSheet() {
        sheet.classList.remove('is-open');
        sheet.style.removeProperty('--hga-drag');
        setTimeout(function () { sheet.hidden = true; if (lastFocus && lastFocus.focus) lastFocus.focus({ preventScroll: true }); }, reduce ? 0 : 380);
    }
    root.querySelectorAll('.js-hga-ios').forEach(function (b) { b.addEventListener('click', openSheet); });
    sheet.querySelectorAll('.js-hga-close').forEach(function (b) { b.addEventListener('click', closeSheet); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !sheet.hidden) closeSheet(); });

    var startY = null, dy = 0, startT = 0;
    panel.addEventListener('pointerdown', function (e) {
        if (window.innerWidth >= 1024 || e.target.closest('button, a')) return;
        startY = e.clientY; dy = 0; startT = Date.now();
        sheet.classList.add('is-dragging');
        panel.setPointerCapture(e.pointerId);
    });
    panel.addEventListener('pointermove', function (e) {
        if (startY === null) return;
        var d = e.clientY - startY;
        dy = d > 0 ? d : d / 6;          // resists being pulled up, like a real sheet
        sheet.style.setProperty('--hga-drag', dy + 'px');
    });
    function endDrag() {
        if (startY === null) return;
        startY = null;
        sheet.classList.remove('is-dragging');
        var fast = dy / Math.max(1, Date.now() - startT) > 0.6;
        if (dy > panel.offsetHeight * 0.3 || (fast && dy > 30)) { tick(6); closeSheet(); }
        else sheet.style.removeProperty('--hga-drag');
    }
    panel.addEventListener('pointerup', endDrag);
    panel.addEventListener('pointercancel', endDrag);

    /* Desktop QR pass: drawn only on wide screens, tilts toward the pointer. */
    var qrEl = document.getElementById('hgaQr');
    var pass = root.querySelector('.hga__pass');
    function drawQr() {
        if (!window.QRCode || qrEl.childNodes.length) return;
        new window.QRCode(qrEl, { text: root.dataset.scan, width: 224, height: 224, colorDark: '#0F172A', colorLight: '#ffffff', correctLevel: window.QRCode.CorrectLevel.M });
        qrEl.removeAttribute('title');
    }
    if (window.matchMedia('(min-width: 1024px)').matches) {
        if (window.QRCode) drawQr();
        else {
            var s = document.createElement('script');
            s.src = @json(asset('js/qrcode.min.js'));
            s.onload = drawQr;
            document.head.appendChild(s);
        }
        if (!reduce && pass) {
            var desk = root.querySelector('.hga__desk');
            desk.addEventListener('pointermove', function (e) {
                var r = pass.getBoundingClientRect();
                var x = (e.clientX - (r.left + r.width / 2)) / r.width;
                var y = (e.clientY - (r.top + r.height / 2)) / r.height;
                x = Math.max(-1, Math.min(1, x)); y = Math.max(-1, Math.min(1, y));
                pass.style.transform = 'perspective(600px) rotateY(' + (x * 9).toFixed(2) + 'deg) rotateX(' + (-y * 9).toFixed(2) + 'deg) translateZ(4px)';
            });
            desk.addEventListener('pointerleave', function () { pass.style.transform = ''; });
        }
    }

    /* The QR's sweep plays once the footer is actually on screen. */
    if ('IntersectionObserver' in window) {
        var io = new IntersectionObserver(function (es) {
            es.forEach(function (en) { if (en.isIntersecting) { root.classList.add('is-seen'); io.disconnect(); } });
        }, { threshold: 0.4 });
        io.observe(root);
    } else root.classList.add('is-seen');

    /* Arrived from the QR (…/partner/login#get-app): bring the strip up and point at it;
       on an iPhone, open the steps right away. */
    if (location.hash === '#get-app' && !standalone) {
        setTimeout(function () {
            var target = root.querySelector('.hga__mob') || root;
            target.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'center' });
            if (target.classList.contains('hga__mob')) { target.classList.add('is-flash'); tick(12); }
            if (isIOS) setTimeout(openSheet, 650);
        }, 900);
    }
})();
</script>
