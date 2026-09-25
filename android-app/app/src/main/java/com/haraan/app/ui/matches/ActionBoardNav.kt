package com.haraan.app.ui.matches

import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow

/**
 * "Open the create-match wizard" from outside the ActionBoard (e.g. "Play again" on the
 * post-match screen). A StateFlow, not an event, so the request survives until the ActionBoard
 * has composed and can act on it — then it's consumed.
 */
object ActionBoardNav {
    private val _createRequested = MutableStateFlow(false)
    val createRequested: StateFlow<Boolean> = _createRequested

    fun requestCreate() {
        _createRequested.value = true
    }

    fun consumeCreate() {
        _createRequested.value = false
    }
}
