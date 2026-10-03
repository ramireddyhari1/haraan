package com.haraan.partner

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.graphics.Color

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        routeFrom(intent)
        // On duty survives the app being closed and reopened.
        val session = Session(this)
        if (session.onDuty && session.isSignedIn) com.haraan.partner.alerts.BookingWatchService.start(this)
        setContent {
            MaterialTheme(colorScheme = PartnerColors) {
                // The app composes immediately behind the splash; the branded
                // moment is a time-boxed overlay, not a gate on any network work.
                var showSplash by remember { mutableStateOf(true) }
                PartnerApp()
                if (showSplash) {
                    BrandSplash(onFinished = { showSplash = false })
                }
            }
        }
    }

    override fun onNewIntent(intent: android.content.Intent) {
        super.onNewIntent(intent)
        routeFrom(intent)
    }

    /** A tap on a booking alert opens the app on Bookings. */
    private fun routeFrom(intent: android.content.Intent?) {
        if (intent?.getStringExtra(com.haraan.partner.alerts.AlertRouter.EXTRA_OPEN) == com.haraan.partner.alerts.AlertRouter.OPEN_BOOKINGS) {
            com.haraan.partner.alerts.AlertRouter.requestBookings()
        }
    }
}

/** Blue-forward palette — progress/actions are blue, never pink (brand rule). */
private val PartnerColors = lightColorScheme(
    primary = Color(0xFF1D4ED8),
    onPrimary = Color.White,
    primaryContainer = Color(0xFFDBE7FF),
    onPrimaryContainer = Color(0xFF0B255C),
    secondary = Color(0xFF0F766E),
    background = Color(0xFFF6F7FB),
    surface = Color.White,
)
