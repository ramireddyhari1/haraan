package com.haraan.app.vision

import android.os.Build
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.BuildConfig
import org.opencv.android.OpenCVLoader
import org.opencv.core.Core
import org.opencv.core.CvType
import org.opencv.core.Mat
import org.opencv.core.Size
import org.opencv.imgproc.Imgproc

/**
 * Does OpenCV actually work on this device?
 *
 * This exists because the previous phase could not answer that. The tracker only ever runs
 * inside the paired-camera flow, so a native loading failure would have surfaced as "the
 * ball was never detected" — indistinguishable from a detector that simply did not see
 * anything, and the most expensive kind of bug to chase.
 *
 * So the question gets its own door. No pairing, no match, no camera permission: open the
 * deep link and the answer is on screen in a second.
 *
 * DEBUG BUILDS ONLY. The activity is registered behind a debug manifest so it cannot be
 * reached in a release build at all, and it double-checks BuildConfig.DEBUG at runtime in
 * case that registration is ever loosened.
 *
 *     adb shell am start -a android.intent.action.VIEW -d "haraan://vision-check"
 */
class VisionDiagnosticsActivity : ComponentActivity() {

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        if (!BuildConfig.DEBUG) {
            finish()
            return
        }
        val report = runDiagnostics()
        setContent { DiagnosticsScreen(report) }
    }

    /**
     * Load the library, then actually use it.
     *
     * Loading alone proves too little: the .so can map successfully and still be the wrong
     * architecture's maths, or be missing the imgproc module. So this allocates a matrix,
     * runs a real filter over it, and reads a pixel back — if any of that is going to fail
     * on this device, it fails here rather than silently mid-delivery.
     */
    private fun runDiagnostics(): List<Pair<String, String>> {
        val out = mutableListOf<Pair<String, String>>()
        out += "ABI" to (Build.SUPPORTED_ABIS.firstOrNull() ?: "unknown")
        out += "All ABIs" to Build.SUPPORTED_ABIS.joinToString(", ")
        out += "Device" to "${Build.MANUFACTURER} ${Build.MODEL} (API ${Build.VERSION.SDK_INT})"

        val loaded = try {
            OpenCVLoader.initLocal()
        } catch (t: Throwable) {
            out += "Native library" to "FAILED — ${t::class.java.simpleName}: ${t.message}"
            false
        }

        if (!loaded) {
            out += "Native library" to "NOT LOADED"
            out += "Verdict" to "FAIL — vision unavailable on this device"
            return out
        }

        out += "Native library" to "LOADED"
        out += "OpenCV version" to runCatching { Core.getVersionString() }.getOrDefault("unknown")

        // A trivial but genuine operation: allocate, blur, read back.
        val opResult = runCatching {
            val mat = Mat(64, 64, CvType.CV_8UC1)
            mat.setTo(org.opencv.core.Scalar(128.0))
            val blurred = Mat()
            Imgproc.GaussianBlur(mat, blurred, Size(5.0, 5.0), 0.0)
            val pixel = blurred.get(32, 32)
            val rows = blurred.rows()
            val cols = blurred.cols()
            mat.release()
            blurred.release()
            "PASS — ${rows}x$cols blurred, centre = ${pixel?.firstOrNull()?.toInt()}"
        }.getOrElse { "FAIL — ${it::class.java.simpleName}: ${it.message}" }
        out += "Basic operation" to opResult

        // And the real thing: push a synthetic frame through the actual tracker, so the
        // whole path — byte array to Mat to contours — is exercised, not just OpenCV.
        val trackerResult = runCatching {
            val tracker = OpenCvBallTracker(analysisWidth = 160)
            val w = 320
            val h = 240
            var sightings = 0
            // A small bright disc crossing a dark field, three frames apart.
            for (step in 0 until 6) {
                val frame = ByteArray(w * h)
                val cx = 40 + step * 30
                val cy = 120
                for (y in (cy - 4)..(cy + 4)) {
                    for (x in (cx - 4)..(cx + 4)) {
                        val dx = x - cx
                        val dy = y - cy
                        if (dx * dx + dy * dy <= 16 && x in 0 until w && y in 0 until h) {
                            frame[y * w + x] = 255.toByte()
                        }
                    }
                }
                tracker.onFrame(frame, w, h, w, step * 33L)
                sightings = tracker.track().size
            }
            val quality = tracker.quality()
            val diag = tracker.diagnostics()
            tracker.release()
            "PASS — $sightings sightings, quality $quality, ${diag.framesSeen} frames"
        }.getOrElse { "FAIL — ${it::class.java.simpleName}: ${it.message}" }
        out += "Tracker (synthetic)" to trackerResult

        val ok = opResult.startsWith("PASS") && trackerResult.startsWith("PASS")
        out += "Verdict" to if (ok) "PASS — OpenCV usable on this device" else "FAIL"

        return out
    }
}

@Composable
private fun DiagnosticsScreen(report: List<Pair<String, String>>) {
    Column(
        Modifier
            .fillMaxSize()
            .background(Color(0xFF0B1B33))
            .verticalScroll(rememberScrollState())
            .padding(20.dp),
        verticalArrangement = Arrangement.spacedBy(2.dp),
    ) {
        Text(
            "CRICKET VISION DIAGNOSTICS",
            color = Color.White,
            fontSize = 15.sp,
            fontWeight = FontWeight.ExtraBold,
            letterSpacing = 1.4.sp,
        )
        Spacer(Modifier.height(4.dp))
        Text(
            "Debug build only. Verifies that OpenCV loads and runs here.",
            color = Color.White.copy(alpha = 0.5f),
            fontSize = 12.sp,
        )
        Spacer(Modifier.height(18.dp))

        report.forEach { (label, value) ->
            Row(Modifier.fillMaxWidth().padding(vertical = 7.dp)) {
                Text(
                    label,
                    color = Color.White.copy(alpha = 0.55f),
                    fontSize = 12.sp,
                    modifier = Modifier.fillMaxWidth(0.34f),
                )
                Text(
                    value,
                    color = when {
                        value.startsWith("PASS") -> Color(0xFF4ADE80)
                        value.startsWith("FAIL") || value.contains("NOT LOADED") -> Color(0xFFF97066)
                        value == "LOADED" -> Color(0xFF4ADE80)
                        else -> Color.White
                    },
                    fontSize = 12.sp,
                    fontFamily = FontFamily.Monospace,
                    modifier = Modifier.fillMaxWidth(),
                )
            }
        }
    }
}
