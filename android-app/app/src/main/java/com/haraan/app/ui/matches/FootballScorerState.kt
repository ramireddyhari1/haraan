package com.haraan.app.ui.matches

import com.haraan.app.data.SquadMember

/**
 * Everything the football scorer needs to open, bundled at create time (mirrors
 * [TossSetup]).
 *
 * Squads are carried because a goal without a scorer is the difference between a
 * tally and a match record — the timeline, the hero's scorer line and any future
 * player stats all hang off knowing who put it in.
 */
data class FootballScorerSetup(
    val matchId: String,
    val teamA: String,
    val teamB: String,
    val teamAEmblem: String = "",
    val teamBEmblem: String = "",
    val formatLabel: String = "",
    val isPrivate: Boolean = false,
    val joinCode: String = "",
    val squadA: List<SquadMember> = emptyList(),
    val squadB: List<SquadMember> = emptyList(),
    /** Who kicks off, decided at the toss. "home" | "away" | "" when unknown. */
    val kickOff: String = "",
    val halfLengthMin: Int = 45,
    // Seed the tallies — 0/0 for a fresh match, or the current score when resuming.
    val initialHome: Int = 0,
    val initialAway: Int = 0,
)

/**
 * The match clock.
 *
 * Kept as elapsed-seconds plus a running flag rather than a wall-clock end time, so
 * pausing at half time and resuming is exact, and so a phone that sleeps mid-half
 * resumes on the minute it left rather than jumping.
 */
data class MatchClock(
    val half: Int = 1,
    val elapsedSec: Int = 0,
    val running: Boolean = false,
    val halfLengthMin: Int = 45,
) {
    val minute: Int get() = elapsedSec / 60

    /** Minutes past the scheduled end of the half — football's added time. */
    val added: Int get() = (minute - halfLengthMin).coerceAtLeast(0)

    /** "12'" or "45'+3" — what goes on an event and in the header. */
    val label: String get() = if (added > 0) "${halfLengthMin}'+$added" else "$minute'"

    val halfLabel: String get() = when (half) {
        1 -> "1st half"
        2 -> "2nd half"
        else -> "Extra time"
    }

    fun tick(): MatchClock = if (running) copy(elapsedSec = elapsedSec + 1) else this

    /** Second half restarts the count but keeps minutes continuous for display. */
    fun startSecondHalf(): MatchClock =
        copy(half = 2, elapsedSec = halfLengthMin * 60, running = true)
}

/** One recorded event, as the scorer's own feed shows it before/after the server settles. */
data class ScorerFeedItem(
    val kind: String,
    val side: String,
    val teamName: String,
    val player: String?,
    val minuteLabel: String,
)
