"""Play Store feature graphic (1024x500) for Haraan.

Same royal-blue depth as the launcher icon (see make_icon.py), with the brand
wordmark recoloured to white out of the master artwork and a faint H watermark
for depth. Play crops this graphic differently across surfaces, so all type
stays inside a generous central safe area.

    python make_feature_graphic.py        -> android-app/store/feature-graphic.png
"""
import json, math, os

from PIL import Image, ImageDraw, ImageFont

HERE = os.path.dirname(os.path.abspath(__file__))
REPO = os.path.abspath(os.path.join(HERE, '..', '..'))          # android-app/
ROOT = os.path.abspath(os.path.join(REPO, '..'))                 # repo root
WORDMARK = os.path.join(ROOT, 'COMPANY LOGOS', 'haraan.png')
H_PATH = json.load(open(os.path.join(HERE, 'h_path.json')))

W, H = 1024, 500
TAGLINE = 'Events, turf booking & live sports'
FOOT = 'haraan.app'

TOP_LEFT = (0x4D, 0x8B, 0xFF)
MID = (0x25, 0x63, 0xEB)
BOTTOM_RIGHT = (0x0A, 0x2A, 0x93)
GLOW = (0xFF, 0xFF, 0xFF)
SHADE = (0x04, 0x14, 0x4B)

FONT_SB = 'C:/Windows/Fonts/seguisb.ttf'
FONT_R = 'C:/Windows/Fonts/segoeui.ttf'


def lerp(a, b, t):
    return tuple(round(a[i] + (b[i] - a[i]) * t) for i in range(3))


def ramp(t):
    t = max(0.0, min(1.0, t))
    if t < 0.55:
        return lerp(TOP_LEFT, MID, t / 0.55)
    return lerp(MID, BOTTOM_RIGHT, (t - 0.55) / 0.45)


def background():
    img = Image.new('RGB', (W, H))
    px = img.load()
    for y in range(H):
        v = y / (H - 1.0)
        for x in range(W):
            u = x / (W - 1.0)
            c = ramp((u * 0.62 + v * 0.38))
            d = math.hypot((u - 0.22) * 0.62, v - 0.10) / 0.62
            if d < 1.0:
                c = lerp(c, GLOW, (1.0 - d) ** 2 * 0.20)
            d = math.hypot((u - 1.02) * 0.66, v - 1.04) / 0.80
            if d < 1.0:
                c = lerp(c, SHADE, (1.0 - d) ** 2 * 0.32)
            px[x, y] = c
    return img


def white_wordmark(target_w):
    """The master wordmark, recoloured to pure white with its own coverage as alpha."""
    src = Image.open(WORDMARK).convert('RGBA')
    px = src.load()
    sw, sh = src.size
    a = Image.new('L', (sw, sh))
    ap = a.load()
    for y in range(sh):
        for x in range(sw):
            r, g, b, al = px[x, y]
            cov = (255 - min(r, g, b)) / 255.0 * (al / 255.0)
            ap[x, y] = min(255, int(cov / 0.60 * 255))
    box = a.getbbox()
    a = a.crop(box)
    w2, h2 = a.size
    a = a.resize((target_w, max(1, round(h2 * target_w / w2))), Image.LANCZOS)
    out = Image.new('RGBA', a.size, (255, 255, 255, 0))
    out.putalpha(a)
    return out


def main():
    img = background()

    mark = white_wordmark(430)
    canvas = img.convert('RGBA')
    # Optically centre wordmark + tagline + footer as one block.
    block = mark.height + 46 + 47 + 30 + 30
    my = round((H - block) / 2) - 6
    mx = (W - mark.width) // 2
    canvas.alpha_composite(mark, (mx, my))

    d = ImageDraw.Draw(canvas)
    f1 = ImageFont.truetype(FONT_SB, 40)
    f2 = ImageFont.truetype(FONT_R, 23)
    y = my + mark.height + 46
    w1 = d.textlength(TAGLINE, font=f1)
    d.text(((W - w1) / 2, y), TAGLINE, font=f1, fill=(255, 255, 255, 255))
    w2 = d.textlength(FOOT, font=f2)
    d.text(((W - w2) / 2, y + 68), FOOT, font=f2, fill=(255, 255, 255, 190))

    out = os.path.join(REPO, 'store', 'feature-graphic.png')
    canvas.convert('RGB').save(out)
    print('wrote', out)


if __name__ == '__main__':
    main()
