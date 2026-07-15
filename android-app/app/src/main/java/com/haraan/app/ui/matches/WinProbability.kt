package com.haraan.app.ui.matches

import kotlin.math.exp

/**
 * Single source of truth for the win-probability shown on the Live and Graphs tabs.
 *
 * Priority:
 *  1. If the backend supplied [MatchUiState.winProbability] (0..100 for team1), use it verbatim.
 *  2. Else, during a run chase (we know runs needed / balls left / wickets in hand),
 *     estimate it with a small logistic chase model — truthful enough to move ball-by-ball
 *     as the simulator/live feed updates the equation.
 *  3. Otherwise it's genuinely unknown (e.g. 1st innings with no model) → null, and the UI
 *     shows a "not available yet" state rather than a fabricated number.
 *
 * The returned value is always team1's win percentage (0..100).
 */
object WinProbability {

    /** @return team1's win chance as 0..100, or null when it can't be known. */
    fun estimate(state: MatchUiState): Int? {
        if (state.winProbability in 0..100) return state.winProbability

        val runsNeeded = state.runsNeeded
        val ballsLeft = state.ballsLeft
        if (runsNeeded == null || ballsLeft == null) return null

        val wicketsLost = state.score.substringAfter('/', "").toIntOrNull() ?: 0
        val wicketsInHand = (10 - wicketsLost).coerceIn(0, 10)

        val chasingPct = chaseWinPct(runsNeeded, ballsLeft, wicketsInHand)
        // The chasing side is whichever team is currently batting in the 2nd innings.
        val team1Pct = if (state.battingTeam == 1) chasingPct else 100 - chasingPct
        return team1Pct.coerceIn(1, 99)
    }

    /** Logistic model: balances required run rate against what the wickets in hand can sustain. */
    private fun chaseWinPct(runsNeeded: Int, ballsLeft: Int, wicketsInHand: Int): Int {
        if (ballsLeft <= 0) return if (runsNeeded <= 0) 100 else 0
        if (runsNeeded <= 0) return 100
        if (wicketsInHand <= 0) return 0

        val requiredRate = runsNeeded * 6f / ballsLeft          // runs/over the chase needs
        val sustainableRate = 6.5f + (wicketsInHand - 5) * 0.7f // runs/over those wickets can push
        val edge = sustainableRate - requiredRate               // +ve favours the chasing side
        val p = 1f / (1f + exp(-(0.45f * edge)))
        return (p * 100f).toInt().coerceIn(1, 99)
    }
}
