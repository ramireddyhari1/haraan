package com.example.thanna.ui.matches

import android.os.Build
import android.view.HapticFeedbackConstants
import android.view.View

/**
 * Small, consistent haptic vocabulary for the match-detail screen so every surface speaks
 * the same tactile language (mirrors the scorer's boundary/wicket feel on the viewer side).
 */

/** Light selection tick — tab switches, accordion toggles, segment changes. */
fun hapticTick(view: View) {
    view.performHapticFeedback(HapticFeedbackConstants.CLOCK_TICK)
}

/** Firm confirm — committing an action (e.g. opening the scorer). */
fun hapticConfirm(view: View) {
    val constant = if (Build.VERSION.SDK_INT >= 30)
        HapticFeedbackConstants.CONFIRM else HapticFeedbackConstants.LONG_PRESS
    view.performHapticFeedback(constant)
}
