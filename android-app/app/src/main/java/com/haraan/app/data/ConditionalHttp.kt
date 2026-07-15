package com.haraan.app.data

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import java.net.HttpURLConnection
import java.net.URL
import java.util.concurrent.ConcurrentHashMap

/**
 * Conditional (ETag) GET for the frequently-polled endpoints. Remembers the ETag +
 * body of the last successful response per URL(+token); on the next poll it sends
 * `If-None-Match`, and when the server replies 304 Not Modified it returns the cached
 * body without re-downloading it.
 *
 * This is what makes the [com.haraan.app.ui.components.AutoRefresh] polls cheap:
 * unchanged data costs a few header bytes instead of the full payload, and the caller
 * re-parses a byte-identical string (so Compose then skips recomposition). Requires the
 * backend `SetConditionalHeaders` middleware, which emits the ETag and returns 304.
 *
 * Also doubles as a tiny stale-while-revalidate cache: on a network error it returns
 * the last good body (if any) rather than null, so a transient blip doesn't blank a
 * screen mid-poll. In-memory only (cleared when the process dies) — a poll optimization,
 * not persistence.
 */
object ConditionalHttp {
    private data class Entry(val etag: String, val body: String)

    private val cache = ConcurrentHashMap<String, Entry>()

    private fun keyOf(url: String, token: String?): String = url + "|" + (token ?: "")

    /**
     * GET [url] with ETag revalidation. Returns the body on 2xx, the cached body on 304,
     * the last good body on a network failure, or null if there's nothing to fall back to.
     */
    suspend fun getText(url: String, token: String? = null): String? = withContext(Dispatchers.IO) {
        val key = keyOf(url, token)
        val cached = cache[key]
        var connection: HttpURLConnection? = null
        try {
            connection = (URL(url).openConnection() as HttpURLConnection).apply {
                requestMethod = "GET"
                connectTimeout = 15000
                readTimeout = 15000
                setRequestProperty("Accept", "application/json")
                if (!token.isNullOrBlank()) setRequestProperty("Authorization", "Bearer $token")
                // Ask the server to skip the payload if it still matches what we hold.
                cached?.let { setRequestProperty("If-None-Match", it.etag) }
            }
            when (val code = connection.responseCode) {
                HttpURLConnection.HTTP_NOT_MODIFIED -> cached?.body
                in 200..299 -> {
                    val body = connection.inputStream.bufferedReader().use { it.readText() }
                    val etag = connection.getHeaderField("ETag")
                    if (!etag.isNullOrBlank()) {
                        cache[key] = Entry(etag, body)
                    } else {
                        // Server (or an old build) stopped sending ETags — drop the entry
                        // so we never revalidate against a stale tag.
                        cache.remove(key)
                    }
                    body
                }
                else -> null
            }
        } catch (_: Exception) {
            // Offline / transient failure → serve the last good body if we have one.
            cached?.body
        } finally {
            connection?.disconnect()
        }
    }
}
