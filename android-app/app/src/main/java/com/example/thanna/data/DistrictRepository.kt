package com.example.thanna.data

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONObject
import java.io.BufferedReader
import java.io.InputStreamReader
import java.net.HttpURLConnection
import java.net.URL

/** Top player in a district (batter by runs, or bowler by wickets). */
data class DistrictTopPlayer(
  val playerId: String,
  val name: String,
  val avatar: String?,
  val value: Int,
)

/** District Home snapshot (from GET /api/districts/summary). */
data class DistrictSummary(
  val district: String,
  val state: String?,
  val liveMatches: Int,
  val totalMatches: Int,
  val players: Int,
  val topBatter: DistrictTopPlayer?,
  val topBowler: DistrictTopPlayer?,
  val districtRank: Int?,
  val districtRankTotal: Int?,
)

/**
 * District Home data. The summary derives from the viewer's own district when a
 * token is supplied (the server reads it from the JWT). Returns null on any
 * failure — e.g. a guest, or a player who hasn't set their district yet — so the
 * UI can show a friendly prompt instead of an error.
 */
class DistrictRepository(
  private val baseUrl: String = ApiConfig.BASE_URL,
) {
  suspend fun fetchSummary(token: String?): DistrictSummary? = withContext(Dispatchers.IO) {
    try {
      val connection = (URL("${baseUrl.trimEnd('/')}/api/districts/summary").openConnection() as HttpURLConnection).apply {
        requestMethod = "GET"
        connectTimeout = 15000
        readTimeout = 15000
        setRequestProperty("Accept", "application/json")
        if (!token.isNullOrBlank()) setRequestProperty("Authorization", "Bearer $token")
      }
      val code = connection.responseCode
      val stream = if (code >= 400) connection.errorStream else connection.inputStream
      val body = stream?.let { BufferedReader(InputStreamReader(it)).use { r -> r.readText() } } ?: ""
      connection.disconnect()
      if (code !in 200..299) return@withContext null

      val d = JSONObject(body).optJSONObject("data") ?: return@withContext null
      DistrictSummary(
        district = d.optString("district", ""),
        state = d.optString("state", null)?.takeIf { it.isNotBlank() && it != "null" },
        liveMatches = d.optInt("liveMatches", 0),
        totalMatches = d.optInt("totalMatches", 0),
        players = d.optInt("players", 0),
        topBatter = parsePlayer(d.optJSONObject("topBatter")),
        topBowler = parsePlayer(d.optJSONObject("topBowler")),
        districtRank = if (d.isNull("districtRank")) null else d.optInt("districtRank"),
        districtRankTotal = if (d.isNull("districtRankTotal")) null else d.optInt("districtRankTotal"),
      )
    } catch (_: Exception) {
      null
    }
  }

  private fun parsePlayer(o: JSONObject?): DistrictTopPlayer? {
    if (o == null) return null
    return DistrictTopPlayer(
      playerId = o.optString("player_id", ""),
      name = o.optString("name", ""),
      avatar = o.optString("avatar", null)?.takeIf { it.isNotBlank() && it != "null" },
      value = o.optInt("value", 0),
    )
  }
}
