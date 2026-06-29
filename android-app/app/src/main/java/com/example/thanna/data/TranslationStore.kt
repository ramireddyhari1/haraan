package com.example.thanna.data

import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue

/**
 * App-wide overlay of server-managed translations for the active locale. Compose
 * state so screens recompose when the bundle loads. Use [t] to look up a key
 * with a built-in fallback — if the key isn't in the overlay (or before the
 * fetch), the fallback (typically the bundled strings.xml value) is used.
 */
object TranslationStore {
    private var bundle by mutableStateOf<Map<String, String>>(emptyMap())

    fun update(map: Map<String, String>) {
        bundle = map
    }

    /** Server value for [key], or [fallback] when not overridden. */
    fun t(key: String, fallback: String): String = bundle[key] ?: fallback
}
