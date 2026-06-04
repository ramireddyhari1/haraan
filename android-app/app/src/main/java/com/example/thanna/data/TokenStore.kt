package com.example.thanna.data

import android.content.Context
import androidx.security.crypto.EncryptedSharedPreferences
import androidx.security.crypto.MasterKeys

object TokenStore {
  private const val PREFS_FILE = "haraan_secure_prefs"
  private const val KEY_JWT = "key_jwt_token"

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
