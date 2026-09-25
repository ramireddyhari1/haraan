# HARAAN ENTERPRISE CRICKET VISION & TRACKING: PHYSICAL DEVICE TESTING GUIDE

This document provides a field testing checklist and step-by-step verification instructions for testing the upgraded Haraan cricket tracking engine on your connected physical Android phone.

---

## 1. Fast Deploy to Connected Device

Ensure your phone has **USB Debugging** enabled and is connected via USB:

```powershell
# 1. Verify ADB connection
adb devices

# 2. Install the freshly compiled Debug APK
adb install -r android-app/app/build/outputs/apk/debug/app-debug.apk

# 3. Launch directly into the Hawk-Eye Bowler Vision Activity
adb shell am start -a android.intent.action.VIEW -d "haraan://bowler-test"
```

---

## 2. On-Device Verification Checklist

### Test Case 1: Bowler Kinematic Lock vs Umpire Rejection
* **Setup**: Place phone on a tripod or hold at bowler's end facing the bowling crease.
* **Test**: Have one person stand still or take slow, small steps near the bowling end (simulating an Umpire at < 4.5 km/h).
* **Expected UI**:
  * Bottom badge should show: `⚠️ UMPIRE FILTERED (v: ~2.1 km/h)` with amber dot.
  * System rejects the umpire from triggering gathering/release states.
* **Test**: Have a bowler run up towards the crease (> 7 km/h with forward acceleration).
* **Expected UI**:
  * Badge turns bright green: `🎯 BOWLER LOCKED #1 (v: 14.5 km/h)`.
  * Wrist crosshair glows green and tracks the bowling arm.

---

### Test Case 2: High-Speed 60 FPS & 1/500s Sports Shutter Lock
* **Test**: In the top control bar, tap **⚡ 60 FPS**.
* **Expected Result**:
  * CameraX switches the preview and image analysis pipeline to 60 FPS target.
  * The top FPS badge should climb to 55–60 FPS (hardware dependent).
* **Test**: Tap **🎯 1/500s LOCK**.
* **Expected Result**:
  * Camera2 shutter speed locks to $\le 1/500$ second.
  * Motion blur on fast bowling arm swings and wrist snap is eliminated.

---

### Test Case 3: Pitch Homography Crease & Front-Foot No-Ball Check
* **Test**: In the secondary control bar, tap **📐 CREASE ON**.
* **Expected UI**:
  * Perspective pitch lines appear on camera:
    * Bowling Crease (Green line)
    * Popping Crease (Cyan / Amber front-foot line)
    * Return Crease corridors (Dashed white lines)
* **Test**: Step with your front foot behind the popping crease line $\to$ Status pill shows `CREASE: FAIR DELIVERY (BEHIND POPPING LINE)`.
* **Test**: Overstep past the popping crease line $\to$ Line flashes Red and status pill displays `CREASE: NO-BALL (FRONT-FOOT OVERSTEPPED)`.

---

### Test Case 4: Delivery Lifecycle State Machine
* **Action**: Simulate a complete bowling delivery:
  1. Stand at top of mark $\to$ UI state shows `[ IDLE ]` or `[ BOWLER_DETECTED ]`.
  2. Run up toward camera $\to$ State transitions to `[ APPROACHING_CREASE ]`.
  3. Gather / bring bowling arm high $\to$ State transitions to `[ DELIVERY_ARMED ]`.
  4. Release arm overhead $\to$ Full-screen neon banner flashes:
     `BALL #X LOCKED` with Arm Angle, Hand Speed, Stride Length, and ICC 15° Flexion check.
  5. State advances to `[ RELEASE_CONFIRMED ]` $\to$ `[ DELIVERY_COMPLETE ]`.

---

### Test Case 5: Rolling Replay Clip Buffer
* **Action**: Immediately after delivery release:
  * A floating card slides up from the bottom:
    `⚡ DELIVERY #X REPLAY READY`
    `Duration: 3500ms (210 frames) • 126 km/h • LEGAL (Δ8°) • UDP SEQ #X`
  * Tap **DISMISS** to close the card.
  * Frames are stored safely in a 5-second circular ring buffer without RAM bloat.

---

### Test Case 6: OLED Eco Stealth Mode (Thermal Shield)
* **Action**: In the secondary control bar, tap **🌿 STEALTH ECO**.
* **Expected Result**:
  * Entire screen goes pitch black (RGB 0, 0, 0), saving ~95% display power on AMOLED/OLED screens.
  * A minimalist pulsating neon green dot displays:
    `● OLED ECO STEALTH ACTIVE`
    `SAVING ~95% SCREEN POWER • RUNNING 60 FPS`
    `BALLS: #X • LIFECYCLE: IDLE`
  * Vision analysis and background UDP sync continue tracking at full 60 FPS!
  * Tap **ANYWHERE** on the screen $\to$ HUD immediately wakes back to full view.

---

### Test Case 7: Multi-Device UDP Broadcast Sync
* **Test**: Tap **SYNC: PING UDP 8888** in the top bar.
* **Expected Result**:
  * Broadcaster emits an ultra-low latency UDP packet on port 8888 with:
    `sessionId`, `deviceId`, `sequenceNumber`, and monotonic clock timestamp.
  * Companion scorer devices or receivers decode and deduplicate packets.

---

## 3. Useful ADB Debugging & Inspection Commands

### Live Log Streaming
Filter for vision, state machine, and sync telemetry:
```powershell
adb logcat -s BowlerKinematics BowlerTracker DeliveryState DeliveryBroadcast
```

### Capture Device Screenshots
Take a screenshot of the live HUD and pull it to your PC:
```powershell
adb shell screencap -p /sdcard/live_test.png
adb pull /sdcard/live_test.png .
```

### Simulate Thermal Throttling
Test adaptive thermal tiers without standing in the sun:
```powershell
# Set thermal status to HOT (3) or CRITICAL (4)
adb shell cmd power set-thermal-status 3

# Reset back to NORMAL (0)
adb shell cmd power set-thermal-status 0
```
