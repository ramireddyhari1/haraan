package com.example.thanna.ui.theme

import android.provider.Settings
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.platform.LocalContext

/**
 * Central reduced-motion gate. Returns true when the user has turned animations off at the OS
 * level (Settings → Accessibility → Remove animations, i.e. animator duration scale = 0).
 *
 * All the high-fidelity motion in the app (staggered entrances, count-ups, sliding pills,
 * selection pops, content slides) should read this and snap to final state instead — so the
 * polish never fights an accessibility preference. Read once per composition; the OS scale
 * effectively never changes within a screen's lifetime.
 */
@Composable
fun rememberReducedMotion(): Boolean {
    val context = LocalContext.current
    return remember {
        Settings.Global.getFloat(
            context.contentResolver,
            Settings.Global.ANIMATOR_DURATION_SCALE,
            1f
        ) == 0f
    }
}
