package com.haraan.app.data

import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.SharedFlow

/**
 * "Conversation {id} moved" — the realtime nudge for chat, mirroring [MatchRealtimeBus].
 *
 * Carries an id and nothing else, because the socket frame carries nothing else: message
 * bodies never travel over the public channel. Whoever is listening refetches through the
 * authenticated endpoint, so the server stays the only thing that decides what was said.
 */
object ChatRealtimeBus {
    private val _updates = MutableSharedFlow<String>(extraBufferCapacity = 32)

    /** Conversation ids, as they change. */
    val updates: SharedFlow<String> = _updates

    fun emit(conversationId: String) {
        _updates.tryEmit(conversationId)
    }
}
