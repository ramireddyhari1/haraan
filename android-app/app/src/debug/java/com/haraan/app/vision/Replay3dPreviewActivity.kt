package com.haraan.app.vision

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import com.haraan.app.BuildConfig

/**
 * haraan://replay-3d — the 3D replay on a known delivery, with no ground, tripod or ball.
 *
 * Debug builds only (registered in the debug manifest). A medium pacer at 120 km/h, pitching
 * six metres out just outside middle and coming on to hit — the kind of ball the replay has
 * to look right on before it is trusted on a real one. `?ball=miss` swaps in one sliding
 * down leg.
 */
class Replay3dPreviewActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        if (!BuildConfig.DEBUG) {
            finish()
            return
        }
        val missing = intent?.data?.getQueryParameter("ball") == "miss"
        // `?t=0.4` holds the replay 40% of the way through, for judging a single frame.
        val hold = intent?.data?.getQueryParameter("t")?.toFloatOrNull()
        val view = intent?.data?.getQueryParameter("view")
        setContent {
            FlightReplayOverlay(
                flight = if (missing) LEG_SIDE else HITTING,
                onClose = { finish() },
                holdAt = hold,
                startView = ReplayView.entries.firstOrNull { it.name.equals(view, ignoreCase = true) },
            )
        }
    }

    private companion object {
        val HITTING = Flight3d(
            bounceX = 0.08,
            bounceY = 6.0,
            bounceMs = 1_000.0,
            inVx = 0.0,
            inVy = -33.3,
            inVzDown = 7.47,
            outVx = 0.05,
            outVy = -28.0,
            outVzUp = 3.6,
            firstSeenMs = 700.0,
            lastSeenMs = 1_150.0,
            cameraHeightM = 1.6,
        )
        val LEG_SIDE = HITTING.copy(bounceX = -0.15, inVx = 0.6, outVx = -1.4)
    }
}
