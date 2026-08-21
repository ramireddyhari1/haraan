package com.haraan.app.ui.matches

import org.json.JSONArray
import org.json.JSONObject

/**
 * The scoreboard for every sport that isn't cricket or football — volleyball, basketball,
 * kabaddi, tennis and table tennis.
 *
 * One state class rather than five, because the five don't differ five ways. They differ in
 * which FAMILY of scoring they use, and the server has already decided that and done the
 * counting: a volleyball set list and a tennis set list are the same list; a basketball
 * quarter line and a kabaddi half line are the same line. The screen renders shape, not
 * sport — the sport only decides labels and which scorer buttons appear.
 *
 * Every number here was derived server-side from the recorded events. Nothing on the phone
 * adds anything up, so a board on two phones cannot disagree.
 */
data class SportBoard(
    /** Normalised key: volleyball, basketball, kabaddi, tennis, table_tennis. */
    val sport: String,
    /** "sets" | "tennis" | "points" — which of the three boards to draw. */
    val family: String,
    /** Finished sets/games, oldest first: [[25, 20], [23, 25]]. */
    val sets: List<Pair<Int, Int>> = emptyList(),
    /** Points in the set being played right now. Null once the match is decided. */
    val current: Pair<Int, Int>? = null,
    /** Tennis only: games in the current set. */
    val games: Pair<Int, Int>? = null,
    /** Tennis only: the point ladder as labels — "40", "AD". */
    val points: Pair<String, String>? = null,
    /** Points that take the set in progress (25, 21, 11 — or 15 in a decider). */
    val target: Int = 0,
    val bestOf: Int = 3,
    /** "Set" for most, "Game" for badminton — the sport's own word. */
    val setNoun: String = "Set",
    /** "home" | "away" | null — whoever won the last rally serves next. */
    val serving: String? = null,
    /** Points family: which period, and its label ("Q3", "2nd half"). */
    val period: Int = 1,
    val periodLabel: String = "",
    /** Points family: per-period splits, oldest first. */
    val periods: List<Pair<Int, Int>> = emptyList(),
    /** Who has scored and how much, best first. Empty when nobody was named. */
    val scorers: List<BoardScorer> = emptyList(),
    /** The last 40 scoring moments, newest first. */
    val feed: List<BoardMoment> = emptyList(),
) {
    val isSetSport: Boolean get() = family == "sets" || family == "tennis"
    val isPointsSport: Boolean get() = family == "points"

    /** Sets needed to take the match — best of 5 is won at 3. */
    val setsToWin: Int get() = (bestOf / 2) + 1
}

data class BoardScorer(val side: String, val name: String, val points: Int)

/** One recorded moment: a point (with what it was worth) or a period change. */
data class BoardMoment(
    val sequence: Int,
    val side: String,
    val kind: String,
    val detail: String,
    val player: String,
    val homeScore: Int,
    val awayScore: Int,
    val value: Int,
)

/** Parse the `board` object from /api/live-matches/{id}. Absent → null, never a fake board. */
fun parseSportBoard(o: JSONObject?): SportBoard? {
    if (o == null) return null
    val sport = o.optString("sport").ifBlank { return null }

    fun pair(arr: JSONArray?): Pair<Int, Int>? =
        if (arr == null || arr.length() < 2) null else arr.optInt(0) to arr.optInt(1)

    val setsArr = o.optJSONArray("sets")
    val sets = buildList {
        for (i in 0 until (setsArr?.length() ?: 0)) {
            pair(setsArr?.optJSONArray(i))?.let { add(it) }
        }
    }
    val periodsArr = o.optJSONArray("periods")
    val periods = buildList {
        for (i in 0 until (periodsArr?.length() ?: 0)) {
            pair(periodsArr?.optJSONArray(i))?.let { add(it) }
        }
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
    val feedArr = o.optJSONArray("feed")
    val feed = buildList {
        for (i in 0 until (feedArr?.length() ?: 0)) {
            val f = feedArr?.optJSONObject(i) ?: continue
            add(
                BoardMoment(
                    sequence = f.optInt("sequence"),
                    side = f.optString("side"),
                    kind = f.optString("kind"),
                    detail = f.optString("detail").takeIf { it != "null" } ?: "",
                    player = f.optString("player").takeIf { it != "null" } ?: "",
                    homeScore = f.optInt("home_score"),
                    awayScore = f.optInt("away_score"),
                    value = f.optInt("value"),
                )
            )
        }
    }

    return SportBoard(
        sport = sport,
        family = o.optString("family", "sets"),
        sets = sets,
        current = pair(o.optJSONArray("current")),
        games = pair(o.optJSONArray("games")),
        points = points,
        target = o.optInt("target"),
        bestOf = o.optInt("best_of", 3),
        setNoun = o.optString("set_noun", "Set").ifBlank { "Set" },
        serving = o.optString("serving").takeIf { it == "home" || it == "away" },
        period = o.optInt("period", 1),
        periodLabel = o.optString("period_label"),
        periods = periods,
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
     * The buttons a scorer gets for this sport, as (label, detail, points) — detail is
     * what the server stores, and it decides what the point was worth. A sport whose
     * points are all worth one gets a single button per side.
     */
    fun scoreButtons(sport: String): List<Triple<String, String, Int>> = when (sport.lowercase()) {
        "basketball" -> listOf(
            Triple("+2", "2", 2),
            Triple("+3", "3", 3),
            Triple("Free throw", "1", 1),
        )
        "kabaddi" -> listOf(
            Triple("Raid", "raid", 1),
            Triple("Tackle", "tackle", 1),
            Triple("Bonus", "bonus", 1),
            Triple("All out", "all_out", 2),
        )
        else -> listOf(Triple("Point", "", 1))
    }

    /** What a moment in the feed reads as. */
    fun momentLabel(sport: String, m: BoardMoment): String = when {
        m.kind == "period" -> "Period change"
        sport.equals("basketball", true) -> when (m.detail) {
            "3" -> "Three-pointer"
            "1" -> "Free throw"
            else -> "Field goal"
        }
        sport.equals("kabaddi", true) -> when (m.detail) {
            "tackle" -> "Tackle point"
            "bonus" -> "Bonus point"
            "all_out" -> "All out"
            "super_raid" -> "Super raid"
            else -> "Raid point"
        }
        else -> "Point"
    }
}
