package com.example.thanna.data

import com.example.thanna.BuildConfig
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONObject
import java.io.BufferedReader
import java.io.InputStreamReader
import java.net.HttpURLConnection
import java.net.URL

/**
 * One section of the GameHub home, ordered and curated in /control. [type] tells
 * the UI which widget to render; [config] carries type-specific params (e.g.
 * section=for_you for a feed_section).
 */
data class HomeBlock(
    val id: String,
    val type: String,
    val title: String?,
    val config: Map<String, String> = emptyMap(),
)

/**
 * Fetches GET /api/home/layout — the admin-curated, viewer-resolved home
 * composition. Returns an empty list on failure so the app can fall back to its
 * built-in default layout.
 */
class HomeLayoutRepository(private val baseUrl: String = ApiConfig.BASE_URL) {
    suspend fun fetch(token: String?): List<HomeBlock> = withContext(Dispatchers.IO) {
        try {
            val connection = (URL("$baseUrl/api/home/layout").openConnection() as HttpURLConnection).apply {
                requestMethod = "GET"
                connectTimeout = 15000
                readTimeout = 15000
                setRequestProperty("Accept", "application/json")
                setRequestProperty("X-App-Version", BuildConfig.VERSION_NAME)
                if (!token.isNullOrBlank()) setRequestProperty("Authorization", "Bearer $token")
            }
            val code = connection.responseCode
            val stream = if (code >= 400) connection.errorStream else connection.inputStream
            val body = stream?.let { BufferedReader(InputStreamReader(it)).use { r -> r.readText() } } ?: ""
            connection.disconnect()
            if (code !in 200..299) return@withContext emptyList()

            val arr = JSONObject(body).optJSONArray("blocks") ?: return@withContext emptyList()
            (0 until arr.length()).map { i ->
                val o = arr.getJSONObject(i)
                val cfgObj = o.optJSONObject("config") ?: JSONObject()
                val cfg = cfgObj.keys().asSequence().associateWith { cfgObj.optString(it) }
                HomeBlock(
                    id = o.optString("id"),
                    type = o.optString("type"),
                    title = o.optString("title").takeIf { it.isNotBlank() && it != "null" },
                    config = cfg,
                )
            }
        } catch (_: Exception) {
            emptyList()
        }
    }
}
