package com.haraan.app.data

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONObject
import java.io.BufferedReader
import java.io.File
import java.io.InputStreamReader
import java.net.HttpURLConnection
import java.net.URL

/** What is on the other end of a pairing code, before anyone commits to joining. */
data class PairingPreview(
    val role: MatchDeviceRole,
    val roleLabel: String,
    val matchTitle: String,
    val venue: String,
)

/** A claimed pairing: this phone is now part of that match, until it is revoked. */
data class CameraSession(
    val sessionToken: String,
    val role: MatchDeviceRole,
    val roleLabel: String,
    val matchId: String,
    val matchTitle: String,
    val venue: String,
)

/** What the camera learns each time it checks in. */
data class CameraHeartbeat(val score: String, val overs: String, val matchStatus: String)

/**
 * The camera phone's half of pairing.
 *
 * Deliberately separate from [MatchDeviceRepository] and from [TokenStore]: the phone
 * filming may belong to someone who has never signed up, and nothing here should be
 * reachable only to an account. The session token IS the credential, it is held in
 * memory for the life of the screen, and it stops working the moment the scorer
 * revokes the device.
 */
class CameraDeviceRepository {

    private val baseUrl: String = ApiConfig.BASE_URL

    suspend fun preview(code: String): PairingPreview = withContext(Dispatchers.IO) {
        val response = request("/api/match-devices/${code.uppercase()}/preview", "GET", null)
        if (response.code !in 200..299) {
            throw IllegalStateException(errorOf(response.body, "That pairing code is not valid."))
        }
        val data = JSONObject(response.body).getJSONObject("data")
        PairingPreview(
            role = MatchDeviceRole.fromServer(data.optString("role")),
            roleLabel = data.optString("roleLabel"),
            matchTitle = data.optString("matchTitle"),
            venue = data.optString("venue"),
        )
    }

    suspend fun claim(code: String, deviceName: String): CameraSession = withContext(Dispatchers.IO) {
        val body = JSONObject()
            .put("token", code.uppercase())
            .put("deviceName", deviceName)
            .put("platform", "android")
        val response = request("/api/match-devices/claim", "POST", body)
        if (response.code !in 200..299) {
            throw IllegalStateException(errorOf(response.body, "Couldn't join the match."))
        }
        val data = JSONObject(response.body).getJSONObject("data")
        CameraSession(
            sessionToken = data.optString("sessionToken"),
            role = MatchDeviceRole.fromServer(data.optString("role")),
            roleLabel = data.optString("roleLabel"),
            matchId = data.optString("matchId"),
            matchTitle = data.optString("matchTitle"),
            venue = data.optString("venue"),
        )
    }

    /**
     * Check in. Null means this device is no longer paired — the caller should stop
     * filming and say so, rather than keep recording for nobody.
     */
    suspend fun heartbeat(sessionToken: String): CameraHeartbeat? = withContext(Dispatchers.IO) {
        val response = request(
            "/api/match-devices/heartbeat",
            "POST",
            JSONObject().put("sessionToken", sessionToken),
        )
        if (response.code !in 200..299) return@withContext null
        val data = JSONObject(response.body).optJSONObject("data") ?: return@withContext null
        CameraHeartbeat(
            score = data.optString("score"),
            overs = data.optString("overs"),
            matchStatus = data.optString("matchStatus"),
        )
    }

    /**
     * Send one clip. Multipart written by hand for the same reason the rest of this
     * package uses HttpURLConnection: adding an HTTP client for one upload would be a
     * dependency the app does not otherwise need.
     */
    suspend fun uploadClip(
        sessionToken: String,
        file: File,
        durationMs: Long,
        overBall: String?,
    ): Boolean = withContext(Dispatchers.IO) {
        val boundary = "----haraan" + System.currentTimeMillis()
        val connection = (URL(baseUrl.trimEnd('/') + "/api/match-devices/clips").openConnection()
            as HttpURLConnection).apply {
            requestMethod = "POST"
            doOutput = true
            connectTimeout = 20000
            readTimeout = 60000
            setRequestProperty("Accept", "application/json")
            setRequestProperty("Content-Type", "multipart/form-data; boundary=$boundary")
            // Streamed, not buffered: a clip is megabytes and this runs on a phone that
            // is also filming.
            setChunkedStreamingMode(16 * 1024)
        }

        try {
            connection.outputStream.use { out ->
                fun field(name: String, value: String) {
                    out.write(
                        ("--$boundary\r\nContent-Disposition: form-data; name=\"$name\"\r\n\r\n$value\r\n")
                            .toByteArray(Charsets.UTF_8),
                    )
                }
                field("sessionToken", sessionToken)
                field("durationMs", durationMs.toString())
                if (!overBall.isNullOrBlank()) field("overBall", overBall)

                out.write(
                    ("--$boundary\r\nContent-Disposition: form-data; name=\"clip\"; " +
                        "filename=\"${file.name}\"\r\nContent-Type: video/mp4\r\n\r\n")
                        .toByteArray(Charsets.UTF_8),
                )
                file.inputStream().use { it.copyTo(out) }
                out.write("\r\n--$boundary--\r\n".toByteArray(Charsets.UTF_8))
            }
            connection.responseCode in 200..299
        } catch (_: Exception) {
            false
        } finally {
            connection.disconnect()
        }
    }

    private fun request(path: String, method: String, body: JSONObject?): HttpResult {
        val connection = (URL(baseUrl.trimEnd('/') + path).openConnection() as HttpURLConnection).apply {
            requestMethod = method
            connectTimeout = 15000
            readTimeout = 15000
            setRequestProperty("Accept", "application/json")
            if (body != null) {
                doOutput = true
                setRequestProperty("Content-Type", "application/json")
            }
        }
        if (body != null) {
            connection.outputStream.use { it.write(body.toString().toByteArray(Charsets.UTF_8)) }
        }
        val code = connection.responseCode
        val stream = (if (code >= 400) connection.errorStream else connection.inputStream)
        val text = stream?.let { BufferedReader(InputStreamReader(it)).use(BufferedReader::readText) } ?: ""
        connection.disconnect()
        return HttpResult(code, text)
    }

    private fun errorOf(body: String, fallback: String): String = try {
        if (body.isBlank()) fallback
        else JSONObject(body).let { it.optString("error", it.optString("message", fallback)) }
    } catch (_: Exception) {
        fallback
    }

    private data class HttpResult(val code: Int, val body: String)
}
