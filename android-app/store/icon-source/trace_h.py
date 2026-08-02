"""Trace the Haraan H monogram from the wordmark raster into a smooth vector path."""
from PIL import Image
import math, json, os

HERE = os.path.dirname(os.path.abspath(__file__))
SRC = os.path.join(HERE, '..', '..', '..', 'COMPANY LOGOS', 'haraan.png')
OUT = HERE + os.sep

im = Image.open(SRC).convert('RGBA')
crop = im.crop((195, 134, 545, 536))  # H bbox found earlier
SC = 4
w, h = crop.size
big = crop.resize((w * SC, h * SC), Image.LANCZOS)
W, H = big.size
px = big.load()

# coverage field: ink = saturated blue / dark; background = near white
PAD = 2
def cov(ix, iy):
    if ix < 0 or iy < 0 or ix >= W or iy >= H:
        return 0.0
    r, g, b, a = px[ix, iy]
    if a < 8:
        return 0.0
    # background is ~ (253,253,253); ink is blue. use "distance from white"
    d = (255 - min(r, g, b)) / 255.0 * (a / 255.0)
    return min(1.0, d / 0.60)

GW, GH = W + 2 * PAD, H + 2 * PAD
f = [[cov(x - PAD, y - PAD) for x in range(GW)] for y in range(GH)]

# ---- marching squares on the 0.5 iso-contour -------------------------------
T = 0.5
def interp(p1, v1, p2, v2):
    t = (T - v1) / (v2 - v1) if v2 != v1 else 0.5
    return (p1[0] + (p2[0] - p1[0]) * t, p1[1] + (p2[1] - p1[1]) * t)

segs = []
for y in range(GH - 1):
    for x in range(GW - 1):
        v = [f[y][x], f[y][x + 1], f[y + 1][x + 1], f[y + 1][x]]
        p = [(x, y), (x + 1, y), (x + 1, y + 1), (x, y + 1)]
        idx = sum((1 << i) for i in range(4) if v[i] >= T)
        if idx in (0, 15):
            continue
        e = {}
        for i in range(4):
            j = (i + 1) % 4
            if (v[i] >= T) != (v[j] >= T):
                e[i] = interp(p[i], v[i], p[j], v[j])
        ks = sorted(e)
        if len(ks) == 2:
            segs.append((e[ks[0]], e[ks[1]]))
        elif len(ks) == 4:  # saddle
            segs.append((e[0], e[1]))
            segs.append((e[2], e[3]))

# ---- stitch segments into closed loops ------------------------------------
def key(p):
    return (round(p[0], 4), round(p[1], 4))

adj = {}
for a, b in segs:
    adj.setdefault(key(a), []).append((key(b), b))
    adj.setdefault(key(b), []).append((key(a), a))

loops = []
used = set()
for a, b in segs:
    ka, kb = key(a), key(b)
    if (ka, kb) in used or (kb, ka) in used:
        continue
    loop = [a]
    used.add((ka, kb))
    cur, prev = kb, ka
    loop.append(b)
    while True:
        nxt = None
        for k2, p2 in adj.get(cur, []):
            if k2 == prev:
                continue
            if (cur, k2) in used or (k2, cur) in used:
                continue
            nxt = (k2, p2)
            break
        if nxt is None:
            break
        used.add((cur, nxt[0]))
        loop.append(nxt[1])
        prev, cur = cur, nxt[0]
        if cur == key(loop[0]):
            break
    if len(loop) > 20:
        loops.append(loop)

loops.sort(key=len, reverse=True)
print('loops:', [len(l) for l in loops[:5]])

# ---- smooth + simplify ----------------------------------------------------
def smooth(pts, passes=3):
    p = pts[:]
    n = len(p)
    for _ in range(passes):
        q = []
        for i in range(n):
            a, b, c = p[(i - 1) % n], p[i], p[(i + 1) % n]
            q.append(((a[0] + 2 * b[0] + c[0]) / 4.0, (a[1] + 2 * b[1] + c[1]) / 4.0))
        p = q
    return p

def rdp(pts, eps):
    if len(pts) < 3:
        return pts
    def d(p, a, b):
        dx, dy = b[0] - a[0], b[1] - a[1]
        L = math.hypot(dx, dy)
        if L < 1e-9:
            return math.hypot(p[0] - a[0], p[1] - a[1])
        return abs(dy * (p[0] - a[0]) - dx * (p[1] - a[1])) / L
    keep = [False] * len(pts)
    keep[0] = keep[-1] = True
    stack = [(0, len(pts) - 1)]
    while stack:
        i, j = stack.pop()
        md, mi = 0.0, -1
        for k in range(i + 1, j):
            dd = d(pts[k], pts[i], pts[j])
            if dd > md:
                md, mi = dd, k
        if mi > 0 and md > eps:
            keep[mi] = True
            stack.append((i, mi))
            stack.append((mi, j))
    return [p for p, k in zip(pts, keep) if k]

path_loops = []
for loop in loops[:3]:
    s = smooth(loop, 4)
    s = rdp(s + [s[0]], 0.5)
    path_loops.append(s)
    print('simplified to', len(s))

# ---- normalize into a 0..1 box -------------------------------------------
allp = [p for l in path_loops for p in l]
minx = min(p[0] for p in allp); maxx = max(p[0] for p in allp)
miny = min(p[1] for p in allp); maxy = max(p[1] for p in allp)
sw, sh = maxx - minx, maxy - miny
print('traced box', sw, sh, 'aspect', sw / sh)

norm = [[((p[0] - minx) / sh, (p[1] - miny) / sh) for p in l] for l in path_loops]
json.dump(norm, open(OUT + 'h_path.json', 'w'))
print('aspect (w/h) =', sw / sh)
