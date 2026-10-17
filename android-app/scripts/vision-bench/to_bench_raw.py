"""A video -> the tracking scoreboard's raw stream: what the camera's analysis frame holds.

Each frame is stood upright, centre-cropped to 9:16, scaled to 720x1280 and turned to the
sensor's sideways 1280x720; written as Y (1280x720) then Cb (640x360). Alongside it,
OUT.idx holds one line per written frame: "<frame index> <presentation time ms>". The
times are the file's own — an iPhone 120 fps clip opens at 60 — never count/duration.

usage: python to_bench_raw.py SRC OUT [FROM TO]
"""
import sys
import cv2
import numpy as np

src, out = sys.argv[1], sys.argv[2]
first = int(sys.argv[3]) if len(sys.argv) > 3 else 0
last = int(sys.argv[4]) if len(sys.argv) > 4 else 10**9

cap = cv2.VideoCapture(src)
if not cap.isOpened():
    sys.exit(f"cannot open {src}")
i = n = 0
with open(out, "wb") as raw, open(out + ".idx", "w") as idx:
    while i <= last:
        ok, frame = cap.read()
        if not ok:
            break
        ms = cap.get(cv2.CAP_PROP_POS_MSEC)
        if i >= first:
            h, w = frame.shape[:2]
            if w > h:  # stored sideways and not turned by the reader
                frame = cv2.rotate(frame, cv2.ROTATE_90_CLOCKWISE)
                h, w = w, h
            want = 9 / 16
            if abs(w / h - want) > 0.01:
                if w / h > want:
                    cw = int(h * want); frame = frame[:, (w - cw) // 2:(w - cw) // 2 + cw]
                else:
                    ch = int(w / want); frame = frame[(h - ch) // 2:(h - ch) // 2 + ch]
            up = cv2.resize(frame, (720, 1280), interpolation=cv2.INTER_AREA).astype(np.int32)
            b, g, r = up[:, :, 0], up[:, :, 1], up[:, :, 2]
            y = ((77 * r + 150 * g + 29 * b) >> 8).astype(np.uint8)
            cb = np.clip(128 + ((-43 * r - 85 * g + 128 * b) >> 8), 0, 255).astype(np.uint8)
            # upright (ux, uy) -> sensor (sx = uy, sy = 719 - ux): the sensor frame is upright turned CCW
            raw.write(np.rot90(y, 1).copy().tobytes())
            cb_small = cv2.resize(np.rot90(cb, 1).astype(np.uint8), (640, 360), interpolation=cv2.INTER_AREA)
            raw.write(cb_small.tobytes())
            idx.write(f"{i} {ms:.3f}\n")
            n += 1
        i += 1
print(f"{out}: {n} frames")
