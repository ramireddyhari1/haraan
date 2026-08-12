# Store screenshots — capture pipeline

Every image in `store/screenshots/` is a real capture of the app running against
production, taken from a scripted emulator session. Nothing is mocked up: Play's
metadata policy requires screenshots to show the actual app, so these are driven
by adb rather than drawn.

## How it works

`driver.py` wraps adb and finds UI elements by their on-screen text (via
`uiautomator dump`), so the same walk works at any screen size — no coordinates
are hard-coded per form factor. `run_flow.py` resizes the device with
`wm size`/`wm density`, walks Home → event detail → ticket sheet → GameHub →
venue detail, and captures each step.

Before each capture it waits for the app to be ANR-free (`wait_stable`), because
a software-rendered emulator throws "isn't responding" dialogs under load and a
dialog must never end up in a shipped screenshot.

## Reproducing

Boot an emulator (the AVD must be a 9:16 phone image), then:

```bash
python run_flow.py phone 1080 1920 420
```

```bash
python run_flow.py tab7 1080 1920 240
```

```bash
python run_flow.py tab10 1440 2560 280
```

```bash
python run_flow.py cb 1920 1080 240 rotate
```

Shots land in `./shots/`. Pick the good frames and copy them into
`store/screenshots/<group>/` with ordered `NN-name.png` filenames.

## Capture conditions

Set by `run_flow.demo_mode()` and the session setup, so frames look consistent:

* SystemUI demo mode — clock pinned to 9:30, battery 100 %, wifi full, mobile
  and notification icons hidden.
* GPS fixed to Bengaluru (`adb emu geo fix 77.6408 12.9784`) so nearby events,
  distances and venues are realistic.
* Animations disabled (`window_animation_scale 0` and friends).
* Guest session via the login screen's "Skip", since phone-OTP sign-in can't be
  automated. That is why the greeting reads "Hey there!" rather than a name.

## Play specs these sets satisfy

| Group | Size | Aspect | Count | Play requires |
| --- | --- | --- | --- | --- |
| phone | 1080×1920 | 9:16 | 7 | 2–8, side 320–3840 |
| tablet-7in | 1080×1920 | 9:16 | 5 | ≤8, side 320–3840 |
| tablet-10in | 1440×2560 | 9:16 | 5 | ≤8, side 1080–7680 |
| chromebook | 1920×1080 | 16:9 | 5 | 4–8, side 1080–7680 |

All PNG, all well under the 8 MB per-image cap.
