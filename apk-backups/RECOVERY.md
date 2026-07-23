# Haraan App — Recovery & Backup Reference

Last updated: 2026-07-07

## ⭐ BASELINE POLICY

**The July-4 app is the protected baseline and must always be restorable.**
Anything created after it is replaceable/deletable.

- Baseline app = `haraan-OLD-v1.0-20260704-from-device.apk` (in this folder)
- Baseline is also live on phone `FAG6XGU8SSOR6HZH` (kept unchanged on purpose)
- Git tag: **`baseline-2026-07-04`** → commit `6da84f3` (holds the archived APK)
- ⚠️ There is **no July-4 *source* commit** — the app's source was uncommitted
  until it was committed at the later (post-July-4) state. So the baseline is
  restored by **reinstalling the archived APK**, not by rebuilding from source.

Restore the baseline anytime:
```
"$ADB" -s <serial> uninstall com.example.thanna
"$ADB" -s <serial> install apk-backups/haraan-OLD-v1.0-20260704-from-device.apk
```
Find the baseline in git: `git checkout baseline-2026-07-04`

## APK backups in this folder

| File | Version | Source | Size | Use |
|------|---------|--------|------|-----|
| `haraan-OLD-v1.0-20260704-from-device.apk` | v1.0 (code 1), built **2026-07-04** | pulled from phone `FAG6XGU8SSOR6HZH` | 39 MB | Restore the pre-recovery ("older") app |
| `haraan-PRESENT-updated-20260707.apk` | v1.0 (code 1), built **2026-07-07** | this session's `assembleDebug` | 30 MB | The current/updated app |

Both are **debug-signed** APKs (fine as personal backups & re-installs on your own
devices; not Play-Store release builds).

## Physical devices (two separate phones)

| Serial | Phone | Currently runs |
|--------|-------|----------------|
| `0D6583012920A2F1` | Realme RMX3933 | **Updated** app (2026-07-07) |
| `FAG6XGU8SSOR6HZH` | (second phone) | **Old** July-4 app — kept as reference/backup |

## What "updated" contains (recovered this session)

Work that was built but never committed, now restored + committed to git branch
`feat/haraan-control-plane`:
- Venue detail + price chart + favorites wired into navigation
- In-app Support chat (header chat icon)
- Football/badminton scorers + result verification (compile; routing still TODO)
- Events tab pulls real `/api/events` (backend-driven) instead of a hardcoded list
- Event card restored (play button + rating badge), rounded header edge kept

Git commits:
- `b0b0abe` — all recovered Android + backend source (120 files)
- `6da84f3` — the July-4 APK archived into the repo

## How to recover / restore

Set adb path (Git Bash):
```
ADB="$LOCALAPPDATA/Android/Sdk/platform-tools/adb.exe"
```

### Reinstall a specific APK on a device
```
"$ADB" -s <serial> install -r apk-backups/haraan-PRESENT-updated-20260707.apk
```
Downgrading (installing OLD over a newer build) is blocked by Android — uninstall first:
```
"$ADB" -s <serial> uninstall com.example.thanna
"$ADB" -s <serial> install apk-backups/haraan-OLD-v1.0-20260704-from-device.apk
```

### Rebuild the updated app from source
```
cd android-app
JAVA_HOME='C:\Program Files\Zulu\zulu-17' ./gradlew assembleDebug --no-daemon --no-build-cache
# APK -> android-app/app/build/outputs/apk/debug/app-debug.apk
```

### Recover the source itself (if the working tree is ever lost)
```
git checkout feat/haraan-control-plane
# or a specific commit:
git checkout b0b0abe
```

### Re-pull the on-device APK from a phone (as done for the July-4 backup)
```
"$ADB" -s <serial> shell pm path com.example.thanna          # find base.apk path
"$ADB" -s <serial> shell cp <base.apk path> /sdcard/x.apk
"$ADB" -s <serial> pull /sdcard/x.apk apk-backups/<name>.apk
"$ADB" -s <serial> shell rm /sdcard/x.apk
```
