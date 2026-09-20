package com.haraan.app.ui.payment

import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.graphics.drawable.Drawable
import android.net.Uri

/**
 * A UPI app that is actually installed on this device.
 *
 * [icon] is the app's own launcher icon, read from PackageManager — not a bundled asset, so it
 * always matches the build the buyer really has and never goes stale when an app rebrands.
 */
data class UpiAppTarget(
    val name: String,
    val packageName: String,
    val icon: Drawable?,
)

/**
 * What the payment provider underneath Haraan Pay can actually do with UPI apps.
 *
 * This exists so the sheet never has to know which Razorpay SDK is wired up. Today that is
 * Standard Checkout, which cannot take a named app ([StandardCheckoutUpi]); if Custom Checkout is
 * enabled on the account later, a second implementation drops in and the UI above it is unchanged.
 */
interface HaraanPayUpiCapability {

    /** UPI apps installed on this device. Empty when there are none, or when we can't see them. */
    fun installedApps(context: Context): List<UpiAppTarget>

    /**
     * True when this provider can open a *named* UPI app against our order and get the result back.
     *
     * False means choosing an app in our sheet is a preference, not a launch: the provider's own
     * screen performs the handoff. The sheet reads this to decide what it is allowed to promise.
     */
    val supportsDirectAppHandoff: Boolean
}

/**
 * The capability of `com.razorpay:checkout` — the SDK this app ships.
 *
 * `supportsDirectAppHandoff` is false, and that is a verified fact rather than a cautious guess.
 * Decompiling the artifact (checkout:1.6.40, which resolves standard-core:1.7.18) shows:
 *
 * - `com.razorpay.BaseRazorpay` — which is where `getAppsWhichSupportUpi` and `openUpiApp` live —
 *   is not on this classpath at all. Only Custom Checkout (`com.razorpay:customui`) brings it.
 * - Standard Checkout does enumerate UPI apps, via `CheckoutUtils.getUpiIntentsDataInJsonArray()`
 *   and the `upi_intents_data` / `callNativeIntent` bridge, but both are package-private to its own
 *   checkout UI. Reaching them would mean calling undocumented internals.
 * - The options payload recognises `prefill.method` and `prefill.vpa` (handled by `PayloadHelper`)
 *   and nothing resembling `upi_app_package_name` or `_[flow]` appears anywhere in the jar.
 *
 * Razorpay's own sheet *does* perform genuine UPI intent handoff with these same apps, which is why
 * [HaraanPaymentInstrument.RAZORPAY] exists: it is the honest route to "tap the app, pay there,
 * come back".
 */
object StandardCheckoutUpi : HaraanPayUpiCapability {

    override val supportsDirectAppHandoff: Boolean = false

    /**
     * Resolves `upi://pay` rather than matching a hardcoded package list, so a bank app or a
     * newcomer we have never heard of is found too.
     *
     * Requires the `<queries>` element in AndroidManifest.xml. Without it this returns empty on
     * Android 11+ with no error of any kind — the failure mode is a sheet that quietly loses its
     * app row on newer phones, which is exactly the sort of thing that survives a code review.
     */
    override fun installedApps(context: Context): List<UpiAppTarget> = try {
        val pm = context.packageManager
        val intent = Intent(Intent.ACTION_VIEW, Uri.parse("upi://pay"))
        pm.queryIntentActivities(intent, PackageManager.MATCH_DEFAULT_ONLY)
            .asSequence()
            .map { it.activityInfo.applicationInfo }
            .distinctBy { it.packageName }
            .filter { it.packageName != context.packageName }
            .map { info ->
                UpiAppTarget(
                    name = pm.getApplicationLabel(info).toString(),
                    packageName = info.packageName,
                    icon = runCatching { pm.getApplicationIcon(info) }.getOrNull(),
                )
            }
            .sortedWith(compareBy({ preferredOrder(it.packageName) }, { it.name.lowercase() }))
            .toList()
    } catch (_: Exception) {
        emptyList()
    }

    /**
     * The apps most people in India reach for first, in front; everything else alphabetical behind
     * them. Ordering by install order or label alone buries PhonePe under a bank app nobody opens.
     */
    private fun preferredOrder(packageName: String): Int = when (packageName) {
        "com.google.android.apps.nbu.paisa.user" -> 0   // Google Pay
        "com.phonepe.app" -> 1
        "net.one97.paytm" -> 2
        "in.org.npci.upiapp" -> 3                       // BHIM
        "com.dreamplug.androidapp" -> 4                 // CRED
        "in.amazon.mShop.android.shopping" -> 5         // Amazon Pay
        else -> 100
    }
}
