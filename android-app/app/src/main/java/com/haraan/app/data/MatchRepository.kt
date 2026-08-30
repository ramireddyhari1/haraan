package com.haraan.app.data

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONArray
import org.json.JSONObject
import java.io.BufferedReader
import java.io.InputStreamReader
import java.net.HttpURLConnection
import java.net.URL

/**
 * One presence heartbeat's answer: the audience size, and whether this viewer is allowed to
 * see who it is made of. The permission is the SERVER's answer, carried rather than decided —
 * the app has no business ruling on its own blue tick.
 */
data class WatchBeat(val watching: Int, val canSeeViewers: Boolean)

/**
 * One person in the room. A guest carries no identity at all — the server sends the row as
 * "Haraan Guest" with nothing attached, so there is nothing here to leak.
 */
data class MatchViewerItem(
  val name: String,
  val username: String,
  val avatar: String,
  val verified: Boolean,
  val guest: Boolean,
  val you: Boolean,
)

data class CreateMatchResult(
  val matchId: Long,
  val title: String,
  val baseXp: Int,
  val matchType: String,
  val isPrivate: Boolean = false,
  val joinCode: String = "",
)

/**
 * The scoreline as the SERVER settled it after an event.
 *
 * Football and badminton never send a score — they post what happened and read
 * this back, so the phone's tally can't drift from the match's own timeline.
 */
data class MatchScoreState(
  val home: Int,
  val away: Int,
  val scoreText: String?,
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
  /**
   * cricket | football | badminton — what the Cricket/Badminton/Football boards
   * filter on. Defaults to cricket so a match created before the column existed
   * never vanishes from the one board that works.
   */
  val sport: String = "cricket",
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
  /**
   * The Haraan venue this match was BOOKED at, blank for every other match.
   *
   * Server-resolved from the confirmed booking, never from the typed venue text — the badge
   * has to be earned by a real booking or it is worth nothing. Blank is the normal case: a
   * match on a maidan is not lesser, it just has no venue to name.
   */
  val venueBadgeName: String = "",
  val venueBadgeArea: String = "",
  val venueBadgeId: Int = 0,
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
 * A match the signed-in creator hasn't started yet (from GET /api/matches/scheduled).
 * Carries everything needed to rebuild the toss/scorer setup and start it, so the
 * Scheduled tab can resume a match without another round trip. [scheduledAtIso] is
 * null for a "play now" match whose toss was skipped (the resume-later case).
 */
data class ScheduledMatch(
  val id: String,
  val sport: String,
  val teamA: String,
  val teamB: String,
  val teamAEmblem: String,
  val teamBEmblem: String,
  val squadA: List<SquadMember>,
  val squadB: List<SquadMember>,
  val isPrivate: Boolean,
  val joinCode: String,
  val venue: String,
  val locality: String,
  /** ISO-8601 kick-off, or null for a "play now" match awaiting its toss. */
  val scheduledAtIso: String?,
)

/**
 * An open match near the viewer that's looking for players (from GET /api/matches/open).
 * [myStatus] is the viewer's own request state: none | pending | accepted | declined.
 */
data class OpenMatch(
  val id: String,
  val sport: String,
  val team1: String,
  val team2: String,
  val team1Emblem: String,
  val team2Emblem: String,
  val venue: String,
  val locality: String,
  val competition: String,
  val slotsNeeded: Int,
  val scheduledAtIso: String?,
  val distanceKm: Double?,
  val myStatus: String,
)

/** A pending request from another player to join one of the viewer's matches. */
data class IncomingJoinRequest(
  val id: String,
  val matchId: String,
  val matchTitle: String,
  val message: String,
  val createdAtIso: String?,
  val playerId: String,
  val playerName: String,
  val playerAvatar: String,
  val trustScore: Int,
)

/**
 * What the server said about one scoring action.
 *
 * This used to be a bare `String?`, and EVERY failure collapsed to null — so a 403 the
 * server had gone to the trouble of explaining ("Complete your ActionBoard player profile
 * first.") was indistinguishable from a dead network. The scorer then told the user to
 * check a connection that was working perfectly, which is the worst kind of error message:
 * confidently wrong, and it sends them to fix something that is not broken.
 */
/** One over of the innings, with the score as it stood after it. */
data class ProgressOver(
  val over: Int,
  val runs: Int,
  val wickets: Int,
  val total: Int,
  val totalWickets: Int,
  /** The scorer's shorthand for each delivery in this over: "4", "0", "W", "wd", "lb1". */
  val balls: List<String>,
)

/** An over that moved the match, scored on runs AND wickets rather than runs alone. */
data class ChangingOver(val over: Int, val runs: Int, val wickets: Int, val swing: Int)

/** A stand, in the order the wickets fell. */
data class Stand(
  val wicket: Int,
  val runs: Int,
  val balls: Int,
  val batters: String,
  val unbroken: Boolean,
)

/** One bowler against one batter, over the balls actually bowled between them. */
data class FaceOff(
  val bowler: String,
  val batter: String,
  val balls: Int,
  val runs: Int,
  val wickets: Int,
  val strikeRate: Double,
)

/**
 * One boundary whose direction the scorer recorded.
 *
 * Only exists for balls scored after the wagon-wheel picker shipped, and only where the
 * scorer answered it. An innings with none is the normal case, not a failure.
 */
data class Shot(
  val zone: Int,
  val runs: Int,
  val batter: String,
  val over: Int,
  /**
   * Exact landing point as a fraction of the ground's radius, or null for shots captured
   * before points were recorded. The wheel falls back to the region's centre for those, so
   * an early capture still plots — just less precisely.
   */
  val x: Float?,
  val y: Float?,
)

/** Runs and shot count per region, rolled up from [Shot]s. */
data class ShotZone(val zone: Int, val shots: Int, val runs: Int)

/** Where the runs came from — counted per delivery, never inferred from a total. */
data class ScoringBreakdown(
  val dots: Int,
  val ones: Int,
  val twos: Int,
  val threes: Int,
  val fours: Int,
  val sixes: Int,
  val extras: Int,
)

/** One phase of an innings — Start / Middle / Finish, proportional to its length. */
data class InsightPhase(val label: String, val overs: Int, val runs: Int, val runRate: Double)

/** Everything derived from one innings' ball log. */
data class InningsInsight(
  val battingName: String,
  /** 1 = home/team1, 2 = away/team2 — drives which side's colour this innings draws in. */
  val battingTeam: Int,
  val runs: Int,
  val wickets: Int,
  val overs: String,
  val runRate: Double,
  val fours: Int,
  val sixes: Int,
  val boundaryPercent: Int,
  val dotPercent: Int,
  val phases: List<InsightPhase>,
  val bestOverNumber: Int,
  val bestOverRuns: Int,
  val bestStandRuns: Int,
  val bestStandBalls: Int,
  val progress: List<ProgressOver>,
  val changingOvers: List<ChangingOver>,
  val partnerships: List<Stand>,
  val faceoffs: List<FaceOff>,
  val breakdown: ScoringBreakdown,
  val shots: List<Shot>,
  val shotZones: List<ShotZone>,
)

/** The Insights payload: real figures, plus prose when there is any. */
data class MatchInsights(
  val innings: List<InningsInsight>,
  val balls: Int,
  /** Null whenever no analysis has been written. Normal, not an error. */
  val analysis: String?,
)

data class ScoreOutcome(
  val ok: Boolean,
  val body: String? = null,
  /** The server's own words when it REFUSED. Null when the request never landed at all. */
  val refusal: String? = null,
  /** Machine-readable reason, e.g. "profile_incomplete", when the server sent one. */
  val code: String = "",
)

/**
 * Talks to the ActionBoard match API. Mirrors [HaraanAuthRepository]'s plain
 * HttpURLConnection style, adding the JWT Bearer header for protected routes.
 */
/** One figure about a ground, already formatted by the server. */
data class GroundStat(val label: String, val value: String, val note: String? = null)

/**
 * A ground and what has happened there.
 *
 * [confidence] and [note] are the honesty controls, and they come from the server
 * because it is the only side that knows the sample size. Below five matches the
 * server sends no stats at all and a note saying so — the client must never fill that
 * silence with an average of two games.
 */
/** Two shares of one whole, each already a percentage of it. */
data class GroundSplit(
  val title: String,
  val leftLabel: String,
  val leftPercent: Int,
  val rightLabel: String,
  val rightPercent: Int,
)

data class GroundInsights(
    val name: String,
    val locality: String?,
    val district: String?,
    val mapUrl: String?,
    val matchesPlayed: Int,
    val confidence: String,
    val hasTrends: Boolean,
    val note: String?,
    val stats: List<GroundStat>,
    val split: GroundSplit? = null,
    /** Plain sentences over the same figures — computed, not written by a model. */
    val bullets: List<String> = emptyList(),
)

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
    /**
     * The sport's own format from the create wizard — `{kind, overs|halves+halfLengthMin|
     * bestOf+pointsTo+doubles}`. Stored in `sport_state.format` so a football or badminton
     * scorer reopens on the numbers the creator chose rather than a hardcoded default.
     */
    format: Map<String, Any> = emptyMap(),
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
    /**
     * Future kick-off as an ISO-8601 string, or null for "play now". When set, the
     * match is born Scheduled with a start time and skips the immediate toss — it
     * surfaces in the Scheduled tab until the creator starts it.
     */
    scheduledAtIso: String? = null,
    /** Open the match for nearby players to request to join, and how many are wanted. */
    openToJoin: Boolean = false,
    slotsNeeded: Int = 0,
  ): CreateMatchResult = withContext(Dispatchers.IO) {
    val body = JSONObject()
      .put("sport", sport.lowercase())
      .put("matchType", matchType)
      .put("isPrivate", isPrivate)
      .put("playersPerSide", playersPerSide)
      .put("venue", venue)
      .put("onHaraanTurf", onHaraanTurf)
      .put("teamA", teamA)
      .put("teamB", teamB)
      .put("squadA", squadJson(squadA))
      .put("squadB", squadJson(squadB))
    // Overs and ball are cricket's. Sending them on a football match is what made every
    // non-cricket match carry "20 overs · tennis ball" and title itself "20 Over Match".
    if (overs > 0) {
      body.put("overs", overs)
      body.put("ball", ball)
    }
    if (format.isNotEmpty()) body.put("format", JSONObject(format))
    // Optional area/village — omitted for private matches (they're hidden from feeds).
    if (locality.isNotBlank()) body.put("locality", locality)
    // The GPS fix. The server requires it on public matches; a private match may
    // legitimately have none, so only send what we actually hold.
    if (latitude != null && longitude != null) {
      body.put("latitude", latitude)
      body.put("longitude", longitude)
    }
    if (district.isNotBlank()) body.put("district", district)
    if (!scheduledAtIso.isNullOrBlank()) body.put("scheduledAt", scheduledAtIso)
    if (openToJoin) { body.put("openToJoin", true); body.put("slotsNeeded", slotsNeeded) }
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
          sport = o.optString("sport", "cricket").ifBlank { "cricket" },
          visibility = o.optString("visibility", "LOCAL"),
          district = o.optString("district", ""),
          locality = o.optString("locality", ""),
          venueBadgeName = o.optJSONObject("venueBadge")?.optString("name").orEmpty(),
          venueBadgeArea = o.optJSONObject("venueBadge")?.optString("area").orEmpty(),
          venueBadgeId = o.optJSONObject("venueBadge")?.optInt("venueId") ?: 0,
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
   * GET /api/matches/scheduled — the signed-in creator's not-yet-started matches
   * (future kick-offs + "play now" matches whose toss was skipped). Backs the
   * Scheduled tab. Returns an empty list on any failure so the tab shows its empty
   * state rather than an error.
   */
  suspend fun getScheduledMatches(token: String): List<ScheduledMatch> = withContext(Dispatchers.IO) {
    try {
      val body = ConditionalHttp.getText("${baseUrl.trimEnd('/')}/api/matches/scheduled", token)
        ?: return@withContext emptyList()
      val arr = JSONObject(body).optJSONArray("data") ?: return@withContext emptyList()
      (0 until arr.length()).map { i ->
        val o = arr.getJSONObject(i)
        ScheduledMatch(
          id = o.optString("id"),
          sport = o.optString("sport", "cricket").ifBlank { "cricket" },
          teamA = o.optString("teamA"),
          teamB = o.optString("teamB"),
          teamAEmblem = o.optString("teamAEmblem", ""),
          teamBEmblem = o.optString("teamBEmblem", ""),
          squadA = parseSquad(o.optJSONArray("squadA")),
          squadB = parseSquad(o.optJSONArray("squadB")),
          isPrivate = o.optBoolean("isPrivate", false),
          joinCode = o.optString("joinCode", ""),
          venue = o.optString("venue", ""),
          locality = o.optString("locality", ""),
          // JSONObject turns a JSON null into the literal "null" via optString; guard it.
          scheduledAtIso = o.optString("scheduledAt").takeIf { it.isNotBlank() && it != "null" },
        )
      }
    } catch (_: Exception) {
      emptyList()
    }
  }

  /**
   * GET /api/matches/open — open matches near the viewer looking for players. Guests
   * may browse (requesting needs auth). Ranked by distance server-side.
   */
  suspend fun getOpenMatches(
    token: String? = null,
    latitude: Double? = null,
    longitude: Double? = null,
    locality: String = "",
    district: String = "",
  ): List<OpenMatch> = withContext(Dispatchers.IO) {
    try {
      val params = buildList {
        if (latitude != null && longitude != null) { add("lat=$latitude"); add("lng=$longitude") }
        if (locality.isNotBlank()) add("locality=${java.net.URLEncoder.encode(locality, "UTF-8")}")
        if (district.isNotBlank()) add("district=${java.net.URLEncoder.encode(district, "UTF-8")}")
      }
      val query = if (params.isEmpty()) "" else "?" + params.joinToString("&")
      val body = ConditionalHttp.getText("${baseUrl.trimEnd('/')}/api/matches/open$query", token)
        ?: return@withContext emptyList()
      val arr = JSONObject(body).optJSONArray("data") ?: return@withContext emptyList()
      (0 until arr.length()).map { i ->
        val o = arr.getJSONObject(i)
        OpenMatch(
          id = o.optString("id"),
          sport = o.optString("sport", "cricket").ifBlank { "cricket" },
          team1 = o.optString("team1"),
          team2 = o.optString("team2"),
          team1Emblem = o.optString("team1Emblem", ""),
          team2Emblem = o.optString("team2Emblem", ""),
          venue = o.optString("venue", ""),
          locality = o.optString("locality", ""),
          competition = o.optString("competition", ""),
          slotsNeeded = o.optInt("slotsNeeded", 0),
          scheduledAtIso = o.optString("scheduledAt").takeIf { it.isNotBlank() && it != "null" },
          distanceKm = o.optDouble("distanceKm", Double.NaN).takeUnless { it.isNaN() },
          myStatus = o.optString("myStatus", "none").ifBlank { "none" },
        )
      }
    } catch (_: Exception) {
      emptyList()
    }
  }

  /** GET /api/matches/join-requests — pending requests to join the viewer's matches. */
  suspend fun getIncomingJoinRequests(token: String): List<IncomingJoinRequest> = withContext(Dispatchers.IO) {
    try {
      val body = ConditionalHttp.getText("${baseUrl.trimEnd('/')}/api/matches/join-requests", token)
        ?: return@withContext emptyList()
      val arr = JSONObject(body).optJSONArray("data") ?: return@withContext emptyList()
      (0 until arr.length()).map { i ->
        val o = arr.getJSONObject(i)
        IncomingJoinRequest(
          id = o.optString("id"),
          matchId = o.optString("matchId"),
          matchTitle = o.optString("matchTitle"),
          message = o.optString("message", ""),
          createdAtIso = o.optString("createdAt").takeIf { it.isNotBlank() && it != "null" },
          playerId = o.optString("playerId", ""),
          playerName = o.optString("playerName", "Player"),
          playerAvatar = o.optString("playerAvatar", ""),
          trustScore = o.optInt("trustScore", 0),
        )
      }
    } catch (_: Exception) {
      emptyList()
    }
  }

  /** POST /api/matches/{id}/join — request to join an open match. */
  suspend fun requestToJoin(token: String, matchId: String, message: String = ""): Boolean = withContext(Dispatchers.IO) {
    try {
      val body = JSONObject()
      if (message.isNotBlank()) body.put("message", message)
      postJson("/api/matches/$matchId/join", body, token).code in 200..299
    } catch (_: Exception) { false }
  }

  /** DELETE /api/matches/{id}/join — withdraw the viewer's own pending request. */
  suspend fun cancelJoinRequest(token: String, matchId: String): Boolean = withContext(Dispatchers.IO) {
    try {
      val conn = (URL("${baseUrl.trimEnd('/')}/api/matches/$matchId/join").openConnection() as HttpURLConnection).apply {
        requestMethod = "DELETE"
        connectTimeout = 15000; readTimeout = 15000
        setRequestProperty("Accept", "application/json")
        setRequestProperty("Authorization", "Bearer $token")
      }
      val ok = conn.responseCode in 200..299
      conn.disconnect()
      ok
    } catch (_: Exception) { false }
  }

  /** POST /api/matches/join-requests/{id}/respond — owner accepts (side) or declines. */
  suspend fun respondToJoinRequest(token: String, requestId: String, accept: Boolean, side: String? = null): Boolean = withContext(Dispatchers.IO) {
    try {
      val body = JSONObject().put("action", if (accept) "accept" else "decline")
      if (accept && side != null) body.put("side", side)
      postJson("/api/matches/join-requests/$requestId/respond", body, token).code in 200..299
    } catch (_: Exception) { false }
  }

  /** Parse a `[{id, name}, …]` squad array into [SquadMember]s (a null id → ""). */
  private fun parseSquad(arr: org.json.JSONArray?): List<SquadMember> {
    if (arr == null) return emptyList()
    return (0 until arr.length()).mapNotNull { i ->
      val o = arr.optJSONObject(i) ?: return@mapNotNull null
      val name = o.optString("name").takeIf { it.isNotBlank() && it != "null" } ?: return@mapNotNull null
      val id = o.optString("id").takeIf { it.isNotBlank() && it != "null" } ?: ""
      val avatar = (o.optString("photo").takeIf { it.isNotBlank() && it != "null" }
        ?: o.optString("avatar").takeIf { it.isNotBlank() && it != "null" }).orEmpty()
      SquadMember(
        id = id,
        name = name,
        avatar = avatar,
        isVerified = o.optBoolean("is_verified", false),
      )
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

  /**
   * POST /api/live-matches/{id}/watching — "I'm watching this match", answered with how
   * many people are on it right now.
   *
   * Presence, not a view counter: the server holds each viewer for a short window and the
   * app re-beats while the detail screen is open and foregrounded, so leaving needs no
   * call — the beat just stops. [viewerKey] is the random install id, which is all a
   * signed-out viewer is identified by. Pass [code] for a private match opened by share
   * code (its viewers never have the id).
   *
   * Returns null on any failure, so a caller keeps the last count on screen instead of
   * flashing a zero at a match a hundred people are watching.
   */
  suspend fun sendWatchHeartbeat(
    matchId: String,
    code: String = "",
    viewerKey: String,
    token: String? = null,
  ): WatchBeat? = withContext(Dispatchers.IO) {
    val clean = code.trim().uppercase()
    val path = when {
      clean.isNotEmpty() -> "/api/live-matches/code/$clean/watching"
      matchId.isNotBlank() -> "/api/live-matches/$matchId/watching"
      else -> return@withContext null
    }
    try {
      val result = postJson(path, JSONObject().put("viewer", viewerKey), token)
      if (result.code !in 200..299) return@withContext null
      val json = JSONObject(result.body)
      val count = json.optInt("watching", -1)
      if (count < 0) return@withContext null
      WatchBeat(count, json.optBoolean("canSeeViewers", false))
    } catch (_: Exception) {
      null
    }
  }

  /**
   * GET /api/live-matches/{id}/viewers — who is in the room right now.
   *
   * Verified accounts only; the server answers 403 for everybody else, which is why the
   * app never decides this for itself. Signed-in viewers come back as themselves, and the
   * rest as "Haraan Guest" rows the server has already stripped of anything identifying —
   * the app receives no install id, address or device for them, and so cannot leak one.
   *
   * Returns null on any failure, so the sheet says it couldn't load rather than claiming
   * an empty room.
   */
  suspend fun getMatchViewers(
    matchId: String,
    code: String = "",
    token: String? = null,
  ): List<MatchViewerItem>? = withContext(Dispatchers.IO) {
    val clean = code.trim().uppercase()
    val path = when {
      clean.isNotEmpty() -> "/api/live-matches/code/$clean/viewers"
      matchId.isNotBlank() -> "/api/live-matches/$matchId/viewers"
      else -> return@withContext null
    }
    try {
      val body = ConditionalHttp.getText("${baseUrl.trimEnd('/')}$path", token)
        ?: return@withContext null
      val arr = JSONObject(body).optJSONArray("viewers") ?: return@withContext emptyList()
      buildList {
        for (i in 0 until arr.length()) {
          val o = arr.optJSONObject(i) ?: continue
          add(
            MatchViewerItem(
              name = o.optString("name"),
              username = o.optString("username"),
              avatar = o.optString("avatar").takeIf { it.isNotBlank() && it != "null" }.orEmpty(),
              verified = o.optBoolean("is_verified", false),
              guest = o.optBoolean("is_guest", false),
              you = o.optBoolean("is_you", false),
            )
          )
        }
      }
    } catch (_: Exception) {
      null
    }
  }


  suspend fun sendScoreAction(
    token: String,
    matchId: String,
    action: JSONObject
  ): ScoreOutcome = withContext(Dispatchers.IO) {
    try {
      val response = postJson("/api/matches/$matchId/score-action", action, token)
      if (response.code in 200..299) {
        ScoreOutcome(ok = true, body = response.body)
      } else {
        ScoreOutcome(
          ok = false,
          refusal = parseErrorMessage(response.body, "Haraan wouldn't accept that ball."),
          code = runCatching { JSONObject(response.body).optString("code") }.getOrDefault(""),
        )
      }
    } catch (_: Exception) {
      // Never landed — no server opinion to report, so refusal stays null and the caller
      // falls back to a connection message, which is then actually true.
      ScoreOutcome(ok = false)
    }
  }

  /**
   * Record one point for a rally / points sport (volleyball, basketball, kabaddi, tennis,
   * table tennis).
   *
   * [detail] is what the point WAS — "3" for a three-pointer, "all_out" for a kabaddi all
   * out, blank for sports where every point is worth one. The server turns that into a
   * value; the phone never sends a score, so a double-tap or a retry can't inflate one.
   */
  suspend fun recordPoint(
    token: String,
    matchId: String,
    side: String,
    detail: String = "",
    player: String? = null,
  ): Boolean = withContext(Dispatchers.IO) {
    try {
      val body = JSONObject()
        .put("kind", "point")
        .put("side", side)
      if (detail.isNotBlank()) body.put("detail", detail)
      if (!player.isNullOrBlank()) body.put("player_name", player)
      postJson("/api/matches/$matchId/events", body, token).code in 200..299
    } catch (_: Exception) {
      false
    }
  }

  /**
   * Record a football / badminton event.
   *
   * Note what this does NOT do: send a score. The server derives the scoreline by
   * counting events, so a dropped call or a double-tap can never leave a scoreboard
   * that disagrees with its own timeline. Returns the server's settled score, or
   * null when the call failed — the caller should re-read rather than assume.
   */
  suspend fun recordMatchEvent(
    token: String,
    matchId: String,
    kind: String,
    side: String? = null,
    minute: Int? = null,
    playerName: String? = null,
    relatedName: String? = null,
    detail: String? = null,
  ): MatchScoreState? = withContext(Dispatchers.IO) {
    val body = JSONObject().put("kind", kind)
    side?.let { body.put("side", it) }
    minute?.let { body.put("minute", it) }
    playerName?.takeIf { it.isNotBlank() }?.let { body.put("player_name", it) }
    relatedName?.takeIf { it.isNotBlank() }?.let { body.put("related_name", it) }
    detail?.let { body.put("detail", it) }

    try {
      val response = postJson("/api/matches/$matchId/events", body, token)
      if (response.code in 200..299) parseScoreState(response.body) else null
    } catch (_: Exception) {
      null
    }
  }

  /**
   * Adjust a football match-stat tally (shots, corners, fouls…) by one. [inc] true
   * records the stat, false removes that side's most recent one. Stat events never
   * move the score. Returns true on success so the scorer can keep its optimistic
   * count, else fall back / retry.
   */
  suspend fun adjustStat(
    token: String,
    matchId: String,
    kind: String,
    side: String,
    inc: Boolean,
  ): Boolean = withContext(Dispatchers.IO) {
    val body = JSONObject()
      .put("kind", kind)
      .put("side", side)
      .put("op", if (inc) "inc" else "dec")
    try {
      val response = postJson("/api/matches/$matchId/stat", body, token)
      response.code in 200..299
    } catch (_: Exception) {
      false
    }
  }

  /**
   * Undo the last event. With a [side], undoes that team's last goal — the "−"
   * beside its tally must not delete the other team's goal just because it was
   * recorded most recently.
   */
  suspend fun undoMatchEvent(
    token: String,
    matchId: String,
    side: String? = null,
  ): MatchScoreState? = withContext(Dispatchers.IO) {
    val body = JSONObject()
    side?.let { body.put("side", it) }

    try {
      val response = postJson("/api/matches/$matchId/events/undo", body, token)
      if (response.code in 200..299) parseScoreState(response.body) else null
    } catch (_: Exception) {
      null
    }
  }

  /**
   * Full time. Freezes stats and opens the verification window — the same endpoint
   * cricket completes through, so a football match earns XP and verification on the
   * identical path rather than a parallel one.
   */
  suspend fun completeMatch(token: String, matchId: String): Boolean = withContext(Dispatchers.IO) {
    try {
      postJson("/api/matches/$matchId/complete", JSONObject(), token).code in 200..299
    } catch (_: Exception) {
      false
    }
  }

  /** Merge into the per-sport score shape (football clock/half, badminton games). */
  suspend fun updateSportState(
    token: String,
    matchId: String,
    state: JSONObject,
  ): Boolean = withContext(Dispatchers.IO) {
    try {
      postJson("/api/matches/$matchId/sport-state", JSONObject().put("state", state), token)
        .code in 200..299
    } catch (_: Exception) {
      false
    }
  }

  private fun parseScoreState(body: String): MatchScoreState? = try {
    val json = JSONObject(body)
    MatchScoreState(
      home = json.optInt("home_score", 0),
      away = json.optInt("away_score", 0),
      scoreText = json.optString("score_text", null)?.takeIf { it.isNotBlank() && it != "null" },
    )
  } catch (_: Exception) {
    null
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

  /**
   * [token] is optional: the public endpoints (presence) accept a guest, and sending no
   * Authorization header at all is safer than sending one the server will reject.
   */
  /**
   * Match insights: the derived figures, and the written read when the server has one.
   *
   * [MatchInsights.analysis] is null far more often than not — no model configured, the
   * call failed, or under an over has been bowled — and that is a normal state, not an
   * error. The FIGURES are always there, and they are the part that must never be wrong.
   */
  /** JSONArray -> list, skipping anything that is not an object. Null array -> empty. */
  private inline fun <T> org.json.JSONArray?.mapObjects(build: (JSONObject) -> T): List<T> {
    if (this == null) return emptyList()
    val out = ArrayList<T>(length())
    for (i in 0 until length()) {
      optJSONObject(i)?.let { out.add(build(it)) }
    }
    return out
  }

  suspend fun fetchInsights(matchId: String): MatchInsights? = withContext(Dispatchers.IO) {
    try {
      val connection = (URL("${baseUrl.trimEnd('/')}/api/live-matches/$matchId/insights")
        .openConnection() as HttpURLConnection).apply {
        requestMethod = "GET"
        connectTimeout = 15000
        // Longer than most reads: a FINISHED match writes its analysis inline on the first
        // request, and that call has to reach a model and come back.
        readTimeout = 40000
        setRequestProperty("Accept", "application/json")
      }
      val code = connection.responseCode
      val body = (if (code >= 400) connection.errorStream else connection.inputStream)
        ?.let { BufferedReader(InputStreamReader(it)).use { r -> r.readText() } }.orEmpty()
      connection.disconnect()
      if (code !in 200..299) return@withContext null

      val o = JSONObject(body)
      val innings = mutableListOf<InningsInsight>()
      val arr = o.optJSONArray("innings")
      for (i in 0 until (arr?.length() ?: 0)) {
        val n = arr!!.optJSONObject(i) ?: continue
        val phases = mutableListOf<InsightPhase>()
        val pa = n.optJSONArray("phases")
        for (j in 0 until (pa?.length() ?: 0)) {
          val ph = pa!!.optJSONObject(j) ?: continue
          phases.add(
            InsightPhase(
              label = ph.optString("label"),
              overs = ph.optInt("overs"),
              runs = ph.optInt("runs"),
              runRate = ph.optDouble("runRate", 0.0),
            )
          )
        }
        val bo = n.optJSONObject("bestOver")
        val bp = n.optJSONObject("bestPartnership")
        innings.add(
          InningsInsight(
            battingName = n.optString("battingName"),
            battingTeam = n.optInt("battingTeam", 1),
            runs = n.optInt("runs"),
            wickets = n.optInt("wickets"),
            overs = n.optString("overs"),
            runRate = n.optDouble("runRate", 0.0),
            fours = n.optInt("fours"),
            sixes = n.optInt("sixes"),
            boundaryPercent = n.optInt("boundaryPercent"),
            dotPercent = n.optInt("dotPercent"),
            phases = phases,
            bestOverNumber = bo?.optInt("over") ?: 0,
            bestOverRuns = bo?.optInt("runs") ?: 0,
            bestStandRuns = bp?.optInt("runs") ?: 0,
            bestStandBalls = bp?.optInt("balls") ?: 0,
            progress = n.optJSONArray("progress").mapObjects { o ->
              ProgressOver(
                over = o.optInt("over"),
                runs = o.optInt("runs"),
                wickets = o.optInt("wickets"),
                total = o.optInt("total"),
                totalWickets = o.optInt("totalWickets"),
                balls = (o.optJSONArray("balls")).let { ba ->
                  if (ba == null) emptyList()
                  else (0 until ba.length()).map { ba.optString(it) }.filter { it.isNotBlank() }
                },
              )
            },
            changingOvers = n.optJSONArray("changingOvers").mapObjects { o ->
              ChangingOver(
                over = o.optInt("over"),
                runs = o.optInt("runs"),
                wickets = o.optInt("wickets"),
                swing = o.optInt("swing"),
              )
            },
            partnerships = n.optJSONArray("partnerships").mapObjects { o ->
              Stand(
                wicket = o.optInt("wicket"),
                runs = o.optInt("runs"),
                balls = o.optInt("balls"),
                batters = o.optString("batters"),
                unbroken = o.optBoolean("unbroken"),
              )
            },
            faceoffs = n.optJSONArray("faceoffs").mapObjects { o ->
              FaceOff(
                bowler = o.optString("bowler"),
                batter = o.optString("batter"),
                balls = o.optInt("balls"),
                runs = o.optInt("runs"),
                wickets = o.optInt("wickets"),
                strikeRate = o.optDouble("strikeRate", 0.0),
              )
            },
            shots = n.optJSONArray("shots").mapObjects { o ->
              Shot(
                zone = o.optInt("zone"),
                runs = o.optInt("runs"),
                batter = o.optString("batter"),
                over = o.optInt("over"),
                x = if (o.isNull("x")) null else o.optDouble("x").toFloat(),
                y = if (o.isNull("y")) null else o.optDouble("y").toFloat(),
              )
            },
            shotZones = n.optJSONArray("shotZones").mapObjects { o ->
              ShotZone(zone = o.optInt("zone"), shots = o.optInt("shots"), runs = o.optInt("runs"))
            },
            breakdown = (n.optJSONObject("breakdown") ?: JSONObject()).let { b ->
              ScoringBreakdown(
                dots = b.optInt("dots"),
                ones = b.optInt("ones"),
                twos = b.optInt("twos"),
                threes = b.optInt("threes"),
                fours = b.optInt("fours"),
                sixes = b.optInt("sixes"),
                extras = b.optInt("extras"),
              )
            },
          )
        )
      }

      MatchInsights(
        innings = innings,
        balls = o.optInt("balls"),
        analysis = o.optString("analysis").takeIf { it.isNotBlank() && it != "null" },
      )
    } catch (_: Exception) {
      null
    }
  }

  /** The ground this match is at, or null when the match names no venue. */
  suspend fun fetchGround(matchId: String): GroundInsights? = withContext(Dispatchers.IO) {
    try {
      val connection = (URL(baseUrl.trimEnd('/') + "/api/matches/$matchId/ground").openConnection()
        as HttpURLConnection).apply {
        requestMethod = "GET"
        connectTimeout = 15000
        readTimeout = 15000
        setRequestProperty("Accept", "application/json")
      }
      val code = connection.responseCode
      val body = readBody(connection)
      connection.disconnect()
      if (code !in 200..299) return@withContext null

      val data = JSONObject(body).optJSONObject("data") ?: return@withContext null
      val stats = mutableListOf<GroundStat>()
      data.optJSONArray("stats")?.let { arr ->
        for (i in 0 until arr.length()) {
          val o = arr.optJSONObject(i) ?: continue
          stats += GroundStat(
            label = o.optString("label"),
            value = o.optString("value"),
            note = o.optString("note").takeIf { it.isNotBlank() && it != "null" },
          )
        }
      }
      GroundInsights(
        name = data.optString("name"),
        locality = data.optString("locality").takeIf { it.isNotBlank() && it != "null" },
        district = data.optString("district").takeIf { it.isNotBlank() && it != "null" },
        mapUrl = data.optString("mapUrl").takeIf { it.isNotBlank() && it != "null" },
        matchesPlayed = data.optInt("matchesPlayed", 0),
        confidence = data.optString("confidence", "building"),
        hasTrends = data.optBoolean("hasTrends", false),
        note = data.optString("note").takeIf { it.isNotBlank() && it != "null" },
        stats = stats,
        split = data.optJSONObject("split")?.let { sp ->
          GroundSplit(
            title = sp.optString("title"),
            leftLabel = sp.optString("leftLabel"),
            leftPercent = sp.optInt("leftPercent"),
            rightLabel = sp.optString("rightLabel"),
            rightPercent = sp.optInt("rightPercent"),
          )
        },
        bullets = data.optJSONArray("bullets")?.let { arr ->
          (0 until arr.length()).mapNotNull { arr.optString(it).takeIf { l -> l.isNotBlank() } }
        } ?: emptyList(),
      )
    } catch (_: Exception) {
      null
    }
  }

  private fun postJson(path: String, jsonBody: JSONObject, token: String?): HttpResult {
    val connection = (URL(baseUrl.trimEnd('/') + path).openConnection() as HttpURLConnection).apply {
      requestMethod = "POST"
      doOutput = true
      connectTimeout = 15000
      readTimeout = 15000
      setRequestProperty("Content-Type", "application/json")
      setRequestProperty("Accept", "application/json")
      if (!token.isNullOrBlank()) setRequestProperty("Authorization", "Bearer $token")
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
