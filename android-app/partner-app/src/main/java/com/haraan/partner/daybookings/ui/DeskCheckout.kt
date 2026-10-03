package com.haraan.partner.daybookings.ui

import android.content.Intent
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.size
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberUpdatedState
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.partner.DeskCheckout
import org.json.JSONObject

/** What Razorpay's checkout said about a walk-in's payment. */
object DeskCheckoutBridge {
    sealed interface Outcome {
        data class Paid(val paymentId: String) : Outcome
        /** The customer (or desk) closed the checkout without paying. */
        data object Closed : Outcome
        data class Failed(val message: String) : Outcome
    }
}

/**
 * Razorpay's checkout for a walk-in paying online at the desk: the same web checkout
 * the Haraan website uses, full screen, with no browser bar.
 *
 * Why not the alternatives:
 *  - Razorpay's hosted payment-link page hides the number the link was made with, so
 *    it asked the customer for it again. Here it goes in as `prefill.contact`.
 *  - Razorpay's Android SDK shows UPI only through UPI apps installed on the phone it
 *    runs on. On the desk phone that hid UPI entirely (or offered the desk's own GPay),
 *    while the web checkout offers every UPI option, as it does on the website.
 *
 * [onResult] gets Paid when the checkout reports success (the server still checks with
 * Razorpay before anything is marked paid) and Closed when it is dismissed. A failed
 * attempt stays inside the checkout, which offers a retry.
 */
@Composable
fun DeskCheckoutPage(
    checkout: DeskCheckout,
    secondsLeft: Int,
    onResult: (DeskCheckoutBridge.Outcome) -> Unit,
) {
    val context = LocalContext.current
    val report by rememberUpdatedState(onResult)
    var loading by remember { mutableStateOf(true) }
    var webRef by remember { mutableStateOf<android.webkit.WebView?>(null) }
    // Read once: the checkout's own timer starts when it opens.
    val timeout = remember { secondsLeft.coerceAtLeast(30) }

    val html = remember(checkout.orderId) { checkoutHtml(checkout, timeout) }

    androidx.compose.ui.window.Dialog(
        onDismissRequest = {
            val web = webRef
            if (web != null && web.canGoBack()) web.goBack() else report(DeskCheckoutBridge.Outcome.Closed)
        },
        properties = androidx.compose.ui.window.DialogProperties(
            usePlatformDefaultWidth = false,
            dismissOnClickOutside = false,
            decorFitsSystemWindows = false,
        ),
    ) {
        Box(Modifier.fillMaxSize().background(Color.White)) {
            androidx.compose.ui.viewinterop.AndroidView(
                modifier = Modifier.fillMaxSize(),
                factory = { ctx ->
                    if (com.haraan.partner.BuildConfig.DEBUG) android.webkit.WebView.setWebContentsDebuggingEnabled(true)
                    android.webkit.WebView(ctx).apply {
                        // WRAP_CONTENT (AndroidView's default) reports a 0px viewport to CSS,
                        // and Razorpay's checkout caps itself at 100dvh: it drew 0px tall.
                        layoutParams = android.view.ViewGroup.LayoutParams(
                            android.view.ViewGroup.LayoutParams.MATCH_PARENT,
                            android.view.ViewGroup.LayoutParams.MATCH_PARENT,
                        )
                        settings.javaScriptEnabled = true
                        settings.domStorageEnabled = true
                        settings.javaScriptCanOpenWindowsAutomatically = true
                        settings.setSupportMultipleWindows(false)
                        val web = this
                        android.webkit.CookieManager.getInstance().apply {
                            setAcceptCookie(true)
                            setAcceptThirdPartyCookies(web, true)
                        }
                        webChromeClient = android.webkit.WebChromeClient()
                        addJavascriptInterface(object {
                            @android.webkit.JavascriptInterface
                            fun paid(paymentId: String?) = post { report(DeskCheckoutBridge.Outcome.Paid(paymentId.orEmpty())) }

                            @android.webkit.JavascriptInterface
                            fun closed() = post { report(DeskCheckoutBridge.Outcome.Closed) }

                            @android.webkit.JavascriptInterface
                            fun ready() = post { loading = false }

                            @android.webkit.JavascriptInterface
                            fun broken(message: String?) = post {
                                report(DeskCheckoutBridge.Outcome.Failed(message?.takeIf { it.isNotBlank() } ?: "Couldn't open Razorpay. Try again, or take cash."))
                            }
                        }, "HaraanDesk")
                        webViewClient = object : android.webkit.WebViewClient() {
                            override fun shouldOverrideUrlLoading(v: android.webkit.WebView, req: android.webkit.WebResourceRequest): Boolean {
                                val u = req.url
                                if (u.scheme == "http" || u.scheme == "https") return false
                                // A UPI app button: let Android open that app.
                                runCatching {
                                    val intent = if (u.scheme == "intent") Intent.parseUri(u.toString(), Intent.URI_INTENT_SCHEME)
                                    else Intent(Intent.ACTION_VIEW, u)
                                    context.startActivity(intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK))
                                }
                                return true
                            }
                        }
                        // Let the window draw first; the load starts on the next frame.
                        post { loadDataWithBaseURL("https://haraan.app/", html, "text/html", "utf-8", null) }
                        webRef = this
                    }
                },
                onRelease = { web ->
                    web.stopLoading()
                    web.removeJavascriptInterface("HaraanDesk")
                    web.destroy()
                    webRef = null
                },
            )
            if (loading) {
                Column(Modifier.align(Alignment.Center), horizontalAlignment = Alignment.CenterHorizontally) {
                    CircularProgressIndicator(color = Color(0xFF1D4ED8), strokeWidth = 2.5.dp, modifier = Modifier.size(28.dp))
                    Spacer(Modifier.height(12.dp))
                    Text("Opening Razorpay…", fontSize = 13.sp, color = Color(0xFF6B7688))
                }
            }
        }
    }
}

/** A blank page that opens Razorpay's checkout for the order straight away. */
private fun checkoutHtml(c: DeskCheckout, timeout: Int): String {
    val options = JSONObject().apply {
        put("key", c.key)
        put("order_id", c.orderId)
        put("name", c.name)
        put("description", c.description)
        put("currency", "INR")
        put("prefill", JSONObject().apply {
            c.contact?.let { put("contact", it) }
            c.customer?.let { put("name", it) }
        })
        put("theme", JSONObject().put("color", "#1D4ED8"))
        put("timeout", timeout)
        put("retry", JSONObject().put("enabled", true).put("max_count", 3))
    }
    return """
        <!doctype html><html><head>
        <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
        <style>html,body{margin:0;height:100%;background:#fff}</style>
        </head><body>
        <script src="https://checkout.razorpay.com/v1/checkout.js"
          onerror="HaraanDesk.broken('Couldn\'t reach Razorpay. Check the internet, or take cash.')"></script>
        <script>
          (function () {
            if (typeof Razorpay === 'undefined') return;
            var o = $options;
            o.handler = function (r) { HaraanDesk.paid(r.razorpay_payment_id || ''); };
            o.modal = { ondismiss: function () { HaraanDesk.closed(); }, escape: false, backdropclose: false };
            try {
              var rzp = new Razorpay(o);
              rzp.open();
              setTimeout(function () { HaraanDesk.ready(); }, 600);
            } catch (e) { HaraanDesk.broken(String(e && e.message || '')); }
          })();
        </script>
        </body></html>
    """.trimIndent()
}
