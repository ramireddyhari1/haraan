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

data class PlayerProfile(
  val id: Int,
  val playerId: String,
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
        throw IllegalStateException(parseError(body))
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
        throw IllegalStateException(parseError(body))
      }
      parseProfile(JSONObject(body))
    } finally {
      connection.disconnect()
    }
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
   * Lightweight gate check: is the player's ActionBoard profile complete?
   * Returns false on any error (treated as "not ready").
   */
  suspend fun isProfileComplete(token: String): Boolean = withContext(Dispatchers.IO) {
    runCatching { fetchMe(token).profileComplete }.getOrDefault(false)
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
  ): Boolean = withContext(Dispatchers.IO) {
    val body = JSONObject()
      .put("name", name)
      .put("state", state)
      .put("district", district)
      .put("primary_sport", primarySport)
      .put("sport_attributes", JSONObject(sportAttributes as Map<*, *>))
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
