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

    /** Open the ActionBoard's create-match wizard: `haraan://actionboard/create`. */
    data object CreateMatch : DeepLinkTarget

    /** A player's post-match rewards: `haraan://rewards/match/{id}`. */
    data class MatchRewards(val matchId: String) : DeepLinkTarget
}

/** Parses a `deep_link` payload into a [DeepLinkTarget]. HTTP(S) URLs are handled */
/** externally by HomeActivity and never reach here. Unknown links return null. */
object DeepLinks {
    fun parse(raw: String?): DeepLinkTarget? {
        val link = raw?.trim()?.lowercase() ?: return null
        if (link.isEmpty()) return null

        REWARDS_MATCH.matchEntire(link)?.let { return DeepLinkTarget.MatchRewards(it.groupValues[1]) }
        if (link.removePrefix("haraan://").trim('/') == "actionboard/create") return DeepLinkTarget.CreateMatch

        // Normalise "haraan://events", "/events", "events" to a bare keyword.
        val key = link
            .removePrefix("haraan://")
            .trim('/')
            .substringBefore('/')
            .substringBefore('?')

        return when (key) {
            "notifications", "inbox", "bell", "rewards" -> DeepLinkTarget.Inbox
            "events", "home", "" -> DeepLinkTarget.Events
            "gamehub", "matches", "play" -> DeepLinkTarget.GameHub
            else -> null
        }
    }

    private val REWARDS_MATCH = Regex("^(?:haraan://)?/?rewards/match/(\\d+)/?$")

    /** True when the payload is a web URL HomeActivity should open in a browser. */
    fun isWebUrl(raw: String?): Boolean {
        val link = raw?.trim()?.lowercase() ?: return false
        return link.startsWith("http://") || link.startsWith("https://")
    }
}
