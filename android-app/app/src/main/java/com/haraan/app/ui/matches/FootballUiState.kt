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
    /** Head-to-head match stats (shots, corners, fouls…), grouped and server-assembled. */
    val stats: MatchStats? = null,
    /** The scorer's running clock, as an anchor this phone ticks forward itself. */
    val clock: LiveClock? = null,
    /** Possession from the scorer's "ball with" taps. Null until a minute was tracked. */
    val possession: Possession? = null,
    /** Assists per player, from the assister recorded on each goal. */
    val assists: List<AssistLine> = emptyList(),
) {
    /**
     * The clock label right now. With a live anchor it ticks every second on its own; without
     * one it falls back to the last recorded minute, never behind the latest event.
     */
    fun clockLabelAt(isLive: Boolean, nowMs: Long): String {
        if (!isLive) return "Full time"
        val c = clock ?: return clockLabel(true)
        if (c.phase == "half_time") return "Half time"
        val minute = maxOf(c.elapsedSecAt(nowMs) / 60 + 1, latestEventMinute ?: 0)
        val regulationEnd = c.halfLength * c.half
        return if (minute > regulationEnd) "$regulationEnd'+${minute - regulationEnd}" else "$minute'"
    }

    fun cardsFor(side: String, kind: String): Int = timeline.count { it.side == side && it.kind == kind }

    /**
     * The latest minute recorded on the timeline (a goal at 90' proves the match reached
     * 90'), so the hero clock can never read behind a recorded event.
     */
    internal val latestEventMinute: Int?
        get() = timeline.mapNotNull { it.minuteLabel?.takeWhile { c -> c.isDigit() }?.toIntOrNull() }.maxOrNull()

    /** "67'" · "HT" · "FT" — whatever the hero should show under the score. */
    fun clockLabel(isLive: Boolean): String {
        if (!isLive) return "Full time"
        // Never behind the last event: an 85' goal with a stale 82' clock reads as broken.
        val base = maxOf(clockMin ?: 0, latestEventMinute ?: 0)
        return when {
            base > 0 && added != null && added > 0 -> "$base'+$added"
            base > 0 -> "$base'"
            half != null -> "Half $half"
            else -> "Live"
        }
    }

    val goals: List<FootballEvent> get() = timeline.filter { it.kind == "goal" || it.kind == "own_goal" }
    val cards: List<FootballEvent> get() = timeline.filter { it.kind == "yellow" || it.kind == "red" }
}

/**
 * The match clock as the scorer last pushed it: elapsed seconds at an anchor instant, and
 * whether it is running. [skewMs] corrects this phone's clock against the server's.
 */
data class LiveClock(
    val half: Int,
    val running: Boolean,
    val baseSec: Int,
    val anchorMs: Long,
    val halfLength: Int,
    val phase: String,
    val skewMs: Long,
) {
    fun elapsedSecAt(nowMs: Long): Int {
        if (!running || anchorMs <= 0) return baseSec
        val serverNow = nowMs + skewMs
        return baseSec + ((serverNow - anchorMs) / 1000).toInt().coerceAtLeast(0)
    }
}

data class Possession(val home: Int, val away: Int, val trackedSec: Int, val current: String?)

data class AssistLine(val side: String, val name: String, val assists: Int)

/** "Rahul 12', 45'" — one row per scorer, not one per goal. */
data class ScorerLine(val name: String, val minutes: List<String>) {
    val label: String get() = if (minutes.isEmpty()) name else "$name ${minutes.joinToString(", ")}"
}

/** One head-to-head stat: a label and the two sides' tallies. */
data class MatchStatRow(val label: String, val home: Int, val away: Int)

/** A titled group of stats (e.g. "Attacking") as the detail screen renders them. */
data class MatchStatGroup(val title: String, val rows: List<MatchStatRow>)

/**
 * The match's head-to-head stats. [hasAny] is false until the scorer has tracked at
 * least one counting stat, so the screen can show an honest empty state instead of a
 * wall of zeroes.
 */
data class MatchStats(val hasAny: Boolean, val groups: List<MatchStatGroup>)

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

    fun parseStats(o: JSONObject?): MatchStats? {
        if (o == null) return null
        val groupsArr = o.optJSONArray("groups") ?: return MatchStats(o.optBoolean("has_any", false), emptyList())
        val groups = buildList {
            for (i in 0 until groupsArr.length()) {
                val g = groupsArr.optJSONObject(i) ?: continue
                val rowsArr = g.optJSONArray("rows")
                val rows = buildList {
                    if (rowsArr != null) for (j in 0 until rowsArr.length()) {
                        val r = rowsArr.optJSONObject(j) ?: continue
                        add(MatchStatRow(r.optString("label"), r.optInt("home", 0), r.optInt("away", 0)))
                    }
                }
                add(MatchStatGroup(g.optString("title"), rows))
            }
        }
        return MatchStats(o.optBoolean("has_any", false), groups)
    }

    val clock = json.optJSONObject("clock")?.let { c ->
        LiveClock(
            half = c.optInt("half", 1),
            running = c.optBoolean("running"),
            baseSec = c.optInt("base_sec"),
            anchorMs = c.optLong("anchor_ms"),
            halfLength = c.optInt("half_length", 45).coerceAtLeast(1),
            phase = c.optString("phase"),
            skewMs = c.optLong("server_ms").takeIf { it > 0 }?.let { it - System.currentTimeMillis() } ?: 0L,
        )
    }
    val possession = json.optJSONObject("possession")?.let { p ->
        Possession(p.optInt("home"), p.optInt("away"), p.optInt("tracked_sec"), p.optString("current").takeIf { it == "home" || it == "away" })
    }
    val assistsArr = json.optJSONArray("assists")
    val assists = buildList {
        for (i in 0 until (assistsArr?.length() ?: 0)) {
            val a = assistsArr?.optJSONObject(i) ?: continue
            add(AssistLine(a.optString("side"), a.optString("name"), a.optInt("assists")))
        }
    }

    val events = json.optJSONArray("timeline")
    return FootballState(
        clock = clock,
        possession = possession,
        assists = assists,
        stats = parseStats(json.optJSONObject("stats")),
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
