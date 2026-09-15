package com.haraan.app.ui.matches

import androidx.compose.ui.graphics.Color

/**
 * What each sport considers worth announcing, and which situations it keeps on screen.
 *
 * Both read only the server's replay — the tags on the newest moment and the live flags on
 * the board — so a banner can never announce something the event log does not contain.
 */

private val Amber = Color(0xFFD97706)
private val Crimson = Color(0xFFDC2626)
private val Emerald = Color(0xFF059669)

private fun sideName(state: MatchUiState, side: String?): String = when (side) {
    "home" -> state.team1.ifBlank { "Home" }
    "away" -> state.team2.ifBlank { "Away" }
    else -> ""
}

private fun withPlayer(m: BoardMoment, state: MatchUiState): String =
    listOf(m.player, sideName(state, m.side)).filter { it.isNotBlank() }.joinToString(" · ")

/** The moment to drop into the hero for the newest recorded event, or null if it is routine. */
fun heroMomentFor(board: SportBoard, state: MatchUiState, theme: SportTheme): HeroMoment? {
    val m = board.feed.firstOrNull() ?: return null
    val who = withPlayer(m, state)
    val team = sideName(state, m.side)
    val noun = board.setNoun

    fun moment(word: String, detail: String, color: Color) = HeroMoment(m.sequence, word, detail, color)

    return when (board.sport.lowercase()) {
        "basketball" -> when {
            m.kind == "period" -> moment("End of Q${(m.period ?: 2) - 1}", "${board.totals.first}–${board.totals.second}", theme.deep)
            m.has("three") -> moment("Three", who, theme.spark)
            m.has("fouled_out") -> moment("Fouled out", who, Crimson)
            m.has("penalty") -> moment("Team penalty", "$team · 5 fouls", Amber)
            m.kind == "timeout" -> moment("Timeout", team, theme.deep)
            m.kind == "block" -> moment("Block", who, theme.deep)
            m.kind == "steal" -> moment("Steal", who, theme.deep)
            else -> null
        }
        "kabaddi" -> when {
            // The all-out's two points go to the event's own side — the raider's team or the defence.
            m.has("all_out") -> moment("All out", "+2 · $team", Crimson)
            m.has("super_tackle") -> moment("Super tackle", who, theme.deep)
            m.has("super_raid") -> moment("Super raid", "$who · +${m.value}", Amber)
            m.has("do_or_die") && m.kind == "point" && m.detail != "dod_out" && m.detail != "tackle" ->
                moment("Do-or-die won", who, Emerald)
            m.detail == "dod_out" -> moment("Do-or-die failed", "Raider out · +1 $team", Crimson)
            m.kind == "period" -> moment("Half time", "${board.totals.first}–${board.totals.second}", theme.deep)
            m.kind == "timeout" -> moment("Timeout", team, theme.deep)
            else -> null
        }
        "tennis" -> when {
            m.has("match") -> moment("Game, set & match", team, theme.deep)
            m.has("set") -> moment("Set", "$team · ${board.sets.lastOrNull()?.let { "${it.first}–${it.second}" } ?: ""}", theme.deep)
            m.has("break") -> moment("Break of serve", team, Amber)
            m.has("ace") -> moment("Ace", who, theme.spark)
            m.has("double_fault") -> moment("Double fault", sideName(state, if (m.side == "home") "away" else "home"), Crimson)
            m.has("break_point_saved") -> moment("Break point saved", team, Emerald)
            else -> null
        }
        else -> when {
            m.has("match") -> moment("Match", "$team wins", theme.deep)
            m.has("set") -> moment("$noun ${(m.setIndex ?: 0) + 1}", "$team · ${m.line}", theme.deep)
            m.has("saved") -> moment("$noun point saved", team, Emerald)
            m.has("change_ends") -> moment("Change ends", m.line, theme.deep)
            m.has("interval") -> moment("Interval", m.line, theme.deep)
            m.has("ace") -> moment("Ace", who, theme.spark)
            m.kind == "timeout" -> moment("Timeout", team, theme.deep)
            else -> null
        }
    }
}

/** The standing situations for the board as it is right now. */
fun situationsFor(board: SportBoard, state: MatchUiState): List<Situation> = buildList {
    if (board.decided || !state.isLive) return@buildList
    val noun = board.setNoun
    when (board.sport.lowercase()) {
        "tennis" -> {
            when {
                board.matchPoint != null -> add(Situation("Match point · ${sideName(state, board.matchPoint)}", Crimson))
                board.setPoint != null -> add(Situation("Set point · ${sideName(state, board.setPoint)}", Amber))
            }
            if (board.breakPoints > 0) {
                add(Situation(if (board.breakPoints > 1) "${board.breakPoints} break points" else "Break point", Amber))
            }
            when {
                board.tiebreak -> add(Situation("Tie-break", Color(0xFF3F6212)))
                board.advantage != null -> add(Situation("Advantage ${sideName(state, board.advantage)}", Color(0xFF3F6212)))
                board.deuce -> add(Situation("Deuce", Color(0xFF3F6212)))
            }
        }
        "basketball" -> {
            // Team-foul penalty is drawn by the foul meter itself, so it is not repeated here.
            board.run?.let { add(Situation("${it.points}–0 run · ${sideName(state, it.side)}", Color(0xFF9A3412))) }
        }
        "kabaddi" -> {
            if (board.doOrDie) add(Situation("Do-or-die · ${sideName(state, board.raiding)}", Crimson))
            board.mat?.let { mat ->
                // Only the DEFENDING side can make a super tackle on the raid about to happen.
                val defending = when (board.raiding) { "home" -> "away"; "away" -> "home"; else -> null }
                if (mat.home in 1..3 && defending != "away") add(Situation("Super tackle on · ${sideName(state, "home")}", Color(0xFF4C1D95)))
                if (mat.away in 1..3 && defending != "home") add(Situation("Super tackle on · ${sideName(state, "away")}", Color(0xFF4C1D95)))
            }
        }
        else -> {
            when {
                board.matchPoint != null -> add(Situation("Match point · ${sideName(state, board.matchPoint)}", Crimson))
                board.setPoint != null -> add(Situation("$noun point · ${sideName(state, board.setPoint)}", Amber))
            }
            when {
                board.goldenPoint -> add(Situation("Golden point", Crimson))
                board.deuce -> add(Situation("Deuce", Color(0xFF475569)))
            }
            if (board.decider && board.sport != "badminton") add(Situation("Decider", Color(0xFF475569), live = false))
        }
    }
}
