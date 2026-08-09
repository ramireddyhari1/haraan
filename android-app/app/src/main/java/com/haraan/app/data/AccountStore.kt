package com.haraan.app.data

import android.content.Context
import androidx.security.crypto.EncryptedSharedPreferences
import androidx.security.crypto.MasterKeys
import org.json.JSONArray
import org.json.JSONObject

/**
 * One signed-in account remembered on this device.
 *
 * [token] is a real JWT and is why this whole file lives in EncryptedSharedPreferences
 * rather than plain prefs. Everything else is display material for the switcher sheet.
 */
data class StoredAccount(
  val playerId: String,
  val name: String,
  val username: String?,
  val avatar: String?,
  val token: String,
) {
  /** "@virat" when they have a handle, otherwise the HRN id — same rule as elsewhere. */
  val handleOrId: String get() = username?.let { "@$it" } ?: playerId
}

/**
 * What [AccountStore.upsertAndActivate] did.
 *
 * It returns an outcome rather than succeeding blindly because the roster has a hard
 * ceiling, and "add" at the ceiling is a real, reachable answer the caller has to render
 * — not an error worth crashing over.
 */
sealed interface AddAccountResult {
  /** Stored and now the active session. [account] is the row as written. */
  data class Added(val account: StoredAccount) : AddAccountResult

  /**
   * Nothing changed: the roster already holds [AccountStore.MAX_ACCOUNTS] accounts and
   * this one is not among them. The live session is untouched, so the user stays signed
   * in as whoever they were.
   */
  data object RosterFull : AddAccountResult
}

/**
 * The device's roster of signed-in accounts, for the Instagram-style switcher.
 *
 * ## Why this sits beside [TokenStore] instead of replacing it
 *
 * 73 call sites across the app read the session statically as
 * `TokenStore.getToken(context)`. Rewriting all of them to thread an account through
 * would be a large, risky change for no behavioural gain. So [TokenStore]'s single slot
 * keeps its exact meaning — *the active session* — and this object owns the roster
 * beside it. Switching accounts rewrites that one slot, and every existing call site
 * follows automatically without being touched.
 *
 * The invariant that makes it safe: **the active account's token and
 * `TokenStore.getToken()` are always the same string.** Every mutator here writes both
 * or neither.
 *
 * ## What is deliberately NOT stored
 *
 * The guest marker never enters the roster — it is not a credential and there is
 * nothing to switch to. Passwords are never stored; re-authenticating an expired
 * account means a real sign-in.
 */
object AccountStore {
  private const val PREFS_FILE = "haraan_secure_prefs" // shared with TokenStore, same master key
  private const val KEY_ACCOUNTS = "key_accounts"

  /** Hard ceiling, matching Instagram's. Also bounds what one stolen device exposes. */
  const val MAX_ACCOUNTS = 5

  private fun prefs(context: Context) = EncryptedSharedPreferences.create(
    PREFS_FILE,
    MasterKeys.getOrCreate(MasterKeys.AES256_GCM_SPEC),
    context.applicationContext,
    EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV,
    EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM,
  )

  /** Everyone signed in on this device, in the order they were added. */
  fun accounts(context: Context): List<StoredAccount> = try {
    val raw = prefs(context).getString(KEY_ACCOUNTS, null)
    if (raw.isNullOrBlank()) emptyList() else parse(raw)
  } catch (_: Exception) {
    emptyList()
  }

  /**
   * Which account the app is currently acting as, resolved by matching the live session
   * token rather than by a separate "active" flag — a flag can drift out of step with
   * the token that requests actually carry, and then the switcher would name one account
   * while the app behaved as another.
   */
  fun active(context: Context): StoredAccount? {
    val token = TokenStore.getToken(context)
    if (!TokenStore.isSignedIn(token)) return null
    return accounts(context).firstOrNull { it.token == token }
  }

  /** True once there is more than one account, i.e. once the switcher earns its place. */
  fun hasMultiple(context: Context): Boolean = accounts(context).size > 1

  fun isFull(context: Context): Boolean = accounts(context).size >= MAX_ACCOUNTS

  /**
   * Record a freshly signed-in account and make it the active one.
   *
   * Re-signing into an account already on the device REPLACES its stored token instead
   * of adding a second entry — otherwise the roster would fill with duplicates of one
   * person, most holding tokens that were revoked the moment the newest one was issued.
   *
   * At the ceiling this refuses rather than half-succeeding. Writing the token without
   * the roster row — which is what an unguarded append gives you, since [write] truncates
   * to [MAX_ACCOUNTS] and the new entry sits last — would break this file's one invariant:
   * requests would carry a session no row in the switcher accounts for, and [active] would
   * answer null while the user is plainly signed in. [isFull] still lets a caller grey out
   * "Add account" before the user gets that far, but the store no longer depends on it.
   *
   * REPLACING an account that is already stored stays allowed at the ceiling: it rewrites
   * a row rather than growing the roster, so there is nothing to truncate.
   */
  fun upsertAndActivate(context: Context, account: StoredAccount): AddAccountResult {
    val current = accounts(context)
    val remaining = current.filterNot { it.playerId == account.playerId }
    val isNewAccount = remaining.size == current.size
    if (isNewAccount && current.size >= MAX_ACCOUNTS) return AddAccountResult.RosterFull

    write(context, remaining + account)
    TokenStore.saveToken(context, account.token)
    return AddAccountResult.Added(account)
  }

  /**
   * Make an already-stored account active. Returns it, or null when it is not on the
   * device — callers must not assume success.
   */
  fun switchTo(context: Context, playerId: String): StoredAccount? {
    val target = accounts(context).firstOrNull { it.playerId == playerId } ?: return null
    TokenStore.saveToken(context, target.token)
    return target
  }

  /**
   * Forget one account and hand back whoever should be active now — the next account in
   * the roster, or null when that was the last one and the app must return to login.
   *
   * Local only: revoking the session server-side is the caller's job (see
   * `AuthRepository.logout`), because it needs the network and can fail independently of
   * forgetting the token here. Forgetting always succeeds; that ordering means a dropped
   * network leaves a stale token on the server, never an un-removable account on screen.
   */
  fun remove(context: Context, playerId: String): StoredAccount? {
    val current = accounts(context)
    val victim = current.firstOrNull { it.playerId == playerId } ?: return active(context)
    val remaining = current.filterNot { it.playerId == playerId }

    // Decide before mutating: once the roster is rewritten there is no longer any record
    // of which account we were acting as.
    val removedTheActiveOne = TokenStore.getToken(context) == victim.token

    write(context, remaining)

    if (!removedTheActiveOne) return active(context)

    val next = remaining.firstOrNull()
    if (next != null) TokenStore.saveToken(context, next.token) else TokenStore.clearToken(context)
    return next
  }

  /** Sign out of everything on this device — roster and active session both. */
  fun clearAll(context: Context) {
    write(context, emptyList())
    TokenStore.clearToken(context)
  }

  /**
   * Adopt a session that was stored before this feature existed.
   *
   * An app updating in place has a valid token in [TokenStore] and an empty roster, so
   * the switcher would show "no accounts" while the user is plainly signed in. Called
   * once the profile behind that token is known.
   *
   * Returns what happened, so a caller can tell adoption apart from a full roster it had
   * no room in — the session is left alone either way.
   */
  fun adoptExistingSessionIfNeeded(context: Context, profile: PlayerProfile): AddAccountResult? {
    val token = TokenStore.getToken(context)
    if (!TokenStore.isSignedIn(token)) return null
    if (accounts(context).any { it.token == token }) return null

    return upsertAndActivate(
      context,
      StoredAccount(
        playerId = profile.playerId,
        name = profile.name,
        username = profile.username,
        avatar = profile.avatar,
        token = token!!,
      ),
    )
  }

  private fun write(context: Context, accounts: List<StoredAccount>) {
    try {
      val arr = JSONArray()
      // Belt-and-braces only: [upsertAndActivate] refuses past the ceiling, so nothing
      // should reach here over-length. This trims a roster written by an older build (or
      // a larger MAX_ACCOUNTS) rather than being the thing that enforces the limit — it
      // drops from the END, which silently ate the newest account back when it was.
      accounts.take(MAX_ACCOUNTS).forEach { a ->
        arr.put(
          JSONObject()
            .put("player_id", a.playerId)
            .put("name", a.name)
            .put("username", a.username ?: JSONObject.NULL)
            .put("avatar", a.avatar ?: JSONObject.NULL)
            .put("token", a.token),
        )
      }
      prefs(context).edit().putString(KEY_ACCOUNTS, arr.toString()).apply()
    } catch (_: Exception) {
      // Best-effort, matching TokenStore: a failed write must not crash sign-in.
    }
  }

  private fun parse(raw: String): List<StoredAccount> {
    val arr = JSONArray(raw)
    return buildList {
      for (i in 0 until arr.length()) {
        val o = arr.optJSONObject(i) ?: continue
        val id = o.optString("player_id", "").takeIf { it.isNotBlank() } ?: continue
        val token = o.optString("token", "").takeIf { it.isNotBlank() } ?: continue
        add(
          StoredAccount(
            playerId = id,
            name = o.optString("name", "").takeIf { it.isNotBlank() } ?: "Player",
            username = o.optString("username", null)?.takeIf { it.isNotBlank() && it != "null" },
            avatar = o.optString("avatar", null)?.takeIf { it.isNotBlank() && it != "null" },
            token = token,
          ),
        )
      }
    }
  }
}
