package com.haraan.app.data

import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.SharedFlow

/**
 * App-wide stream of venue ids whose bookable hours just changed, pushed over the realtime
 * socket (`venue.availability` on channel `venue.{id}`). The open venue page collects this
 * and re-pulls slot availability — the WebSocket counterpart to its poll, so a slot someone
 * else just took flips to "Booked" in seconds.
 *
 * Carries only the id: the client refetches and the server stays the source of truth.
 */
object VenueRealtimeBus {
    private val _updates = MutableSharedFlow<String>(extraBufferCapacity = 16)
    val updates: SharedFlow<String> = _updates

    fun emit(venueId: String) {
        _updates.tryEmit(venueId)
    }
}
