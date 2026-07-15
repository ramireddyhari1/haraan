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
    suspend fun getAds(placement: String): List<AdItem> = withContext(Dispatchers.IO) {
        val url = "${ApiConfig.BASE_URL}/api/ads?placement=$placement"
        val body = URL(url).readText()
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
