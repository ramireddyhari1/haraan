package com.haraan.app.camera

import android.Manifest
import android.content.pm.PackageManager
import android.os.Bundle
import android.view.WindowManager
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.result.contract.ActivityResultContracts
import androidx.core.content.ContextCompat
import com.haraan.app.theme.ThannaTheme

/**
 * A phone joining a match as a camera.
 *
 * Its own Activity, entered only from a pairing link. Everything the scoring app takes
 * for granted is absent here on purpose: no account, no splash, no bottom navigation.
 * The person holding this phone is a friend at the boundary who was handed a QR code,
 * and asking them to sign in first would end the feature before it started.
 *
 * The screen is kept awake for the whole session — a camera pointed at the stumps that
 * dims between deliveries is a camera that missed the ball.
 */
class CameraDeviceActivity : ComponentActivity() {

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        window.addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON)

        val code = pairingCodeFrom(intent?.data?.toString())
        /*
         * Debug builds: `haraan://camera/TEST?video=net.mp4` feeds a filmed delivery from the
         * app's files folder to the live analyser instead of the lens. A bare file name only.
         */
        val videoFeed = if (com.haraan.app.BuildConfig.DEBUG) {
            intent?.data?.getQueryParameter("video")
                ?.takeIf { it.isNotBlank() && !it.contains('/') && !it.contains("..") }
                ?.let { java.io.File(getExternalFilesDir(null), it) }
                ?.takeIf { it.isFile }
                ?.absolutePath
        } else {
            null
        }

        // Debug builds: `?quality=highest` (or 1080p30 …) for the field-test session.
        val testQuality = if (com.haraan.app.BuildConfig.DEBUG) intent?.data?.getQueryParameter("quality") else null

        // A Hero bowling session, started from the Sessions screen inside the app. An extra,
        // not part of the link, and checked again against the member's plan by the screen.
        val practiceSessionId = intent?.getStringExtra(EXTRA_BOWLING_SESSION)
        bowlingSessionId = practiceSessionId

        setContent {
            ThannaTheme {
                CameraDeviceScreen(
                    initialCode = if (practiceSessionId != null) null else code,
                    practiceSessionId = practiceSessionId,
                    hasCameraPermission = {
                        ContextCompat.checkSelfPermission(this, Manifest.permission.CAMERA) ==
                            PackageManager.PERMISSION_GRANTED
                    },
                    requestCameraPermission = { onResult -> permissionCallback = onResult; askForCamera() },
                    onExit = { finish() },
                    videoFeed = videoFeed,
                    testQuality = testQuality,
                )
            }
        }
    }

    private var bowlingSessionId: String? = null

    override fun onDestroy() {
        // Closed with Back or swiped away: the session is over, so it moves to History.
        if (isFinishing) bowlingSessionId?.let { BowlingSessionStore.end(applicationContext, it) }
        super.onDestroy()
    }

    // ── Camera permission, asked at the moment it is needed and not before ──

    private var permissionCallback: ((Boolean) -> Unit)? = null

    private val cameraPermission = registerForActivityResult(
        ActivityResultContracts.RequestPermission(),
    ) { granted ->
        permissionCallback?.invoke(granted)
        permissionCallback = null
    }

    private fun askForCamera() = cameraPermission.launch(Manifest.permission.CAMERA)

    companion object {
        const val EXTRA_BOWLING_SESSION = "com.haraan.app.extra.BOWLING_SESSION"

        /** Films the bowling session [sessionId] on this phone, with no match behind it. */
        fun openBowlingSession(context: android.content.Context, sessionId: String) {
            context.startActivity(
                android.content.Intent(context, CameraDeviceActivity::class.java)
                    .putExtra(EXTRA_BOWLING_SESSION, sessionId)
                    .addFlags(android.content.Intent.FLAG_ACTIVITY_NEW_TASK),
            )
        }

        /**
         * The code out of either link shape: `haraan://camera/H7XH4ZH4WJ` or
         * `https://haraan.app/join/camera/H7XH4ZH4WJ`. Returns null for anything else
         * rather than guessing — a wrong code is a clearer failure than a silent one.
         */
        fun pairingCodeFrom(uri: String?): String? {
            val raw = uri?.trim().orEmpty()
            if (raw.isEmpty()) return null
            // The path's last segment only: a query or fragment is never part of a code.
            val last = raw.substringBefore('?').substringBefore('#').trimEnd('/').substringAfterLast('/')
            return last.takeIf { it.isNotBlank() && it.length in 6..32 }?.uppercase()
        }
    }
}
