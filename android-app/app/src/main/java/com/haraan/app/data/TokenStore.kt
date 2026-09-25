package com.haraan.app.data

import android.content.Context
import androidx.security.crypto.EncryptedSharedPreferences
import androidx.security.crypto.MasterKeys

object TokenStore {
  private const val PREFS_FILE = "haraan_secure_prefs"
  private const val KEY_JWT = "key_jwt_token"

  /**
   * Stored in place of a JWT when the user taps "Skip" on login. It is a local
   * marker for "browsing as a guest" — never a credential — so it must never be
   * sent to the API: the backend rightly rejects it with 401 "Invalid or expired
   * token". Screens that need a real session must check [isGuest] first and offer
   * a sign-in instead of firing a request that is guaranteed to fail.
   */
  const val GUEST_TOKEN = "skipped_guest"

  fun isGuest(token: String?): Boolean = token == GUEST_TOKEN

  /**
   * Returns true if the token is a JWT whose 'exp' claim is in the past.
   */
  fun isTokenExpired(token: String?): Boolean {
    if (token.isNullOrBlank() || isGuest(token)) return true
    return try {
      val parts = token.split(".")
      if (parts.size != 3) return false
      val payloadJson = String(
        android.util.Base64.decode(
          parts[1],
          android.util.Base64.URL_SAFE or android.util.Base64.NO_PADDING or android.util.Base64.NO_WRAP
        )
      )
      val json = org.json.JSONObject(payloadJson)
      val exp = json.optLong("exp", 0L)
      if (exp > 0) {
        exp * 1000L < (System.currentTimeMillis() - 10000L)
      } else {
        false
      }
    } catch (_: Exception) {
      false
    }
  }

  /** True only for a real signed-in session — guest, expired, and empty all fail this. */
  fun isSignedIn(token: String?): Boolean = !token.isNullOrBlank() && !isGuest(token) && !isTokenExpired(token)

  fun saveToken(context: Context, token: String) {
    try {
      val masterKeyAlias = MasterKeys.getOrCreate(MasterKeys.AES256_GCM_SPEC)
      val prefs = EncryptedSharedPreferences.create(
        PREFS_FILE,
        masterKeyAlias,
        context,
        EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV,
        EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM
      )
      prefs.edit().putString(KEY_JWT, token).apply()
    } catch (e: Exception) {
      // swallow: saving is best-effort, ViewModel still holds token
    }
  }

  fun clearToken(context: Context) {
    try {
      val masterKeyAlias = MasterKeys.getOrCreate(MasterKeys.AES256_GCM_SPEC)
      val prefs = EncryptedSharedPreferences.create(
        PREFS_FILE,
        masterKeyAlias,
        context,
        EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV,
        EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM
      )
      prefs.edit().remove(KEY_JWT).apply()
    } catch (e: Exception) {
      // swallow
    }
  }

  /**
   * The stored token, but ONLY for a real signed-in session — a guest gets null.
   *
   * Prefer this over [getToken] anywhere the token is about to be sent to the API.
   * `getToken(ctx)` returns the non-blank [GUEST_TOKEN] for guests, so the natural
   * `?: return` / `isNullOrBlank()` guards silently let them through to a
   * guaranteed 401; this returns null so those same guards do the right thing,
   * and it keeps Kotlin's smart-cast to non-null after the check.
   */
  fun getSignedInToken(context: Context): String? = getToken(context)?.takeIf { isSignedIn(it) }

  fun getToken(context: Context): String? {
    return try {
      val masterKeyAlias = MasterKeys.getOrCreate(MasterKeys.AES256_GCM_SPEC)
      val prefs = EncryptedSharedPreferences.create(
        PREFS_FILE,
        masterKeyAlias,
        context,
        EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV,
        EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM
      )
      prefs.getString(KEY_JWT, null)
    } catch (e: Exception) {
      null
    }
  }
}
