package com.example.thanna.data

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONObject
import java.io.BufferedReader
import java.io.InputStreamReader
import java.net.HttpURLConnection
import java.net.URL
import java.net.URLEncoder

/** One ranked row from GET /api/leaderboards/{scope}. */
data class LeaderboardRow(
  val rank: Int,
  val playerId: String,
  val name: String,
  val avatar: String?,
  val district: String?,
  val state: String?,
  val xp: Int,
  val matches: Int,
)

/**
 * Public ranked leaderboards. The monthly board is ranked by RANKED XP (a single
 * list per scope — not split by batting/bowling). Returns an empty list on any
 * failure so callers can fall back to demo content.
 */
class LeaderboardRepository(
  private val baseUrl: String = ApiConfig.BASE_URL,
) {
  /**
   * @param scope "district" | "state" | "india"
   * @param location district or state name (required for district/state)
   */
  suspend fun fetchBoard(scope: String, location: String?, limit: Int = 50): List<LeaderboardRow> =
    withContext(Dispatchers.IO) {
      try {
        val q = StringBuilder("?limit=$limit")
        if (!location.isNullOrBlank()) {
          q.append("&location=").append(URLEncoder.encode(location, "UTF-8"))
        }
        val connection = (URL("${baseUrl.trimEnd('/')}/api/leaderboards/$scope$q").openConnection() as HttpURLConnection).apply {
          requestMethod = "GET"
          connectTimeout = 15000
          readTimeout = 15000
          setRequestProperty("Accept", "application/json")
        }
        val code = connection.responseCode
        val body = if (code >= 400) "" else BufferedReader(InputStreamReader(connection.inputStream)).use { it.readText() }
        connection.disconnect()
        if (code !in 200..299) return@withContext emptyList()

        val arr = JSONObject(body).optJSONArray("data") ?: return@withContext emptyList()
        (0 until arr.length()).map { i ->
          val o = arr.getJSONObject(i)
          LeaderboardRow(
            rank = o.optInt("rank", i + 1),
            playerId = o.optString("player_id", ""),
            name = o.optString("name", ""),
            avatar = o.optString("avatar", null)?.takeIf { it.isNotBlank() && it != "null" },
            district = o.optString("district", null)?.takeIf { it.isNotBlank() && it != "null" },
            state = o.optString("state", null)?.takeIf { it.isNotBlank() && it != "null" },
            xp = o.optInt("xp", 0),
            matches = o.optInt("matches", 0),
          )
        }
      } catch (_: Exception) {
        emptyList()
      }
    }
}
