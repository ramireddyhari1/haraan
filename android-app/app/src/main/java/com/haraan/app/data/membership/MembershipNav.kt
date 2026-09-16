package com.haraan.app.data.membership

import kotlinx.coroutines.channels.BufferOverflow
import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.SharedFlow
import kotlinx.coroutines.flow.asSharedFlow

/**
 * "Show me the plans" from anywhere — the account card, a locked profile card, a gate the
 * server refused. Screens deep in overlays don't hold the back stack, so they raise this and
 * MainNavigation pushes the Membership destination.
 */
object MembershipNav {
    private val _requests = MutableSharedFlow<Unit>(
        extraBufferCapacity = 1,
        onBufferOverflow = BufferOverflow.DROP_OLDEST,
    )
    val requests: SharedFlow<Unit> = _requests.asSharedFlow()

    fun open() {
        _requests.tryEmit(Unit)
    }

    private val _sportRequests = MutableSharedFlow<Unit>(
        extraBufferCapacity = 1,
        onBufferOverflow = BufferOverflow.DROP_OLDEST,
    )

    /** The advanced-insights sport picker. */
    val sportRequests: SharedFlow<Unit> = _sportRequests.asSharedFlow()

    fun openInsightSports() {
        _sportRequests.tryEmit(Unit)
    }
}
