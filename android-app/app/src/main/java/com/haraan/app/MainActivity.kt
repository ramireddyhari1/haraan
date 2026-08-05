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
import kotlinx.coroutines.launch

/**
 * Hosts the app UI and also receives Razorpay Checkout results. The SDK delivers payment
 * callbacks to the Activity, which forwards them to [PaymentBridge] so the checkout screen
 * that opened the sheet can confirm (or release) the reservation.
 */
class MainActivity : ComponentActivity(), PaymentResultWithDataListener {
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
    super.onCreate(savedInstanceState)

    // Warm up the checkout SDK so the first payment sheet opens without a cold-start lag.
    Checkout.preload(applicationContext)

    // Push: ensure the channel exists, ask for the 13+ notification permission, and
    // register this device's FCM token with the backend (no-op unless signed in).
    PushNotifications.ensureChannel(applicationContext)
    maybeRequestNotificationPermission()
    PushRegistrar.syncToken(applicationContext)

    // Load remote config (feature flags + theme) and the translation overlay at
    // launch, then open the realtime socket so later admin changes arrive live.
    // Best-effort: the in-memory stores keep their defaults if it fails.
    lifecycleScope.launch {
      RemoteBootstrap.load(applicationContext)
      RealtimeClient.start(applicationContext, RemoteConfigStore.config.realtime)
    }

    // A deep link carried on the launch intent (cold start from a tapped push).
    handleDeepLinkIntent(intent)

    enableEdgeToEdge()
    setContent {
      ThannaTheme {
        // The app composes BEHIND the brand splash and is fully interactive the
        // moment it lifts — the splash is a time-boxed brand moment, never a gate
        // on loading. A slow network can't strand the user here.
        var splashDone by rememberSaveable { mutableStateOf(false) }
        Box(modifier = Modifier.fillMaxSize()) {
          MainNavigation()
          if (!splashDone) {
            com.haraan.app.ui.BrandSplash(onFinished = { splashDone = true })
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
