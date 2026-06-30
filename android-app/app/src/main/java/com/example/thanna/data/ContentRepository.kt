package com.example.thanna.data

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
                subtitle = o.optString("subtitle").takeIf { it.isNotBlank() },
                sponsor = o.optString("sponsor").takeIf { it.isNotBlank() },
                ctaText = o.optString("cta_text").takeIf { it.isNotBlank() },
                ctaUrl = o.optString("cta_url").takeIf { it.isNotBlank() },
                image = o.optString("image").takeIf { it.isNotBlank() },
                logo = o.optString("logo").takeIf { it.isNotBlank() },
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
                    subtitle = o.optString("subtitle").takeIf { it.isNotBlank() && it != "null" },
                    image = o.optString("image").takeIf { it.isNotBlank() && it != "null" },
                    badge = o.optString("badge").takeIf { it.isNotBlank() && it != "null" },
                    rating = o.optString("rating").takeIf { it.isNotBlank() && it != "null" },
                    linkType = o.optString("link_type").takeIf { it.isNotBlank() && it != "null" },
                    linkId = o.optString("link_id").takeIf { it.isNotBlank() && it != "null" },
                )
            }
        }
    }
}
