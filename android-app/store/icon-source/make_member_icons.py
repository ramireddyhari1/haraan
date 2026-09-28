"""Pro and Hero launcher icons.

Members on a plan that includes the icon can swap Haraan's home-screen icon for their
tier's (see AppIcons.kt). Each one is the same three-piece brand H as the default icon,
in the tier's identity colours from MemberTierStyles, struck like a coin rather than
printed like a sticker:

  PRO   midnight-navy plate, ice-blue light from the top-left, a frosted white-to-ice H.
  HERO  onyx plate, a warm low light, a champagne-gold H.

The H is layered to read as a raised piece of metal: a two-step drop shadow below it
(hard offsets, because adaptive-icon vectors can't blur), a bright lip one hairline
above it, then the metal face. Every layer is plain vector so the launcher renders it
crisp at any size; the PIL renderer below draws the SAME layers for the legacy webp
icons and the previews, from the same numbers.

  python make_member_icons.py --preview   # store/icon-source/member_preview.png
  python make_member_icons.py --emit      # res/ drawables, mipmaps, store PNGs

Never hand-edit the emitted assets — change a TIER entry and re-run.
"""
import math, os, sys

from PIL import Image, ImageDraw

from make_icon import (CANVAS, H_HEIGHT, H_PATH, H_ASPECT, DENSITIES,
                       circle_mask, squircle_mask, path_data, REPO)

HERE = os.path.dirname(os.path.abspath(__file__))


def rgb(h):
    h = h.lstrip('#')
    return tuple(int(h[i:i + 2], 16) for i in (0, 2, 4))


TIERS = {
    'pro': {
        'label': 'PRO',
        # Plate: MemberTierStyles.Pro band, lifted a step so it reads on a wallpaper.
        'ramp': ['#2F5FD0', '#15327A', '#081634'],
        'ramp_mid': 0.45,
        'light': ('#BFDBFE', 0.30),     # ice-blue, top-left
        'shade': ('#030A1F', 0.40),     # deep corner
        # The H: frosted white into ice.
        'face': [(0, '#FFFFFF'), (0.55, '#E6EEFF'), (1, '#BFD3FB')],
        'lip': ('#FFFFFF', 0.55),
        'shadow': ('#01061A', 0.42, 0.20),
    },
    'hero': {
        'label': 'HERO',
        # Plate: MemberTierStyles.Hero band — onyx with a warm top.
        'ramp': ['#4A3F2F', '#1C1813', '#0A0908'],
        'ramp_mid': 0.50,
        'light': ('#F6DE9A', 0.22),     # a warm glint that lifts the top edge off dark wallpapers
        'shade': ('#000000', 0.45),
        # The H: champagne gold, bright at the top-left edge, burnished at the foot.
        # Bright → burnished → a returning glint at the foot, which is what makes it read as metal.
        'face': [(0, '#FFF1C9'), (0.42, '#DDAE52'), (0.78, '#9C6E24'), (1, '#C9974A')],
        'lip': ('#FFF4D2', 0.70),
        'shadow': ('#000000', 0.55, 0.26),
    },
}

# Layer geometry, in dp on the 108dp canvas.
LIGHT_C, LIGHT_R = (30.2, 21.6), 77.8
SHADE_C, SHADE_R = (114.5, 114.5), 99.4
SHADOW_NEAR, SHADOW_FAR = 0.9, 2.0     # drop-shadow offsets (down)
LIP = 0.45                             # highlight offset (up)


def lerp(a, b, t):
    return tuple(round(a[i] + (b[i] - a[i]) * t) for i in range(3))


def ramp(stops, t):
    """[(offset, '#hex'), ...] -> colour at t."""
    t = max(0.0, min(1.0, t))
    for (o0, c0), (o1, c1) in zip(stops, stops[1:]):
        if t <= o1:
            return lerp(rgb(c0), rgb(c1), 0 if o1 == o0 else (t - o0) / (o1 - o0))
    return rgb(stops[-1][1])


def ramp3(stops, mid, t):
    t = max(0.0, min(1.0, t))
    a, b, c = (rgb(s) for s in stops)
    if t < mid:
        return lerp(a, b, t / mid)
    return lerp(b, c, (t - mid) / (1 - mid))


def aarrggbb(hex_colour, alpha):
    return '#%02X%s' % (round(alpha * 255), hex_colour.lstrip('#').upper())


# ------------------------------------------------------------------- raster --
def plate(t, size):
    img = Image.new('RGB', (size, size))
    px = img.load()
    n = size - 1.0
    lc, la = rgb(t['light'][0]), t['light'][1]
    sc, sa = rgb(t['shade'][0]), t['shade'][1]
    lx, ly, lr = LIGHT_C[0] / CANVAS, LIGHT_C[1] / CANVAS, LIGHT_R / CANVAS
    sx, sy, sr = SHADE_C[0] / CANVAS, SHADE_C[1] / CANVAS, SHADE_R / CANVAS
    for y in range(size):
        v = y / n
        for x in range(size):
            u = x / n
            c = ramp3(t['ramp'], t['ramp_mid'], (u + v) / 2.0)
            d = math.hypot(u - lx, v - ly) / lr
            if d < 1.0:
                c = lerp(c, lc, la * (1.0 - d) ** 2)
            d = math.hypot(u - sx, v - sy) / sr
            if d < 1.0:
                c = lerp(c, sc, sa * (1.0 - d) ** 2)
            px[x, y] = c
    return img


def h_mask(size, dy_dp=0.0, ss=4):
    big = size * ss
    m = Image.new('L', (big, big), 0)
    d = ImageDraw.Draw(m)
    s = big / CANVAS
    hh = H_HEIGHT * s
    hw = hh * H_ASPECT
    ox = (big - hw) / 2.0
    oy = (big - hh) / 2.0 + dy_dp * s
    for loop in H_PATH:
        d.polygon([(ox + p[0] * hh, oy + p[1] * hh) for p in loop], fill=255)
    return m.resize((size, size), Image.LANCZOS)


def face(t, size):
    """The H's metal: a diagonal ramp across the H's own bounds."""
    img = Image.new('RGB', (size, size))
    px = img.load()
    hh = H_HEIGHT / CANVAS * size
    hw = hh * H_ASPECT
    x0, y0 = (size - hw) / 2, (size - hh) / 2
    for y in range(size):
        for x in range(size):
            u = (x - x0) / hw
            v = (y - y0) / hh
            px[x, y] = ramp(t['face'], (u + v) / 2.0)
    return img


def paint(base, colour, mask, alpha):
    layer = Image.new('RGB', base.size, colour)
    m = mask.point(lambda a: round(a * alpha))
    return Image.composite(layer, base, m)


def render(t, size):
    """The full 108dp canvas at `size` px."""
    img = plate(t, size)
    sh_c, near_a, far_a = rgb(t['shadow'][0]), t['shadow'][1], t['shadow'][2]
    img = paint(img, sh_c, h_mask(size, SHADOW_FAR), far_a)
    img = paint(img, sh_c, h_mask(size, SHADOW_NEAR), near_a)
    img = paint(img, rgb(t['lip'][0]), h_mask(size, -LIP), t['lip'][1])
    img = Image.composite(face(t, size), img, h_mask(size))
    return img


def icon(t, size, mask=None):
    """A finished icon: the adaptive 72dp view cropped from the 108dp canvas."""
    full = int(round(size * CANVAS / 72.0))
    img = render(t, full)
    off = (full - size) // 2
    out = img.crop((off, off, off + size, off + size)).convert('RGBA')
    if mask is not None:
        out.putalpha(mask(size))
    return out


# ------------------------------------------------------------------- vector --
def background_xml(name, t):
    a, b, c = t['ramp']
    lc, la = t['light']
    sc, sa = t['shade']
    return f'''<?xml version="1.0" encoding="utf-8"?>
<!--
  {t['label']} member launcher icon: plate. GENERATED by
  store/icon-source/make_member_icons.py — edit the TIERS entry there, not this file.
-->
<vector xmlns:android="http://schemas.android.com/apk/res/android"
    xmlns:aapt="http://schemas.android.com/aapt"
    android:width="108dp"
    android:height="108dp"
    android:viewportWidth="108"
    android:viewportHeight="108">

    <path android:pathData="M0,0h108v108h-108z">
        <aapt:attr name="android:fillColor">
            <gradient android:type="linear" android:startX="0" android:startY="0" android:endX="108" android:endY="108">
                <item android:offset="0" android:color="{aarrggbb(a, 1)}" />
                <item android:offset="{t['ramp_mid']}" android:color="{aarrggbb(b, 1)}" />
                <item android:offset="1" android:color="{aarrggbb(c, 1)}" />
            </gradient>
        </aapt:attr>
    </path>

    <!-- light -->
    <path android:pathData="M0,0h108v108h-108z">
        <aapt:attr name="android:fillColor">
            <gradient android:type="radial" android:centerX="{LIGHT_C[0]}" android:centerY="{LIGHT_C[1]}" android:gradientRadius="{LIGHT_R}">
                <item android:offset="0" android:color="{aarrggbb(lc, la)}" />
                <item android:offset="0.55" android:color="{aarrggbb(lc, la * 0.2)}" />
                <item android:offset="1" android:color="{aarrggbb(lc, 0)}" />
            </gradient>
        </aapt:attr>
    </path>

    <!-- corner falloff -->
    <path android:pathData="M0,0h108v108h-108z">
        <aapt:attr name="android:fillColor">
            <gradient android:type="radial" android:centerX="{SHADE_C[0]}" android:centerY="{SHADE_C[1]}" android:gradientRadius="{SHADE_R}">
                <item android:offset="0" android:color="{aarrggbb(sc, sa)}" />
                <item android:offset="0.55" android:color="{aarrggbb(sc, sa * 0.2)}" />
                <item android:offset="1" android:color="{aarrggbb(sc, 0)}" />
            </gradient>
        </aapt:attr>
    </path>
</vector>
'''


def foreground_xml(name, t):
    d = path_data()
    hh = H_HEIGHT
    hw = hh * H_ASPECT
    x0, y0 = (CANVAS - hw) / 2, (CANVAS - hh) / 2
    stops = '\n'.join(f'                <item android:offset="{o}" android:color="{aarrggbb(c, 1)}" />'
                      for o, c in t['face'])
    sh, near_a, far_a = t['shadow']
    lip_c, lip_a = t['lip']
    return f'''<?xml version="1.0" encoding="utf-8"?>
<!--
  {t['label']} member launcher icon: the brand H struck in the tier's metal. Bottom to top:
  a two-step drop shadow, a bright lip one hairline above, then the metal face — so the H
  sits proud of the plate. GENERATED by store/icon-source/make_member_icons.py.
-->
<vector xmlns:android="http://schemas.android.com/apk/res/android"
    xmlns:aapt="http://schemas.android.com/aapt"
    android:width="108dp"
    android:height="108dp"
    android:viewportWidth="108"
    android:viewportHeight="108">

    <group android:translateY="{SHADOW_FAR}">
        <path android:fillColor="{aarrggbb(sh, far_a)}" android:pathData="{d}" />
    </group>
    <group android:translateY="{SHADOW_NEAR}">
        <path android:fillColor="{aarrggbb(sh, near_a)}" android:pathData="{d}" />
    </group>
    <group android:translateY="-{LIP}">
        <path android:fillColor="{aarrggbb(lip_c, lip_a)}" android:pathData="{d}" />
    </group>

    <path android:pathData="{d}">
        <aapt:attr name="android:fillColor">
            <gradient android:type="linear" android:startX="{x0:.2f}" android:startY="{y0:.2f}" android:endX="{x0 + hw:.2f}" android:endY="{y0 + hh:.2f}">
{stops}
            </gradient>
        </aapt:attr>
    </path>
</vector>
'''


ADAPTIVE_XML = '''<?xml version="1.0" encoding="utf-8"?>
<!-- GENERATED by store/icon-source/make_member_icons.py -->
<adaptive-icon xmlns:android="http://schemas.android.com/apk/res/android">
    <background android:drawable="@drawable/ic_launcher_%(n)s_background" />
    <foreground android:drawable="@drawable/ic_launcher_%(n)s_foreground" />
    <monochrome android:drawable="@drawable/ic_launcher_monochrome" />
</adaptive-icon>
'''


def emit():
    res = os.path.join(REPO, 'app', 'src', 'main', 'res')
    for n, t in TIERS.items():
        drawable = os.path.join(res, 'drawable')
        open(os.path.join(drawable, f'ic_launcher_{n}_background.xml'), 'w', encoding='utf-8').write(background_xml(n, t))
        open(os.path.join(drawable, f'ic_launcher_{n}_foreground.xml'), 'w', encoding='utf-8').write(foreground_xml(n, t))
        any_dir = os.path.join(res, 'mipmap-anydpi-v26')
        for suffix in ('', '_round'):
            open(os.path.join(any_dir, f'ic_launcher_{n}{suffix}.xml'), 'w', encoding='utf-8').write(ADAPTIVE_XML % {'n': n})
        # Legacy (API 24-25) launchers and the in-app picker's preview both read these.
        for dens, size in DENSITIES:
            d = os.path.join(res, 'mipmap-' + dens)
            icon(t, size, squircle_mask).save(os.path.join(d, f'ic_launcher_{n}.webp'), lossless=True, quality=100, method=6)
            icon(t, size, circle_mask).save(os.path.join(d, f'ic_launcher_{n}_round.webp'), lossless=True, quality=100, method=6)
        icon(t, 512).convert('RGB').save(os.path.join(REPO, 'store', f'ic_launcher_{n}_512.png'))
    print('emitted')


def preview(path):
    from make_icon import icon as default_icon
    rows = [('default', lambda s, m: default_icon(s, mask=m))] + \
           [(n, (lambda t: lambda s, m: icon(t, s, m))(t)) for n, t in TIERS.items()]
    sizes = ((256, squircle_mask), (256, circle_mask), (144, squircle_mask), (96, circle_mask), (48, squircle_mask))
    pad = 28
    width = sum(s for s, _ in sizes) + pad * (len(sizes) + 1)
    row_h = 256 + pad * 2
    sheet = Image.new('RGB', (width * 2, row_h * len(rows)), (244, 245, 248))
    sheet.paste(Image.new('RGB', (width, row_h * len(rows)), (16, 18, 22)), (width, 0))
    for r, (_, fn) in enumerate(rows):
        for half in (0, width):
            x = half + pad
            for s, m in sizes:
                tile = fn(s, m)
                sheet.paste(tile, (x, r * row_h + pad + (256 - s) // 2), tile)
                x += s + pad
    sheet.save(path)
    print('preview written', path)


if __name__ == '__main__':
    if '--preview' in sys.argv:
        preview(sys.argv[sys.argv.index('--preview') + 1] if len(sys.argv) > sys.argv.index('--preview') + 1
                else os.path.join(HERE, 'member_preview.png'))
    if '--emit' in sys.argv:
        emit()
