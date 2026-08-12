package com.haraan.app

import android.Manifest
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Build
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.core.content.ContextCompat
import androidx.core.splashscreen.SplashScreen.Companion.installSplashScreen
import androidx.lifecycle.lifecycleScope
import com.haraan.app.data.LanguageManager
import com.haraan.app.data.PaymentBridge
import com.haraan.app.data.RealtimeClient
import com.haraan.app.data.RemoteBootstrap
import com.haraan.app.data.RemoteConfigStore
import com.haraan.app.push.DeepLinkState
import com.haraan.app.push.DeepLinks
import com.haraan.app.push.PushNotifications
import com.haraan.app.push.PushRegistrar
import com.haraan.app.theme.ThannaTheme
import com.razorpay.Checkout
import com.razorpay.PaymentData
import com.razorpay.PaymentResultWithDataListener
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext

/**
 * Backstop for the deferred warm-ups when the splash never reports finished —
 * skipped after a config change, animation interrupted, whatever. Comfortably
 * longer than BrandSplash's ~1.65s so the normal path wins the race.
 */
private const val WARMUP_FALLBACK_MS = 6000L

/**
 * Hosts the app UI and also receives Razorpay Checkout results. The SDK delivers payment
 * callbacks to the Activity, which forwards them to [PaymentBridge] so the checkout screen
 * that opened the sheet can confirm (or release) the reservation.
 */
class MainActivity : ComponentActivity(), PaymentResultWithDataListener {
  /**
   * Heavy third-party warm-ups, kicked off once the launch is visually done.
   *
   * Both of these used to run synchronously in [onCreate] and were 11.2s of a
   * 15.3s cold start on the Pixel_9 emulator (measured):
   *   Checkout.preload         9747ms  Razorpay SDK class-load + warm-up
   *   PushRegistrar.syncToken  1415ms  FirebaseMessaging.getInstance()
   *
   * Idempotent — the splash callback and the fallback timer both call it, and
   * whichever arrives first wins.
   */
  private val warmupsStarted = java.util.concurrent.atomic.AtomicBoolean(false)

  private fun startDeferredWarmups() {
    if (!warmupsStarted.compareAndSet(false, true)) return
    lifecycleScope.launch(Dispatchers.IO) {
      val t = android.os.SystemClock.uptimeMillis()
      // Failures are non-fatal by design: preload is only a warm-up (checkout
      // works without it) and the token sync retries on the next launch.
      val preload = runCatching { Checkout.preload(applicationContext) }
      val push = runCatching { PushRegistrar.syncToken(applicationContext) }
      if (BuildConfig.DEBUG) {
        android.util.Log.i(
          "StartupTrace",
          "deferred warm-ups: ${android.os.SystemClock.uptimeMillis() - t}ms " +
            "(preload ok=${preload.isSuccess}, push ok=${push.isSuccess})"
        )
      }
    }
  }

  // POST_NOTIFICATIONS runtime prompt (Android 13+). The result doesn't gate anything —
  // a denied prompt just means the OS drops shade notifications; the FCM token is still
  // registered so the in-app bell inbox keeps working either way.
  private val requestNotificationPermission =
    registerForActivityResult(ActivityResultContracts.RequestPermission()) { /* no-op */ }

  // Apply the user's chosen language to every resource lookup in this Activity.
  override fun attachBaseContext(newBase: Context) {
    super.attachBaseContext(LanguageManager.wrap(newBase))
  }

  override fun onCreate(savedInstanceState: Bundle?) {
    // Stage 1 of the launch experience. MUST run before super.onCreate() — that's
    // what swaps the launch theme (Theme.Haraan.Splash) for postSplashScreenTheme,
    // and it's what makes the system splash brand-plated instead of the bare
    // launcher icon on a blank window. It dismisses on the first frame; the
    // branded animation continues in-app as BrandSplash.
    installSplashScreen()
    val t0 = android.os.SystemClock.uptimeMillis()
    super.onCreate(savedInstanceState)

    // Cheap and ordering-sensitive, so these stay on the critical path: the channel
    // must exist before any notification can be posted, and the permission prompt
    // is a no-op call that only registers a contract. Measured at 33ms and 4ms.
    PushNotifications.ensureChannel(applicationContext)
    maybeRequestNotificationPermission()

    // ── Everything heavy runs OFF the main thread. ───────────────────────────
    // These two used to sit here synchronously and were 11.2s of a 15.3s cold
    // start on the Pixel_9 emulator (measured with a startup trace):
    //
    //   Checkout.preload(...)         9747ms   Razorpay SDK class-load + warm-up
    //   PushRegistrar.syncToken(...)  1415ms   FirebaseMessaging.getInstance()
    //
    // Neither is needed to draw the first frame — preload only has to finish
    // before the user reaches a payment sheet, and the FCM token only before the
    // backend sends a push. Deferring them to the main thread post-frame would
    // just move a 10s freeze; they have to be off it entirely.
    //
    // Separate coroutine from the config load below ON PURPOSE: chaining them
    // would put the slow preload in front of remote config, which the UI reads.
    //
    // It also waits for the brand splash to finish (see [startDeferredWarmups]).
    // Off-thread alone was not enough: the preload is CPU-bound, and on a 2-core
    // device it competes with Compose's first composition and with the splash
    // animation. Idle-time work, not launch-time work. Razorpay needs preload
    // only before a payment sheet opens, and it is a warm-up — checkout still
    // works if it hasn't run yet, so nothing is gated on this.
    //
    // Safety net: if the splash never reports finished (skipped after a config
    // change, animation interrupted), the warm-ups must still happen.
    lifecycleScope.launch {
      kotlinx.coroutines.delay(WARMUP_FALLBACK_MS)
      startDeferredWarmups()
    }

    // Load remote config (feature flags + theme) and the translation overlay at
    // launch, then open the realtime socket so later admin changes arrive live.
    // Best-effort: the in-memory stores keep their defaults if it fails.
    lifecycleScope.launch {
      RemoteBootstrap.load(applicationContext)
      RealtimeClient.start(applicationContext, RemoteConfigStore.config.realtime)
    }

    // A deep link carried on the launch intent (cold start from a tapped push).
    handleDeepLinkIntent(intent)

    // Debug-only startup trace. Kept because this is easy to regress: one heavy
    // call added back into onCreate costs seconds and nothing else would catch it.
    // Read with: adb logcat -s StartupTrace:I
    if (BuildConfig.DEBUG) {
      android.util.Log.i("StartupTrace", "onCreate main-thread work: ${android.os.SystemClock.uptimeMillis() - t0}ms")
    }
    enableEdgeToEdge()
    setContent {
      if (BuildConfig.DEBUG) {
        androidx.compose.runtime.SideEffect {
          android.util.Log.i("StartupTrace", "first composition: ${android.os.SystemClock.uptimeMillis() - t0}ms")
        }
      }
      ThannaTheme {
        // The app composes BEHIND the brand splash and is fully interactive the
        // moment it lifts — the splash is a time-boxed brand moment, never a gate
        // on loading. A slow network can't strand the user here.
        var splashDone by rememberSaveable { mutableStateOf(false) }
        Box(modifier = Modifier.fillMaxSize()) {
          MainNavigation()
          if (!splashDone) {
            com.haraan.app.ui.BrandSplash(onFinished = {
              splashDone = true
              // The launch is visually complete — now it's safe to burn CPU.
              startDeferredWarmups()
            })
          }
        }
      }
    }
  }

  // A push tapped while the app is already running (singleTop) arrives here.
  override fun onNewIntent(intent: Intent) {
    super.onNewIntent(intent)
    setIntent(intent)
    handleDeepLinkIntent(intent)
  }

  /**
   * Pull a deep link off a launch/tap intent. Web URLs open in the browser; app
   * links are handed to [DeepLinkState] for MainAppContainer to route.
   */
  private fun handleDeepLinkIntent(intent: Intent?) {
    val link = intent?.getStringExtra(PushNotifications.EXTRA_DEEP_LINK)?.takeIf { it.isNotBlank() }
      ?: return
    if (DeepLinks.isWebUrl(link)) {
      runCatching { startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(link))) }
    } else {
      DeepLinkState.set(link)
    }
  }

  /** Prompt for POST_NOTIFICATIONS once on 13+ if it hasn't already been granted. */
  private fun maybeRequestNotificationPermission() {
    if (Build.VERSION.SDK_INT < Build.VERSION_CODES.TIRAMISU) return
    val granted = ContextCompat.checkSelfPermission(
      this,
      Manifest.permission.POST_NOTIFICATIONS,
    ) == PackageManager.PERMISSION_GRANTED
    if (!granted) {
      requestNotificationPermission.launch(Manifest.permission.POST_NOTIFICATIONS)
    }
  }

  override fun onPaymentSuccess(razorpayPaymentId: String?, data: PaymentData?) {
    PaymentBridge.deliver(
      PaymentBridge.Outcome.Success(
        orderId = data?.orderId.orEmpty(),
        paymentId = razorpayPaymentId ?: data?.paymentId.orEmpty(),
        signature = data?.signature.orEmpty(),
      )
    )
  }

  override fun onPaymentError(code: Int, response: String?, data: PaymentData?) {
    // Razorpay reports a user-dismissed sheet as a distinct code — release the hold then;
    // anything else is a genuine failure to surface.
    if (code == Checkout.PAYMENT_CANCELED) {
      PaymentBridge.deliver(PaymentBridge.Outcome.Cancelled)
    } else {
      PaymentBridge.deliver(PaymentBridge.Outcome.Failed(response ?: "Payment failed."))
    }
  }
}
