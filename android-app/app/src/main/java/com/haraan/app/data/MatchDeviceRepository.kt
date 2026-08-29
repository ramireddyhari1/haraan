package com.haraan.app.data

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONObject
import java.io.BufferedReader
import java.io.InputStreamReader
import java.net.HttpURLConnection
import java.net.URL

/**
 * One role a second phone can play in a match. The set is deliberately open-ended on
 * the server (a plain string column), so adding a role here and a card in the sheet is
 * the whole cost of the next AI-assisted feature that needs its own camera.
 */
enum class MatchDeviceRole(val serverValue: String, val label: String, val blurb: String) {
    LBW_REVIEW(
        "LBW_AI_CAMERA",
        "LBW / review camera",
        "Point it down the pitch at the stumps. Captures the delivery for review.",
    ),
    BOWLER_ANALYSIS(
        "BOWLER_ANALYSIS_CAMERA",
        "Bowler analysis camera",
        "Point it side-on at the bowler. Captures the run-up and action.",
    );

    companion object {
        fun fromServer(value: String?): MatchDeviceRole =
            entries.firstOrNull { it.serverValue == value } ?: LBW_REVIEW
    }
}

/** A pairing the scorer has opened but nobody has claimed yet. */
data class PairingSession(
    val id: String,
    val role: MatchDeviceRole,
    val roleLabel: String,
    /** Typed by hand across a ground when a camera cannot scan. */
    val token: String,
    val link: String,
    val deepLink: String,
    val expiresAt: String?,
)

/**
 * A device attached to the match.
 *
 * [status] is the server's word, and "connected" and "lost" are deliberately different:
 * a phone that has stopped checking in is not still filming, and telling the scorer it
 * is would be worse than telling them nothing.
 */
data class MatchDeviceInfo(
    val id: String,
    val role: MatchDeviceRole,
    val roleLabel: String,
    val status: String,
    val deviceName: String,
    val platform: String,
) {
    val isLive: Boolean get() = status == "connected"
    val isLost: Boolean get() = status == "lost"
}

/**
 * One piece of footage a paired camera sent back.
 *
 * [overBall] is the delivery the clip covers, as the scorer's own screen read when it
 * was cut — which is what a scorer looks a clip up by. Blank when the camera joined
 * mid-over and had nothing honest to stamp on it.
 */
data class MatchClip(
    val id: String,
    val roleLabel: String,
    val url: String,
    val overBall: String,
    val durationMs: Long,
)

/**
 * The scorer's half of multi-device pairing. The camera's half needs no account and no
 * token store, so it does not live here.
 */
class MatchDeviceRepository {

    private val baseUrl: String = ApiConfig.BASE_URL

    suspend fun openPairing(token: String, matchId: String, role: MatchDeviceRole): PairingSession =
        withContext(Dispatchers.IO) {
            val body = JSONObject().put("role", role.serverValue)
            val response = postJson("/api/matches/$matchId/devices", body, token)
            if (response.code !in 200..299) {
                throw IllegalStateException(
                    parseErrorMessage(response.body, "Couldn't start pairing."),
                )
            }
            val data = JSONObject(response.body).getJSONObject("data")
            PairingSession(
                id = data.optString("id"),
                role = MatchDeviceRole.fromServer(data.optString("role")),
                roleLabel = data.optString("roleLabel"),
                token = data.optString("token"),
                link = data.optString("link"),
                deepLink = data.optString("deepLink"),
                expiresAt = data.optString("expiresAt").takeIf { it.isNotBlank() },
            )
        }

    suspend fun devices(token: String, matchId: String): List<MatchDeviceInfo> =
        withContext(Dispatchers.IO) {
            val response = getJson("/api/matches/$matchId/devices", token)
            if (response.code !in 200..299) return@withContext emptyList()
            val arr = JSONObject(response.body).optJSONArray("data") ?: return@withContext emptyList()
            buildList {
                for (i in 0 until arr.length()) {
                    val o = arr.optJSONObject(i) ?: continue
                    add(
                        MatchDeviceInfo(
                            id = o.optString("id"),
                            role = MatchDeviceRole.fromServer(o.optString("role")),
                            roleLabel = o.optString("roleLabel"),
                            status = o.optString("status"),
                            deviceName = o.optString("deviceName"),
                            platform = o.optString("platform"),
                        ),
                    )
                }
            }
        }

    suspend fun clips(token: String, matchId: String): List<MatchClip> =
        withContext(Dispatchers.IO) {
            val response = getJson("/api/matches/$matchId/clips", token)
            if (response.code !in 200..299) return@withContext emptyList()
            val arr = JSONObject(response.body).optJSONArray("data") ?: return@withContext emptyList()
            buildList {
                for (i in 0 until arr.length()) {
                    val o = arr.optJSONObject(i) ?: continue
                    add(
                        MatchClip(
                            id = o.optString("id"),
                            roleLabel = o.optString("roleLabel"),
                            url = o.optString("url"),
                            overBall = o.optString("overBall"),
                            durationMs = o.optLong("durationMs", 0L),
                        ),
                    )
                }
            }
        }

    suspend fun revoke(token: String, matchId: String, deviceId: String): Boolean =
        withContext(Dispatchers.IO) {
            val response = deleteJson("/api/matches/$matchId/devices/$deviceId", token)
            response.code in 200..299
        }

    // ── Plumbing ──
    //
    // Written out rather than shared: every repository in this package carries its own
    // copy of these four, and introducing a base class here would mean touching all of
    // them in a change that is supposed to be about pairing.

    private fun request(path: String, method: String, token: String?, body: JSONObject?): HttpResult {
        val connection = (URL(baseUrl.trimEnd('/') + path).openConnection() as HttpURLConnection).apply {
            requestMethod = method
            connectTimeout = 15000
            readTimeout = 15000
            setRequestProperty("Accept", "application/json")
            if (body != null) {
                doOutput = true
                setRequestProperty("Content-Type", "application/json")
            }
            if (!token.isNullOrBlank()) setRequestProperty("Authorization", "Bearer $token")
        }
        if (body != null) {
            connection.outputStream.use { it.write(body.toString().toByteArray(Charsets.UTF_8)) }
        }
        val code = connection.responseCode
        val text = readBody(connection)
        connection.disconnect()
        return HttpResult(code, text)
    }

    private fun postJson(path: String, body: JSONObject, token: String?) =
        request(path, "POST", token, body)

    private fun getJson(path: String, token: String?) = request(path, "GET", token, null)

    private fun deleteJson(path: String, token: String?) = request(path, "DELETE", token, null)

    private fun readBody(connection: HttpURLConnection): String {
        val stream = (if (connection.responseCode >= 400) connection.errorStream else connection.inputStream)
            ?: return ""
        return BufferedReader(InputStreamReader(stream)).use { it.readText() }
    }

    private fun parseErrorMessage(body: String, fallback: String): String = try {
        if (body.isBlank()) fallback
        else JSONObject(body).let { it.optString("error", it.optString("message", fallback)) }
    } catch (_: Exception) {
        fallback
    }

    private data class HttpResult(val code: Int, val body: String)
}
