package com.haraan.app.data

import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.SharedFlow

/**
 * App-wide stream of match ids that just changed, pushed over the realtime socket
 * (`match.updated`). The open match-detail screen collects this and refetches the
 * instant its own match changes — the WebSocket counterpart to the 12s poll, giving
 * a live scoreboard that updates the moment the scorer taps rather than on a timer.
 *
 * Carries only the id, never the scoreline: the client re-pulls the detail endpoint
 * and stays the source of truth, so a dropped or reordered frame can't desync it.
 */
object MatchRealtimeBus {
    private val _updates = MutableSharedFlow<String>(extraBufferCapacity = 32)
    val updates: SharedFlow<String> = _updates

    fun emit(matchId: String) {
        _updates.tryEmit(matchId)
    }
}
