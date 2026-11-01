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

    private val _inbox = MutableSharedFlow<Unit>(extraBufferCapacity = 8)

    /**
     * "Your inbox moved" — a message landed in, or was unsent from, one of your threads. The
     * chat list reloads on it. Carries nothing: the frame comes over a public channel.
     */
    val inbox: SharedFlow<Unit> = _inbox

    fun emitInbox() {
        _inbox.tryEmit(Unit)
    }

    /** Someone is typing in [conversationId]. [senderId] is the typist's user id. */
    data class Typing(val conversationId: String, val senderId: String)

    private val _typing = MutableSharedFlow<Typing>(extraBufferCapacity = 16)

    /**
     * "Typing…" signals, from the open thread's channel and from the inbox channel alike. They
     * have no "stopped" partner: listeners time each one out themselves, so a lost frame can
     * never leave the dots running.
     */
    val typing: SharedFlow<Typing> = _typing

    fun emitTyping(conversationId: String, senderId: String) {
        _typing.tryEmit(Typing(conversationId, senderId))
    }
}
