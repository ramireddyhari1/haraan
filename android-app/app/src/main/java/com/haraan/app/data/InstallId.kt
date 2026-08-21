package com.haraan.app.data

import android.content.Context
import java.util.UUID

/**
 * A random id for THIS install of the app.
 *
 * Not a device id, not an advertising id, and not tied to any account — it's generated
 * locally the first time it's asked for and dies with the app's data. Its one job is to let
 * the backend tell two signed-out viewers of the same live match apart when counting who's
 * watching, without us sending anything that identifies a person or a handset.
 *
 * Deliberately in plain SharedPreferences, not EncryptedSharedPreferences: there's nothing
 * secret here, and an unreadable keyset after a restore would just silently break counting
 * (see the EncryptedPrefs + Auto Backup landmine).
 */
object InstallId {
    private const val PREFS = "install_prefs"
    private const val KEY = "install_id"

    @Volatile private var cached: String? = null

    fun get(context: Context): String {
        cached?.let { return it }
        synchronized(this) {
            cached?.let { return it }
            val prefs = context.applicationContext.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
            val existing = prefs.getString(KEY, null)
            val id = if (!existing.isNullOrBlank()) {
                existing
            } else {
                UUID.randomUUID().toString().also { prefs.edit().putString(KEY, it).apply() }
            }
            cached = id
            return id
        }
    }
}
