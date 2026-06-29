package com.example.thanna.data

import android.content.Context

/**
 * Loads app-wide server state at launch: remote config (feature flags + theme)
 * and the translation overlay for the active locale. Best-effort — failures
 * leave the in-memory stores at their defaults, so the app still works offline
 * or against an old backend.
 */
object RemoteBootstrap {
    private val configRepo = RemoteConfigRepository()
    private val i18nRepo = LocalizationRepository()

    suspend fun load(context: Context) {
        val token = TokenStore.getToken(context)

        configRepo.fetch(token)?.let { RemoteConfigStore.update(it) }

        val locale = LanguageManager.getLanguage(context)
        val bundle = i18nRepo.fetchBundle(locale)
        if (bundle.isNotEmpty()) {
            TranslationStore.update(bundle)
        }
    }
}
