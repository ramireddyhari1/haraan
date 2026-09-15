package com.haraan.app.ui.matches

import org.json.JSONArray
import org.json.JSONObject

/**
 * The scoreboard for every sport that isn't cricket or football — volleyball, basketball,
 * kabaddi, tennis, table tennis and badminton.
 *
 * One state class rather than six, because the server has already replayed the event log
 * through each sport's own rules: the serve in tennis and table tennis, a tie-break, the
 * kabaddi mat, a basketball box score. The screens render that; nothing on the phone adds
 * anything up, so a board on two phones cannot disagree, and a situation the log cannot
 * prove (an unknown server, an untracked stat) arrives as null and is simply not drawn.
 */
data class SportBoard(
    /** Normalised key: volleyball, basketball, kabaddi, tennis, table_tennis, badminton. */
    val sport: String,
    /** "sets" | "tennis" | "points" — which scoring family the server used. */
    val family: String,
    /** Finished sets/games, oldest first: [[25, 20], [23, 25]]. */
    val sets: List<Pair<Int, Int>> = emptyList(),
    /** Tennis: the tie-break loser's points per finished set, null where there was none. */
    val tiebreaks: List<Int?> = emptyList(),
    /** Points in the set being played right now. Null once the match is decided. */
    val current: Pair<Int, Int>? = null,
    /** Tennis only: games in the current set. */
    val games: Pair<Int, Int>? = null,
    /** Tennis only: the point column as labels — "40", "AD", or tie-break numbers. */
    val points: Pair<String, String>? = null,
    /** Points that take the set in progress (25, 21, 11 — or 15 in a volleyball decider). */
    val target: Int = 0,
    /** Badminton's hard cap (30), where win-by-two stops applying. */
    val cap: Int? = null,
    val bestOf: Int = 3,
    /** "Set" for most, "Game" for badminton — the sport's own word. */
    val setNoun: String = "Set",
    /** "home" | "away" | null — who serves the next point. Null when the log can't prove it. */
    val serving: String? = null,
    /** Table tennis: serves left in this server's turn (2 or 1). */
    val servesLeft: Int? = null,
    /** Badminton: "right" | "left" — the court the server serves from. */
    val serviceCourt: String? = null,
    val decider: Boolean = false,
    val deuce: Boolean = false,
    /** Tennis: who holds the advantage, if anyone. */
    val advantage: String? = null,
    val tiebreak: Boolean = false,
    /** Badminton at 29–29: the next rally takes the game. */
    val goldenPoint: Boolean = false,
    /** Tennis: break points on the point about to be played (0–40 is three). */
    val breakPoints: Int = 0,
    val setPoint: String? = null,
    val matchPoint: String? = null,
    val decided: Boolean = false,
    /** Volleyball: rotation 1–6 per side, in the current set. */
    val rotation: Pair<Int, Int>? = null,
    /** Timeouts used in the current set (volleyball, TT) / half (basketball) / match (kabaddi). */
    val timeouts: Pair<Int, Int>? = null,
    val timeoutsAllowed: Int? = null,
    /** Points family: which period, and its label ("Q3", "OT", "2nd half"). */
    val period: Int = 1,
    val periodLabel: String = "",
    /** Points family: per-period splits, oldest first. */
    val periods: List<Pair<Int, Int>> = emptyList(),
    val regulationPeriods: Int? = null,
    /** Basketball: team fouls in the current quarter. */
    val teamFouls: Pair<Int, Int>? = null,
    /** Basketball: an unanswered scoring run of 6+ points still going. */
    val run: ScoringRun? = null,
    /** Basketball: the box score. */
    val box: BoxScore? = null,
    /** Kabaddi (mat scoring only): players on the mat, and the team size. */
    val mat: KabaddiMat? = null,
    /** Kabaddi: which side raids next. */
    val raiding: String? = null,
    val doOrDie: Boolean = false,
    /** Kabaddi: consecutive empty raids per side (the third raid is do-or-die). */
    val emptyStreak: Pair<Int, Int>? = null,
    /** Kabaddi: true when the match is scored on the mat (raid outcomes, auto all-outs). */
    val matRules: Boolean = false,
    /** Kabaddi: each named player's raid, bonus and tackle points. */
    val kabaddiPlayers: List<KabaddiPlayer> = emptyList(),
    /** Per-side counting stats the sport's replay kept (aces, break points, raids…). */
    val teamStats: TeamStats? = null,
    /** Who has scored and how much, best first. Empty when nobody was named. */
    val scorers: List<BoardScorer> = emptyList(),
    /** The last 60 moments, newest first — points, timeouts, box-score events, raids. */
    val feed: List<BoardMoment> = emptyList(),
) {
    val isSetSport: Boolean get() = family == "sets" || family == "tennis"
    val isPointsSport: Boolean get() = family == "points"

    /** Sets needed to take the match — best of 5 is won at 3. */
    val setsToWin: Int get() = (bestOf / 2) + 1

    val setsHome: Int get() = sets.count { it.first > it.second }
    val setsAway: Int get() = sets.count { it.second > it.first }

    /** Scoring moments only — what a run-of-play strip or a points total reads. */
    val pointMoments: List<BoardMoment> get() = feed.filter { it.kind == "point" && it.value > 0 }

    /** The running total for points sports (basketball, kabaddi). */
    val totals: Pair<Int, Int> get() = periods.sumOf { it.first } to periods.sumOf { it.second }
}

data class BoardScorer(val side: String, val name: String, val points: Int)

data class KabaddiPlayer(
    val side: String,
    val name: String,
    val raidPoints: Int,
    val bonusPoints: Int,
    val tacklePoints: Int,
    val raids: Int,
    val superRaids: Int,
    val superTackles: Int,
) {
    val total: Int get() = raidPoints + bonusPoints + tacklePoints
}

data class ScoringRun(val side: String, val points: Int)

data class KabaddiMat(val size: Int, val home: Int, val away: Int)

/** One player's basketball line. */
data class BoxLine(
    val side: String,
    val name: String,
    val pts: Int,
    val fg2: Int,
    val fg3: Int,
    val ft: Int,
    val reb: Int,
    val ast: Int,
    val stl: Int,
    val blk: Int,
    val pf: Int,
    val to: Int,
)

data class BoxScore(val home: BoxLine, val away: BoxLine, val players: List<BoxLine>)

/** Counting stats per side, keyed exactly as the server names them. */
data class TeamStats(val home: Map<String, Int>, val away: Map<String, Int>) {
    fun home(key: String): Int = home[key] ?: 0
    fun away(key: String): Int = away[key] ?: 0
    fun has(key: String): Boolean = home(key) > 0 || away(key) > 0
}

/** One recorded moment and what it meant, annotated by the server's replay. */
data class BoardMoment(
    val sequence: Int,
    val side: String,
    val kind: String,
    val detail: String,
    val player: String,
    val homeScore: Int,
    val awayScore: Int,
    /** Points it put on the board for its side (an all-out bonus included). */
    val value: Int,
    /** What made it notable: three, super_raid, super_tackle, all_out, break, set, deuce… */
    val tags: List<String> = emptyList(),
    /** The score in the sport's own live unit at that moment: "24–22", "40–30", "5–4". */
    val line: String = "",
    val related: String = "",
    val period: Int? = null,
    val setIndex: Int? = null,
    val server: String? = null,
    val mat: Pair<Int, Int>? = null,
) {
    fun has(tag: String): Boolean = tag in tags
}

private fun JSONObject.nullableString(key: String): String? =
    optString(key).takeIf { has(key) && !isNull(key) && it.isNotBlank() && it != "null" }

private fun JSONObject.sideOrNull(key: String): String? =
    nullableString(key)?.takeIf { it == "home" || it == "away" }

private fun pairOf(arr: JSONArray?): Pair<Int, Int>? =
    if (arr == null || arr.length() < 2) null else arr.optInt(0) to arr.optInt(1)

private fun statMap(o: JSONObject?): Map<String, Int> {
    if (o == null) return emptyMap()
    return buildMap { o.keys().forEach { k -> put(k, o.optInt(k)) } }
}

private fun boxLine(o: JSONObject, side: String, name: String): BoxLine = BoxLine(
    side = side,
    name = name,
    pts = o.optInt("pts"), fg2 = o.optInt("fg2"), fg3 = o.optInt("fg3"), ft = o.optInt("ft"),
    reb = o.optInt("reb"), ast = o.optInt("ast"), stl = o.optInt("stl"), blk = o.optInt("blk"),
    pf = o.optInt("pf"), to = o.optInt("to"),
)

/** Parse the `board` object from /api/live-matches/{id}. Absent → null, never a fake board. */
fun parseSportBoard(o: JSONObject?): SportBoard? {
    if (o == null) return null
    val sport = o.optString("sport").ifBlank { return null }

    val setsArr = o.optJSONArray("sets")
    val sets = buildList {
        for (i in 0 until (setsArr?.length() ?: 0)) pairOf(setsArr?.optJSONArray(i))?.let { add(it) }
    }
    val tbArr = o.optJSONArray("tiebreaks")
    val tiebreaks = buildList {
        for (i in 0 until (tbArr?.length() ?: 0)) add(if (tbArr!!.isNull(i)) null else tbArr.optInt(i))
    }
    val periodsArr = o.optJSONArray("periods")
    val periods = buildList {
        for (i in 0 until (periodsArr?.length() ?: 0)) pairOf(periodsArr?.optJSONArray(i))?.let { add(it) }
    }
    val pointsArr = o.optJSONArray("points")
    val points = if (pointsArr != null && pointsArr.length() >= 2) {
        pointsArr.optString(0) to pointsArr.optString(1)
    } else {
        null
    }
    val scorersArr = o.optJSONArray("scorers")
    val scorers = buildList {
        for (i in 0 until (scorersArr?.length() ?: 0)) {
            val s = scorersArr?.optJSONObject(i) ?: continue
            val name = s.optString("name")
            if (name.isNotBlank()) add(BoardScorer(s.optString("side"), name, s.optInt("points")))
        }
    }

    val box = o.optJSONObject("box")?.let { b ->
        val team = b.optJSONObject("team")
        val playersArr = b.optJSONArray("players")
        BoxScore(
            home = boxLine(team?.optJSONObject("home") ?: JSONObject(), "home", ""),
            away = boxLine(team?.optJSONObject("away") ?: JSONObject(), "away", ""),
            players = buildList {
                for (i in 0 until (playersArr?.length() ?: 0)) {
                    val p = playersArr?.optJSONObject(i) ?: continue
                    val name = p.optString("name")
                    if (name.isNotBlank()) add(boxLine(p, p.optString("side"), name))
                }
            },
        )
    }

    val mat = o.optJSONObject("mat")?.let { m ->
        val on = m.optJSONArray("on")
        KabaddiMat(m.optInt("size", 7), on?.optInt(0) ?: 7, on?.optInt(1) ?: 7)
    }

    val teamStats = o.optJSONObject("team_stats")?.let {
        TeamStats(statMap(it.optJSONObject("home")), statMap(it.optJSONObject("away")))
    }

    val kpArr = o.optJSONArray("kabaddi_players")
    val kabaddiPlayers = buildList {
        for (i in 0 until (kpArr?.length() ?: 0)) {
            val p = kpArr?.optJSONObject(i) ?: continue
            val name = p.optString("name")
            if (name.isBlank()) continue
            add(
                KabaddiPlayer(
                    side = p.optString("side"), name = name,
                    raidPoints = p.optInt("raid_points"), bonusPoints = p.optInt("bonus_points"),
                    tacklePoints = p.optInt("tackle_points"), raids = p.optInt("raids"),
                    superRaids = p.optInt("super_raids"), superTackles = p.optInt("super_tackles"),
                )
            )
        }
    }

    val run = o.optJSONObject("run")?.let { r ->
        r.sideOrNull("side")?.let { ScoringRun(it, r.optInt("points")) }
    }

    val feedArr = o.optJSONArray("feed")
    val feed = buildList {
        for (i in 0 until (feedArr?.length() ?: 0)) {
            val f = feedArr?.optJSONObject(i) ?: continue
            val tagsArr = f.optJSONArray("tags")
            add(
                BoardMoment(
                    sequence = f.optInt("sequence"),
                    side = f.nullableString("side") ?: "",
                    kind = f.optString("kind"),
                    detail = f.nullableString("detail") ?: "",
                    player = f.nullableString("player") ?: "",
                    homeScore = f.optInt("home_score"),
                    awayScore = f.optInt("away_score"),
                    value = f.optInt("value"),
                    tags = buildList { for (j in 0 until (tagsArr?.length() ?: 0)) add(tagsArr!!.optString(j)) },
                    line = f.nullableString("line") ?: "",
                    related = f.nullableString("related") ?: "",
                    period = if (f.isNull("period")) null else f.optInt("period").takeIf { f.has("period") },
                    setIndex = if (f.isNull("set_index")) null else f.optInt("set_index").takeIf { f.has("set_index") },
                    server = f.sideOrNull("server"),
                    mat = pairOf(f.optJSONArray("mat")),
                )
            )
        }
    }

    return SportBoard(
        sport = sport,
        family = o.optString("family", "sets"),
        sets = sets,
        tiebreaks = tiebreaks,
        current = pairOf(o.optJSONArray("current")),
        games = pairOf(o.optJSONArray("games")),
        points = points,
        target = o.optInt("target"),
        cap = if (o.isNull("cap")) null else o.optInt("cap").takeIf { o.has("cap") },
        bestOf = o.optInt("best_of", 3),
        setNoun = o.optString("set_noun", "Set").ifBlank { "Set" },
        serving = o.sideOrNull("serving"),
        servesLeft = if (o.isNull("serves_left")) null else o.optInt("serves_left").takeIf { o.has("serves_left") && it > 0 },
        serviceCourt = o.nullableString("service_court"),
        decider = o.optBoolean("decider"),
        deuce = o.optBoolean("deuce"),
        advantage = o.sideOrNull("advantage"),
        tiebreak = o.optBoolean("tiebreak"),
        goldenPoint = o.optBoolean("golden_point"),
        breakPoints = o.optInt("break_points"),
        setPoint = o.sideOrNull("set_point"),
        matchPoint = o.sideOrNull("match_point"),
        decided = o.optBoolean("decided"),
        rotation = pairOf(o.optJSONArray("rotation")),
        timeouts = pairOf(o.optJSONArray("timeouts")),
        timeoutsAllowed = if (o.isNull("timeouts_allowed")) null else o.optInt("timeouts_allowed").takeIf { o.has("timeouts_allowed") },
        period = o.optInt("period", 1),
        periodLabel = o.nullableString("period_label") ?: "",
        periods = periods,
        regulationPeriods = if (o.isNull("regulation_periods")) null else o.optInt("regulation_periods").takeIf { o.has("regulation_periods") },
        teamFouls = pairOf(o.optJSONArray("team_fouls")),
        run = run,
        box = box,
        mat = mat,
        raiding = o.sideOrNull("raiding"),
        doOrDie = o.optBoolean("do_or_die"),
        emptyStreak = pairOf(o.optJSONArray("empty_streak")),
        matRules = o.optBoolean("mat_rules"),
        kabaddiPlayers = kabaddiPlayers,
        teamStats = teamStats,
        scorers = scorers,
        feed = feed,
    )
}

/** How each sport reads on screen. Labels only — the scoring itself is the server's. */
object SportLook {
    fun displayName(sport: String): String = when (sport.lowercase()) {
        "volleyball" -> "Volleyball"
        "basketball" -> "Basketball"
        "kabaddi" -> "Kabaddi"
        "tennis" -> "Tennis"
        "table_tennis" -> "Table Tennis"
        "badminton" -> "Badminton"
        else -> sport.replaceFirstChar { it.uppercase() }
    }

    /**
     * The one-tap score buttons for a sport, as (label, detail, points). Kabaddi's raid
     * builder and basketball's stat row are drawn by the scorer itself; this is the plain
     * "a point happened" row.
     */
    fun scoreButtons(sport: String): List<Triple<String, String, Int>> = when (sport.lowercase()) {
        "basketball" -> listOf(
            Triple("+2", "2", 2),
            Triple("+3", "3", 3),
            Triple("FT", "1", 1),
        )
        "kabaddi" -> listOf(
            Triple("Raid", "raid", 1),
            Triple("Tackle", "tackle", 1),
            Triple("Bonus", "bonus", 1),
            Triple("All out", "all_out", 2),
        )
        else -> listOf(Triple("Point", "", 1))
    }

    /** What a moment in a feed reads as, in the sport's own words. */
    fun momentLabel(sport: String, m: BoardMoment): String {
        val s = sport.lowercase()
        return when (m.kind) {
            "period" -> if (s == "kabaddi") "Half time" else "End of ${if (m.period != null) "Q${m.period - 1}" else "period"}"
            "timeout" -> "Timeout"
            "serve" -> if (s == "kabaddi") "First raid" else "Serve"
            "raid" -> if (m.has("do_or_die")) "Do-or-die raid, empty" else "Empty raid"
            "rebound" -> "Rebound"
            "assist" -> "Assist"
            "steal" -> "Steal"
            "block" -> "Block"
            "foul" -> if (m.has("fouled_out")) "Foul — fouled out" else "Personal foul"
            "turnover" -> "Turnover"
            else -> when (s) {
                "basketball" -> when (m.detail) {
                    "3" -> "Three-pointer"
                    "1" -> "Free throw"
                    else -> "Two-pointer"
                }
                "kabaddi" -> kabaddiLabel(m)
                "tennis" -> when {
                    m.has("ace") -> "Ace"
                    m.has("double_fault") -> "Double fault"
                    m.has("winner") -> "Winner"
                    m.has("error") -> "Unforced error"
                    else -> "Point"
                }
                else -> when {
                    m.has("ace") -> "Ace"
                    m.detail == "error" -> "Error"
                    else -> "Point"
                }
            }
        }
    }

    private fun kabaddiLabel(m: BoardMoment): String {
        val d = m.detail.lowercase()
        return when {
            m.has("super_tackle") -> "Super tackle"
            d == "tackle" || d == "super_tackle" -> "Tackle"
            d == "dod_out" -> "Do-or-die, raider out"
            m.has("super_raid") -> "Super raid"
            d.startsWith("raid:") -> {
                val parts = d.split(":")
                val touches = parts.getOrNull(1)?.toIntOrNull() ?: 1
                val bonus = parts.drop(2).contains("b")
                when {
                    touches == 0 && bonus -> "Bonus point"
                    bonus -> "$touches-point raid + bonus"
                    else -> "$touches-point raid"
                }
            }
            d == "bonus" -> "Bonus point"
            d == "all_out" -> "All out"
            d == "super_raid" -> "Super raid"
            d == "technical" -> "Technical point"
            else -> "Raid point"
        }
    }
}
