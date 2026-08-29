package com.haraan.app.data

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONObject
import java.io.BufferedReader
import java.io.InputStreamReader
import java.net.HttpURLConnection
import java.net.URL

/**
 * The scoreboard of a match, in the shape the ActionBoard card already reads — same
 * field names, same attribution, built by the same rules on the server. The profile
 * can therefore draw a player's history with the feed's own card instead of a line
 * of text, and the two can never drift apart.
 */
data class MatchCard(
  val team1: String,
  val team2: String,
  val team1Logo: String,
  val team2Logo: String,
  val team1Emblem: String,
  val team2Emblem: String,
  val score1: String,
  val score2: String,
  val overs1: String,
  val overs2: String,
  val battingTeam: Int,
  val sport: String,
  val isLive: Boolean,
  val competition: String,
  val venue: String,
  val district: String,
  val locality: String,
)

data class RecentMatch(
  val matchId: Long,
  val title: String,
  val matchType: String,
  val xp: Int,
  val trustLevel: String,
  val isRanked: Boolean,
  val won: Boolean,
  val mom: Boolean,
  val awardedAt: String,
  /** Null only for a server too old to send it; the row then reads as plain text. */
  val card: MatchCard? = null,
)

data class AchievementDto(
  val key: String,
  val icon: String,
  val label: String,
  val tier: String,
  val unlocked: Boolean,
  val progress: String?,
)

/** Outcome of a live username availability check. */
sealed class UsernameCheck {
  object Available : UsernameCheck()
  /** Unusable, with the server's specific reason (too short, reserved, taken…). */
  data class Rejected(val reason: String) : UsernameCheck()
  /** Couldn't reach the server — say nothing rather than claim it's taken. */
  object Unknown : UsernameCheck()
}

/**
 * One career figure — a real number with the word the sport actually uses for it.
 *
 * Modelled as a list rather than fixed fields because sports genuinely have
 * different numbers of them: cricket has three, football three, badminton one.
 * A sport with no per-player record contributes FEWER cells rather than a zero
 * standing in for a stat nobody tracks.
 */
data class CareerStat(val label: String, val value: Int)

data class SportCareer(val sport: String, val stats: List<CareerStat>)

/**
 * THE CAREER BOOK — the full record, one entry per sport the player has played.
 *
 * [SportCareer] above is three totals; this is the batting and bowling line a
 * cricketer expects to see about themselves. Values arrive as formatted STRINGS
 * because the numbers genuinely are not all integers: an average reads "-" until
 * the player has been out, best bowling reads "4/23", milestones read "3 / 0".
 * Formatting them here would mean re-deriving rules the server already applied.
 */
data class CareerFigure(val label: String, val value: String)

/**
 * A drawn view of figures the group already reports.
 *
 * [kind] is "split" (a stacked bar of parts that sum to the whole) or "meter" (one
 * value on a fixed scale). Deliberately server-chosen: which figure is worth drawing
 * is a question about the SPORT, and the client should not be holding that opinion.
 */
data class CareerSegment(val label: String, val value: Float)

data class CareerVisual(
    val kind: String,
    val title: String,
    val caption: String?,
    val segments: List<CareerSegment> = emptyList(),
    val value: Float = 0f,
    val max: Float = 0f,
)

/** One discipline — Batting, Bowling, Attacking — with the figure that leads it. */
data class CareerGroup(
    val title: String,
    val leadLabel: String,
    val leadValue: String,
    val stats: List<CareerFigure>,
    val visual: CareerVisual? = null,
)

/**
 * One region of the ground and what the player has scored into it. Only boundaries the
 * scorer actually placed are here — the wheel is a record of real shots, so a region
 * with no dots means nobody plotted one, not that the player never hit there.
 */
data class WagonZone(
    val zone: Int,
    val label: String,
    val shots: Int,
    val fours: Int,
    val sixes: Int,
    val runs: Int,
)

data class WagonWheel(
    val title: String,
    val total: Int,
    val shots: Int,
    val zones: List<WagonZone>,
    val caption: String?,
)

/**
 * Three sentences about the figures on this page, written by a model that was handed
 * those figures and forbidden to produce any of its own. [source] is shown verbatim:
 * a reader is entitled to know which lines were written rather than counted.
 */
data class CareerAnalysis(val title: String, val lines: List<String>, val source: String)

data class SportRecord(
    val key: String,
    val label: String,
    val matches: Int,
    /** The three numbers that lead the sport, before any discipline is opened. */
    val headline: List<CareerFigure>,
    val groups: List<CareerGroup>,
    /**
     * Why this sport has no groups, in the server's own words. It knows the reason —
     * never played, never ball-by-ball scored, or a sport that only scores per side —
     * and the app has no way to tell those apart from an empty list.
     */
    val note: String? = null,
    /** Cricket only, and only once a boundary has actually been placed. */
    val wagon: WagonWheel? = null,
    /** Null until a read has been written for the player's current figures. */
    val analysis: CareerAnalysis? = null,
)

/** [primary] is the player's own sport, and always the first entry in [sports]. */
data class CareerBook(val primary: String, val sports: List<SportRecord>)

/**
 * The follow graph as it applies to the viewer looking at this profile.
 *
 * [canFollow] is deliberately separate from [isFollowing]: a signed-out visitor and a
 * player looking at their own profile both have `isFollowing = false`, but neither
 * should be offered a Follow button, and for opposite reasons.
 */
/**
 * How complete this profile is, measured against the player's OWN sport.
 *
 * [missing] carries KEYS, not sentences — the app owns the wording. The app used to
 * work this out itself against cricket's fields, which is why a footballer was asked
 * for a batting style and could never reach 100%.
 */
data class ProfileCompletion(val pct: Int, val missing: List<String>)

data class SocialState(
  val followersCount: Int,
  val followingCount: Int,
  val isFollowing: Boolean,
  /** Do THEY follow ME — the other half of the mutual test that gates messaging. */
  val followsMe: Boolean,
  val isSelf: Boolean,
  val canFollow: Boolean,
  /**
   * Have I blocked them. Only ever MY block — the server never reports being blocked
   * BY someone, because telling you a block exists hands you the thing it withholds.
   */
  val isBlocked: Boolean = false,
)

data class PlayerProfile(
  val id: Int,
  val playerId: String,
  val username: String?,
  val name: String,
  val bio: String?,
  val avatar: String?,
  val district: String?,
  val state: String?,
  val isOrganizer: Boolean,
  /**
   * Admin-granted blue tick (`/control` → People → Verified). Read-only here: nothing in
   * the app can set it, which is what keeps the badge worth anything.
   */
  val isVerified: Boolean = false,
  val rankedXp: Int,
  val casualXp: Int,
  val trustScore: Int,
  val monthRankedXp: Int,
  val rankDistrict: Int?,
  val rankState: Int?,
  val rankCountry: Int?,
  val careerMatches: Int,
  val careerRuns: Int,
  val careerWickets: Int,
  /**
   * Career in the player's OWN sport. The three fields above are cricket's — a
   * footballer's profile was advertising runs and wickets that could only ever
   * read zero. Null only for a server too old to send the block.
   */
  val sportCareer: SportCareer? = null,
  /**
   * The full per-sport record behind the Stats tab. Null only for a server too old
   * to send it — the profile then falls back to [sportCareer]'s three totals.
   */
  val careerBook: CareerBook? = null,
  /**
   * Follower counts + whether the viewer already follows this player. Null only for a
   * server too old to send the block — the follow UI hides rather than guessing.
   */
  val social: SocialState? = null,
  /** Null only for a server too old to send it; the app then falls back to its own count. */
  val completion: ProfileCompletion? = null,
  val profileComplete: Boolean,
  val recentMatches: List<RecentMatch>,
  val achievements: List<AchievementDto> = emptyList(),
  // Crex-style "About" details (any may be null if not filled in yet).
  val playerRole: String? = null,
  val battingStyle: String? = null,
  val bowlingStyle: String? = null,
  val gender: String? = null,
  val dateOfBirth: String? = null,
  val birthPlace: String? = null,
  val height: String? = null,
  val nationality: String? = null,
)

/**
 * Thrown when the profile API answers with a non-2xx status. Carries the HTTP
 * [code] so callers can tell "your session is gone" (401/403) apart from "the
 * server is unhappy" — a distinction the ranked-access gate depends on. Still an
 * IllegalStateException so existing catch sites keep working unchanged.
 */
class ProfileHttpException(val code: Int, message: String) : IllegalStateException(message)

/**
 * Answer to "can this user do a ranked action?" — deliberately four-valued.
 *
 * The important one is [Unavailable]: a timeout or a 500 is NOT the same thing as
 * "this player has no profile", and must never route a fully set-up player into
 * the profile-setup wizard.
 */
sealed class ProfileStatus {
  /** Signed in and the ActionBoard profile is complete — let them through. */
  object Complete : ProfileStatus()

  /** Signed in, but the profile still needs setting up. */
  object Incomplete : ProfileStatus()

  /** No real session (signed out, or browsing as a guest) — needs login first. */
  object NeedsLogin : ProfileStatus()

  /** We could not find out. Show [message]; change nothing. */
  data class Unavailable(val message: String) : ProfileStatus()
}

/**
 * Fetches the logged-in player's ActionBoard profile. Same HttpURLConnection +
 * JWT style as [MatchRepository] / [PlayerRepository].
 */
class ProfileRepository(
  private val baseUrl: String = ApiConfig.BASE_URL,
) {
  suspend fun fetchMe(token: String): PlayerProfile = withContext(Dispatchers.IO) {
    val connection = (URL("${baseUrl.trimEnd('/')}/api/players/me").openConnection() as HttpURLConnection).apply {
      requestMethod = "GET"
      connectTimeout = 15000
      readTimeout = 15000
      setRequestProperty("Accept", "application/json")
      setRequestProperty("Authorization", "Bearer $token")
    }

    try {
      val code = connection.responseCode
      val stream = if (code >= 400) connection.errorStream else connection.inputStream
      val body = stream?.let { BufferedReader(InputStreamReader(it)).use { r -> r.readText() } } ?: ""
      if (code !in 200..299) {
        throw ProfileHttpException(code, parseError(body))
      }
      parseProfile(JSONObject(body))
    } finally {
      connection.disconnect()
    }
  }

  /**
   * Fetches ANY player's public ActionBoard profile by their Player ID (HRN…).
   * Same payload shape as [fetchMe], so the leaderboard can open the real profile
   * screen instead of a fabricated one. Token is optional (public endpoint) but
   * forwarded when present.
   */
  suspend fun fetchPlayer(token: String?, playerId: String): PlayerProfile = withContext(Dispatchers.IO) {
    val encoded = java.net.URLEncoder.encode(playerId, "UTF-8")
    val connection = (URL("${baseUrl.trimEnd('/')}/api/players/$encoded").openConnection() as HttpURLConnection).apply {
      requestMethod = "GET"
      connectTimeout = 15000
      readTimeout = 15000
      setRequestProperty("Accept", "application/json")
      if (!token.isNullOrBlank()) setRequestProperty("Authorization", "Bearer $token")
    }

    try {
      val code = connection.responseCode
      val stream = if (code >= 400) connection.errorStream else connection.inputStream
      val body = stream?.let { BufferedReader(InputStreamReader(it)).use { r -> r.readText() } } ?: ""
      if (code !in 200..299) {
        throw ProfileHttpException(code, parseError(body))
      }
      parseProfile(JSONObject(body))
    } finally {
      connection.disconnect()
    }
  }

  /**
   * Turns the server's semantic career block into ordered, labelled cells.
   *
   * The server sends facts (`goals`, `wickets`); the wording lives here so the app
   * controls how a stat reads without a deploy. A key the server omitted is a stat
   * that sport does not record — it is skipped, never rendered as a zero.
   */
  private fun parseSportCareer(json: JSONObject?): SportCareer? {
    if (json == null) return null
    val sport = json.optString("sport", "cricket").ifBlank { "cricket" }

    val order = when (sport.lowercase()) {
      "football" -> listOf("matches" to "Matches", "goals" to "Goals", "assists" to "Assists")
      "badminton" -> listOf("matches" to "Matches")
      else -> listOf("matches" to "Matches", "runs" to "Runs", "wickets" to "Wickets")
    }

    val stats = order.mapNotNull { (key, label) ->
      if (json.has(key)) CareerStat(label, json.optInt(key, 0)) else null
    }
    return SportCareer(sport = sport.lowercase(), stats = stats)
  }

  private fun parseCareerBook(json: JSONObject?): CareerBook? {
    if (json == null) return null
    val sportsJson = json.optJSONArray("sports") ?: return null
    val sports = mutableListOf<SportRecord>()
    for (i in 0 until sportsJson.length()) {
      val s = sportsJson.optJSONObject(i) ?: continue
      val groups = mutableListOf<CareerGroup>()
      val groupsJson = s.optJSONArray("groups")
      if (groupsJson != null) {
        for (g in 0 until groupsJson.length()) {
          val group = groupsJson.optJSONObject(g) ?: continue
          val lead = group.optJSONObject("lead")
          groups += CareerGroup(
            title = group.optString("title"),
            leadLabel = lead?.optString("label").orEmpty(),
            leadValue = lead?.optString("value").orEmpty(),
            stats = parseFigures(group.optJSONArray("stats")),
            visual = parseVisual(group.optJSONObject("visual")),
          )
        }
      }
      sports += SportRecord(
        key = s.optString("key", "cricket"),
        label = s.optString("label", "Cricket"),
        matches = s.optInt("matches", 0),
        headline = parseFigures(s.optJSONArray("headline")),
        groups = groups,
        note = s.optString("note").takeIf { it.isNotBlank() && it != "null" },
        wagon = parseWagon(s.optJSONObject("wagon")),
        analysis = parseAnalysis(s.optJSONObject("analysis")),
      )
    }
    if (sports.isEmpty()) return null
    return CareerBook(primary = json.optString("primary", "cricket"), sports = sports)
  }

  private fun parseWagon(json: JSONObject?): WagonWheel? {
    if (json == null) return null
    val arr = json.optJSONArray("zones") ?: return null
    val zones = mutableListOf<WagonZone>()
    for (i in 0 until arr.length()) {
      val z = arr.optJSONObject(i) ?: continue
      val runs = z.optInt("runs", 0)
      if (runs <= 0) continue
      zones += WagonZone(
        zone = z.optInt("zone", 0),
        label = z.optString("label"),
        shots = z.optInt("shots", 0),
        fours = z.optInt("fours", 0),
        sixes = z.optInt("sixes", 0),
        runs = runs,
      )
    }
    if (zones.isEmpty()) return null
    return WagonWheel(
      title = json.optString("title", "Where the runs go"),
      total = json.optInt("total", zones.sumOf { it.runs }),
      shots = json.optInt("shots", zones.sumOf { it.shots }),
      zones = zones,
      caption = json.optString("caption").takeIf { it.isNotBlank() && it != "null" },
    )
  }

  private fun parseAnalysis(json: JSONObject?): CareerAnalysis? {
    if (json == null) return null
    val arr = json.optJSONArray("lines") ?: return null
    val lines = mutableListOf<String>()
    for (i in 0 until arr.length()) {
      arr.optString(i).takeIf { it.isNotBlank() }?.let { lines += it }
    }
    if (lines.isEmpty()) return null
    return CareerAnalysis(
      title = json.optString("title", "The read on your game"),
      lines = lines,
      source = json.optString("source"),
    )
  }

  private fun parseVisual(json: JSONObject?): CareerVisual? {
    if (json == null) return null
    val kind = json.optString("kind")
    if (kind.isBlank()) return null
    val segments = mutableListOf<CareerSegment>()
    val arr = json.optJSONArray("segments")
    if (arr != null) {
      for (i in 0 until arr.length()) {
        val o = arr.optJSONObject(i) ?: continue
        val value = o.optDouble("value", 0.0).toFloat()
        // A zero part is not drawable and reads as a gap in the bar, so it is
        // dropped rather than rendered as a sliver of nothing.
        if (value > 0f) segments += CareerSegment(o.optString("label"), value)
      }
    }
    if (kind == "split" && segments.isEmpty()) return null
    return CareerVisual(
      kind = kind,
      title = json.optString("title"),
      caption = json.optString("caption").takeIf { it.isNotBlank() && it != "null" },
      segments = segments,
      value = json.optDouble("value", 0.0).toFloat(),
      max = json.optDouble("max", 0.0).toFloat(),
    )
  }

  private fun parseFigures(arr: org.json.JSONArray?): List<CareerFigure> {
    if (arr == null) return emptyList()
    val out = mutableListOf<CareerFigure>()
    for (i in 0 until arr.length()) {
      val o = arr.optJSONObject(i) ?: continue
      val label = o.optString("label")
      if (label.isNotBlank()) out += CareerFigure(label, o.optString("value", "-"))
    }
    return out
  }

  private fun parseProfile(json: JSONObject): PlayerProfile {
    val recent = mutableListOf<RecentMatch>()
    val arr = json.optJSONArray("recent_matches")
    if (arr != null) {
      for (i in 0 until arr.length()) {
        val o = arr.getJSONObject(i)
        recent.add(
          RecentMatch(
            card = o.optJSONObject("card")?.let { c ->
              MatchCard(
                team1 = c.optString("team1"),
                team2 = c.optString("team2"),
                team1Logo = c.optString("team1Logo"),
                team2Logo = c.optString("team2Logo"),
                team1Emblem = c.optString("team1Emblem"),
                team2Emblem = c.optString("team2Emblem"),
                score1 = c.optString("score1"),
                score2 = c.optString("score2"),
                overs1 = c.optString("overs1"),
                overs2 = c.optString("overs2"),
                battingTeam = c.optInt("battingTeam", 1),
                sport = c.optString("sport", "cricket"),
                isLive = c.optBoolean("isLive"),
                competition = c.optString("competition"),
                venue = c.optString("venue"),
                district = c.optString("district"),
                locality = c.optString("locality"),
              )
            },
            matchId = o.optLong("match_id", 0L),
            title = o.optString("title", ""),
            matchType = o.optString("match_type", ""),
            xp = o.optInt("xp", 0),
            trustLevel = o.optString("trust_level", "low"),
            isRanked = o.optBoolean("is_ranked", false),
            won = o.optBoolean("won", false),
            mom = o.optBoolean("mom", false),
            awardedAt = o.optString("awarded_at", ""),
          )
        )
      }
    }
    val achievements = mutableListOf<AchievementDto>()
    json.optJSONArray("achievements")?.let { aa ->
      for (i in 0 until aa.length()) {
        val o = aa.getJSONObject(i)
        achievements.add(
          AchievementDto(
            key = o.optString("key", ""),
            icon = o.optString("icon", ""),
            label = o.optString("label", ""),
            tier = o.optString("tier", "bronze"),
            unlocked = o.optBoolean("unlocked", false),
            progress = o.optString("progress", null).cleanNull(),
          )
        )
      }
    }
    return PlayerProfile(
      id = json.optInt("id", 0),
      playerId = json.optString("player_id", ""),
      username = json.optString("username", null).cleanNull(),
      name = json.optString("name", ""),
      bio = json.optString("bio", null).cleanNull(),
      avatar = json.optString("avatar", null).cleanNull(),
      district = json.optString("district", null).cleanNull(),
      state = json.optString("state", null).cleanNull(),
      isOrganizer = json.optBoolean("is_organizer", false),
      isVerified = json.optBoolean("is_verified", false),
      rankedXp = json.optInt("ranked_xp", 0),
      casualXp = json.optInt("casual_xp", 0),
      trustScore = json.optInt("trust_score", 100),
      monthRankedXp = json.optInt("month_ranked_xp", 0),
      rankDistrict = json.optIntOrNull("rank_district"),
      rankState = json.optIntOrNull("rank_state"),
      rankCountry = json.optIntOrNull("rank_country"),
      careerMatches = json.optJSONObject("career")?.optInt("matches", 0) ?: 0,
      careerRuns = json.optJSONObject("career")?.optInt("runs", 0) ?: 0,
      careerWickets = json.optJSONObject("career")?.optInt("wickets", 0) ?: 0,
      sportCareer = parseSportCareer(json.optJSONObject("sport_career")),
      careerBook = parseCareerBook(json.optJSONObject("career_book")),
      completion = json.optJSONObject("profile_completion")?.let { c ->
        val arr = c.optJSONArray("missing")
        ProfileCompletion(
          pct = c.optInt("pct", 0),
          missing = buildList { if (arr != null) for (i in 0 until arr.length()) add(arr.optString(i)) },
        )
      },
      social = json.optJSONObject("social")?.let { s ->
        SocialState(
          followersCount = s.optInt("followers_count", 0),
          followingCount = s.optInt("following_count", 0),
          isFollowing = s.optBoolean("is_following", false),
          followsMe = s.optBoolean("follows_me", false),
          isSelf = s.optBoolean("is_self", false),
          canFollow = s.optBoolean("can_follow", false),
          isBlocked = s.optBoolean("is_blocked", false),
        )
      },
      profileComplete = json.optBoolean("profile_complete", false),
      recentMatches = recent,
      achievements = achievements,
      playerRole = json.optString("player_role", null).cleanNull(),
      battingStyle = json.optString("batting_style", null).cleanNull(),
      bowlingStyle = json.optString("bowling_style", null).cleanNull(),
      gender = json.optJSONObject("about")?.optString("gender", null).cleanNull(),
      dateOfBirth = json.optJSONObject("about")?.optString("date_of_birth", null).cleanNull(),
      birthPlace = json.optJSONObject("about")?.optString("birth_place", null).cleanNull(),
      height = json.optJSONObject("about")?.optString("height", null).cleanNull(),
      nationality = json.optJSONObject("about")?.optString("nationality", null).cleanNull(),
    )
  }

  /**
   * Gate check for ranked actions (create a match, open your player profile…).
   *
   * Never collapses to a bare boolean: the old `isProfileComplete` returned false
   * on ANY failure, so a guest token, an expired session or a five-second timeout
   * all looked exactly like "this player has no profile" — and dumped a fully
   * set-up player into the setup wizard every time they tapped Create.
   */
  suspend fun profileStatus(token: String?): ProfileStatus = withContext(Dispatchers.IO) {
    if (!TokenStore.isSignedIn(token)) return@withContext ProfileStatus.NeedsLogin
    try {
      if (fetchMe(token!!).profileComplete) ProfileStatus.Complete else ProfileStatus.Incomplete
    } catch (e: ProfileHttpException) {
      // The session is gone (or was never real) → send them to login, not setup.
      if (e.code == 401 || e.code == 403) ProfileStatus.NeedsLogin
      else ProfileStatus.Unavailable(e.message ?: "Couldn't check your player profile.")
    } catch (_: Exception) {
      ProfileStatus.Unavailable("Couldn't reach Haraan. Check your connection and try again.")
    }
  }

  /**
   * Lightweight boolean view of [profileStatus] for callers that only care whether
   * the player is good to go. Anything other than a confirmed complete profile is
   * false, so do NOT use this to decide whether to show the setup wizard.
   */
  suspend fun isProfileComplete(token: String): Boolean =
    profileStatus(token) is ProfileStatus.Complete

  /**
   * Is this handle free? Called as the player types, so failures are quiet: a dropped
   * request returns [UsernameCheck.Unknown] and the field says nothing, rather than
   * accusing a perfectly good handle of being taken. The save endpoint re-checks and is
   * the real guarantee — two people can claim the same handle between keystrokes.
   */
  suspend fun checkUsername(token: String, username: String): UsernameCheck = withContext(Dispatchers.IO) {
    val candidate = username.trim()
    if (candidate.isEmpty()) return@withContext UsernameCheck.Unknown

    val encoded = java.net.URLEncoder.encode(candidate, "UTF-8")
    val connection = (URL("${baseUrl.trimEnd('/')}/api/players/username-available?username=$encoded")
      .openConnection() as HttpURLConnection).apply {
      requestMethod = "GET"
      connectTimeout = 10000
      readTimeout = 10000
      setRequestProperty("Accept", "application/json")
      setRequestProperty("Authorization", "Bearer $token")
    }

    try {
      val code = connection.responseCode
      if (code !in 200..299) return@withContext UsernameCheck.Unknown
      val body = connection.inputStream?.let {
        BufferedReader(InputStreamReader(it)).use { r -> r.readText() }
      } ?: return@withContext UsernameCheck.Unknown
      val json = JSONObject(body)
      if (json.optBoolean("available", false)) {
        UsernameCheck.Available
      } else {
        UsernameCheck.Rejected(
          json.optString("reason", null).cleanNull() ?: "That username can't be used.",
        )
      }
    } catch (_: Exception) {
      UsernameCheck.Unknown
    } finally {
      connection.disconnect()
    }
  }

  /**
   * Create / complete the ActionBoard player profile. Returns true on success.
   */
  suspend fun saveProfile(
    token: String,
    name: String,
    state: String,
    district: String,
    primarySport: String,
    sportAttributes: Map<String, String>,
    gender: String? = null,
    dateOfBirth: String? = null,
    birthPlace: String? = null,
    height: String? = null,
    nationality: String? = null,
    username: String? = null,
    // Instagram-style account privacy chosen at profile creation. Null = don't touch the
    // server's current setting (older callers that never ask). true = private account.
    isPrivate: Boolean? = null,
  ): Boolean = withContext(Dispatchers.IO) {
    val body = JSONObject()
      .put("name", name)
      .put("state", state)
      .put("district", district)
      .put("primary_sport", primarySport)
      .put("sport_attributes", JSONObject(sportAttributes as Map<*, *>))
    if (!username.isNullOrBlank()) body.put("username", username.trim())
    if (isPrivate != null) body.put("is_private", isPrivate)
    if (!gender.isNullOrBlank()) body.put("gender", gender)
    if (!dateOfBirth.isNullOrBlank()) body.put("date_of_birth", dateOfBirth)
    if (!birthPlace.isNullOrBlank()) body.put("birth_place", birthPlace)
    if (!height.isNullOrBlank()) body.put("height", height)
    if (!nationality.isNullOrBlank()) body.put("nationality", nationality)

    val connection = (URL("${baseUrl.trimEnd('/')}/api/players/profile").openConnection() as HttpURLConnection).apply {
      requestMethod = "POST"
      doOutput = true
      connectTimeout = 15000
      readTimeout = 15000
      setRequestProperty("Content-Type", "application/json")
      setRequestProperty("Accept", "application/json")
      setRequestProperty("Authorization", "Bearer $token")
    }
    try {
      connection.outputStream.use { it.write(body.toString().toByteArray(Charsets.UTF_8)) }
      val code = connection.responseCode
      if (code !in 200..299) {
        val err = connection.errorStream?.let { BufferedReader(InputStreamReader(it)).use { r -> r.readText() } } ?: ""
        throw IllegalStateException(parseError(err))
      }
      true
    } finally {
      connection.disconnect()
    }
  }

  /**
   * Inline edit of just the display name + bio (the profile's Edit button). Returns true on
   * success. Lighter than [saveProfile], which needs the full setup payload.
   */
  suspend fun updateBasics(token: String, name: String, bio: String?): Boolean = withContext(Dispatchers.IO) {
    val body = JSONObject().put("name", name.trim())
    body.put("bio", bio?.trim() ?: "")
    val connection = (URL("${baseUrl.trimEnd('/')}/api/players/profile/basics").openConnection() as HttpURLConnection).apply {
      requestMethod = "POST"
      doOutput = true
      connectTimeout = 15000
      readTimeout = 15000
      setRequestProperty("Content-Type", "application/json")
      setRequestProperty("Accept", "application/json")
      setRequestProperty("Authorization", "Bearer $token")
    }
    try {
      connection.outputStream.use { it.write(body.toString().toByteArray(Charsets.UTF_8)) }
      connection.responseCode in 200..299
    } catch (_: Exception) {
      false
    } finally {
      connection.disconnect()
    }
  }

  /**
   * Upload / replace the player's profile photo. Returns the stored URL (e.g.
   * "/storage/avatars/x.jpg"). Mirrors [MatchRepository.uploadTeamLogo].
   */
  suspend fun uploadAvatar(token: String, imageBytes: ByteArray, mimeType: String): String =
    withContext(Dispatchers.IO) {
      val boundary = "----HaraanBoundary${System.currentTimeMillis()}"
      val connection = (URL("${baseUrl.trimEnd('/')}/api/players/avatar").openConnection() as HttpURLConnection).apply {
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
          out.write(("$dashes$boundary$lineEnd").toByteArray())
          out.write(("Content-Disposition: form-data; name=\"avatar\"; filename=\"avatar.$ext\"$lineEnd").toByteArray())
          out.write(("Content-Type: $mimeType$lineEnd$lineEnd").toByteArray())
          out.write(imageBytes)
          out.write(lineEnd.toByteArray())
          out.write(("$dashes$boundary$dashes$lineEnd").toByteArray())
        }
        val code = connection.responseCode
        val stream = if (code >= 400) connection.errorStream else connection.inputStream
        val body = stream?.let { BufferedReader(InputStreamReader(it)).use { r -> r.readText() } } ?: ""
        if (code !in 200..299) {
          throw IllegalStateException(parseError(body))
        }
        JSONObject(body).optString("url", "")
      } finally {
        connection.disconnect()
      }
    }

  private fun parseError(body: String): String = try {
    if (body.isBlank()) "Unable to load profile." else JSONObject(body).optString("error", "Unable to load profile.")
  } catch (_: Exception) {
    "Unable to load profile."
  }

  private fun String?.cleanNull(): String? = this?.takeIf { it.isNotBlank() && it != "null" }

  private fun JSONObject.optIntOrNull(key: String): Int? =
    if (isNull(key) || !has(key)) null else optInt(key)
}
