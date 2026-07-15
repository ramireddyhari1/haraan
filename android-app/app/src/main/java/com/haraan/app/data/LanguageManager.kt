package com.haraan.app.data

import android.content.Context
import android.content.res.Configuration
import java.util.Locale

/**
 * App language (locale) selection, persisted locally and applied by wrapping the
 * Activity's base context. South-Indian languages + Hindi, with English as default.
 * No external dependency — uses createConfigurationContext + recreate().
 */
object LanguageManager {
    private const val PREF = "haraan_lang_prefs"
    private const val KEY = "app_language"

    /** (tag, native display name) — English first, then the supported regional languages. */
    val supported: List<Pair<String, String>> = listOf(
        "en" to "English",
        "te" to "తెలుగు",
        "ta" to "தமிழ்",
        "kn" to "ಕನ್ನಡ",
        "ml" to "മലയാളം",
        "hi" to "हिन्दी",
    )

    fun getLanguage(context: Context): String =
        context.getSharedPreferences(PREF, Context.MODE_PRIVATE).getString(KEY, "en") ?: "en"

    fun displayName(tag: String): String =
        supported.firstOrNull { it.first == tag }?.second ?: "English"

    fun setLanguage(context: Context, tag: String) {
        context.getSharedPreferences(PREF, Context.MODE_PRIVATE).edit().putString(KEY, tag).apply()
    }

    /** Returns a context configured with the saved locale — call from attachBaseContext. */
    fun wrap(context: Context): Context {
        val locale = Locale.forLanguageTag(getLanguage(context))
        Locale.setDefault(locale)
        val config = Configuration(context.resources.configuration)
        config.setLocale(locale)
        return context.createConfigurationContext(config)
    }
}
