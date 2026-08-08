package com.haraan.app.data

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONObject
import java.io.BufferedReader
import java.io.InputStreamReader
import java.net.HttpURLConnection
import java.net.URL

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
 * The follow graph as it applies to the viewer looking at this profile.
 *
 * [canFollow] is deliberately separate from [isFollowing]: a signed-out visitor and a
 * player looking at their own profile both have `isFollowing = false`, but neither
 * should be offered a Follow button, and for opposite reasons.
 */
data class SocialState(
  val followersCount: Int,
  val followingCount: Int,
  val isFollowing: Boolean,
  val isSelf: Boolean,
  val canFollow: Boolean,
)

data class PlayerProfile(
  val id: Int,
  val playerId: String,
  val username: String?,
  val name: String,
  val avatar: String?,
  val district: String?,
  val state: String?,
  val isOrganizer: Boolean,
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
   * Follower counts + whether the viewer already follows this player. Null only for a
   * server too old to send the block — the follow UI hides rather than guessing.
   */
  val social: SocialState? = null,
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

  private fun parseProfile(json: JSONObject): PlayerProfile {
    val recent = mutableListOf<RecentMatch>()
    val arr = json.optJSONArray("recent_matches")
    if (arr != null) {
      for (i in 0 until arr.length()) {
        val o = arr.getJSONObject(i)
        recent.add(
          RecentMatch(
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
      avatar = json.optString("avatar", null).cleanNull(),
      district = json.optString("district", null).cleanNull(),
      state = json.optString("state", null).cleanNull(),
      isOrganizer = json.optBoolean("is_organizer", false),
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
      social = json.optJSONObject("social")?.let { s ->
        SocialState(
          followersCount = s.optInt("followers_count", 0),
          followingCount = s.optInt("following_count", 0),
          isFollowing = s.optBoolean("is_following", false),
          isSelf = s.optBoolean("is_self", false),
          canFollow = s.optBoolean("can_follow", false),
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
  ): Boolean = withContext(Dispatchers.IO) {
    val body = JSONObject()
      .put("name", name)
      .put("state", state)
      .put("district", district)
      .put("primary_sport", primarySport)
      .put("sport_attributes", JSONObject(sportAttributes as Map<*, *>))
    if (!username.isNullOrBlank()) body.put("username", username.trim())
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
