package com.haraan.app.ui

import androidx.activity.compose.BackHandler
import androidx.compose.runtime.Composable

/**
 * Makes an in-place overlay respond to the system Back gesture.
 *
 * Most of this app's "screens" are not navigation destinations — they are boolean
 * state inside a parent composable (`if (showBookings) { …; return }`). To the user
 * that is still a screen they navigated into, so Back must close it. Without a
 * handler the press falls through to the Activity, which has only one destination,
 * and **the app closes** — losing whatever the user was doing.
 *
 * Pass the same condition that renders the overlay, and the same action its close
 * button performs, so Back and the ✕ can never disagree:
 *
 * ```
 * DismissOnBack(showBookings) { showBookings = false }
 * ```
 *
 * [enabled] must be false when the overlay is hidden, otherwise this swallows Back
 * for the screen underneath and the app becomes impossible to exit.
 */
@Composable
fun DismissOnBack(enabled: Boolean, onDismiss: () -> Unit) {
    BackHandler(enabled = enabled, onBack = onDismiss)
}
