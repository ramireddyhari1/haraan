package com.haraan.app.commentary

import com.haraan.app.data.ApiConfig
import com.haraan.app.data.membership.PhoneFeatureAccess
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONObject
import java.net.HttpURLConnection
import java.net.URL

/**
 * Live commentary over HTTP. The voice never goes through here: these calls only get a pass
 * for the audio server (LiveKit) and keep the server's record of who is on air.
 *
 * Scorer: [start] (Hero; the server checks), [heartbeat] every 15 s, [stop].
 * Listener: [listen] (free) and [report].
 */
object CommentaryApi {

    /** Going on air answered. [token] lets this phone publish its mic into [room]. */
    data class OnAir(
        val session: Long,
        val url: String,
        val room: String,
        val token: String,
        val heartbeatSeconds: Int,
        val silenceMinutes: Int,
        val maxMinutes: Int,
    )

    data class Beat(val onAir: Boolean, val listeners: Int, val reason: String, val message: String)

    data class ListenPass(val session: Long, val url: String, val room: String, val token: String, val by: String)

    /** A refusal in the server's own words, with its code (upgrade_required, full, off_air…). */
    data class Refused(val code: String, val message: String, val upgradePlan: String = "")

    sealed interface Result<out T> {
        data class Ok<T>(val value: T) : Result<T>
        data class No(val refused: Refused) : Result<Nothing>
    }

    suspend fun start(token: String, matchId: String): Result<OnAir> = call("POST", "/api/matches/$matchId/commentary", JSONObject(), token) { o ->
        OnAir(
            session = o.optLong("session"),
            url = o.optString("url"),
            room = o.optString("room"),
            token = o.optString("token"),
            heartbeatSeconds = o.optInt("heartbeatSeconds", 15).coerceIn(5, 60),
            silenceMinutes = o.optInt("silenceMinutes", 5).coerceAtLeast(0),
            maxMinutes = o.optInt("maxMinutes", 240),
        )
    }

    suspend fun heartbeat(token: String, matchId: String, session: Long, muted: Boolean): Result<Beat> =
        call("POST", "/api/matches/$matchId/commentary/$session/heartbeat", JSONObject().put("muted", muted), token) { o ->
            Beat(
                onAir = o.optBoolean("onAir", false),
                listeners = o.optInt("listeners", 0),
                reason = o.optString("reason"),
                message = o.optString("message"),
            )
        }

    suspend fun stop(token: String, matchId: String, session: Long, silence: Boolean = false): Result<Unit> =
        // DELETE carries no body (HttpURLConnection won't reliably send one): reason rides the query.
        call("DELETE", "/api/matches/$matchId/commentary/$session?reason=" + (if (silence) "silence" else "commentator"), JSONObject(), token) { }

    suspend fun listen(matchId: String, code: String, viewerKey: String, token: String?): Result<ListenPass> {
        val clean = code.trim().uppercase()
        val path = if (clean.isNotEmpty()) "/api/live-matches/code/$clean/commentary/listen" else "/api/live-matches/$matchId/commentary/listen"
        return call("POST", path, JSONObject().put("viewer", viewerKey), token) { o ->
            ListenPass(o.optLong("session"), o.optString("url"), o.optString("room"), o.optString("token"), o.optString("by"))
        }
    }

    suspend fun report(session: Long, reason: String, viewerKey: String, token: String?): Result<Boolean> =
        call("POST", "/api/live-matches/commentary/$session/report", JSONObject().put("reason", reason).put("viewer", viewerKey), token) { o ->
            o.optBoolean("offAir", false)
        }

    private suspend fun <T> call(
        method: String,
        path: String,
        body: JSONObject,
        token: String?,
        parse: (JSONObject) -> T,
    ): Result<T> = withContext(Dispatchers.IO) {
        try {
            val conn = (URL(ApiConfig.BASE_URL.trimEnd('/') + path).openConnection() as HttpURLConnection).apply {
                requestMethod = method
                doOutput = method != "DELETE"
                connectTimeout = 10_000
                readTimeout = 10_000
                setRequestProperty("Content-Type", "application/json")
                setRequestProperty("Accept", "application/json")
                if (!token.isNullOrBlank()) setRequestProperty("Authorization", "Bearer $token")
            }
            if (conn.doOutput) conn.outputStream.use { it.write(body.toString().toByteArray(Charsets.UTF_8)) }
            val code = conn.responseCode
            val text = (if (code in 200..299) conn.inputStream else conn.errorStream)
                ?.bufferedReader()?.use { it.readText() }.orEmpty()
            conn.disconnect()
            val json = runCatching { JSONObject(text) }.getOrDefault(JSONObject())
            if (code in 200..299) {
                Result.Ok(parse(json))
            } else {
                Result.No(
                    Refused(
                        code = json.optString("code").ifBlank { "http_$code" },
                        message = json.optString("error").ifBlank { "Couldn’t reach Haraan. Try again." },
                        upgradePlan = json.optString("upgrade_plan"),
                    )
                )
            }
        } catch (_: Exception) {
            Result.No(Refused("network", "No connection. Check your internet and try again."))
        }
    }
}

/** Who may go on air: the member feature `matches.live_commentary` (Hero at launch), never a plan code. */
object LiveCommentaryAccess : PhoneFeatureAccess("matches.live_commentary")
