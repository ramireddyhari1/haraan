package com.haraan.app.data

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONArray
import org.json.JSONObject
import java.net.HttpURLConnection
import java.net.URL

/**
 * Talks to the server-side translation proxy (POST /api/translate → Google Cloud
 * Translation). The Google key lives on the server, never in the app. Returns the
 * texts translated into [target], or the originals unchanged on any failure so the
 * UI always stays readable.
 */
class TranslationRepository(
    private val baseUrl: String = ApiConfig.BASE_URL,
) {
    suspend fun translate(texts: List<String>, target: String): List<String> = withContext(Dispatchers.IO) {
        if (texts.isEmpty() || target.isBlank() || target == "en") return@withContext texts
        try {
            val body = JSONObject()
                .put("q", JSONArray(texts))
                .put("target", target)
            val conn = (URL("${baseUrl.trimEnd('/')}/api/translate").openConnection() as HttpURLConnection).apply {
                requestMethod = "POST"
                doOutput = true
                connectTimeout = 15000
                readTimeout = 15000
                setRequestProperty("Content-Type", "application/json")
                setRequestProperty("Accept", "application/json")
            }
            conn.outputStream.use { it.write(body.toString().toByteArray()) }
            if (conn.responseCode !in 200..299) return@withContext texts
            val text = conn.inputStream.bufferedReader().use { it.readText() }
            val arr = JSONObject(text).optJSONArray("translations") ?: return@withContext texts
            (0 until arr.length()).map { arr.optString(it) }
        } catch (_: Exception) {
            texts
        }
    }
}
