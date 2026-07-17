package com.haraan.app

import android.content.Context
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.lifecycle.lifecycleScope
import com.haraan.app.data.LanguageManager
import com.haraan.app.data.PaymentBridge
import com.haraan.app.data.RealtimeClient
import com.haraan.app.data.RemoteBootstrap
import com.haraan.app.data.RemoteConfigStore
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
  // Apply the user's chosen language to every resource lookup in this Activity.
  override fun attachBaseContext(newBase: Context) {
    super.attachBaseContext(LanguageManager.wrap(newBase))
  }

  override fun onCreate(savedInstanceState: Bundle?) {
    super.onCreate(savedInstanceState)

    // Warm up the checkout SDK so the first payment sheet opens without a cold-start lag.
    Checkout.preload(applicationContext)

    // Load remote config (feature flags + theme) and the translation overlay at
    // launch, then open the realtime socket so later admin changes arrive live.
    // Best-effort: the in-memory stores keep their defaults if it fails.
    lifecycleScope.launch {
      RemoteBootstrap.load(applicationContext)
      RealtimeClient.start(applicationContext, RemoteConfigStore.config.realtime)
    }

    enableEdgeToEdge()
    setContent {
      ThannaTheme {
        MainNavigation()
      }
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
