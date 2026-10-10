package com.haraan.app.ui.devices

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.LifecycleEventObserver
import androidx.lifecycle.compose.LocalLifecycleOwner
import androidx.lifecycle.viewmodel.compose.viewModel
import com.haraan.app.data.TokenStore
import com.haraan.app.ui.membership.MembershipScreen
import com.haraan.app.ui.theme.HaraanColors

/**
 * Wraps the signed-in shell with the plan's device limit.
 *
 * Checks this phone when the session starts and again on every resume (at most every 30s),
 * so a phone signed out from elsewhere, or one past the limit, finds out without the user
 * doing anything. The shell renders while the check runs — a slow network never blocks the
 * app — and an inconclusive check never locks anyone out; the server enforces regardless.
 */
@Composable
fun DeviceGate(
    token: String,
    onSessionEnded: (message: String) -> Unit,
    onTokenReplaced: () -> Unit,
    content: @Composable () -> Unit,
) {
    if (!TokenStore.isSignedIn(token)) {
        content()
        return
    }

    val vm: DevicesViewModel = viewModel(key = "device-gate")
    val ui by vm.state.collectAsState()
    val event by vm.events.collectAsState()
    var showPlans by remember { mutableStateOf(false) }

    LaunchedEffect(token) { vm.enter() }

    val lifecycle = LocalLifecycleOwner.current.lifecycle
    DisposableEffect(lifecycle) {
        val observer = LifecycleEventObserver { _, e -> if (e == Lifecycle.Event.ON_RESUME) vm.refresh() }
        lifecycle.addObserver(observer)
        onDispose { lifecycle.removeObserver(observer) }
    }

    LaunchedEffect(event) {
        when (val e = event) {
            is DevicesEvent.SessionEnded -> { vm.consumeEvent(); onSessionEnded(e.message) }
            DevicesEvent.TokenReplaced -> { vm.consumeEvent(); onTokenReplaced() }
            null -> Unit
        }
    }

    val held = ui.state?.takeIf { ui.held }
    if (held == null) {
        content()
        return
    }

    com.haraan.app.ui.DismissOnBack(showPlans) { showPlans = false }
    if (showPlans) {
        // Coming back from the plans re-checks: a member who just bought Hero is let straight in.
        MembershipScreen(onClose = { showPlans = false; vm.refresh(force = true) }, onSignIn = {})
        return
    }

    DevicesScreen(
        state = held,
        held = true,
        busyDeviceId = ui.busyDeviceId,
        error = ui.error,
        onSignOutDevice = vm::signOut,
        onUpgrade = { showPlans = true },
        onClose = null,
    )
}

/** Account → Signed-in devices. */
@Composable
fun SignedInDevicesRoute(onClose: () -> Unit, onSignedOutHere: () -> Unit, onOpenPlans: () -> Unit) {
    val vm: DevicesViewModel = viewModel(key = "devices-manage")
    val ui by vm.state.collectAsState()
    val event by vm.events.collectAsState()

    LaunchedEffect(Unit) { vm.enter() }
    LaunchedEffect(event) {
        if (event is DevicesEvent.SessionEnded) { vm.consumeEvent(); onSignedOutHere() } else if (event != null) vm.consumeEvent()
    }

    val state = ui.state
    if (state == null) {
        Box(Modifier.fillMaxSize().background(HaraanColors.Background), contentAlignment = Alignment.Center) {
            if (ui.loading) CircularProgressIndicator(color = HaraanColors.EventsBlue)
            else DevicesScreen(
                state = com.haraan.app.data.devices.DeviceState(),
                held = false, busyDeviceId = null, error = ui.error,
                onSignOutDevice = {}, onUpgrade = null, onClose = onClose,
            )
        }
        return
    }

    DevicesScreen(
        state = state,
        held = false,
        busyDeviceId = ui.busyDeviceId,
        error = ui.error,
        onSignOutDevice = vm::signOut,
        onUpgrade = onOpenPlans,
        onClose = onClose,
    )
}
