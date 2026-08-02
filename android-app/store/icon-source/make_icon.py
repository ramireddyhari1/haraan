"""Haraan launcher icon generator.

Design: the brand H monogram (traced from the official wordmark, three-shape
construction with its signature hairline gaps) in white, on a royal-blue
gradient field with a soft top-left light and a deep bottom-right falloff.

Emits:
  * PIL previews (for review)
  * adaptive-icon vector drawables (background / foreground / monochrome)
  * legacy raster launcher icons (square + round, mdpi..xxxhdpi)
  * a 512x512 Play Store icon
"""
import json, math, os, sys

from PIL import Image, ImageDraw

HERE = os.path.dirname(os.path.abspath(__file__))
H_PATH = json.load(open(os.path.join(HERE, 'h_path.json')))

# ---------------------------------------------------------------- geometry --
CANVAS = 108.0        # adaptive-icon dp canvas
H_HEIGHT = 43.0       # monogram cap height on that canvas
H_ASPECT = 0.87064708691548

# ------------------------------------------------------------------ colours --
TOP_LEFT = (0x4D, 0x8B, 0xFF)     # light royal blue
MID = (0x25, 0x63, 0xEB)          # Haraan accent blue
BOTTOM_RIGHT = (0x0A, 0x2A, 0x93)  # deep brand navy-blue
GLOW = (0xFF, 0xFF, 0xFF)
SHADE = (0x04, 0x14, 0x4B)


def lerp(a, b, t):
    return tuple(round(a[i] + (b[i] - a[i]) * t) for i in range(3))


def base_ramp(t):
    """Three-stop diagonal ramp."""
    t = max(0.0, min(1.0, t))
    if t < 0.55:
        return lerp(TOP_LEFT, MID, t / 0.55)
    return lerp(MID, BOTTOM_RIGHT, (t - 0.55) / 0.45)


def background(size):
    """Per-pixel gradient field, `size` px covering the full 108dp canvas."""
    img = Image.new('RGB', (size, size))
    px = img.load()
    n = size - 1.0
    # radial light: centred up-left; radial shade: bottom-right corner
    gx, gy, gr = 0.28, 0.20, 0.72
    sx, sy, sr = 1.06, 1.06, 0.92
    for y in range(size):
        v = y / n
        for x in range(size):
            u = x / n
            c = base_ramp((u + v) / 2.0)
            d = math.hypot(u - gx, v - gy) / gr
            if d < 1.0:
                a = (1.0 - d) ** 2 * 0.22
                c = lerp(c, GLOW, a)
            d = math.hypot(u - sx, v - sy) / sr
            if d < 1.0:
                a = (1.0 - d) ** 2 * 0.34
                c = lerp(c, SHADE, a)
            px[x, y] = c
    return img


def monogram_mask(size, height_dp=H_HEIGHT, canvas=CANVAS, ss=4):
    """Anti-aliased white-H alpha mask, `size` px covering `canvas` dp."""
    big = size * ss
    m = Image.new('L', (big, big), 0)
    d = ImageDraw.Draw(m)
    scale = big / canvas
    hh = height_dp * scale
    hw = hh * H_ASPECT
    ox = (big - hw) / 2.0
    oy = (big - hh) / 2.0
    for loop in H_PATH:
        d.polygon([(ox + p[0] * hh, oy + p[1] * hh) for p in loop], fill=255)
    return m.resize((size, size), Image.LANCZOS)


def squircle_mask(size, ss=4, radius_ratio=0.2237):
    """Android-style rounded square (matches the launcher's legacy mask)."""
    big = size * ss
    m = Image.new('L', (big, big), 0)
    ImageDraw.Draw(m).rounded_rectangle([0, 0, big - 1, big - 1],
                                        radius=big * radius_ratio, fill=255)
    return m.resize((size, size), Image.LANCZOS)


def circle_mask(size, ss=4):
    big = size * ss
    m = Image.new('L', (big, big), 0)
    ImageDraw.Draw(m).ellipse([0, 0, big - 1, big - 1], fill=255)
    return m.resize((size, size), Image.LANCZOS)


def icon(size, mask=None, crop_to_visible=True, height_dp=H_HEIGHT):
    """Render a finished icon.

    crop_to_visible: emulate the adaptive-icon crop (108dp canvas -> 72dp view).
    """
    if crop_to_visible:
        full = int(round(size * CANVAS / 72.0))
        bg = background(full)
        fg = monogram_mask(full, height_dp=height_dp)
        white = Image.new('RGB', (full, full), (255, 255, 255))
        bg = Image.composite(white, bg, fg)
        off = (full - size) // 2
        bg = bg.crop((off, off, off + size, off + size))
    else:
        bg = background(size)
        fg = monogram_mask(size, height_dp=height_dp)
        white = Image.new('RGB', (size, size), (255, 255, 255))
        bg = Image.composite(white, bg, fg)
    out = bg.convert('RGBA')
    if mask is not None:
        out.putalpha(mask(size))
    return out


# ------------------------------------------------------------ vector output --
def path_data(height_dp=H_HEIGHT, canvas=CANVAS):
    hh = height_dp
    hw = hh * H_ASPECT
    ox = (canvas - hw) / 2.0
    oy = (canvas - hh) / 2.0
    out = []
    for loop in H_PATH:
        pts = [(ox + p[0] * hh, oy + p[1] * hh) for p in loop]
        seg = 'M%.2f,%.2f' % pts[0]
        seg += ''.join('L%.2f,%.2f' % p for p in pts[1:])
        out.append(seg + 'Z')
    return ' '.join(out)


BACKGROUND_XML = '''<?xml version="1.0" encoding="utf-8"?>
<!--
  Haraan launcher icon background.
  Royal-blue diagonal ramp (#4D8BFF -> #2563EB -> #0A2A93) with a soft
  top-left light and a deep bottom-right falloff, so the plate reads with
  depth instead of as a flat colour block.
-->
<vector xmlns:android="http://schemas.android.com/apk/res/android"
    xmlns:aapt="http://schemas.android.com/aapt"
    android:width="108dp"
    android:height="108dp"
    android:viewportWidth="108"
    android:viewportHeight="108">

    <path android:pathData="M0,0h108v108h-108z">
        <aapt:attr name="android:fillColor">
            <gradient
                android:type="linear"
                android:startX="0"
                android:startY="0"
                android:endX="108"
                android:endY="108">
                <item android:offset="0" android:color="#FF4D8BFF" />
                <item android:offset="0.55" android:color="#FF2563EB" />
                <item android:offset="1" android:color="#FF0A2A93" />
            </gradient>
        </aapt:attr>
    </path>

    <!-- top-left light -->
    <path android:pathData="M0,0h108v108h-108z">
        <aapt:attr name="android:fillColor">
            <gradient
                android:type="radial"
                android:centerX="30.2"
                android:centerY="21.6"
                android:gradientRadius="77.8">
                <item android:offset="0" android:color="#38FFFFFF" />
                <item android:offset="0.55" android:color="#0BFFFFFF" />
                <item android:offset="1" android:color="#00FFFFFF" />
            </gradient>
        </aapt:attr>
    </path>

    <!-- bottom-right falloff -->
    <path android:pathData="M0,0h108v108h-108z">
        <aapt:attr name="android:fillColor">
            <gradient
                android:type="radial"
                android:centerX="114.5"
                android:centerY="114.5"
                android:gradientRadius="99.4">
                <item android:offset="0" android:color="#5704144B" />
                <item android:offset="0.55" android:color="#1204144B" />
                <item android:offset="1" android:color="#0004144B" />
            </gradient>
        </aapt:attr>
    </path>
</vector>
'''

FOREGROUND_XML = '''<?xml version="1.0" encoding="utf-8"?>
<!--
  Haraan launcher icon foreground: the brand H monogram, traced from the
  official wordmark so the three-piece construction and its hairline gaps
  are preserved. Sits inside the 66dp adaptive-icon safe zone.
-->
<vector xmlns:android="http://schemas.android.com/apk/res/android"
    android:width="108dp"
    android:height="108dp"
    android:viewportWidth="108"
    android:viewportHeight="108">
    <path
        android:fillColor="#FFFFFFFF"
        android:pathData="%s" />
</vector>
'''

MONOCHROME_XML = '''<?xml version="1.0" encoding="utf-8"?>
<!--
  Themed-icon (Android 13+) layer: the same monogram, a touch smaller as the
  system tints and re-crops it. The system supplies the colour.
-->
<vector xmlns:android="http://schemas.android.com/apk/res/android"
    android:width="108dp"
    android:height="108dp"
    android:viewportWidth="108"
    android:viewportHeight="108">
    <path
        android:fillColor="#FFFFFFFF"
        android:pathData="%s" />
</vector>
'''


def write_vectors(res_dir):
    drawable = os.path.join(res_dir, 'drawable')
    os.makedirs(drawable, exist_ok=True)
    open(os.path.join(drawable, 'ic_launcher_background.xml'), 'w').write(BACKGROUND_XML)
    open(os.path.join(drawable, 'ic_launcher_foreground.xml'), 'w').write(
        FOREGROUND_XML % path_data())
    open(os.path.join(drawable, 'ic_launcher_monochrome.xml'), 'w').write(
        MONOCHROME_XML % path_data(height_dp=43.0))


DENSITIES = [('mdpi', 48), ('hdpi', 72), ('xhdpi', 96), ('xxhdpi', 144), ('xxxhdpi', 192)]


def write_legacy(res_dir):
    for name, size in DENSITIES:
        d = os.path.join(res_dir, 'mipmap-' + name)
        os.makedirs(d, exist_ok=True)
        icon(size, mask=squircle_mask).save(os.path.join(d, 'ic_launcher.webp'),
                                            lossless=True, quality=100, method=6)
        icon(size, mask=circle_mask).save(os.path.join(d, 'ic_launcher_round.webp'),
                                         lossless=True, quality=100, method=6)


def write_store(path):
    os.makedirs(os.path.dirname(path), exist_ok=True)
    icon(512, mask=None).convert('RGB').save(path)


# ----------------------------------------------------------------- previews --
def preview(path):
    tiles = []
    for size, mask in ((192, squircle_mask), (192, circle_mask), (96, squircle_mask),
                       (72, circle_mask), (48, squircle_mask)):
        tiles.append(icon(size, mask=mask))
    pad = 24
    Wd = sum(t.width for t in tiles) + pad * (len(tiles) + 1)
    Ht = max(t.height for t in tiles) + pad * 2
    sheet = Image.new('RGB', (Wd, Ht * 2), (245, 246, 248))
    dark = Image.new('RGB', (Wd, Ht), (17, 19, 23))
    sheet.paste(dark, (0, Ht))
    x = pad
    for t in tiles:
        sheet.paste(t, (x, pad + (Ht - 2 * pad - t.height) // 2), t)
        sheet.paste(t, (x, Ht + pad + (Ht - 2 * pad - t.height) // 2), t)
        x += t.width + pad
    sheet.save(path)


REPO = os.path.abspath(os.path.join(HERE, '..', '..'))  # android-app/


if __name__ == '__main__':
    if '--preview' in sys.argv:
        preview(os.path.join(HERE, 'preview.png'))
        print('preview written')
    if '--emit' in sys.argv:
        res = os.path.join(REPO, 'app', 'src', 'main', 'res')
        write_vectors(res)
        write_legacy(res)
        write_store(os.path.join(REPO, 'store', 'ic_launcher_playstore.png'))
        print('emitted')
