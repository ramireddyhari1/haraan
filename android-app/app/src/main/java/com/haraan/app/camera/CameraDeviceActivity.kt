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

        setContent {
            ThannaTheme {
                CameraDeviceScreen(
                    initialCode = code,
                    hasCameraPermission = {
                        ContextCompat.checkSelfPermission(this, Manifest.permission.CAMERA) ==
                            PackageManager.PERMISSION_GRANTED
                    },
                    requestCameraPermission = { onResult -> permissionCallback = onResult; askForCamera() },
                    onExit = { finish() },
                )
            }
        }
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
        /**
         * The code out of either link shape: `haraan://camera/H7XH4ZH4WJ` or
         * `https://haraan.app/join/camera/H7XH4ZH4WJ`. Returns null for anything else
         * rather than guessing — a wrong code is a clearer failure than a silent one.
         */
        fun pairingCodeFrom(uri: String?): String? {
            val raw = uri?.trim().orEmpty()
            if (raw.isEmpty()) return null
            val last = raw.trimEnd('/').substringAfterLast('/')
            return last.takeIf { it.isNotBlank() && it.length in 6..32 }?.uppercase()
        }
    }
}
