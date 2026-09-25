package com.haraan.partner.ui

import android.content.Context
import android.os.Build
import android.os.VibrationEffect
import android.os.Vibrator
import android.os.VibratorManager
import android.provider.Settings
import android.view.HapticFeedbackConstants
import android.view.View

/**
 * The partner app's four buzzes, each meaning one thing.
 *
 * Before this, almost every tap in the app gave the same small tick, so the phone
 * said "something happened" and nothing more. At a turf desk or an event gate the
 * operator is usually looking at the customer, not the screen — the buzz itself has
 * to tell them how it went:
 *
 *  - [tick]    tabs, chips, toggles, cards. The lightest thing the phone has.
 *  - [confirm] it went through: a ticket checked in, a sign-in accepted.
 *  - [money]   money landed: a booking taken, a payment collected. The firmest.
 *  - [warn]    it went through, but look: a ticket that was already used.
 *  - [reject]  it did not: an invalid ticket, a slot that was just taken.
 *    A double pulse, so it can never be mistaken for the others.
 *
 * [tick] goes through the View so it follows the system touch-feedback setting like
 * every other tap on the phone. The outcome buzzes use the Vibrator directly —
 * `View` confirm/reject constants only exist from API 30 and MIUI maps them to the
 * same generic click — but they honour that same setting, so a partner who has
 * turned haptics off is never buzzed. Needs `android.permission.VIBRATE`: without it
 * `Vibrator.vibrate()` is a silent no-op, with no exception and no log.
 */
object Haptics {

    fun tick(view: View) {
        view.performHapticFeedback(HapticFeedbackConstants.KEYBOARD_TAP)
    }

    fun confirm(view: View) = play(view, Buzz.CONFIRM)

    fun money(view: View) = play(view, Buzz.MONEY)

    fun warn(view: View) = play(view, Buzz.WARN)

    fun reject(view: View) = play(view, Buzz.REJECT)

    private enum class Buzz(
        /** Predefined effect on API 29+, which the vendor tunes to the phone's motor. */
        val predefined: Int,
        /**
         * Fallback on older phones: on/off timings only. Amplitude arrays are dropped
         * by most rotary motors anyway, so the rhythm has to carry the meaning.
         */
        val timings: LongArray,
    ) {
        CONFIRM(VibrationEffect.EFFECT_CLICK, longArrayOf(0, 22)),
        MONEY(VibrationEffect.EFFECT_HEAVY_CLICK, longArrayOf(0, 48)),
        WARN(VibrationEffect.EFFECT_HEAVY_CLICK, longArrayOf(0, 90)),
        REJECT(VibrationEffect.EFFECT_DOUBLE_CLICK, longArrayOf(0, 38, 80, 38)),
    }

    private fun play(view: View, buzz: Buzz) {
        val context = view.context
        if (!systemHapticsOn(context)) return
        val vibrator = vibratorOf(context)
        if (vibrator == null || !vibrator.hasVibrator()) {
            // No motor we can drive: still give the View's own feedback.
            view.performHapticFeedback(HapticFeedbackConstants.KEYBOARD_TAP)
            return
        }
        runCatching {
            when {
                // WARN is the one outcome the predefined set has no shape for: a
                // held buzz, longer than a click, reads as "hang on" rather than "no".
                buzz == Buzz.WARN && Build.VERSION.SDK_INT >= Build.VERSION_CODES.O ->
                    vibrator.vibrate(VibrationEffect.createOneShot(90, VibrationEffect.DEFAULT_AMPLITUDE))
                Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q ->
                    vibrator.vibrate(VibrationEffect.createPredefined(buzz.predefined))
                Build.VERSION.SDK_INT >= Build.VERSION_CODES.O ->
                    vibrator.vibrate(VibrationEffect.createWaveform(buzz.timings, -1))
                else ->
                    @Suppress("DEPRECATION") vibrator.vibrate(buzz.timings, -1)
            }
        }
    }

    private fun systemHapticsOn(context: Context): Boolean =
        Settings.System.getInt(context.contentResolver, Settings.System.HAPTIC_FEEDBACK_ENABLED, 1) != 0

    private fun vibratorOf(context: Context): Vibrator? =
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            context.getSystemService(VibratorManager::class.java)?.defaultVibrator
        } else {
            @Suppress("DEPRECATION")
            context.getSystemService(Context.VIBRATOR_SERVICE) as? Vibrator
        }
}
