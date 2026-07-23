package com.haraan.app.data

import android.content.Context

/**
 * Loads app-wide server state: remote config (feature flags + theme + realtime
 * endpoint) and the translation overlay for the active locale. Best-effort —
 * failures leave the in-memory stores at their defaults, so the app still works
 * offline or against an old backend. Individual reloads are also used by the
 * realtime client to refresh a single domain on a `content.updated` signal.
 */
object RemoteBootstrap {
    private val configRepo = RemoteConfigRepository()
    private val i18nRepo = LocalizationRepository()

    suspend fun load(context: Context) {
        reloadConfig(context)
        reloadTranslations(context)
    }

    suspend fun reloadConfig(context: Context) {
        configRepo.fetch(TokenStore.getToken(context))?.let { RemoteConfigStore.update(it) }
    }

    suspend fun reloadTranslations(context: Context) {
        val bundle = i18nRepo.fetchBundle(LanguageManager.getLanguage(context))
        if (bundle.isNotEmpty()) {
            TranslationStore.update(bundle)
        }
    }
}
