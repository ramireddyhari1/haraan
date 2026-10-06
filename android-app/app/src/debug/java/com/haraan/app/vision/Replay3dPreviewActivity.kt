package com.haraan.app.vision

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.layout.BoxWithConstraints
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.runtime.key
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalDensity
import com.haraan.app.BuildConfig
import com.haraan.app.camera.LivePathOverlay
import com.haraan.app.camera.ReplayClip
import com.haraan.app.camera.VideoReplayOverlay
import java.io.File

/**
 * haraan://replay-3d — the after-the-ball screens on a known delivery, with no ground,
 * tripod or ball. Debug builds only (registered in the debug manifest).
 *
 *   (default)     the 3D replay. `?t=0.4` holds a frame, `?view=keeper` picks a shot.
 *   `?mode=ar`    the path on the "camera picture": the ground drawn as a phone behind the
 *                 bowler would film it, pitch corners taken from that drawing, and the path
 *                 projected back through them — so it must land on the drawn pitch and
 *                 stumps, which is the check that the projection is right.
 *   `?mode=video` the slow-motion clip replay, on `cache/replay/test.mp4`.
 *
 * `?ball=miss` swaps in a ball sliding down leg.
 */
class Replay3dPreviewActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        if (!BuildConfig.DEBUG) {
            finish()
            return
        }
        val data = intent?.data
        val flight = if (data?.getQueryParameter("ball") == "miss") LEG_SIDE else HITTING
        val hold = data?.getQueryParameter("t")?.toFloatOrNull()
        val view = data?.getQueryParameter("view")
        val mode = data?.getQueryParameter("mode")
        setContent {
            when (mode) {
                "ar" -> ArPreview(flight)
                "video" -> {
                    val path = remember { pathFor(flight, 720.0, 1280.0) }
                    if (path != null) {
                        VideoReplayOverlay(
                            clip = ReplayClip(File(cacheDir, "replay/test.mp4"), path.startMs - 1_500.0, path),
                            onClose = { finish() },
                            onOpen3d = null,
                        )
                    }
                }
                else -> FlightReplayOverlay(
                    flight = flight,
                    onClose = { finish() },
                    holdAt = hold,
                    startView = ReplayView.entries.firstOrNull { it.name.equals(view, ignoreCase = true) },
                )
            }
        }
    }

    @androidx.compose.runtime.Composable
    private fun ArPreview(flight: Flight3d) {
        BoxWithConstraints(Modifier.fillMaxSize()) {
            val density = LocalDensity.current
            val w = with(density) { maxWidth.toPx() }.toDouble()
            val h = with(density) { maxHeight.toPx() }.toDouble()
            val path = remember(w, h) { pathFor(flight, w, h) }
            val grain = remember { grainBrush() }
            val crowd = remember { crowdBrush() }
            val turf = remember { turfBrush() }
            var round by remember { mutableIntStateOf(0) }
            Canvas(Modifier.fillMaxSize()) {
                drawScene(PHONE, flight, flight.releaseMs, flight.releaseMs - 1.0, grain, crowd, turf, 0f)
            }
            if (path != null) {
                key(round) {
                    LivePathOverlay(path = path, uprightAspect = (w / h).toFloat(), onHandOff = {}, onDone = { round++ })
                }
            }
        }
    }

    private companion object {
        /** A phone on a tripod behind the bowler's arm, chest height. */
        val PHONE = ReplayCamera(Point3(0.0, 24.6, 1.7), Point3(0.0, 6.0, 0.0), zoom = 1.7)

        /** The path as [PHONE] would see it on a [w] x [h] picture, through corners read off it. */
        fun pathFor(flight: Flight3d, w: Double, h: Double): ArPath? {
            val corners = PitchGeometry.calibrationCorners(CameraEnd.BOWLER).map { c ->
                val p = PHONE.project(Point3(c.x, c.y, 0.0), w, h) ?: return null
                Point2(p.x / w, p.y / h)
            }
            val quad = PitchQuad(corners, QuadSource.TAPPED, 1f, CameraEnd.BOWLER)
            return ArPath.fromFlight(flight, quad, (w / h).toFloat())
        }

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
