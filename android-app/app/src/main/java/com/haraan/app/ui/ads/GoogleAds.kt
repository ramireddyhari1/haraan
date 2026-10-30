package com.haraan.app.ui.ads

import android.app.Activity
import com.google.android.gms.ads.MobileAds
import com.google.android.ump.ConsentRequestParameters
import com.google.android.ump.UserMessagingPlatform
import kotlinx.coroutines.suspendCancellableCoroutine
import kotlin.coroutines.resume

/**
 * The one way into the Google Mobile Ads SDK: the consent check (Google UMP), then a single
 * lazy initialise. Shared by the rewarded videos and the ActionBoard ad spots, so the SDK still
 * never starts at app launch — only when a screen actually has an ad to request — and never
 * for a member whose plan has no ads (the server sends them no ad unit at all).
 */
object GoogleAds {
    @Volatile private var initialised = false
    @Volatile private var consentRefreshed = false

    /** True when an ad may be requested now: consent is settled and the SDK is up. */
    suspend fun ready(activity: Activity): Boolean {
        if (!consent(activity)) return false
        initialise(activity)
        return true
    }

    /**
     * Ads are requested only when the player can be asked or already agreed. The consent status
     * is refreshed once per app run (Google's guidance), not on every ad spot that scrolls in.
     */
    private suspend fun consent(activity: Activity): Boolean {
        val info = UserMessagingPlatform.getConsentInformation(activity)
        if (consentRefreshed) return info.canRequestAds()
        suspendCancellableCoroutine { cont ->
            info.requestConsentInfoUpdate(
                activity,
                ConsentRequestParameters.Builder().build(),
                {
                    UserMessagingPlatform.loadAndShowConsentFormIfRequired(activity) {
                        if (cont.isActive) cont.resume(Unit)
                    }
                },
                { if (cont.isActive) cont.resume(Unit) },
            )
        }
        consentRefreshed = true
        return info.canRequestAds()
    }

    private suspend fun initialise(activity: Activity) {
        if (initialised) return
        suspendCancellableCoroutine { cont ->
            MobileAds.initialize(activity.applicationContext) {
                initialised = true
                if (cont.isActive) cont.resume(Unit)
            }
        }
    }
}
