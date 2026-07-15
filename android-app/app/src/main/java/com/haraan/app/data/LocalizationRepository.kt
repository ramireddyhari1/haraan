package com.haraan.app.data

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONObject
import java.io.BufferedReader
import java.io.InputStreamReader
import java.net.HttpURLConnection
import java.net.URL

/**
 * Fetches GET /api/i18n/{locale} — the admin-managed translation bundle for a
 * locale (English-filled, so always complete). Lets copy/translation fixes ship
 * without an app release; the app overlays these on its built-in strings.
 * Returns an empty map on failure so the app keeps its bundled strings.
 */
class LocalizationRepository(private val baseUrl: String = ApiConfig.BASE_URL) {
    suspend fun fetchBundle(locale: String): Map<String, String> = withContext(Dispatchers.IO) {
        try {
            val connection = (URL("$baseUrl/api/i18n/$locale").openConnection() as HttpURLConnection).apply {
                requestMethod = "GET"
                connectTimeout = 15000
                readTimeout = 15000
                setRequestProperty("Accept", "application/json")
            }
            val code = connection.responseCode
            val stream = if (code >= 400) connection.errorStream else connection.inputStream
            val body = stream?.let { BufferedReader(InputStreamReader(it)).use { r -> r.readText() } } ?: ""
            connection.disconnect()
            if (code !in 200..299) return@withContext emptyMap()

            val obj = JSONObject(body).optJSONObject("translations") ?: return@withContext emptyMap()
            obj.keys().asSequence().associateWith { obj.optString(it) }
        } catch (_: Exception) {
            emptyMap()
        }
    }
}
