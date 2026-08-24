package com.haraan.app.ui.matches

import android.content.Context
import android.os.Build
import android.os.VibrationEffect
import android.os.Vibrator
import android.os.VibratorManager
import kotlinx.coroutines.delay

/**
 * The one place cricket turns into something you can feel.
 *
 * Both surfaces that buzz share this: the hero card's boundary burst (a viewer watching a
 * six land) and the scorer's keypad (the person entering it). They must agree — a six felt
 * one way while watching and another way while scoring is two apps in one — and the last
 * time this logic existed twice, the two copies fired on the SAME ball a few milliseconds
 * apart and read as one smeared buzz.
 *
 * ## Why count, not strength
 *
 * The obvious design is intensity: a soft tick for a single, a heavy slam for a six. It
 * does not survive contact with real phones. Any handset with a rotary (ERM) motor — which
 * is most of them, and nearly all of the ones this app runs on — reports
 * `hasAmplitudeControl() == false`, and on those the amplitude array handed to
 * `createWaveform` is **discarded**: every pulse plays flat out. A carefully graded scale
 * arrives as one undifferentiated buzz.
 *
 * So meaning is carried by the NUMBER of knocks, which every motor can render:
 *
 *   one light   → a run, or a dot
 *   one firm    → a four
 *   two         → a six
 *   three       → a wicket
 *
 * A scorer watching the game rather than the screen can tell what they just entered
 * without looking down, which is the entire point.
 *
 * On hardware without amplitude control the device's own predefined effects are used.
 * They are tuned by the OEM for that exact motor, including its spin-up time, and land far
 * crisper than a hand-rolled 40ms pulse an ERM barely has time to start. The shaped
 * waveform is kept only for hardware that can actually express it.
 *
 * Nothing here ever throws. A phone with no vibrator, or a user who has switched haptics
 * off, must not be able to break a scoreboard.
 */
enum class Thud {
    /** A dot ball, or a tap that changed nothing. The lightest thing that still registers. */
    TICK,

    /** Runs off the bat — one, two, three. Present, unremarkable. */
    RUN,

    /** Four. One firm knock. */
    FOUR,

    /** Six. Two — the only routine event that gets a pair. */
    SIX,

    /** A wicket. Three sharp knocks; never reads as celebration. */
    WICKET,

    /** Wide, no-ball, bye, leg bye. Deliberately duller than a run — it wasn't off the bat. */
    EXTRA,

    /** Undo. Two quick LIGHT taps: paired like a six, but nothing like it in weight. */
    UNDO,
}

private fun vibratorFor(context: Context): Vibrator? = try {
    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
        (context.getSystemService(Context.VIBRATOR_MANAGER_SERVICE) as? VibratorManager)?.defaultVibrator
    } else {
        @Suppress("DEPRECATION")
        context.getSystemService(Context.VIBRATOR_SERVICE) as? Vibrator
    }?.takeIf { it.hasVibrator() }
} catch (_: Throwable) {
    null
}

/**
 * Play [kind]. Suspends only for the multi-knock patterns, which need real gaps between
 * pulses — a gap shorter than the motor's own ring-down is not felt as two knocks.
 *
 * NOTE: `android.permission.VIBRATE` must be declared or every call here is a silent
 * no-op. It is granted at install with no prompt, so its absence throws nothing and logs
 * nothing — the UI animates perfectly and the phone sits still.
 */
suspend fun cricketThud(context: Context, kind: Thud) {
    try {
        val vibrator = vibratorFor(context) ?: return

        if (!vibrator.hasAmplitudeControl()) {
            val heavy = VibrationEffect.createPredefined(VibrationEffect.EFFECT_HEAVY_CLICK)
            val light = VibrationEffect.createPredefined(VibrationEffect.EFFECT_TICK)
            val click = VibrationEffect.createPredefined(VibrationEffect.EFFECT_CLICK)
            when (kind) {
                Thud.TICK -> vibrator.vibrate(light)
                Thud.RUN -> vibrator.vibrate(click)
                Thud.EXTRA -> vibrator.vibrate(light)
                Thud.FOUR -> vibrator.vibrate(heavy)
                // One tuned double-knock rather than two singles: the OEM spaces the pair
                // for its own motor better than a guessed gap can.
                Thud.SIX -> vibrator.vibrate(
                    VibrationEffect.createPredefined(VibrationEffect.EFFECT_DOUBLE_CLICK)
                )
                Thud.WICKET -> repeat(3) { i ->
                    if (i > 0) delay(95)
                    vibrator.vibrate(heavy)
                }
                Thud.UNDO -> repeat(2) { i ->
                    if (i > 0) delay(70)
                    vibrator.vibrate(light)
                }
            }
            return
        }

        // Amplitude-capable hardware: timings alternate off/on, starting with a 0ms wait,
        // and the amplitudes actually mean something here.
        val (timings, amplitudes) = when (kind) {
            Thud.TICK -> longArrayOf(0, 18) to intArrayOf(0, 70)
            Thud.RUN -> longArrayOf(0, 26) to intArrayOf(0, 120)
            Thud.EXTRA -> longArrayOf(0, 22) to intArrayOf(0, 90)
            Thud.FOUR -> longArrayOf(0, 60) to intArrayOf(0, 170)
            Thud.SIX -> longArrayOf(0, 55, 70, 90) to intArrayOf(0, 200, 0, 255)
            Thud.WICKET -> longArrayOf(0, 40, 45, 40, 45, 110) to intArrayOf(0, 255, 0, 255, 0, 255)
            Thud.UNDO -> longArrayOf(0, 20, 60, 20) to intArrayOf(0, 90, 0, 90)
        }
        vibrator.vibrate(VibrationEffect.createWaveform(timings, amplitudes, -1))
    } catch (e: kotlinx.coroutines.CancellationException) {
        // Leaving the screen mid-wicket cancels this. Cancellation is not a failure and
        // must travel on, or the coroutine that owns it never learns it is done.
        throw e
    } catch (_: Throwable) {
        // Haptics are a nicety. Losing them must never cost the screen.
    }
}
