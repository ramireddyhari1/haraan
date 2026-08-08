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

/**
 * A player as they appear in social discovery — richer than [PlayerLite] because a
 * search result that is only a name reads dead. Kept as its own type so the squad
 * pickers that consume [PlayerLite] are untouched by the social surface.
 */
data class DiscoveredPlayer(
  val playerId: String,
  val name: String,
  val username: String?,
  val avatar: String?,
  val district: String?,
  val state: String?,
  val primarySport: String?,
  val matches: Int,
  val xp: Int,
  /** Null when nobody is signed in — the button renders as "Sign in to follow". */
  val isFollowing: Boolean?,
) {
  /** "@virat" when they have a handle, otherwise their HRN id. */
  val handleOrId: String get() = username?.let { "@$it" } ?: playerId

  /** "YSR Kadapa · 12 matches" — whichever halves actually exist. */
  val subtitle: String
    get() = listOfNotNull(
      district?.takeIf { it.isNotBlank() },
      matches.takeIf { it > 0 }?.let { "$it ${if (it == 1) "match" else "matches"}" },
    ).joinToString(" · ")
}

/**
 * Outcome of a directory search.
 *
 * `Unauthorized` exists because collapsing it into an empty list is a lie: the app
 * would tell someone "No players for hari" when the truth is their session expired.
 * A stored token can be present and still be rejected, so "we have a token" is not
 * the same as "we are signed in" — cf. the guest-token gate.
 */
sealed interface DiscoveryOutcome {
  data class Success(val players: List<DiscoveredPlayer>) : DiscoveryOutcome
  data object Unauthorized : DiscoveryOutcome
  data object Failed : DiscoveryOutcome
}

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

  /**
   * Social search — the same `/api/players/find` endpoint the squad picker uses,
   * but parsed into the richer [DiscoveredPlayer] shape (follow state, matches, XP).
   *
   * The server resolves follow state for the whole page in one query, so a 20-result
   * search costs one round trip rather than twenty.
   */
  suspend fun discover(token: String, query: String): DiscoveryOutcome = withContext(Dispatchers.IO) {
    val q = query.trim()
    if (q.length < 2) return@withContext DiscoveryOutcome.Success(emptyList())

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
      // A stored token can be expired or issued against another environment. Say so
      // rather than reporting an empty directory.
      if (code == 401 || code == 403) return@withContext DiscoveryOutcome.Unauthorized
      if (code !in 200..299) return@withContext DiscoveryOutcome.Failed

      val body = BufferedReader(InputStreamReader(connection.inputStream)).use { it.readText() }
      val arr = JSONObject(body).optJSONArray("results")
        ?: return@withContext DiscoveryOutcome.Success(emptyList())

      DiscoveryOutcome.Success(buildList {
        for (i in 0 until arr.length()) parseDiscovered(arr.getJSONObject(i))?.let { add(it) }
      })
    } catch (_: Exception) {
      DiscoveryOutcome.Failed
    } finally {
      connection.disconnect()
    }
  }

  /**
   * Who follows [playerId], or who they follow — `relation` is "followers" or
   * "following".
   *
   * Reuses [DiscoveryOutcome] and [parseDiscovered] because the server returns the
   * same player-card shape here as it does for search, so a follower row and a search
   * row are the same object and can share one row composable.
   */
  suspend fun followList(token: String?, playerId: String, relation: String): DiscoveryOutcome =
    withContext(Dispatchers.IO) {
      val encoded = URLEncoder.encode(playerId.trim(), "UTF-8")
      val connection = (URL("${baseUrl.trimEnd('/')}/api/players/$encoded/$relation").openConnection() as HttpURLConnection).apply {
        requestMethod = "GET"
        connectTimeout = 10000
        readTimeout = 10000
        setRequestProperty("Accept", "application/json")
        if (!token.isNullOrBlank()) setRequestProperty("Authorization", "Bearer $token")
      }

      try {
        val code = connection.responseCode
        if (code == 401 || code == 403) return@withContext DiscoveryOutcome.Unauthorized
        if (code !in 200..299) return@withContext DiscoveryOutcome.Failed

        val body = BufferedReader(InputStreamReader(connection.inputStream)).use { it.readText() }
        val arr = JSONObject(body).optJSONArray("results")
          ?: return@withContext DiscoveryOutcome.Success(emptyList())

        DiscoveryOutcome.Success(buildList {
          for (i in 0 until arr.length()) parseDiscovered(arr.getJSONObject(i))?.let { add(it) }
        })
      } catch (_: Exception) {
        DiscoveryOutcome.Failed
      } finally {
        connection.disconnect()
      }
    }

  /**
   * Follow or unfollow, returning the state the SERVER settled on rather than what
   * we optimistically assumed. Null means the call failed and the caller should roll
   * its optimistic toggle back — silently leaving a filled "Following" button on a
   * request that never landed is the worst outcome here.
   *
   * POST for both directions: Android's HttpURLConnection has no dependable
   * DELETE path, which is why the API exposes a POST `/unfollow` twin.
   */
  suspend fun setFollowing(token: String, playerId: String, follow: Boolean): Boolean? =
    withContext(Dispatchers.IO) {
      val path = if (follow) "follow" else "unfollow"
      val encoded = URLEncoder.encode(playerId.trim(), "UTF-8")
      val connection = (URL("${baseUrl.trimEnd('/')}/api/players/$encoded/$path").openConnection() as HttpURLConnection).apply {
        requestMethod = "POST"
        connectTimeout = 10000
        readTimeout = 10000
        doOutput = true
        setRequestProperty("Accept", "application/json")
        setRequestProperty("Content-Type", "application/json")
        setRequestProperty("Authorization", "Bearer $token")
      }

      try {
        connection.outputStream.use { it.write("{}".toByteArray()) }
        if (connection.responseCode !in 200..299) return@withContext null
        val body = BufferedReader(InputStreamReader(connection.inputStream)).use { it.readText() }
        JSONObject(body).optBoolean("is_following", follow)
      } catch (_: Exception) {
        null
      } finally {
        connection.disconnect()
      }
    }

  private fun parseDiscovered(json: JSONObject): DiscoveredPlayer? {
    val id = json.optString("player_id", "").takeIf { it.isNotBlank() } ?: return null
    return DiscoveredPlayer(
      playerId = id,
      name = json.optString("name", "").takeIf { it.isNotBlank() } ?: "Player",
      username = json.optString("username", null).clean(),
      avatar = json.optString("avatar", null).clean(),
      district = json.optString("district", null).clean(),
      state = json.optString("state", null).clean(),
      primarySport = json.optString("primary_sport", null).clean(),
      matches = json.optInt("matches", 0),
      xp = json.optInt("xp", 0),
      // `optBoolean` can't distinguish false from absent, so read the raw value.
      isFollowing = if (json.isNull("is_following")) null else json.optBoolean("is_following"),
    )
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
