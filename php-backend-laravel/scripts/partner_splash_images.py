"""
Renders the iPhone launch images for the Haraan Partner home-screen app, plus the
small wordmark mask the in-page splash uses.

The launch image is iOS's own splash: it shows the instant the icon is tapped, before
the server answers. It is drawn to match the first frame of the in-page splash
(App\\Support\\PartnerSplash) — white stage, soft cool light, the wordmark in ghost grey
and an empty meter — so when the page takes over and the blue starts filling, nothing
jumps. With apple-mobile-web-app-status-bar-style "default" the page starts BELOW the
status bar, so the lockup is centred in the area under it, not in the whole screen.

Run from php-backend-laravel/:  python scripts/partner_splash_images.py
"""
from pathlib import Path

from PIL import Image, ImageDraw

ROOT = Path(__file__).resolve().parent.parent / 'public'
OUT = ROOT / 'partner-app' / 'splash'
LOGO = ROOT / 'images' / 'haraan-logo.png'  # transparent RGBA, 1680x445

# (css width, css height, device pixel ratio, status bar height in css px)
DEVICES = [
    (440, 956, 3, 62),  # 16 Pro Max
    (402, 874, 3, 62),  # 16 Pro
    (430, 932, 3, 59),  # 15 Pro Max, 15 Plus, 14 Pro Max
    (393, 852, 3, 59),  # 15, 15 Pro, 14 Pro
    (428, 926, 3, 47),  # 14 Plus, 13 Pro Max, 12 Pro Max
    (390, 844, 3, 47),  # 14, 13, 13 Pro, 12, 12 Pro
    (375, 812, 3, 44),  # X, XS, 11 Pro, 12/13 mini
    (414, 896, 3, 44),  # XS Max, 11 Pro Max
    (414, 896, 2, 48),  # XR, 11
    (414, 736, 3, 20),  # 6/7/8 Plus
    (375, 667, 2, 20),  # 6/7/8, SE 2nd/3rd gen
    (320, 568, 2, 20),  # SE 1st gen
]

WORDMARK_W = 228          # css px, same as .hs-mark
METER_W, METER_H, GAP = 150, 3, 22
GHOST = (0xDB, 0xE3, 0xEF)
TRACK = (37, 99, 235, int(255 * 0.14))


def lerp(a, b, t):
    return tuple(round(a[i] + (b[i] - a[i]) * t) for i in range(3))


def glow(w, h, cx, cy, radius):
    """radial-gradient(circle R at cx cy, #EFF4FF 0%, #F7FAFF 50%, #fff 100%), drawn small then scaled."""
    s = 8
    sw, sh = max(1, w // s), max(1, h // s)
    img = Image.new('RGB', (sw, sh))
    px = img.load()
    c0, c1, c2 = (0xEF, 0xF4, 0xFF), (0xF7, 0xFA, 0xFF), (0xFF, 0xFF, 0xFF)
    for y in range(sh):
        for x in range(sw):
            d = (((x * s - cx) ** 2 + (y * s - cy) ** 2) ** 0.5) / radius
            px[x, y] = lerp(c0, c1, d / 0.5) if d < 0.5 else lerp(c1, c2, min(1, (d - 0.5) / 0.5))
    return img.resize((w, h), Image.BICUBIC)


def main():
    OUT.mkdir(parents=True, exist_ok=True)
    logo = Image.open(LOGO).convert('RGBA')
    alpha = logo.getchannel('A')
    aspect = logo.width / logo.height

    # The in-page splash's mask: white letterforms on transparent, sized for 3x screens.
    mw = WORDMARK_W * 3
    mask = Image.new('RGBA', (mw, round(mw / aspect)), (255, 255, 255, 0))
    mask.putalpha(alpha.resize(mask.size, Image.LANCZOS))
    mask.save(OUT / 'wordmark-mask.png', optimize=True)

    for cw, ch, dpr, bar in DEVICES:
        W, H = cw * dpr, ch * dpr
        page_top = bar * dpr
        page_h = H - page_top
        img = glow(W, H, W / 2, page_top + page_h * 0.44, cw * 0.82 * dpr).convert('RGBA')

        ww = WORDMARK_W * dpr
        wh = round(ww / aspect)
        lock_h = wh + (GAP + METER_H) * dpr
        top = round(page_top + (page_h - lock_h) / 2)
        left = round((W - ww) / 2)

        ghost = Image.new('RGBA', (ww, wh), GHOST + (255,))
        ghost.putalpha(alpha.resize((ww, wh), Image.LANCZOS))
        img.alpha_composite(ghost, (left, top))

        track = Image.new('RGBA', (W, H), (0, 0, 0, 0))
        d = ImageDraw.Draw(track)
        mx = round((W - METER_W * dpr) / 2)
        my = top + wh + GAP * dpr
        d.rounded_rectangle([mx, my, mx + METER_W * dpr, my + METER_H * dpr], radius=METER_H * dpr, fill=TRACK)
        img.alpha_composite(track)

        img.convert('RGB').save(OUT / f'launch-{W}x{H}.png', optimize=True)
        print(f'launch-{W}x{H}.png')


if __name__ == '__main__':
    main()
