package com.haraan.app.vision

import org.junit.Assume

/**
 * OpenCV on a laptop JVM, for tests that run the real detector.
 *
 * The app loads OpenCV through the Android AAR, whose natives only a phone can load, so
 * [OpenCvBallTracker.ensureLoaded] answers false here and every OpenCV engine reports itself
 * unavailable. The openpnp test dependency bundles desktop natives for the same JNI entry
 * points; this loads them and then tells the app's loader it has already succeeded.
 *
 * A machine where they cannot load SKIPS the test rather than failing it: that is a fact
 * about the machine, not about the detector.
 */
object OpenCvJvm {
    @Volatile private var ready: Boolean? = null

    fun require() {
        val ok = ready ?: synchronized(this) {
            ready ?: runCatching {
                nu.pattern.OpenCV.loadLocally()
                val field = OpenCvBallTracker::class.java.getDeclaredField("loaded")
                field.isAccessible = true
                field.set(null, true)
                true
            }.getOrElse {
                System.err.println("desktop OpenCV unavailable: $it")
                false
            }.also { ready = it }
        }
        Assume.assumeTrue("desktop OpenCV natives could not be loaded", ok)
    }
}
