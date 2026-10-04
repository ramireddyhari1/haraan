<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The Haraan Partner launch screen on a phone / the iPhone home-screen app — the web
 * twin of BrandSplash.kt: a white stage, the Haraan wordmark filling in brand blue
 * left→right, and a slim meter that fills in lockstep. The logo is the progress bar.
 *
 * Two layers, so there is never a blank white screen:
 *  1. iOS launch images (apple-touch-startup-image, rendered by
 *     scripts/partner_splash_images.py) — shown by iOS the instant the icon is tapped,
 *     before the server has answered. They are the splash's first frame: grey ghost
 *     wordmark, empty meter.
 *  2. This page-level splash — the same frame, which then fills blue and stays until
 *     the app has actually drawn (app-shell.js fires `ha:ready`), then fades.
 *
 * Shown once per session on phones. Inline CSS so it paints before any stylesheet.
 */
final class PartnerSplash
{
    /** [css width, css height, dpr] — must match the images the script renders. */
    private const DEVICES = [
        [440, 956, 3], [402, 874, 3], [430, 932, 3], [393, 852, 3], [428, 926, 3], [390, 844, 3],
        [375, 812, 3], [414, 896, 3], [414, 896, 2], [414, 736, 3], [375, 667, 2], [320, 568, 2],
    ];

    public static function head(): string
    {
        $links = '';
        foreach (self::DEVICES as [$w, $h, $dpr]) {
            $file = 'partner-app/splash/launch-' . ($w * $dpr) . 'x' . ($h * $dpr) . '.png';
            $links .= '<link rel="apple-touch-startup-image" media="(device-width: ' . $w . 'px) and (device-height: ' . $h . 'px) and (-webkit-device-pixel-ratio: ' . $dpr . ') and (orientation: portrait)" href="' . e(asset($file)) . '">';
        }
        // Root-relative: always this origin, whatever APP_URL says.
        $mask = '/partner-app/splash/wordmark-mask.png?v=' . (@filemtime(public_path('partner-app/splash/wordmark-mask.png')) ?: 1);

        return $links
            . '<style>'
            . '#ha-splash{display:none}'
            . 'html.ha-splash-on #ha-splash{display:grid;position:fixed;inset:0;z-index:2147483000;place-items:center;background:radial-gradient(circle 82vw at 50% 44%,#EFF4FF 0%,#F7FAFF 50%,#fff 100%) #fff;transition:opacity .38s ease}'
            . 'html.ha-splash-on.ha-splash-out #ha-splash{opacity:0;pointer-events:none}'
            . 'html.ha-splash-on body{overflow:hidden}'
            . '.hs-lock{display:flex;flex-direction:column;align-items:center}'
            . '.hs-mark{position:relative;width:228px;aspect-ratio:1680/445}'
            . '.hs-mark i{position:absolute;inset:0;-webkit-mask:url(' . $mask . ') center/contain no-repeat;mask:url(' . $mask . ') center/contain no-repeat}'
            . '.hs-ghost{background:#DBE3EF}'
            . '.hs-fill{background:linear-gradient(90deg,#60A5FA,#3B82F6 46%,#2563EB);clip-path:inset(0 100% 0 0);animation:hs-sweep .88s cubic-bezier(.62,.02,.34,1) .1s forwards}'
            . '.hs-meter{position:relative;width:150px;height:3px;margin-top:22px;border-radius:99px;background:rgba(37,99,235,.14);overflow:hidden}'
            . '.hs-meter i{position:absolute;inset:0;border-radius:99px;background:linear-gradient(90deg,#3B82F6,#2563EB);transform-origin:left;transform:scaleX(0);animation:hs-meter .88s cubic-bezier(.62,.02,.34,1) .1s forwards}'
            // Still loading after the fill: a light glint runs along the full meter.
            . 'html.ha-splash-wait .hs-meter::after{content:"";position:absolute;top:0;bottom:0;width:40%;background:linear-gradient(90deg,transparent,rgba(255,255,255,.75),transparent);animation:hs-glint 1.1s ease-in-out infinite}'
            . '@keyframes hs-sweep{to{clip-path:inset(0 0 0 0)}}'
            . '@keyframes hs-meter{to{transform:scaleX(1)}}'
            . '@keyframes hs-glint{from{left:-40%}to{left:100%}}'
            . '@media (prefers-reduced-motion:reduce){.hs-fill{animation:none;clip-path:none}.hs-meter i{animation:none;transform:none}html.ha-splash-wait .hs-meter::after{animation:none}}'
            . '</style>'
            // Decide before the body paints: phones only, first page of the session.
            . '<script>(function(){try{if(!matchMedia("(max-width: 767px)").matches)return;if(sessionStorage.getItem("haPartnerSplash"))return;sessionStorage.setItem("haPartnerSplash","1");document.documentElement.classList.add("ha-splash-on")}catch(e){}})();</script>';
    }

    public static function body(): string
    {
        return '<div id="ha-splash" aria-hidden="true"><div class="hs-lock"><div class="hs-mark"><i class="hs-ghost"></i><i class="hs-fill"></i></div><div class="hs-meter"><i></i></div></div></div>'
            // Leave once the app has drawn (ha:ready) and the fill has finished, so it
            // never snaps away half-filled. Pages without the app shell (sign-in) leave
            // on load; nothing can hold the partner here longer than 6s.
            . '<script>(function(){var r=document.documentElement;if(!r.classList.contains("ha-splash-on"))return;var t0=Date.now(),FILL=1100,done=false;'
            . 'function finish(){if(done)return;done=true;setTimeout(function(){r.classList.add("ha-splash-out");setTimeout(function(){r.classList.remove("ha-splash-on","ha-splash-out","ha-splash-wait")},420)},Math.max(0,FILL-(Date.now()-t0)))}'
            . 'document.addEventListener("ha:ready",finish);'
            . 'setTimeout(function(){if(!done)r.classList.add("ha-splash-wait")},FILL);'
            . 'window.addEventListener("load",function(){setTimeout(function(){if(!r.classList.contains("ha-app"))finish()},250)});'
            . 'setTimeout(finish,6000)})();</script>';
    }
}
