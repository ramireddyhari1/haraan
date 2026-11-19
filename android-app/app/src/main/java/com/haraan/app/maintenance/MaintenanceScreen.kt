package com.haraan.app.maintenance

import android.app.Activity
import android.content.Context
import android.content.ContextWrapper
import androidx.activity.compose.BackHandler
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.LifecycleEventObserver
import androidx.lifecycle.compose.LocalLifecycleOwner
import com.haraan.app.R
import com.haraan.app.data.MaintenanceConfig
import com.haraan.app.data.RemoteBootstrap
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.launch
import org.json.JSONObject

/**
 * /control → Platform → Operations → Maintenance mode, in the app.
 *
 * Two ways in: /api/config says so (launch, resume, or the realtime `config` push the moment an
 * admin saves), or any API call comes back 503 `maintenance`. One way out: /api/config saying
 * it's off. The server refuses requests regardless; this screen just says why, instead of every
 * tab failing on its own.
 */
object MaintenanceState {
    private val _state = MutableStateFlow(MaintenanceConfig())
    val state: StateFlow<MaintenanceConfig> = _state

    fun fromConfig(config: MaintenanceConfig) {
        _state.value = config
    }

    fun from503(body: String) {
        val o = runCatching { JSONObject(body) }.getOrNull() ?: return
        if (o.optString("error") != "maintenance") return
        fun str(k: String) = o.optString(k).takeIf { it.isNotBlank() && it != "null" } ?: ""
        _state.value = MaintenanceConfig(enabled = true, title = str("title"), message = str("message"))
    }
}

private const val RECHECK_MS = 45_000L

@Composable
fun MaintenanceHost() {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()

    // Coming back to the app re-reads the switch, so a phone left open overnight catches up
    // even when the realtime socket was asleep.
    val lifecycle = LocalLifecycleOwner.current.lifecycle
    DisposableEffect(lifecycle) {
        val observer = LifecycleEventObserver { _, event ->
            if (event == Lifecycle.Event.ON_RESUME) {
                scope.launch { RemoteBootstrap.reloadConfig(context.applicationContext) }
            }
        }
        lifecycle.addObserver(observer)
        onDispose { lifecycle.removeObserver(observer) }
    }

    val state by MaintenanceState.state.collectAsState()
    if (!state.enabled) return

    var checking by remember { mutableStateOf(false) }
    val recheck: () -> Unit = {
        if (!checking) {
            checking = true
            scope.launch {
                RemoteBootstrap.reloadConfig(context.applicationContext)
                delay(600) // long enough to read as "checked", not a flicker
                checking = false
            }
        }
    }

    // While it's up, look again now and then, so the app opens by itself when it's over.
    LaunchedEffect(Unit) {
        while (true) {
            delay(RECHECK_MS)
            RemoteBootstrap.reloadConfig(context.applicationContext)
        }
    }

    BackHandler { context.findActivity()?.finish() }

    MaintenanceScreen(
        title = state.title.ifBlank { "We’re making Haraan better" },
        message = state.message.ifBlank { "Haraan is down for scheduled maintenance. We’ll be back shortly." },
        checking = checking,
        onRetry = recheck,
    )
}

@Composable
private fun MaintenanceScreen(title: String, message: String, checking: Boolean, onRetry: () -> Unit) {
    Box(
        Modifier
            .fillMaxSize()
            .background(Color(0xFFF8FAFC))
            // Swallow taps so nothing underneath can be reached.
            .clickable(interactionSource = remember { MutableInteractionSource() }, indication = null) {}
            .statusBarsPadding()
            .navigationBarsPadding()
            .padding(horizontal = 28.dp),
        contentAlignment = Alignment.Center,
    ) {
        Column(
            modifier = Modifier.widthIn(max = 420.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.Center,
        ) {
            Image(
                painter = painterResource(R.drawable.haraan_logo),
                contentDescription = "Haraan",
                // The wordmark in Haraan blue, at a size that carries the screen.
                colorFilter = androidx.compose.ui.graphics.ColorFilter.tint(Color(0xFF2563EB)),
                modifier = Modifier.size(width = 136.dp, height = 36.dp),
            )
            Spacer(Modifier.height(16.dp))
            PitchRollerArt(
                Modifier
                    .fillMaxWidth()
                    .aspectRatio(320f / 180f),
            )
            Spacer(Modifier.height(24.dp))
            Text(
                text = title,
                color = Color(0xFF0F172A),
                fontSize = 22.sp,
                lineHeight = 28.sp,
                fontWeight = FontWeight.Bold,
                textAlign = TextAlign.Center,
            )
            Spacer(Modifier.height(10.dp))
            Text(
                text = message,
                color = Color(0xFF475569),
                fontSize = 15.sp,
                lineHeight = 22.sp,
                textAlign = TextAlign.Center,
            )
            Spacer(Modifier.height(28.dp))
            OutlinedButton(
                onClick = onRetry,
                enabled = !checking,
                shape = RoundedCornerShape(12.dp),
                modifier = Modifier.height(44.dp),
            ) {
                if (checking) {
                    CircularProgressIndicator(Modifier.size(16.dp), strokeWidth = 2.dp, color = Color(0xFF2563EB))
                } else {
                    Text("Try again", color = Color(0xFF0F172A), fontWeight = FontWeight.SemiBold)
                }
            }
            Spacer(Modifier.height(12.dp))
            Text(
                text = "The app opens by itself once we’re back.",
                color = Color(0xFF94A3B8),
                fontSize = 12.sp,
                textAlign = TextAlign.Center,
            )
        }
    }
}

private tailrec fun Context.findActivity(): Activity? = when (this) {
    is Activity -> this
    is ContextWrapper -> baseContext.findActivity()
    else -> null
}
