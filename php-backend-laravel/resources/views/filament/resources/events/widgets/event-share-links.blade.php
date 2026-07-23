{{-- "Share your event" — per-channel copy buttons that tag the public URL with
     ?src= so opens attribute themselves in Traffic Sources / the funnel.
     Self-contained (markup + inline CSS/JS). Data from getShare(). --}}
@php
    $s = $this->getShare();
@endphp

<x-filament-widgets::widget>
    <div class="esl">
        <div class="esl-head">
            <div>
                <div class="esl-title">Share your event</div>
                <div class="esl-sub">Copy a tagged link per channel — every open then shows up in Traffic&nbsp;sources.</div>
            </div>
        </div>

        <div class="esl-list">
            @foreach ($s['channels'] as $c)
                <div class="esl-row">
                    <span class="esl-dot" style="background:{{ $c['color'] }}"></span>
                    <span class="esl-name">{{ $c['label'] }}</span>
                    <input class="esl-url" type="text" readonly value="{{ $c['url'] }}"
                           onclick="this.select()" aria-label="{{ $c['label'] }} link">
                    <button type="button" class="esl-copy" data-url="{{ $c['url'] }}"
                            onclick="hrnCopyShare(this)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
                             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="9" y="9" width="11" height="11" rx="2"/>
                            <path d="M5 15V5a2 2 0 0 1 2-2h10"/>
                        </svg>
                        <span class="esl-copy-t">Copy</span>
                    </button>
                </div>
            @endforeach
        </div>
    </div>

    <style>
        .esl{background:#fff;border:1px solid #e7e9ee;border-radius:16px;
            box-shadow:0 1px 2px rgba(11,18,32,.06);padding:18px 20px;}
        .esl-head{margin-bottom:14px;}
        .esl-title{font-size:14px;font-weight:800;color:#0b1220;letter-spacing:-.01em;}
        .esl-sub{font-size:11.5px;color:#9aa2b1;font-weight:600;margin-top:1px;}
        .esl-list{display:flex;flex-direction:column;gap:9px;}
        .esl-row{display:grid;grid-template-columns:10px 118px 1fr auto;align-items:center;gap:10px;}
        .esl-dot{width:9px;height:9px;border-radius:50%;}
        .esl-name{font-size:12.5px;font-weight:700;color:#374151;white-space:nowrap;}
        .esl-url{font-size:12px;color:#475569;background:#f7f9fc;border:1px solid #e7e9ee;
            border-radius:9px;padding:7px 10px;width:100%;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;
            text-overflow:ellipsis;cursor:text;}
        .esl-url:focus{outline:2px solid #93c5fd;outline-offset:0;border-color:#93c5fd;}
        .esl-copy{display:inline-flex;align-items:center;gap:6px;border:0;cursor:pointer;
            background:linear-gradient(180deg,#2f6bff,#1e50e6);color:#fff;font-size:12.5px;font-weight:700;
            padding:7px 12px;border-radius:9px;white-space:nowrap;transition:filter .15s,transform .05s;}
        .esl-copy:hover{filter:brightness(1.06);}
        .esl-copy:active{transform:translateY(1px);}
        .esl-copy.is-done{background:linear-gradient(180deg,#12b473,#0f9d63);}
        .esl-copy svg{width:15px;height:15px;}

        .dark .esl{background:#111722;border-color:#1e2633;box-shadow:0 1px 2px rgba(0,0,0,.4);}
        .dark .esl-title{color:#eef1f6;} .dark .esl-sub{color:#5e6675;}
        .dark .esl-name{color:#c3cad6;}
        .dark .esl-url{background:#0e141e;border-color:#1e2633;color:#c3cad6;}

        @media (max-width:640px){
            .esl-row{grid-template-columns:10px 1fr auto;grid-template-areas:"dot name copy" "url url url";row-gap:6px;}
            .esl-dot{grid-area:dot;} .esl-name{grid-area:name;} .esl-copy{grid-area:copy;} .esl-url{grid-area:url;}
        }
    </style>

    <script>
        (function () {
            if (window.hrnCopyShare) return; // guard against SPA re-inits
            window.hrnCopyShare = function (btn) {
                var url = btn.getAttribute('data-url');
                var label = btn.querySelector('.esl-copy-t');
                var done = function () {
                    btn.classList.add('is-done');
                    if (label) { var old = label.textContent; label.textContent = 'Copied';
                        setTimeout(function () { label.textContent = old; btn.classList.remove('is-done'); }, 1600); }
                };
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(url).then(done, function () { fallback(url); done(); });
                } else { fallback(url); done(); }
            };
            function fallback(text) {
                var ta = document.createElement('textarea');
                ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
                document.body.appendChild(ta); ta.select();
                try { document.execCommand('copy'); } catch (e) {}
                document.body.removeChild(ta);
            }
        })();
    </script>
</x-filament-widgets::widget>
