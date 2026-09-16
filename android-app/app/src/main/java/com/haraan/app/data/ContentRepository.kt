package com.haraan.app.data

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONArray
import org.json.JSONObject
import java.net.URL

data class AdItem(
    val id: String,
    val title: String,
    val subtitle: String?,
    val sponsor: String?,
    val ctaText: String?,
    val ctaUrl: String?,
    val image: String?,
    val logo: String?,
    val placement: String,
)

/** One curated home-feed card (For You / Trending), from GET /api/home/feed. */
data class FeedCard(
    val id: String,
    val title: String,
    val subtitle: String?,
    val image: String?,
    val badge: String?,
    val rating: String?,
    val linkType: String?,
    val linkId: String?,
)

/**
 * Read an optional string field.
 *
 * `optString` returns the four-character string "null" when the value is JSON null,
 * not "" and not null — so a plain `isNotBlank()` check passes it straight through
 * to the UI, where it renders as the word "null". Always read nullable strings
 * through this.
 */
private fun JSONObject.optStringOrNull(key: String): String? =
    optString(key).takeIf { it.isNotBlank() && it != "null" }

class ContentRepository {
    companion object {
        const val AD_IMPRESSION = "impression"
        const val AD_CLICK = "click"
    }

    /**
     * Report an ad impression or click. Fire-and-forget: a failed beacon costs a sponsor one
     * count, never the viewer a broken screen. The install id lets the server de-duplicate
     * repeats (it stores only a hash); a signed-in token adds the account when there is one.
     */
    suspend fun trackAd(
        context: android.content.Context,
        adId: String,
        kind: String,
        placement: String,
        matchId: String? = null,
    ) = withContext(Dispatchers.IO) {
        if (adId.isBlank() || placement.isBlank()) return@withContext
        runCatching {
            val body = JSONObject().put("placement", placement)
            matchId?.toLongOrNull()?.let { body.put("match_id", it) }
            val connection = (URL("${ApiConfig.BASE_URL.trimEnd('/')}/api/ads/$adId/$kind").openConnection()
                as java.net.HttpURLConnection).apply {
                requestMethod = "POST"
                doOutput = true
                connectTimeout = 8000
                readTimeout = 8000
                setRequestProperty("Content-Type", "application/json")
                setRequestProperty("Accept", "application/json")
                setRequestProperty("X-Install-Id", InstallId.get(context))
                TokenStore.getSignedInToken(context)?.let { setRequestProperty("Authorization", "Bearer $it") }
            }
            connection.outputStream.use { it.write(body.toString().toByteArray(Charsets.UTF_8)) }
            connection.responseCode
            connection.disconnect()
        }
    }

    /**
     * Ads for a placement. [token] identifies a signed-in member: when their plan includes
     * ad-free browsing the server returns an empty list, so the placement renders nothing.
     */
    suspend fun getAds(placement: String, token: String? = null): List<AdItem> = withContext(Dispatchers.IO) {
        val url = "${ApiConfig.BASE_URL}/api/ads?placement=$placement"
        val connection = (URL(url).openConnection() as java.net.HttpURLConnection).apply {
            connectTimeout = 10000
            readTimeout = 10000
            setRequestProperty("Accept", "application/json")
            token?.takeIf { TokenStore.isSignedIn(it) }?.let { setRequestProperty("Authorization", "Bearer $it") }
        }
        val body = try {
            connection.inputStream.bufferedReader().use { it.readText() }
        } finally {
            connection.disconnect()
        }
        // Endpoint returns {"data":[...]}; tolerate a bare array too for forward/back compat.
        val arr = runCatching { JSONObject(body).optJSONArray("data") }.getOrNull()
            ?: runCatching { JSONArray(body) }.getOrNull()
            ?: JSONArray()
        (0 until arr.length()).map { i ->
            val o = arr.getJSONObject(i)
            AdItem(
                id = o.optString("id"),
                title = o.optString("title"),
                subtitle = o.optStringOrNull("subtitle"),
                sponsor = o.optStringOrNull("sponsor"),
                ctaText = o.optStringOrNull("cta_text"),
                ctaUrl = o.optStringOrNull("cta_url"),
                image = o.optStringOrNull("image"),
                logo = o.optStringOrNull("logo"),
                placement = o.optString("placement"),
            )
        }
    }

    /** Curated home feed grouped by section (for_you / trending). Empty on failure. */
    suspend fun getFeed(): Map<String, List<FeedCard>> = withContext(Dispatchers.IO) {
        val body = URL("${ApiConfig.BASE_URL}/api/home/feed").readText()
        val root = JSONObject(body)
        listOf("for_you", "trending").associateWith { section ->
            val arr = root.optJSONArray(section) ?: JSONArray()
            (0 until arr.length()).map { i ->
                val o = arr.getJSONObject(i)
                FeedCard(
                    id = o.optString("id"),
                    title = o.optString("title"),
                    subtitle = o.optStringOrNull("subtitle"),
                    image = o.optStringOrNull("image"),
                    badge = o.optStringOrNull("badge"),
                    rating = o.optStringOrNull("rating"),
                    linkType = o.optStringOrNull("link_type"),
                    linkId = o.optStringOrNull("link_id"),
                )
            }
        }
    }
}
