package com.haraan.app.ui.matches

import org.json.JSONObject

/**
 * A football match's own state — the half that cricket's [MatchUiState] cannot
 * express.
 *
 * Kept as a separate type hanging off `MatchUiState.football` rather than a dozen
 * nullable fields on the cricket state: a football match has no runs, overs or
 * economy, and pretending otherwise is how a screen ends up with blanked-out
 * cricket furniture.
 */
data class FootballState(
    val half: Int? = null,
    val clockMin: Int? = null,
    val added: Int? = null,
    val homeScorers: List<ScorerLine> = emptyList(),
    val awayScorers: List<ScorerLine> = emptyList(),
    val timeline: List<FootballEvent> = emptyList(),
) {
    /** "67'" · "HT" · "FT" — whatever the hero should show under the score. */
    fun clockLabel(isLive: Boolean): String = when {
        !isLive -> "Full time"
        clockMin != null && added != null && added > 0 -> "$clockMin'+$added"
        clockMin != null -> "$clockMin'"
        half != null -> "Half $half"
        else -> "Live"
    }

    val goals: List<FootballEvent> get() = timeline.filter { it.kind == "goal" || it.kind == "own_goal" }
    val cards: List<FootballEvent> get() = timeline.filter { it.kind == "yellow" || it.kind == "red" }
}

/** "Rahul 12', 45'" — one row per scorer, not one per goal. */
data class ScorerLine(val name: String, val minutes: List<String>) {
    val label: String get() = if (minutes.isEmpty()) name else "$name ${minutes.joinToString(", ")}"
}

/**
 * One entry in the timeline. `headline` is composed server-side so the app, the
 * website and anything later all describe an event identically.
 */
data class FootballEvent(
    val sequence: Int,
    val minuteLabel: String?,
    val side: String?,
    val kind: String,
    val player: String?,
    val related: String?,
    val homeScore: Int?,
    val awayScore: Int?,
    val headline: String,
) {
    val isHome: Boolean get() = side == "home"
    val scoreLabel: String? get() =
        if (homeScore != null && awayScore != null) "$homeScore–$awayScore" else null
}

/** Parse the `football` block of the match-detail payload. Null for cricket. */
fun parseFootball(json: JSONObject?): FootballState? {
    if (json == null) return null

    fun scorers(key: String): List<ScorerLine> {
        val arr = json.optJSONArray(key) ?: return emptyList()
        return buildList {
            for (i in 0 until arr.length()) {
                val o = arr.optJSONObject(i) ?: continue
                val mins = o.optJSONArray("minutes")
                add(
                    ScorerLine(
                        name = o.optString("name", "Unknown"),
                        minutes = buildList {
                            if (mins != null) for (j in 0 until mins.length()) add(mins.optString(j))
                        }.filter { it.isNotBlank() },
                    )
                )
            }
        }
    }

    val events = json.optJSONArray("timeline")
    return FootballState(
        half = json.optInt("half", -1).takeIf { it >= 0 },
        clockMin = json.optInt("clock_min", -1).takeIf { it >= 0 },
        added = json.optInt("added", -1).takeIf { it >= 0 },
        homeScorers = scorers("home_scorers"),
        awayScorers = scorers("away_scorers"),
        timeline = buildList {
            if (events != null) for (i in 0 until events.length()) {
                val o = events.optJSONObject(i) ?: continue
                add(
                    FootballEvent(
                        sequence = o.optInt("sequence", i),
                        minuteLabel = o.optString("minute_label", null)?.takeIf { it.isNotBlank() && it != "null" },
                        side = o.optString("side", null)?.takeIf { it.isNotBlank() && it != "null" },
                        kind = o.optString("kind", ""),
                        player = o.optString("player", null)?.takeIf { it.isNotBlank() && it != "null" },
                        related = o.optString("related", null)?.takeIf { it.isNotBlank() && it != "null" },
                        homeScore = o.optInt("home_score", -1).takeIf { it >= 0 },
                        awayScore = o.optInt("away_score", -1).takeIf { it >= 0 },
                        headline = o.optString("headline", ""),
                    )
                )
            }
        },
    )
}
