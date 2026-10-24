package com.haraan.app.data

import android.Manifest
import android.content.Context
import android.content.pm.PackageManager
import android.provider.ContactsContract
import androidx.core.content.ContextCompat
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.withContext
import org.json.JSONArray
import org.json.JSONObject
import java.net.HttpURLConnection
import java.net.URL
import java.security.MessageDigest

/**
 * Which of the viewer's phone contacts play on Haraan, as player id → the name THEY saved.
 *
 * Opt-in only (READ_CONTACTS, asked from the ActionBoard card that explains why). The phone
 * book is read here, each number reduced to its last 10 digits and SHA-256 hashed, and only
 * the hashes go to POST /api/players/contacts/match — which stores nothing. The answer is kept
 * in local prefs and intersected with each ActionBoard card's `playerIds` on the device, so the
 * server never learns who is in anyone's phone book or what they call them.
 */
object ContactFriends {
  private const val PREFS = "contact_friends"
  private const val KEY_MAP = "names"
  private const val KEY_SYNCED_AT = "synced_at"
  private const val KEY_DISMISSED = "dismissed"
  private const val PAGE = 3000
  private const val RESYNC_MS = 24L * 60 * 60 * 1000

  private val _names = MutableStateFlow<Map<String, String>>(emptyMap())
  /** player id → contact display name. Empty until the user opts in and a sync lands. */
  val names: StateFlow<Map<String, String>> = _names.asStateFlow()

  fun hasPermission(context: Context): Boolean =
    ContextCompat.checkSelfPermission(context, Manifest.permission.READ_CONTACTS) == PackageManager.PERMISSION_GRANTED

  fun isDismissed(context: Context): Boolean = prefs(context).getBoolean(KEY_DISMISSED, false)

  fun dismiss(context: Context) {
    prefs(context).edit().putBoolean(KEY_DISMISSED, true).apply()
  }

  /** Loads the last answer from disk so cards can show contacts before any network call. */
  fun load(context: Context) {
    if (!hasPermission(context)) { _names.value = emptyMap(); return }
    val raw = prefs(context).getString(KEY_MAP, null) ?: return
    _names.value = runCatching {
      val o = JSONObject(raw)
      o.keys().asSequence().associateWith { o.optString(it) }
    }.getOrDefault(emptyMap())
  }

  /**
   * Re-matches the phone book when permission is held and the last sync is over a day old
   * (or [force]). Quiet on failure — the cards simply keep the last answer.
   */
  suspend fun syncIfDue(context: Context, token: String?, force: Boolean = false) {
    if (token.isNullOrBlank() || !hasPermission(context)) return
    val last = prefs(context).getLong(KEY_SYNCED_AT, 0L)
    if (!force && System.currentTimeMillis() - last < RESYNC_MS && _names.value.isNotEmpty()) return
    withContext(Dispatchers.IO) {
      runCatching {
        val book = readContacts(context)              // hash → contact name
        if (book.isEmpty()) return@runCatching
        val found = HashMap<String, String>()
        book.keys.chunked(PAGE).forEach { page ->
          for ((hash, playerId) in postHashes(token, page)) {
            book[hash]?.let { found[playerId] = it }
          }
        }
        prefs(context).edit()
          .putString(KEY_MAP, JSONObject(found as Map<*, *>).toString())
          .putLong(KEY_SYNCED_AT, System.currentTimeMillis())
          .apply()
        _names.value = found
      }
    }
  }

  /** The comparable form of a number: its last 10 digits, matching the server's phoneKey. */
  internal fun phoneKey(raw: String): String? {
    val digits = raw.filter { it.isDigit() }
    return if (digits.length >= 10) digits.takeLast(10) else null
  }

  private fun sha256(s: String): String =
    MessageDigest.getInstance("SHA-256").digest(s.toByteArray()).joinToString("") { "%02x".format(it) }

  private fun readContacts(context: Context): Map<String, String> {
    val out = LinkedHashMap<String, String>()
    val cursor = context.contentResolver.query(
      ContactsContract.CommonDataKinds.Phone.CONTENT_URI,
      arrayOf(
        ContactsContract.CommonDataKinds.Phone.NUMBER,
        ContactsContract.CommonDataKinds.Phone.DISPLAY_NAME,
      ),
      null, null, null,
    ) ?: return out
    cursor.use { c ->
      while (c.moveToNext()) {
        val key = phoneKey(c.getString(0).orEmpty()) ?: continue
        val name = c.getString(1)?.trim().orEmpty()
        if (name.isNotEmpty()) out.putIfAbsent(sha256(key), name)
      }
    }
    return out
  }

  /** hash → player id for the hashes that belong to players. */
  private fun postHashes(token: String, hashes: List<String>): List<Pair<String, String>> {
    val conn = (URL("${ApiConfig.BASE_URL.trimEnd('/')}/api/players/contacts/match").openConnection() as HttpURLConnection).apply {
      requestMethod = "POST"
      connectTimeout = 15_000
      readTimeout = 20_000
      doOutput = true
      setRequestProperty("Content-Type", "application/json")
      setRequestProperty("Accept", "application/json")
      setRequestProperty("Authorization", "Bearer $token")
    }
    try {
      conn.outputStream.use { it.write(JSONObject().put("hashes", JSONArray(hashes)).toString().toByteArray()) }
      if (conn.responseCode !in 200..299) return emptyList()
      val arr = JSONObject(conn.inputStream.bufferedReader().readText()).optJSONArray("data") ?: return emptyList()
      return (0 until arr.length()).mapNotNull { i ->
        val o = arr.optJSONObject(i) ?: return@mapNotNull null
        val h = o.optString("hash"); val p = o.optString("playerId")
        if (h.isBlank() || p.isBlank()) null else h to p
      }
    } finally {
      conn.disconnect()
    }
  }

  private fun prefs(context: Context) = context.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
}
