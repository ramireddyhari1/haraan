package com.haraan.app.ui.devices

import android.app.Application
import androidx.lifecycle.AndroidViewModel
import androidx.lifecycle.viewModelScope
import com.haraan.app.data.AccountStore
import com.haraan.app.data.TokenStore
import com.haraan.app.data.devices.DeviceCheck
import com.haraan.app.data.devices.DeviceIdentity
import com.haraan.app.data.devices.DeviceSessionRepository
import com.haraan.app.data.devices.DeviceState
import com.haraan.app.data.devices.SignedInDevice
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

data class DevicesUiState(
    val loading: Boolean = false,
    val state: DeviceState? = null,
    /** This phone is past the plan's limit — the chooser takes the whole screen. */
    val held: Boolean = false,
    val busyDeviceId: String? = null,
    val error: String? = null,
)

/** One-off outcomes the host acts on. */
sealed interface DevicesEvent {
    /** This phone's session ended (signed out here, or from elsewhere). */
    data class SessionEnded(val message: String) : DevicesEvent
    /** The active token was re-issued bound to this phone; the shell should re-read it. */
    data object TokenReplaced : DevicesEvent
}

/**
 * Drives both the device-limit gate around the signed-in shell and Account → Signed-in devices.
 * Activity-scoped (see the Nav3 note in member-subscriptions), so hosts call [enter] on entry.
 */
class DevicesViewModel(app: Application) : AndroidViewModel(app) {

    private val repo = DeviceSessionRepository()
    private val _state = MutableStateFlow(DevicesUiState())
    val state: StateFlow<DevicesUiState> = _state.asStateFlow()

    private val _events = MutableStateFlow<DevicesEvent?>(null)
    val events: StateFlow<DevicesEvent?> = _events.asStateFlow()

    private var lastCheckAt = 0L

    fun consumeEvent() {
        _events.value = null
    }

    fun enter() {
        _state.value = DevicesUiState(loading = true)
        lastCheckAt = 0L
        refresh(force = true)
    }

    /**
     * Re-check this phone. On resume this runs at most every 30s; a forced check always runs.
     * An older session (no device in its token) is first re-issued bound to this phone.
     */
    fun refresh(force: Boolean = false) {
        val app = getApplication<Application>()
        val token = TokenStore.getSignedInToken(app) ?: run {
            _state.update { it.copy(loading = false) }
            return
        }
        val now = System.currentTimeMillis()
        if (!force && now - lastCheckAt < 30_000) return
        lastCheckAt = now

        viewModelScope.launch {
            if (!DeviceIdentity.tokenHasDevice(token)) {
                val fresh = repo.enroll(app, token)
                if (fresh != null && TokenStore.getToken(app) == token) {
                    AccountStore.replaceActiveToken(app, fresh)
                    _events.value = DevicesEvent.TokenReplaced
                    return@launch
                }
            }
            apply(repo.check(token))
        }
    }

    fun signOut(device: SignedInDevice) {
        val app = getApplication<Application>()
        val token = TokenStore.getSignedInToken(app) ?: return
        _state.update { it.copy(busyDeviceId = device.id, error = null) }
        viewModelScope.launch {
            val result = repo.signOut(token, device.id)
            if (result is DeviceCheck.Unknown) {
                _state.update { it.copy(busyDeviceId = null, error = "Couldn’t sign that device out. Check your connection and try again.") }
                return@launch
            }
            if (result is DeviceCheck.SignedOut && device.thisDevice) {
                _state.update { it.copy(busyDeviceId = null) }
                _events.value = DevicesEvent.SessionEnded("Signed out of this phone.")
                return@launch
            }
            apply(result)
        }
    }

    private fun apply(result: DeviceCheck) {
        when (result) {
            is DeviceCheck.Ok -> _state.update {
                it.copy(loading = false, state = result.state, held = false, busyDeviceId = null)
            }
            is DeviceCheck.Held -> _state.update {
                it.copy(loading = false, state = result.state, held = true, busyDeviceId = null)
            }
            DeviceCheck.SignedOut -> {
                _state.update { it.copy(loading = false, busyDeviceId = null, held = false) }
                _events.value = DevicesEvent.SessionEnded("You were signed out on this phone from another device.")
            }
            // Offline or server trouble: keep whatever we last knew, never lock anyone out on it.
            DeviceCheck.Unknown -> _state.update {
                it.copy(
                    loading = false,
                    busyDeviceId = null,
                    error = if (it.state == null) "Couldn’t load your devices. Pull back in a moment." else null,
                )
            }
        }
    }
}
