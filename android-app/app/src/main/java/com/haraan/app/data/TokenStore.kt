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

  /** True only for a real signed-in session — guest and empty both fail this. */
  fun isSignedIn(token: String?): Boolean = !token.isNullOrBlank() && !isGuest(token)

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
