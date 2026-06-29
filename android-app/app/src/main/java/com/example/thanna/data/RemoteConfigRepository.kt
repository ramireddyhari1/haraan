package com.example.thanna.data

import com.example.thanna.BuildConfig
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONObject
import java.io.BufferedReader
import java.io.InputStreamReader
import java.net.HttpURLConnection
import java.net.URL

/** App branding/theme pushed from /control → Branding & theme. */
data class AppTheme(
    val appName: String? = null,
    val tagline: String? = null,
    val primaryColor: String? = null,
    val accentColor: String? = null,
    val logo: String? = null,
    val supportWhatsapp: String? = null,
)

/** Resolved remote config: feature flags + theme. */
data class RemoteConfig(
    val features: Map<String, Boolean> = emptyMap(),
    val theme: AppTheme = AppTheme(),
)

/**
 * Fetches GET /api/config — feature flags + branding resolved for the current
 * viewer. Sends the app version (X-App-Version) so version-gated flags work, and
 * the JWT when available so per-user rollouts/district targeting apply.
 * Anonymous-safe: returns null on any failure so callers keep their defaults.
 */
class RemoteConfigRepository(private val baseUrl: String = ApiConfig.BASE_URL) {
    suspend fun fetch(token: String?): RemoteConfig? = withContext(Dispatchers.IO) {
        try {
            val connection = (URL("$baseUrl/api/config").openConnection() as HttpURLConnection).apply {
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
            if (code !in 200..299) return@withContext null

            val root = JSONObject(body)

            val featuresObj = root.optJSONObject("features") ?: JSONObject()
            val features = featuresObj.keys().asSequence()
                .associateWith { featuresObj.optBoolean(it, false) }

            val t = root.optJSONObject("theme") ?: JSONObject()
            fun str(k: String) = t.optString(k).takeIf { it.isNotBlank() && it != "null" }

            RemoteConfig(
                features = features,
                theme = AppTheme(
                    appName = str("app_name"),
                    tagline = str("tagline"),
                    primaryColor = str("primary_color"),
                    accentColor = str("accent_color"),
                    logo = str("logo"),
                    supportWhatsapp = str("support_whatsapp"),
                ),
            )
        } catch (_: Exception) {
            null
        }
    }
}
