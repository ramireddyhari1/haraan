package com.haraan.app.push

/**
 * Where a notification's `deep_link` string should take the user inside the app.
 *
 * Kept intentionally small and robust — the targets the app can always reach from
 * anywhere (main tabs + the bell inbox). Entity links (a specific event/venue) are
 * not modelled yet because those screens need a fully-hydrated object, not just an
 * id; add them here (plus a fetch) when that routing is built out.
 */
sealed interface DeepLinkTarget {
    /** Open the in-app bell inbox. */
    data object Inbox : DeepLinkTarget

    /** Home / the Events tab. */
    data object Events : DeepLinkTarget

    /** The GameHub (matches) tab. */
    data object GameHub : DeepLinkTarget
}

/** Parses a `deep_link` payload into a [DeepLinkTarget]. HTTP(S) URLs are handled */
/** externally by MainActivity and never reach here. Unknown links return null. */
object DeepLinks {
    fun parse(raw: String?): DeepLinkTarget? {
        val link = raw?.trim()?.lowercase() ?: return null
        if (link.isEmpty()) return null

        // Normalise "haraan://events", "/events", "events" to a bare keyword.
        val key = link
            .removePrefix("haraan://")
            .trim('/')
            .substringBefore('/')
            .substringBefore('?')

        return when (key) {
            "notifications", "inbox", "bell" -> DeepLinkTarget.Inbox
            "events", "home", "" -> DeepLinkTarget.Events
            "gamehub", "matches", "play" -> DeepLinkTarget.GameHub
            else -> null
        }
    }

    /** True when the payload is a web URL MainActivity should open in a browser. */
    fun isWebUrl(raw: String?): Boolean {
        val link = raw?.trim()?.lowercase() ?: return false
        return link.startsWith("http://") || link.startsWith("https://")
    }
}
