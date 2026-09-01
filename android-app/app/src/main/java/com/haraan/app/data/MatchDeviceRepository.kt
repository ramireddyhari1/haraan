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
    /** Null until somebody asks for a review. Most clips are never reviewed. */
    val review: DeliveryReview? = null,
    /** Where that review has got to. NONE means nobody has ever asked. */
    val reviewStatus: ReviewStatus = ReviewStatus.NONE,
    /** Server-supplied and safe to print — never an exception message. */
    val reviewError: String? = null,
)

/**
 * Where a clip's review has got to.
 *
 * Mirrors the server's strings. NONE is ours, not the server's: it stands for a clip
 * nobody has asked about, which the server represents as a null status and which the UI
 * has to tell apart from a review that ran and failed.
 */
enum class ReviewStatus {
    NONE, PENDING, PROCESSING, COMPLETED, FAILED;

    val settled: Boolean get() = this == COMPLETED || this == FAILED

    companion object {
        fun fromServer(value: String?): ReviewStatus = when (value?.lowercase()) {
            "pending" -> PENDING
            "processing" -> PROCESSING
            "completed" -> COMPLETED
            "failed" -> FAILED
            else -> NONE
        }
    }
}

/** One answer shape for every review call, so the screen has a single thing to read. */
data class ReviewState(
    val status: ReviewStatus,
    val review: DeliveryReview?,
    val error: String?,
)

/**
 * What the footage showed, factor by factor.
 *
 * There is deliberately no "out" field and there never will be one. A phone at an unknown
 * angle cannot adjudicate an LBW, and a model that answered the appeal would be inventing
 * certainty the camera does not have. Every factor may come back UNKNOWN, and on most
 * ground-level footage most of them will.
 */
data class DeliveryReview(
    val factors: List<ReviewFactor>,
    /** Null when the model returned no coordinates at all, which is common. */
    val evidence: DeliveryEvidence? = null,
    /** How much the camera could see at all: good, partial or poor. */
    val visibility: String,
    /** One line about the footage itself — the angle, what was out of frame. */
    val notes: String?,
)

/**
 * The coordinate evidence behind a review — what a 2D map can actually be drawn from.
 *
 * Every coordinate is normalised 0..1 in IMAGE space: x across the frame, y down it.
 * These are positions in the video, not positions on a pitch. Nothing in the pipeline is
 * calibrated — no stump height, no crease reference, no camera pose — so a renderer must
 * never present these on a diagram of a cricket pitch, which would silently upgrade a
 * point in a picture into a measurement on the ground.
 */
data class DeliveryEvidence(
    val coordinateSpace: String,
    val ballDetected: Boolean,
    val ballPoints: List<TrackPoint>,
    val pitching: MarkedPoint?,
    val impact: MarkedPoint?,
    val projection: WicketProjection?,
    val uncertainty: Map<String, String>,
) {
    /** True when there is literally nothing to draw. The map says so rather than drawing an empty box. */
    val isEmpty: Boolean
        get() = ballPoints.isEmpty() &&
            pitching?.detected != true &&
            impact?.detected != true &&
            projection?.predicted != true
}

data class TrackPoint(val timestampMs: Int, val x: Float, val y: Float)

/** An OBSERVED position. [detected] is derived server-side from surviving coordinates. */
data class MarkedPoint(
    val detected: Boolean,
    val x: Float?,
    val y: Float?,
    val timestampMs: Int?,
    val modelConfidence: Float?,
)

/**
 * A PREDICTED position, kept in its own type so a renderer cannot accidentally draw it
 * with the same weight as something the camera actually saw.
 */
data class WicketProjection(
    val predicted: Boolean,
    val stumpsHit: String,
    val x: Float?,
    val y: Float?,
    val modelConfidence: Float?,
)

data class ReviewFactor(
    /** Server key: pitching, impact, bat_involved, height, line. */
    val key: String,
    /** Server reading, e.g. in_line, outside_off, pad_first, cannot_tell. */
    val reading: String,
    /** The model's own claim that the footage settles this factor. */
    val certain: Boolean,
) {
    val unknown: Boolean get() = reading == "cannot_tell"
}

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
                            review = parseReview(o.optJSONObject("review")),
                            reviewStatus = ReviewStatus.fromServer(
                                o.optString("reviewStatus").takeIf { it.isNotBlank() && it != "null" },
                            ),
                            reviewError = o.optString("reviewError")
                                .takeIf { it.isNotBlank() && it != "null" },
                        ),
                    )
                }
            }
        }

    /**
     * Ask for a review of one clip.
     *
     * Returns as soon as the server has taken the request — the Vertex call runs on a
     * queue now, so this no longer waits out an eight-to-ninety-second model call on a
     * ground's connection. The caller polls [reviewStatus] until the state settles.
     *
     * A 202 with PENDING and a 200 with COMPLETED are both successes: on a server with no
     * queue worker the job runs inline and the answer is already there.
     */
    suspend fun requestReview(token: String, matchId: String, clipId: String): ReviewState =
        withContext(Dispatchers.IO) {
            val response = postJson(
                "/api/matches/$matchId/clips/$clipId/review",
                JSONObject(),
                token,
            )
            parseReviewState(response.body, response.code)
        }

    /** Where a queued review has got to. Cheap enough to poll every couple of seconds. */
    suspend fun reviewStatus(token: String, matchId: String, clipId: String): ReviewState =
        withContext(Dispatchers.IO) {
            val response = getJson("/api/matches/$matchId/clips/$clipId/review", token)
            parseReviewState(response.body, response.code)
        }

    /**
     * One parse for both calls.
     *
     * A transport failure becomes FAILED with a plain message rather than an exception:
     * the screen has one thing to render either way, and a scorer at a ground does not
     * need to know whether the review died at nginx or at Vertex.
     */
    private fun parseReviewState(body: String, code: Int): ReviewState {
        val json = runCatching { JSONObject(body) }.getOrNull()
        val data = json?.optJSONObject("data")

        if (data == null) {
            val message = json?.optString("error")?.takeIf { it.isNotBlank() && it != "null" }
                ?: "The review could not be started."
            return ReviewState(ReviewStatus.FAILED, null, message)
        }

        val review = parseReview(data.optJSONObject("review"))
        val status = ReviewStatus.fromServer(
            data.optString("status").takeIf { it.isNotBlank() && it != "null" },
        )
        return ReviewState(
            // A body carrying a review is complete whatever the status string says.
            status = if (review != null) ReviewStatus.COMPLETED else status,
            review = review,
            error = data.optString("error").takeIf { it.isNotBlank() && it != "null" }
                ?: "The review could not be started.".takeIf { code !in 200..299 && status == ReviewStatus.NONE },
        )
    }

    /**
     * Read the review, keeping the server's factor ORDER rather than the JSON object's.
     *
     * The order is cricket's — pitching, then impact, then whether the bat was involved,
     * then height, then line — because that is the sequence an umpire actually decides in,
     * and a list that arrives shuffled reads as a data dump instead of a decision.
     */
    private fun parseReview(json: JSONObject?): DeliveryReview? {
        if (json == null) return null
        val factorsJson = json.optJSONObject("factors") ?: return null
        val order = listOf("pitching", "impact", "bat_involved", "height", "line")
        val factors = order.mapNotNull { key ->
            val f = factorsJson.optJSONObject(key) ?: return@mapNotNull null
            val reading = f.optString("reading").takeIf { it.isNotBlank() && it != "null" }
                ?: return@mapNotNull null
            ReviewFactor(key = key, reading = reading, certain = f.optBoolean("certain", false))
        }
        if (factors.isEmpty()) return null
        return DeliveryReview(
            factors = factors,
            evidence = parseEvidence(json.optJSONObject("delivery")),
            visibility = json.optString("visibility").takeIf { it.isNotBlank() } ?: "poor",
            notes = json.optString("notes").takeIf { it.isNotBlank() && it != "null" },
        )
    }

    /**
     * The coordinates, read exactly as sent and never repaired.
     *
     * The server has already dropped anything outside the frame and derived every
     * `detected` flag from coordinates that survived, so this parses rather than
     * validates. A point missing an x or a y is skipped instead of being filled in:
     * interpolating a position would be inventing evidence at the last possible moment.
     */
    private fun parseEvidence(json: JSONObject?): DeliveryEvidence? {
        if (json == null) return null

        val ball = json.optJSONObject("ball")
        val points = ball?.optJSONArray("points")?.let { arr ->
            (0 until arr.length()).mapNotNull { i ->
                val o = arr.optJSONObject(i) ?: return@mapNotNull null
                if (!o.has("x") || !o.has("y")) return@mapNotNull null
                TrackPoint(
                    timestampMs = o.optInt("timestampMs", 0),
                    x = o.optDouble("x").toFloat(),
                    y = o.optDouble("y").toFloat(),
                )
            }
        } ?: emptyList()

        fun marked(key: String): MarkedPoint? {
            val o = json.optJSONObject(key) ?: return null
            return MarkedPoint(
                detected = o.optBoolean("detected", false),
                x = if (o.isNull("x")) null else o.optDouble("x").toFloat(),
                y = if (o.isNull("y")) null else o.optDouble("y").toFloat(),
                timestampMs = if (o.isNull("timestampMs")) null else o.optInt("timestampMs"),
                modelConfidence = if (o.isNull("modelConfidence")) null
                    else o.optDouble("modelConfidence").toFloat(),
            )
        }

        val projectionJson = json.optJSONObject("wicketProjection")
        val projection = projectionJson?.let { o ->
            WicketProjection(
                predicted = o.optBoolean("predicted", false),
                stumpsHit = o.optString("stumpsHit").takeIf { it.isNotBlank() } ?: "cannot_tell",
                x = if (o.isNull("x")) null else o.optDouble("x").toFloat(),
                y = if (o.isNull("y")) null else o.optDouble("y").toFloat(),
                modelConfidence = if (o.isNull("modelConfidence")) null
                    else o.optDouble("modelConfidence").toFloat(),
            )
        }

        val uncertainty = json.optJSONObject("uncertainty")?.let { o ->
            buildMap {
                o.keys().forEach { key ->
                    o.optString(key).takeIf { it.isNotBlank() && it != "null" }?.let { put(key, it) }
                }
            }
        } ?: emptyMap()

        return DeliveryEvidence(
            coordinateSpace = json.optString("coordinateSpace").takeIf { it.isNotBlank() }
                ?: "image_normalised",
            ballDetected = ball?.optBoolean("detected", false) ?: false,
            ballPoints = points,
            pitching = marked("pitching"),
            impact = marked("impact"),
            projection = projection,
            uncertainty = uncertainty,
        )
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
