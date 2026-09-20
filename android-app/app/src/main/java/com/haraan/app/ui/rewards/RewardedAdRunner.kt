package com.haraan.app.ui.rewards

import android.app.Activity
import com.google.android.gms.ads.AdError
import com.google.android.gms.ads.AdRequest
import com.google.android.gms.ads.FullScreenContentCallback
import com.google.android.gms.ads.LoadAdError
import com.google.android.gms.ads.MobileAds
import com.google.android.gms.ads.rewarded.RewardedAd
import com.google.android.gms.ads.rewarded.RewardedAdLoadCallback
import com.google.android.gms.ads.rewarded.ServerSideVerificationOptions
import com.google.android.ump.ConsentRequestParameters
import com.google.android.ump.UserMessagingPlatform
import com.haraan.app.data.rewards.AdSession
import kotlinx.coroutines.suspendCancellableCoroutine
import kotlin.coroutines.resume

/**
 * Plays ONE rewarded video the player chose to watch.
 *
 * The app never decides that the reward is earned. The video carries our session nonce and the
 * player's opaque id as server-side-verification data; Google calls our server, and only that
 * callback unlocks anything. This class just shows the ad and says how it went, so the screen
 * knows whether to start asking the server.
 *
 * The SDK is initialised here, on the first tap — never at app start, and never at all for a
 * member whose plan has no ads (the server never offers them a video).
 */
object RewardedAdRunner {

    sealed interface Outcome {
        /** The video ran to its reward point. Verification is still the server's job. */
        data object Watched : Outcome

        /** Closed before the end — no reward. */
        data object Closed : Outcome

        data object NoConsent : Outcome

        data class Failed(val message: String) : Outcome
    }

    @Volatile private var initialised = false

    suspend fun play(activity: Activity, session: AdSession): Outcome {
        if (!consent(activity)) return Outcome.NoConsent
        initialise(activity)

        val ad = load(activity, session.adUnitId) ?: return Outcome.Failed("No video is available right now. Try again later.")
        ad.setServerSideVerificationOptions(
            ServerSideVerificationOptions.Builder()
                .setUserId(session.ssvUserId)
                .setCustomData(session.nonce)
                .build(),
        )

        return suspendCancellableCoroutine { cont ->
            var earned = false
            ad.fullScreenContentCallback = object : FullScreenContentCallback() {
                override fun onAdDismissedFullScreenContent() {
                    if (cont.isActive) cont.resume(if (earned) Outcome.Watched else Outcome.Closed)
                }

                override fun onAdFailedToShowFullScreenContent(error: AdError) {
                    if (cont.isActive) cont.resume(Outcome.Failed("The video couldn't play. Try again."))
                }
            }
            ad.show(activity) { earned = true }
        }
    }

    /** Google UMP consent. Ads are requested only when the player can be asked or already agreed. */
    private suspend fun consent(activity: Activity): Boolean {
        val info = UserMessagingPlatform.getConsentInformation(activity)
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

    private suspend fun load(activity: Activity, adUnitId: String): RewardedAd? =
        suspendCancellableCoroutine { cont ->
            RewardedAd.load(
                activity,
                adUnitId,
                AdRequest.Builder().build(),
                object : RewardedAdLoadCallback() {
                    override fun onAdLoaded(ad: RewardedAd) {
                        if (cont.isActive) cont.resume(ad)
                    }

                    override fun onAdFailedToLoad(error: LoadAdError) {
                        if (cont.isActive) cont.resume(null)
                    }
                },
            )
        }
}
