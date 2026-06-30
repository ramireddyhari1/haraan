package com.example.thanna.ui.theme

import androidx.compose.ui.graphics.Color
import com.example.thanna.data.RemoteConfigStore

/**
 * Remote-or-default branding resolved from /control (RemoteConfigStore.theme), with hardcoded
 * fallbacks. Read these inside composables: the underlying config is Compose state, so the UI
 * rebrands automatically when a new config arrives (launch fetch or Reverb `config` signal).
 *
 * Safe by construction — a missing or malformed value falls back to the built-in brand, so the
 * app never shows a blank name or an invalid colour. This is the only place branding is applied,
 * so the full palette stays compile-time; only these flagship brand points are server-driven.
 */
object Brand {
    val name: String
        get() = RemoteConfigStore.theme.appName?.takeIf { it.isNotBlank() } ?: "Haraan"

    val tagline: String
        get() = RemoteConfigStore.theme.tagline?.takeIf { it.isNotBlank() } ?: "Crafting premium experiences"

    /** Primary brand colour (CTAs / progress). Falls back to the GameHub green. */
    val primary: Color
        get() = parseHex(RemoteConfigStore.theme.primaryColor) ?: HaraanColors.GameHubGreen

    /** Accent colour (links / highlights). Falls back to the Events blue. */
    val accent: Color
        get() = parseHex(RemoteConfigStore.theme.accentColor) ?: HaraanColors.EventsBlue

    /** Support WhatsApp number from /control, or null if unset. */
    val supportWhatsapp: String?
        get() = RemoteConfigStore.theme.supportWhatsapp?.takeIf { it.isNotBlank() }

    /** Parse "#RRGGBB" or "#AARRGGBB" (with/without #) → Color; null if missing/malformed. */
    private fun parseHex(hex: String?): Color? {
        val h = hex?.trim()?.removePrefix("#")?.takeIf { it.isNotBlank() } ?: return null
        val v = h.toLongOrNull(16) ?: return null
        return when (h.length) {
            6 -> Color(0xFF000000L or v) // RGB → opaque
            8 -> Color(v)               // ARGB
            else -> null
        }
    }
}
