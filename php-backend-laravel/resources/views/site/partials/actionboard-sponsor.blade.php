{{--
    One sponsor spot in the ActionBoard. A Haraan sponsor ($house, App\Models\Ad) always wins;
    the Google AdSense unit ($adsense = NetworkAds::webSlot) fills the spot only when there is
    none. Both are switched in /control (Marketing → Ads / Ad networks); with neither, nothing
    renders — no empty box.

    The AdSense unit is requested only once it scrolls into view: these spots sit in tab
    panels that start hidden (and in the phone and desktop layouts, one of them hidden), and
    a unit pushed while display:none gets no size and no ad.
--}}
@php
    $house = $house ?? null;
    $adsense = $house ? null : ($adsense ?? null);
@endphp
@if($house)
    <a class="abs-ad" @if($house->link_url) href="{{ route('site.ad.click', $house->id) }}" rel="sponsored noopener" @endif>
        @if($house->image_url)<img class="abs-ad__img" src="{{ $house->image_url }}" alt="" loading="lazy">@endif
        <span class="abs-ad__body">
            <span class="abs-ad__label">Sponsored{{ $house->sponsor ? ' · ' . $house->sponsor : '' }}</span>
            <strong class="abs-ad__title">{{ $house->title }}</strong>
            @if($house->subtitle)<span class="abs-ad__sub">{{ $house->subtitle }}</span>@endif
        </span>
        @if($house->cta_text && $house->link_url)<span class="abs-ad__cta">{{ $house->cta_text }}</span>@endif
    </a>
@elseif($adsense)
    <div class="abs-gad">
        <span class="abs-ad__label">Advertisement</span>
        <ins class="abs-gad__ins" style="display:block"
             data-ad-client="{{ $adsense['client'] }}"
             data-ad-slot="{{ $adsense['slot'] }}"
             data-ad-format="auto"
             data-full-width-responsive="true"></ins>
    </div>
    @once
        @push('head')
            <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client={{ $adsense['client'] }}" crossorigin="anonymous"></script>
        @endpush
        <script>
            (function () {
                var push = function (ins) {
                    if (ins.dataset.absPushed) return;
                    ins.dataset.absPushed = '1';
                    // AdSense fills the FIRST unprocessed ins.adsbygoogle on the page, so a unit
                    // only becomes one now — otherwise a hidden unit earlier in the page takes the ad.
                    ins.classList.add('adsbygoogle');
                    try { (window.adsbygoogle = window.adsbygoogle || []).push({}); } catch (e) {}
                };
                var start = function () {
                    var units = document.querySelectorAll('.abs-gad__ins');
                    if (!('IntersectionObserver' in window)) { units.forEach(push); return; }
                    var io = new IntersectionObserver(function (entries) {
                        entries.forEach(function (e) {
                            if (e.isIntersecting && e.boundingClientRect.width > 0) { push(e.target); io.unobserve(e.target); }
                        });
                    }, { rootMargin: '200px 0px' });
                    units.forEach(function (u) { io.observe(u); });
                };
                if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
            })();
        </script>
        <style>
            .abs-gad { margin: 14px 0 4px; }
            /* No fill from Google: collapse the spot, caption included, instead of a blank box. */
            .abs-gad:has(ins[data-ad-status="unfilled"]) { display: none; }
        </style>
    @endonce
@endif
@if($house)
    @once
        <style>
            .abs-ad { display: flex; align-items: center; gap: 12px; margin: 14px 0 4px; padding: 10px; border-radius: 16px; background: #fff; border: 1px solid #E2E8F0; color: #0F172A; text-decoration: none; }
            .abs-ad__img { width: 64px; height: 64px; border-radius: 12px; object-fit: cover; background: #F1F5F9; flex: none; }
            .abs-ad__body { display: flex; flex-direction: column; gap: 2px; min-width: 0; flex: 1; }
            .abs-ad__title { font-size: 14px; font-weight: 700; line-height: 1.3; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
            .abs-ad__sub { font-size: 12px; color: #475569; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .abs-ad__cta { flex: none; padding: 8px 14px; border-radius: 8px; background: #2563EB; color: #fff; font-size: 12px; font-weight: 700; }
        </style>
    @endonce
@endif
@once
    <style>
        .abs-ad__label { display: block; font-size: 9px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: #94A3B8; margin-bottom: 6px; }
        .abs-ad .abs-ad__label { margin-bottom: 0; letter-spacing: .5px; }
    </style>
@endonce
