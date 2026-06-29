package com.example.thanna.data

import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.SharedFlow

/**
 * App-wide stream of `content.updated` domains from the realtime client. Screens
 * collect this to refetch their own data (e.g. a GameHub screen reacts to "home"
 * or "venues"). Global stores (config/i18n) are refreshed by the client directly;
 * this bus is for screen-level content.
 */
object RealtimeBus {
    private val _updates = MutableSharedFlow<String>(extraBufferCapacity = 16)
    val updates: SharedFlow<String> = _updates

    fun emit(domain: String) {
        _updates.tryEmit(domain)
    }
}
