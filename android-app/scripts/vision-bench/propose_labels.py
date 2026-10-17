"""Propose ball positions for frames F0..F1 by difference against a median background,
chained from a seed (x,y normalised upright). Independent of the app's tracker.
usage: propose.py RAW IDX BG0 BG1 F0 F1 SEEDX SEEDY OUTCSV SHEET"""
import sys, numpy as np, cv2
raw, idxp = sys.argv[1:3]
import os; R0 = float(os.environ.get("R0", "60"))
bg0, bg1, f0, f1 = map(int, sys.argv[3:7]); sx, sy = map(float, sys.argv[7:9]); outcsv, sheet = sys.argv[9:11]
W, H = 1280, 720; FS = W*H + (W//2)*(H//2)
idx = [int(l.split()[0]) for l in open(idxp) if l.strip()]; pos = {f: k for k, f in enumerate(idx)}
fh = open(raw, 'rb')
def up(f):
    fh.seek(pos[f]*FS); return np.rot90(np.frombuffer(fh.read(W*H), np.uint8).reshape(H, W), -1).astype(np.int16)
bg = np.median(np.stack([up(f) for f in range(bg0, bg1, max(1, (bg1-bg0)//25))]), axis=0).astype(np.int16)
prev = np.array([sx*720, sy*1280]); vel = np.zeros(2); rows = []; tiles = []
for f in range(f0, f1+1):
    im = up(f); back = up(f-4) if (f-4) in pos else bg
    d = np.minimum(np.abs(im - bg), np.abs(im - back)).astype(np.uint8)
    pred = prev + vel; r = R0 + 1.5*np.linalg.norm(vel)
    x0, y0 = int(max(0, pred[0]-r)), int(max(0, pred[1]-r)); x1, y1 = int(min(720, pred[0]+r)), int(min(1280, pred[1]+r))
    win = d[y0:y1, x0:x1]
    _, m = cv2.threshold(win, 25, 255, cv2.THRESH_BINARY)
    m = cv2.morphologyEx(m, cv2.MORPH_OPEN, np.ones((2, 2), np.uint8))
    n, lab, st, cen = cv2.connectedComponentsWithStats(m)
    best = None
    for k in range(1, n):
        a = st[k, cv2.CC_STAT_AREA]; w, h = st[k, 2], st[k, 3]
        if a < 3 or a > 900 or max(w, h) > 3*min(w, h)+2: continue
        c = cen[k] + [x0, y0]; dist = np.linalg.norm(c - pred)
        if best is None or dist < best[0]: best = (dist, c, a)
    if best is not None and best[0] < r:
        c = best[1]
        if rows: vel = 0.5*vel + 0.5*(c - prev) if f > f0 + 1 else (c - prev)
        prev = c; rows.append((f, c[0]/720, c[1]/1280, best[2]))
    else:
        rows.append((f, None, None, 0)); prev = prev + vel
    # tile: 80x80 crop around the proposal (or prediction), 3x
    cx, cy = int(prev[0]), int(prev[1]); a, b = max(0, cx-40), max(0, cy-40)
    t = cv2.cvtColor(np.clip(im[b:b+80, a:a+80], 0, 255).astype(np.uint8), cv2.COLOR_GRAY2BGR)
    t = cv2.resize(cv2.copyMakeBorder(t, 0, 80-t.shape[0], 0, 80-t.shape[1], cv2.BORDER_CONSTANT), None, fx=3, fy=3, interpolation=cv2.INTER_NEAREST)
    if rows[-1][1] is not None:
        cv2.circle(t, (int((rows[-1][1]*720-a)*3), int((rows[-1][2]*1280-b)*3)), 14, (0, 0, 255), 1)
    cv2.putText(t, str(f), (3, 14), 0, 0.45, (0, 255, 0), 1); tiles.append(t)
with open(outcsv, 'w') as o:
    for f, x, y, a in rows: o.write(f"{f},{'' if x is None else f'{x:.4f}'},{'' if y is None else f'{y:.4f}'},{a}\n")
cols = 12
while len(tiles) % cols: tiles.append(np.zeros_like(tiles[0]))
cv2.imwrite(sheet, np.vstack([np.hstack(tiles[i:i+cols]) for i in range(0, len(tiles), cols)]))
print(sum(1 for r in rows if r[1] is not None), "of", len(rows))
