package com.haraan.app.data

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONArray
import org.json.JSONObject
import java.io.BufferedReader
import java.io.InputStreamReader
import java.net.HttpURLConnection
import java.net.URL

data class CreateMatchResult(
  val matchId: Long,
  val title: String,
  val baseXp: Int,
  val matchType: String,
  val isPrivate: Boolean = false,
  val joinCode: String = "",
)

/** One row for the GameHub live-scores list (from GET /api/live-matches). */
data class LiveMatchRow(
  val id: String,
  val team1: String,
  val team2: String,
  val score1: String,
  val score2: String,
  val overs1: String,
  val overs2: String,
  val status: String,
  val venue: String,
  val competition: String,
  val isLive: Boolean,
  val visibility: String = "LOCAL",
  val district: String = "",
  val locality: String = "",
  /** 1 = team1 (home) batting, 2 = team2 (away). Drives card ordering + "Yet to bat". */
  val battingTeam: Int = 1,
  /** Team icons chosen at create time — an uploaded logo URL or a default emblem key. */
  val team1Logo: String = "",
  val team2Logo: String = "",
  val team1Emblem: String = "",
  val team2Emblem: String = "",
  /** True when the signed-in viewer created this match (server-scoped) — tags "mine" in the feed. */
  val isMine: Boolean = false,
  /**
   * True when this match sits in the viewer's own district. Everyone sees every
   * public match, so this is a *grouping* hint only — never an access rule.
   * Always false for guests, collapsing their feed to Featured + All matches.
   */
  val isLocalToViewer: Boolean = false,
  /** Admin-curated — drawn as a ⭐ on the card, not as a separate section. */
  val isFeatured: Boolean = false,
  /**
   * Measured km from the viewer, or null when either side has no GPS fix (every
   * match created before coordinates were required). Null must render as *nothing* —
   * an unmeasurable distance is never estimated.
   */
  val distanceKm: Double? = null,
)

/**
 * Talks to the ActionBoard match API. Mirrors [HaraanAuthRepository]'s plain
 * HttpURLConnection style, adding the JWT Bearer header for protected routes.
 */
class MatchRepository(
  private val baseUrl: String = ApiConfig.BASE_URL,
) {
  /**
   * POST /api/matches — create a match from the Create Match wizard.
   * Squad entries are player names (or registered player_ids); the backend
   * resolves any that match a registered player.
   */
  suspend fun createMatch(
    token: String,
    sport: String = "cricket",
    matchType: String,
    overs: Int,
    ball: String,
    playersPerSide: Int,
    venue: String,
    locality: String = "",
    onHaraanTurf: Boolean,
    teamA: String,
    teamB: String,
    squadA: List<SquadMember>,
    squadB: List<SquadMember>,
    teamAEmblem: String? = null,
    teamBEmblem: String? = null,
    venueBookingId: Long? = null,
    isPrivate: Boolean = false,
    /** GPS fix taken at creation — required by the server for public matches. */
    latitude: Double? = null,
    longitude: Double? = null,
    /** District resolved from that fix; the server prefers it over the profile's. */
    district: String = "",
  ): CreateMatchResult = withContext(Dispatchers.IO) {
    val body = JSONObject()
      .put("sport", sport.lowercase())
      .put("matchType", matchType)
      .put("isPrivate", isPrivate)
      .put("overs", overs)
      .put("ball", ball)
      .put("playersPerSide", playersPerSide)
      .put("venue", venue)
      .put("onHaraanTurf", onHaraanTurf)
      .put("teamA", teamA)
      .put("teamB", teamB)
      .put("squadA", squadJson(squadA))
      .put("squadB", squadJson(squadB))
    // Optional area/village — omitted for private matches (they're hidden from feeds).
    if (locality.isNotBlank()) body.put("locality", locality)
    // The GPS fix. The server requires it on public matches; a private match may
    // legitimately have none, so only send what we actually hold.
    if (latitude != null && longitude != null) {
      body.put("latitude", latitude)
      body.put("longitude", longitude)
    }
    if (district.isNotBlank()) body.put("district", district)
    if (!teamAEmblem.isNullOrBlank()) body.put("teamAEmblem", teamAEmblem)
    if (!teamBEmblem.isNullOrBlank()) body.put("teamBEmblem", teamBEmblem)
    if (venueBookingId != null) body.put("venueBookingId", venueBookingId)

    val response = postJson("/api/matches", body, token)

    if (response.code !in 200..299) {
      throw IllegalStateException(parseErrorMessage(response.body, "Unable to create match."))
    }

    val data = JSONObject(response.body).getJSONObject("data")
    CreateMatchResult(
      matchId = data.optLong("id", 0L),
      title = data.optString("title", "$teamA vs $teamB"),
      baseXp = data.optInt("base_xp", 0),
      matchType = data.optString("match_type", matchType),
      isPrivate = data.optBoolean("is_private", isPrivate),
      joinCode = data.optString("join_code", ""),
    )
  }

  /**
   * POST /api/matches/{id}/team-logo — upload a custom crest for one side
   * (multipart/form-data). [side] is "home" or "away". Returns the stored URL,
   * or throws on failure. [mimeType] picks the part's content type + extension.
   */
  suspend fun uploadTeamLogo(
    token: String,
    matchId: Long,
    side: String,
    imageBytes: ByteArray,
    mimeType: String,
  ): String = withContext(Dispatchers.IO) {
    val boundary = "----HaraanBoundary${System.currentTimeMillis()}"
    val connection = (URL("${baseUrl.trimEnd('/')}/api/matches/$matchId/team-logo").openConnection() as HttpURLConnection).apply {
      requestMethod = "POST"
      doOutput = true
      connectTimeout = 20000
      readTimeout = 20000
      setRequestProperty("Content-Type", "multipart/form-data; boundary=$boundary")
      setRequestProperty("Accept", "application/json")
      setRequestProperty("Authorization", "Bearer $token")
    }

    val ext = when (mimeType.lowercase()) {
      "image/png" -> "png"
      "image/webp" -> "webp"
      else -> "jpg"
    }
    val lineEnd = "\r\n"
    val dashes = "--"

    try {
      connection.outputStream.use { out ->
        // side field
        out.write(("$dashes$boundary$lineEnd").toByteArray())
        out.write(("Content-Disposition: form-data; name=\"side\"$lineEnd$lineEnd").toByteArray())
        out.write((side + lineEnd).toByteArray())
        // logo file
        out.write(("$dashes$boundary$lineEnd").toByteArray())
        out.write(("Content-Disposition: form-data; name=\"logo\"; filename=\"logo.$ext\"$lineEnd").toByteArray())
        out.write(("Content-Type: $mimeType$lineEnd$lineEnd").toByteArray())
        out.write(imageBytes)
        out.write(lineEnd.toByteArray())
        // closing boundary
        out.write(("$dashes$boundary$dashes$lineEnd").toByteArray())
      }

      val code = connection.responseCode
      val body = readBody(connection)
      if (code !in 200..299) {
        throw IllegalStateException(parseErrorMessage(body, "Unable to upload team logo."))
      }
      JSONObject(body).optString("url", "")
    } finally {
      connection.disconnect()
    }
  }

  /**
   * GET /api/live-matches — GameHub live-scores feed. Every public match is
   * returned to every viewer, guests included; only private matches are withheld.
   * Passing [token] doesn't widen the feed — it just tags rows (`isMine`,
   * `isLocalToViewer`) so the app can group them. [scope] ("local" | "featured"
   * | "all") narrows to one slice.
   * Returns an empty list on any failure so the screen can fall back to its demo
   * content without ever showing an error.
   */
  suspend fun getLiveMatches(
    token: String? = null,
    scope: String? = null,
    /**
     * Where the viewer is, for proximity ranking. Sent by guests too — that's the
     * point: "matches near me" must not require an account. Omitted when unknown,
     * in which case the server falls back to the profile district (or nothing).
     */
    latitude: Double? = null,
    longitude: Double? = null,
    locality: String = "",
    district: String = "",
  ): List<LiveMatchRow> = withContext(Dispatchers.IO) {
    try {
      val params = buildList {
        if (!scope.isNullOrBlank()) add("scope=$scope")
        if (latitude != null && longitude != null) {
          add("lat=$latitude")
          add("lng=$longitude")
        }
        if (locality.isNotBlank()) add("locality=${java.net.URLEncoder.encode(locality, "UTF-8")}")
        if (district.isNotBlank()) add("district=${java.net.URLEncoder.encode(district, "UTF-8")}")
      }
      val query = if (params.isEmpty()) "" else "?" + params.joinToString("&")
      // Conditional GET: an unchanged feed comes back 304 and is served from cache, so
      // the 20s AutoRefresh poll re-downloads nothing when nothing's happening.
      val body = ConditionalHttp.getText("${baseUrl.trimEnd('/')}/api/live-matches$query", token)
        ?: return@withContext emptyList()
      val arr = JSONObject(body).optJSONArray("data") ?: return@withContext emptyList()
      (0 until arr.length()).map { i ->
        val o = arr.getJSONObject(i)
        LiveMatchRow(
          id = o.optString("id"),
          team1 = o.optString("team1"),
          team2 = o.optString("team2"),
          score1 = o.optString("score1"),
          score2 = o.optString("score2"),
          overs1 = o.optString("overs1"),
          overs2 = o.optString("overs2"),
          status = o.optString("status"),
          venue = o.optString("venue"),
          competition = o.optString("competition"),
          isLive = o.optBoolean("isLive", false),
          visibility = o.optString("visibility", "LOCAL"),
          district = o.optString("district", ""),
          locality = o.optString("locality", ""),
          battingTeam = o.optInt("battingTeam", 1),
          team1Logo = o.optString("team1Logo", ""),
          team2Logo = o.optString("team2Logo", ""),
          team1Emblem = o.optString("team1Emblem", ""),
          team2Emblem = o.optString("team2Emblem", ""),
          isMine = o.optBoolean("isMine", false),
          isLocalToViewer = o.optBoolean("isLocalToViewer", false),
          isFeatured = o.optBoolean("isFeatured", false),
          // optDouble yields NaN when absent — map that back to a real null so the
          // card can tell "no fix" apart from "zero km away".
          distanceKm = o.optDouble("distanceKm", Double.NaN).takeUnless { it.isNaN() },
        )
      }
    } catch (_: Exception) {
      emptyList()
    }
  }

  /**
   * GET /api/live-matches/{id} — live-match detail for the Match Details screen.
   * Pass [token] so a LOCAL match opened from the viewer's own district feed stays
   * reachable (the server 404s LOCAL matches outside the viewer's district).
   * Returns the raw JSON body, or null on any failure (so callers can fall back to
   * cached/mock data without crashing the screen).
   */
  suspend fun getLiveMatchJson(id: String, token: String? = null): String? =
    // Conditional GET so the 12s live-detail poll is a 304 (served from cache) between
    // actual score changes — the biggest single bandwidth win of the auto-refresh work.
    ConditionalHttp.getText("${baseUrl.trimEnd('/')}/api/live-matches/$id", token)

  /**
   * GET /api/live-matches/code/{code} — open a PRIVATE match by its share code.
   * Public, no auth: the code itself is the grant. Returns the raw detail JSON, or
   * null on any failure (bad/expired code, network).
   */
  suspend fun getLiveMatchByCode(code: String): String? = withContext(Dispatchers.IO) {
    val clean = code.trim().uppercase()
    if (clean.isEmpty()) return@withContext null
    ConditionalHttp.getText("${baseUrl.trimEnd('/')}/api/live-matches/code/$clean")
  }

  suspend fun sendScoreAction(
    token: String,
    matchId: String,
    action: JSONObject
  ): String? = withContext(Dispatchers.IO) {
    try {
      val response = postJson("/api/matches/$matchId/score-action", action, token)
      if (response.code in 200..299) response.body else null
    } catch (_: Exception) {
      null
    }
  }

  private fun squadJson(squad: List<SquadMember>): JSONArray {
    val arr = JSONArray()
    squad.forEach { member ->
      arr.put(
        JSONObject()
          .put("id", member.id)
          .put("name", member.name)
          .put("isCaptain", member.isCaptain)
          .put("isViceCaptain", member.isViceCaptain),
      )
    }
    return arr
  }

  private fun postJson(path: String, jsonBody: JSONObject, token: String): HttpResult {
    val connection = (URL(baseUrl.trimEnd('/') + path).openConnection() as HttpURLConnection).apply {
      requestMethod = "POST"
      doOutput = true
      connectTimeout = 15000
      readTimeout = 15000
      setRequestProperty("Content-Type", "application/json")
      setRequestProperty("Accept", "application/json")
      setRequestProperty("Authorization", "Bearer $token")
    }

    connection.outputStream.use { outputStream ->
      outputStream.write(jsonBody.toString().toByteArray(Charsets.UTF_8))
    }

    val code = connection.responseCode
    val body = readBody(connection)
    connection.disconnect()
    return HttpResult(code = code, body = body)
  }

  private fun readBody(connection: HttpURLConnection): String {
    val stream = if (connection.responseCode >= 400) connection.errorStream else connection.inputStream
      ?: return ""
    return BufferedReader(InputStreamReader(stream)).use { it.readText() }
  }

  private fun parseErrorMessage(body: String, fallback: String): String {
    return try {
      if (body.isBlank()) {
        fallback
      } else {
        val json = JSONObject(body)
        json.optString("error", json.optString("message", fallback))
      }
    } catch (_: Exception) {
      fallback
    }
  }

  private data class HttpResult(val code: Int, val body: String)
}
