package com.haraan.app.push

import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow

/**
 * A one-shot channel for a pending deep link, from [com.haraan.app.MainActivity]
 * (which reads it off the launch/tap intent) to the composable that can act on it
 * ([com.haraan.app.ui.main.MainAppContainer], which owns the tab + inbox state).
 *
 * A [StateFlow] rather than a callback so the link survives until something is
 * ready to consume it — e.g. a push tapped while signed out is applied after login,
 * once MainAppContainer first composes.
 */
object DeepLinkState {
    private val _pending = MutableStateFlow<String?>(null)
    val pending: StateFlow<String?> = _pending

    /** Record a deep link to be handled when a consumer is ready. */
    fun set(link: String) {
        _pending.value = link
    }

    /** Clear the pending link once it has been handled. */
    fun consume() {
        _pending.value = null
    }
}
