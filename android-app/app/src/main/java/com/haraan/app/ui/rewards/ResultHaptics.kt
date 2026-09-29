package com.haraan.app.ui.rewards

import android.content.Context
import android.os.Build
import android.os.VibrationEffect
import com.haraan.app.ui.matches.vibratorFor
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.delay

/**
 * The result, felt. Fired once, at the exact frame the illustration touches down, so the
 * hand and the eye get the same moment. It is the screen's only haptic on entry.
 *
 * Same rule as [com.haraan.app.ui.matches.cricketThud]: meaning rides on the COUNT of
 * knocks, because most phones here have ERM motors that throw amplitude away.
 *
 *   Victory  → a thump, then a quick ba-dum (three knocks). The trophy lands, the crowd answers.
 *   Draw     → two even clicks. Balanced.
 *   Defeat   → one light tick. Acknowledged, never punished.
 *   Full time → one click.
 */
internal suspend fun resultThud(context: Context, outcome: Outcome) {
    try {
        val vibrator = vibratorFor(context) ?: return

        if (!vibrator.hasAmplitudeControl()) {
            fun predefined(id: Int, fallbackMs: Long): VibrationEffect =
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) VibrationEffect.createPredefined(id)
                else VibrationEffect.createOneShot(fallbackMs, VibrationEffect.DEFAULT_AMPLITUDE)

            when (outcome) {
                Outcome.WON -> {
                    vibrator.vibrate(predefined(VibrationEffect.EFFECT_HEAVY_CLICK, 45))
                    delay(150)
                    vibrator.vibrate(predefined(VibrationEffect.EFFECT_DOUBLE_CLICK, 30))
                }
                Outcome.TIED -> {
                    vibrator.vibrate(predefined(VibrationEffect.EFFECT_CLICK, 28))
                    delay(170)
                    vibrator.vibrate(predefined(VibrationEffect.EFFECT_CLICK, 28))
                }
                Outcome.LOST -> vibrator.vibrate(predefined(VibrationEffect.EFFECT_TICK, 18))
                Outcome.FINISHED -> vibrator.vibrate(predefined(VibrationEffect.EFFECT_CLICK, 28))
            }
            return
        }

        val (timings, amplitudes) = when (outcome) {
            // Landing thump, a breath, then a lighter-heavier pair.
            Outcome.WON -> longArrayOf(0, 70, 130, 35, 60, 80) to intArrayOf(0, 255, 0, 150, 0, 255)
            Outcome.TIED -> longArrayOf(0, 35, 150, 35) to intArrayOf(0, 150, 0, 150)
            Outcome.LOST -> longArrayOf(0, 22) to intArrayOf(0, 70)
            Outcome.FINISHED -> longArrayOf(0, 30) to intArrayOf(0, 120)
        }
        vibrator.vibrate(VibrationEffect.createWaveform(timings, amplitudes, -1))
    } catch (e: CancellationException) {
        throw e
    } catch (_: Throwable) {
        // A phone without a motor must never cost the result screen.
    }
}
