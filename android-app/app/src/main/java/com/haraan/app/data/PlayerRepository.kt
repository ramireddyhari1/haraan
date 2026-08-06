package com.haraan.app.data

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONObject
import java.io.BufferedReader
import java.io.InputStreamReader
import java.net.HttpURLConnection
import java.net.URLEncoder
import java.net.URL

/** A registered player resolved from a Player ID or username. */
data class PlayerLite(
  val playerId: String,
  val name: String,
  val district: String?,
  /** The @handle, when they've chosen one. Accounts created before usernames have none. */
  val username: String? = null,
  val avatar: String? = null,
)

/** One member of a team squad. Registered players have a Player ID; guests have a name only. */
data class SquadMember(
  val id: String,
  val name: String,
  val isGuest: Boolean = false,
  val isCaptain: Boolean = false,
  val isViceCaptain: Boolean = false,
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
      parsePlayer(JSONObject(body), fallbackId = trimmed)
    } catch (_: Exception) {
      null
    } finally {
      connection.disconnect()
    }
  }

  /**
   * Find players to add to a squad, by @username or name (an exact Player ID also works).
   *
   * This exists because the old flow demanded a teammate's Player ID (HRN-000123) typed
   * from memory — which meant in practice you could only build a squad with people
   * standing next to you. Returns empty for anything under 2 characters, and on any
   * failure, so the picker just shows nothing rather than an error mid-typing.
   */
  suspend fun search(token: String, query: String): List<PlayerLite> = withContext(Dispatchers.IO) {
    val q = query.trim()
    if (q.length < 2) return@withContext emptyList()

    val encoded = URLEncoder.encode(q, "UTF-8")
    val connection = (URL("${baseUrl.trimEnd('/')}/api/players/find?q=$encoded").openConnection() as HttpURLConnection).apply {
      requestMethod = "GET"
      connectTimeout = 10000
      readTimeout = 10000
      setRequestProperty("Accept", "application/json")
      setRequestProperty("Authorization", "Bearer $token")
    }

    try {
      val code = connection.responseCode
      if (code !in 200..299) return@withContext emptyList()
      val stream = connection.inputStream ?: return@withContext emptyList()
      val body = BufferedReader(InputStreamReader(stream)).use { it.readText() }
      val arr = JSONObject(body).optJSONArray("results") ?: return@withContext emptyList()
      buildList {
        for (i in 0 until arr.length()) {
          parsePlayer(arr.getJSONObject(i), fallbackId = "")?.let { add(it) }
        }
      }
    } catch (_: Exception) {
      emptyList()
    } finally {
      connection.disconnect()
    }
  }

  private fun parsePlayer(json: JSONObject, fallbackId: String): PlayerLite? {
    val id = json.optString("player_id", fallbackId).takeIf { it.isNotBlank() } ?: return null
    return PlayerLite(
      playerId = id,
      name = json.optString("name", ""),
      district = json.optString("district", null).clean(),
      username = json.optString("username", null).clean(),
      avatar = json.optString("avatar", null).clean(),
    )
  }

  private fun String?.clean(): String? = this?.takeIf { it.isNotBlank() && it != "null" }
}
