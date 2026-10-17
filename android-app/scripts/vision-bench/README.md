# Tracking scoreboard bench

`TrackingScoreboardTest` runs the shipped per-frame chain (`DeliveryChain`) over real clips
and scores it against hand-checked truth in `app/src/test/resources/vision-bench/truth.txt`.

1. Convert each clip named in `truth.txt` into one folder:
   `python to_bench_raw.py IMG_6056.MOV <bench>/img6056.raw`
2. Run (Gradle caches results, so `--rerun`):
   `VISION_BENCH_DIR=<bench> ./gradlew :app:testDebugUnitTest --tests "*TrackingScoreboardTest" --rerun`
   Optional: `BENCH_ONLY=img6056,net02` and `BENCH_LOG=1` (the chain's reasoning per call).
3. Read `app/build/reports/vision-bench/scoreboard.md`.

Adding truth for a new clip:
- `contact_sheet.py` — numbered, gridded crops of a frame range, to find release / bounce /
  at-the-stumps frames by eye.
- `propose_labels.py` — proposes ball centres by background-and-motion difference (not the
  app's tracker) and draws them circled on a sheet. Check every tile; re-seed or shrink the
  search (`R0=22`) where a circle is off the ball. Only verified positions go in `truth.txt`.
