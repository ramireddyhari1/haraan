package com.haraan.app.ui.ads

import android.content.Context
import android.graphics.Typeface
import android.graphics.drawable.GradientDrawable
import android.text.TextUtils
import android.util.TypedValue
import android.view.Gravity
import android.view.View
import android.view.ViewGroup
import android.widget.Button
import android.widget.ImageView
import android.widget.LinearLayout
import android.widget.TextView
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalConfiguration
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.viewinterop.AndroidView
import com.google.android.gms.ads.AdListener
import com.google.android.gms.ads.AdLoader
import com.google.android.gms.ads.AdRequest
import com.google.android.gms.ads.AdSize
import com.google.android.gms.ads.AdView
import com.google.android.gms.ads.LoadAdError
import com.google.android.gms.ads.nativead.MediaView
import com.google.android.gms.ads.nativead.NativeAd
import com.google.android.gms.ads.nativead.NativeAdOptions
import com.google.android.gms.ads.nativead.NativeAdView
import com.haraan.app.BuildConfig
import com.haraan.app.data.AdItem
import com.haraan.app.data.RemoteConfigStore
import com.haraan.app.ui.matches.CrexColors
import com.haraan.app.ui.matches.MatchAdBar
import com.haraan.app.ui.rewards.findActivity
import kotlinx.coroutines.suspendCancellableCoroutine
import kotlin.coroutines.resume

/*
 * Ads in the ActionBoard. Each spot shows a Haraan sponsor booked in /control → Marketing → Ads
 * when one is running, and only otherwise a Google AdMob ad from /control → Marketing → Ad
 * networks (RemoteConfigStore.config.ads). Either can be empty; then the spot renders nothing.
 * The scoring keypad never has an ad — these are only placed in lists and on the match screen.
 */

/** Debug builds always ask for Google's test ads: tapping a live ad from a dev phone gets the account flagged. */
private const val TEST_NATIVE_UNIT = "ca-app-pub-3940256099942544/2247696110"
private const val TEST_BANNER_UNIT = "ca-app-pub-3940256099942544/9214589741"

private fun nativeUnit(unitId: String) = if (BuildConfig.DEBUG) TEST_NATIVE_UNIT else unitId
private fun bannerUnit(unitId: String) = if (BuildConfig.DEBUG) TEST_BANNER_UNIT else unitId

/** Whether an ActionBoard list has anything to put between its matches. */
fun actionBoardFeedHasAd(houseAds: List<AdItem>): Boolean =
    houseAds.isNotEmpty() || RemoteConfigStore.config.ads.feedUnitId != null

/** Matches between two list ads, from the console. */
fun actionBoardFeedEvery(): Int = RemoteConfigStore.config.ads.feedEvery

/**
 * One ad between matches in an ActionBoard list. [position] is which ad this is in the list
 * (0, 1, 2…): house sponsors take turns by it, and a Google ad is kept per position so the
 * list doesn't reload ads as the player scrolls back and forth.
 */
@Composable
fun ActionBoardFeedAd(position: Int, houseAds: List<AdItem>, modifier: Modifier = Modifier) {
    if (houseAds.isNotEmpty()) {
        Column(modifier.fillMaxWidth()) { MatchAdBar(houseAds[position % houseAds.size], matchId = "") }
        return
    }
    val unitId = RemoteConfigStore.config.ads.feedUnitId ?: return
    AdMobNativeCard(unitId = unitId, position = position, modifier = modifier)
}

/**
 * The match screen's sponsor spot (Live tab, every sport's summary): the `match_live` sponsor,
 * else the AdMob banner. Renders nothing when neither exists.
 */
@Composable
fun MatchSponsorSpot(houseAd: AdItem?, modifier: Modifier = Modifier) {
    if (houseAd != null) {
        Column(modifier) { MatchAdBar(houseAd) }
        return
    }
    val unitId = RemoteConfigStore.config.ads.matchUnitId ?: return
    AdMobBanner(unitId = unitId, modifier = modifier)
}

fun matchSponsorSpotHasAd(houseAd: AdItem?): Boolean =
    houseAd != null || RemoteConfigStore.config.ads.matchUnitId != null

/**
 * Native ads loaded for the ActionBoard lists, by list position. Google's native ads are good
 * for an hour; past that a position loads a fresh one. Cleared (and every ad destroyed) when
 * the ActionBoard closes — see [ActionBoardAdsLifecycle].
 */
private object NativeAdCache {
    private const val MAX_AGE_MS = 55 * 60 * 1000L
    private val ads = mutableMapOf<Int, Pair<NativeAd, Long>>()

    @Synchronized fun get(position: Int): NativeAd? {
        val (ad, at) = ads[position] ?: return null
        if (System.currentTimeMillis() - at < MAX_AGE_MS) return ad
        ads.remove(position)
        ad.destroy()
        return null
    }

    @Synchronized fun put(position: Int, ad: NativeAd) {
        ads.put(position, ad to System.currentTimeMillis())?.first?.takeIf { it !== ad }?.destroy()
    }

    @Synchronized fun clear() {
        ads.values.forEach { it.first.destroy() }
        ads.clear()
    }
}

/** Put once on the ActionBoard screen: frees its native ads when the screen leaves. */
@Composable
fun ActionBoardAdsLifecycle() {
    DisposableEffect(Unit) { onDispose { NativeAdCache.clear() } }
}

@Composable
private fun AdMobNativeCard(unitId: String, position: Int, modifier: Modifier) {
    val context = LocalContext.current
    var ad by remember(unitId, position) { mutableStateOf(NativeAdCache.get(position)) }

    LaunchedEffect(unitId, position) {
        if (ad != null) return@LaunchedEffect
        val activity = context.findActivity() ?: return@LaunchedEffect
        if (!GoogleAds.ready(activity)) return@LaunchedEffect
        val loaded = loadNative(activity, nativeUnit(unitId)) ?: return@LaunchedEffect
        if (activity.isDestroyed) {
            loaded.destroy()
            return@LaunchedEffect
        }
        NativeAdCache.put(position, loaded)
        ad = loaded
    }

    val current = ad ?: return
    AndroidView(
        modifier = modifier.fillMaxWidth(),
        factory = { ctx -> NativeAdCard(ctx).root },
        update = { (it.tag as NativeAdCard).bind(current) },
    )
}

private suspend fun loadNative(context: Context, unitId: String): NativeAd? =
    suspendCancellableCoroutine { cont ->
        AdLoader.Builder(context, unitId)
            .forNativeAd { ad -> if (cont.isActive) cont.resume(ad) else ad.destroy() }
            .withAdListener(object : AdListener() {
                override fun onAdFailedToLoad(error: LoadAdError) {
                    if (cont.isActive) cont.resume(null)
                }
            })
            .withNativeAdOptions(
                NativeAdOptions.Builder()
                    .setAdChoicesPlacement(NativeAdOptions.ADCHOICES_TOP_RIGHT)
                    .setMediaAspectRatio(NativeAdOptions.NATIVE_MEDIA_ASPECT_RATIO_LANDSCAPE)
                    .build(),
            )
            .build()
            .loadAd(AdRequest.Builder().build())
    }

/**
 * A native ad drawn as one of the list's match cards: white, 16dp corners, hairline border,
 * an "Ad" tag, the advertiser's icon, headline and line, the creative, and a blue button.
 * Every asset is registered with the NativeAdView, as AdMob's policy requires.
 */
private class NativeAdCard(context: Context) {
    /** The NativeAdView is final in the Ads SDK, so the card owns one instead of being one. */
    val root = NativeAdView(context).also { it.tag = this }
    private val resources = context.resources
    private fun dp(v: Int) = TypedValue.applyDimension(TypedValue.COMPLEX_UNIT_DIP, v.toFloat(), resources.displayMetrics).toInt()
    private fun argb(c: androidx.compose.ui.graphics.Color) = android.graphics.Color.argb(
        (c.alpha * 255).toInt(), (c.red * 255).toInt(), (c.green * 255).toInt(), (c.blue * 255).toInt(),
    )

    private val adBadge = TextView(context).apply {
        text = "Ad"
        setTextSize(TypedValue.COMPLEX_UNIT_SP, 10f)
        typeface = Typeface.DEFAULT_BOLD
        setTextColor(android.graphics.Color.WHITE)
        background = GradientDrawable().apply { cornerRadius = dp(4).toFloat(); setColor(argb(CrexColors.AccentYellow)) }
        setPadding(dp(5), dp(1), dp(5), dp(1))
    }
    private val advertiser = TextView(context).apply {
        setTextSize(TypedValue.COMPLEX_UNIT_SP, 11f)
        setTextColor(argb(CrexColors.TextMuted))
        maxLines = 1
        ellipsize = TextUtils.TruncateAt.END
    }
    private val icon = ImageView(context).apply {
        scaleType = ImageView.ScaleType.CENTER_CROP
        clipToOutline = true
        background = GradientDrawable().apply { cornerRadius = dp(10).toFloat(); setColor(argb(CrexColors.Background)) }
    }
    private val headline = TextView(context).apply {
        setTextSize(TypedValue.COMPLEX_UNIT_SP, 15f)
        typeface = Typeface.DEFAULT_BOLD
        setTextColor(argb(CrexColors.TextPrimary))
        maxLines = 2
        ellipsize = TextUtils.TruncateAt.END
    }
    private val body = TextView(context).apply {
        setTextSize(TypedValue.COMPLEX_UNIT_SP, 12f)
        setTextColor(argb(CrexColors.TextSecondary))
        maxLines = 2
        ellipsize = TextUtils.TruncateAt.END
    }
    private val media = MediaView(context).apply {
        clipToOutline = true
        background = GradientDrawable().apply { cornerRadius = dp(12).toFloat(); setColor(argb(CrexColors.Background)) }
    }
    private val cta = Button(context).apply {
        isAllCaps = false
        setTextSize(TypedValue.COMPLEX_UNIT_SP, 14f)
        typeface = Typeface.DEFAULT_BOLD
        setTextColor(android.graphics.Color.WHITE)
        background = GradientDrawable().apply { cornerRadius = dp(10).toFloat(); setColor(argb(CrexColors.AccentBlue)) }
        stateListAnimator = null
        minHeight = dp(44)
    }

    init {
        val card = LinearLayout(context).apply {
            orientation = LinearLayout.VERTICAL
            setPadding(dp(14), dp(12), dp(14), dp(14))
            background = GradientDrawable().apply {
                cornerRadius = dp(16).toFloat()
                setColor(argb(CrexColors.Surface))
                setStroke(dp(1), argb(CrexColors.Border))
            }
        }
        card.addView(LinearLayout(context).apply {
            orientation = LinearLayout.HORIZONTAL
            gravity = Gravity.CENTER_VERTICAL
            addView(adBadge)
            // Leaves room on the right for the AdChoices icon Google draws over the corner.
            addView(advertiser, LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f).apply {
                marginStart = dp(8); marginEnd = dp(28)
            })
        })
        card.addView(LinearLayout(context).apply {
            orientation = LinearLayout.HORIZONTAL
            gravity = Gravity.CENTER_VERTICAL
            addView(icon, LinearLayout.LayoutParams(dp(44), dp(44)).apply { marginEnd = dp(12) })
            addView(LinearLayout(context).apply {
                orientation = LinearLayout.VERTICAL
                addView(headline)
                addView(body)
            }, LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f))
        }, LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT).apply { topMargin = dp(10) })
        card.addView(media, LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(180)).apply { topMargin = dp(12) })
        card.addView(cta, LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT).apply { topMargin = dp(12) })
        root.addView(card, ViewGroup.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT))

        root.headlineView = headline
        root.bodyView = body
        root.iconView = icon
        root.mediaView = media
        root.callToActionView = cta
        root.advertiserView = advertiser
    }

    fun bind(ad: NativeAd) {
        headline.text = ad.headline
        body.text = ad.body
        body.visibility = if (ad.body.isNullOrBlank()) View.GONE else View.VISIBLE
        advertiser.text = ad.advertiser ?: ""
        ad.icon?.drawable?.let { icon.setImageDrawable(it); icon.visibility = View.VISIBLE } ?: run { icon.visibility = View.GONE }
        cta.text = ad.callToAction ?: "Learn more"
        cta.visibility = if (ad.callToAction.isNullOrBlank()) View.GONE else View.VISIBLE
        val content = ad.mediaContent
        if (content != null) {
            media.mediaContent = content
            // Keep the creative's own shape (clamped), instead of cropping a square into a strip.
            val ratio = content.aspectRatio.takeIf { it > 0f }?.coerceIn(1f, 2f) ?: 1.91f
            media.layoutParams = media.layoutParams.apply { height = ((resources.displayMetrics.widthPixels - dp(60)) / ratio).toInt() }
            media.visibility = View.VISIBLE
        } else {
            media.visibility = View.GONE
        }
        root.setNativeAd(ad)
    }
}

/**
 * Inline adaptive banner for the match screen. Takes no space until an ad has loaded, so a
 * no-fill leaves no gap; the "ADVERTISEMENT" caption appears with the ad.
 */
@Composable
private fun AdMobBanner(unitId: String, modifier: Modifier) {
    val context = LocalContext.current
    val widthDp = LocalConfiguration.current.screenWidthDp - 32
    var ready by remember { mutableStateOf(false) }
    var loaded by remember { mutableStateOf(false) }

    LaunchedEffect(unitId) {
        val activity = context.findActivity() ?: return@LaunchedEffect
        ready = GoogleAds.ready(activity)
    }
    if (!ready) return

    Column(modifier.fillMaxWidth()) {
        if (loaded) {
            Text(
                "ADVERTISEMENT",
                color = CrexColors.TextMuted,
                fontSize = 9.sp,
                fontWeight = FontWeight.Bold,
                letterSpacing = 1.sp,
            )
            Spacer(Modifier.height(6.dp))
        }
        AndroidView(
            modifier = Modifier.fillMaxWidth(),
            factory = { ctx ->
                AdView(ctx).apply {
                    setAdSize(AdSize.getInlineAdaptiveBannerAdSize(widthDp, 250))
                    adUnitId = bannerUnit(unitId)
                    adListener = object : AdListener() {
                        override fun onAdLoaded() { loaded = true }
                        override fun onAdFailedToLoad(error: LoadAdError) { loaded = false }
                    }
                    loadAd(AdRequest.Builder().build())
                }
            },
            onRelease = { it.destroy() },
        )
    }
}
