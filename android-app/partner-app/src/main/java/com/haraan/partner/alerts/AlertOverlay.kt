package com.haraan.partner.alerts

import android.content.Context
import android.content.Intent
import android.graphics.PixelFormat
import android.os.Build
import android.provider.Settings
import android.view.Gravity
import android.view.WindowManager
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.runtime.mutableStateOf
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.ComposeView
import androidx.compose.ui.unit.dp
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.LifecycleOwner
import androidx.lifecycle.LifecycleRegistry
import androidx.lifecycle.setViewTreeLifecycleOwner
import androidx.savedstate.SavedStateRegistry
import androidx.savedstate.SavedStateRegistryController
import androidx.savedstate.SavedStateRegistryOwner
import androidx.savedstate.setViewTreeSavedStateRegistryOwner
import com.haraan.partner.MainActivity
import com.haraan.partner.formatInr

/**
 * Draws the booking card over whatever app the partner has open — the way a ride
 * request appears on a driver's phone. Needs "Display over other apps"; without it the
 * service posts a high-priority notification instead.
 *
 * One window, reused: a second booking while a card is up replaces its contents.
 */
class AlertOverlay(private val context: Context) {

    private val wm = context.getSystemService(Context.WINDOW_SERVICE) as WindowManager
    private var view: ComposeView? = null
    private var owner: OverlayOwner? = null
    private val current = mutableStateOf<BookingAlert?>(null)

    fun show(alert: BookingAlert) {
        current.value = alert
        if (view != null) return
        val o = OverlayOwner().also { it.start() }
        val v = ComposeView(context).apply {
            setViewTreeLifecycleOwner(o)
            setViewTreeSavedStateRegistryOwner(o)
            setContent {
                val a = current.value
                if (a != null) {
                    Box(Modifier.fillMaxWidth().statusBarsPadding().padding(top = 8.dp)) {
                        // Keyed so a new booking replays the drop-in.
                        androidx.compose.runtime.key(a.id) {
                            BookingAlertCard(
                                alert = a,
                                onOpen = { open(); hide() },
                                onDismiss = { hide() },
                                formatInr = ::formatInr,
                            )
                        }
                    }
                }
            }
        }
        val type = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            WindowManager.LayoutParams.TYPE_APPLICATION_OVERLAY
        } else {
            @Suppress("DEPRECATION") WindowManager.LayoutParams.TYPE_PHONE
        }
        val lp = WindowManager.LayoutParams(
            WindowManager.LayoutParams.MATCH_PARENT,
            WindowManager.LayoutParams.WRAP_CONTENT,
            type,
            // Touches outside the card go to the app underneath; the keyboard is never taken.
            WindowManager.LayoutParams.FLAG_NOT_FOCUSABLE or
                WindowManager.LayoutParams.FLAG_NOT_TOUCH_MODAL or
                WindowManager.LayoutParams.FLAG_LAYOUT_IN_SCREEN,
            PixelFormat.TRANSLUCENT,
        ).apply { gravity = Gravity.TOP }
        runCatching { wm.addView(v, lp) }.onFailure { o.stop(); return }
        view = v
        owner = o
    }

    fun hide() {
        current.value = null
        view?.let { runCatching { wm.removeView(it) } }
        owner?.stop()
        view = null
        owner = null
    }

    private fun open() {
        AlertRouter.requestBookings()
        context.startActivity(
            Intent(context, MainActivity::class.java)
                .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_SINGLE_TOP or Intent.FLAG_ACTIVITY_CLEAR_TOP)
                .putExtra(AlertRouter.EXTRA_OPEN, AlertRouter.OPEN_BOOKINGS),
        )
    }

    companion object {
        fun allowed(context: Context): Boolean =
            Build.VERSION.SDK_INT < Build.VERSION_CODES.M || Settings.canDrawOverlays(context)
    }
}

/** Compose needs a lifecycle and saved state; a window owned by a service has neither. */
private class OverlayOwner : LifecycleOwner, SavedStateRegistryOwner {
    private val registry = LifecycleRegistry(this)
    private val saved = SavedStateRegistryController.create(this)
    override val lifecycle: Lifecycle get() = registry
    override val savedStateRegistry: SavedStateRegistry get() = saved.savedStateRegistry

    fun start() {
        saved.performRestore(null)
        registry.currentState = Lifecycle.State.RESUMED
    }

    fun stop() {
        registry.currentState = Lifecycle.State.DESTROYED
    }
}
