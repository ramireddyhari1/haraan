"""Contact sheet of upright analysis frames from a bench raw stream, for frame-checking.
usage: sheet.py RAW IDX OUT FROM TO STEP X0 Y0 X1 Y1 [SCALE]  (x/y normalised upright)"""
import sys, numpy as np, cv2
raw, idxp, out = sys.argv[1:4]
f0, f1, step = map(int, sys.argv[4:7])
x0, y0, x1, y1 = map(float, sys.argv[7:11])
scale = float(sys.argv[11]) if len(sys.argv) > 11 else 2.0
W, H = 1280, 720; FS = W*H + (W//2)*(H//2)
rows = [l.split() for l in open(idxp) if l.strip()]
idx = [int(r[0]) for r in rows]
pos = {f: k for k, f in enumerate(idx)}
tiles = []
with open(raw, 'rb') as fh:
    for f in range(f0, f1 + 1, step):
        if f not in pos: continue
        fh.seek(pos[f] * FS); y = np.frombuffer(fh.read(W*H), np.uint8).reshape(H, W)
        up = np.rot90(y, -1)  # upright 720x1280
        a, b, c, d = int(x0*720), int(y0*1280), int(x1*720), int(y1*1280)
        crop = cv2.cvtColor(np.ascontiguousarray(up[b:d, a:c]), cv2.COLOR_GRAY2BGR)
        crop = cv2.resize(crop, None, fx=scale, fy=scale, interpolation=cv2.INTER_NEAREST)
        for gx in np.arange(np.ceil(x0*50)/50, x1, 0.02):
            px = int((gx*720 - a)*scale); col = (0,160,255) if abs(gx*10-round(gx*10))<1e-6 else (0,90,140)
            cv2.line(crop, (px, 0), (px, 6), col, 1); cv2.line(crop, (px, crop.shape[0]-7), (px, crop.shape[0]-1), col, 1)
        for gy in np.arange(np.ceil(y0*50)/50, y1, 0.02):
            py = int((gy*1280 - b)*scale); col = (0,160,255) if abs(gy*10-round(gy*10))<1e-6 else (0,90,140)
            cv2.line(crop, (0, py), (6, py), col, 1); cv2.line(crop, (crop.shape[1]-7, py), (crop.shape[1]-1, py), col, 1)
        cv2.putText(crop, str(f), (4, 16), cv2.FONT_HERSHEY_SIMPLEX, 0.5, (0,255,0), 1)
        tiles.append(crop)
cols = max(1, min(len(tiles), int(1800 // tiles[0].shape[1])))
h, w = tiles[0].shape[:2]
while len(tiles) % cols: tiles.append(np.zeros_like(tiles[0]))
sheet = np.vstack([np.hstack(tiles[i:i+cols]) for i in range(0, len(tiles), cols)])
cv2.imwrite(out, sheet); print(out, sheet.shape, len(tiles))
