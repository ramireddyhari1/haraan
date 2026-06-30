package com.example.thanna.data

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONObject
import java.io.BufferedReader
import java.io.InputStreamReader
import java.net.HttpURLConnection
import java.net.URLEncoder
import java.net.URL

/** A registered player resolved from a Player ID. */
data class PlayerLite(
  val playerId: String,
  val name: String,
  val district: String?,
)

/** One member of a team squad. Registered players have a Player ID; guests have a name only. */
data class SquadMember(
  val id: String,
  val name: String,
  val isGuest: Boolean = false,
)

/**
 * Player directory lookups. Mirrors [MatchRepository]'s HttpURLConnection + JWT style.
 */
class PlayerRepository(
  private val baseUrl: String = ApiConfig.BASE_URL,
) {
  /**
   * Resolve a registered player by Player ID. Returns null when not found (404)
   * or on any error, so callers can treat it as "not a valid player".
   */
  suspend fun lookup(token: String, playerId: String): PlayerLite? = withContext(Dispatchers.IO) {
    val trimmed = playerId.trim()
    if (trimmed.isEmpty()) return@withContext null

    val encoded = URLEncoder.encode(trimmed, "UTF-8")
    val connection = (URL("${baseUrl.trimEnd('/')}/api/players/lookup?playerId=$encoded").openConnection() as HttpURLConnection).apply {
      requestMethod = "GET"
      connectTimeout = 10000
      readTimeout = 10000
      setRequestProperty("Accept", "application/json")
      setRequestProperty("Authorization", "Bearer $token")
    }

    try {
      val code = connection.responseCode
      if (code !in 200..299) return@withContext null
      val stream = connection.inputStream ?: return@withContext null
      val body = BufferedReader(InputStreamReader(stream)).use { it.readText() }
      val json = JSONObject(body)
      PlayerLite(
        playerId = json.optString("player_id", trimmed),
        name = json.optString("name", ""),
        district = json.optString("district", null).takeIf { it.isNotBlank() && it != "null" },
      )
    } catch (_: Exception) {
      null
    } finally {
      connection.disconnect()
    }
  }
}
