package com.haraan.app.data

import com.haraan.app.BuildConfig
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

/** Where/whether to open a realtime (Reverb) connection. */
data class RealtimeConfig(
    val enabled: Boolean = false,
    val key: String? = null,
    val host: String? = null,
    val port: Int = 443,
    val scheme: String = "https",
    val channel: String = "content",
)

/** Resolved remote config: feature flags + theme + realtime endpoint. */
data class RemoteConfig(
    val features: Map<String, Boolean> = emptyMap(),
    val theme: AppTheme = AppTheme(),
    val realtime: RealtimeConfig = RealtimeConfig(),
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

            val rt = root.optJSONObject("realtime") ?: JSONObject()
            fun rtStr(k: String) = rt.optString(k).takeIf { it.isNotBlank() && it != "null" }

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
                realtime = RealtimeConfig(
                    enabled = rt.optBoolean("enabled", false),
                    key = rtStr("key"),
                    host = rtStr("host"),
                    port = rt.optInt("port", 443),
                    scheme = rt.optString("scheme", "https"),
                    channel = rt.optString("channel", "content"),
                ),
            )
        } catch (_: Exception) {
            null
        }
    }
}
