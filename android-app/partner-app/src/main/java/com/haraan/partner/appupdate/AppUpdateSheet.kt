package com.haraan.partner.appupdate

import android.app.Activity
import android.content.Context
import android.content.ContextWrapper
import android.os.Build
import android.view.HapticFeedbackConstants
import android.view.View
import androidx.activity.compose.BackHandler
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.LinearOutSlowInEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.LifecycleEventObserver
import androidx.lifecycle.compose.LocalLifecycleOwner
import kotlinx.coroutines.launch
import kotlin.math.abs
import kotlin.math.floor

private val Ink = Color(0xFF0F172A)
private val Muted = Color(0xFF64748B)
private val Track = Color(0xFFE2E8F0)
private val Blue = Color(0xFF2563EB)
private val Red = Color(0xFFDC2626)

private const val DOTS = 14

/**
 * Mount once, above the app's content. Checks for an update whenever the app comes to the
 * front, and shows a bottom sheet (optional update) or a full screen that can't be dismissed
 * (required update) when /control has published one.
 */
@Composable
fun AppUpdateHost() {
    val context = LocalContext.current
    val activity = remember(context) { context.findActivity() }
    val lifecycle = LocalLifecycleOwner.current.lifecycle
    DisposableEffect(lifecycle, activity) {
        val observer = LifecycleEventObserver { _, event ->
            if (event == Lifecycle.Event.ON_RESUME && activity != null) AppUpdater.onResume(activity)
        }
        lifecycle.addObserver(observer)
        onDispose { lifecycle.removeObserver(observer) }
    }

    val state by AppUpdater.state.collectAsState()
    val s = state ?: return
    if (s.minimized) return

    val view = LocalView.current
    LaunchedEffect(s.phase) {
        when (s.phase) {
            UpdatePhase.READY, UpdatePhase.RESTART -> confirmBuzz(view)
            UpdatePhase.FAILED -> rejectBuzz(view)
            else -> Unit
        }
    }

    if (s.info.required) {
        RequiredScreen(s, activity)
    } else {
        OfferSheet(s, activity)
    }
}

@Composable
private fun OfferSheet(s: UpdateState, activity: Activity?) {
    val context = LocalContext.current
    val rise = remember { Animatable(1f) }
    LaunchedEffect(Unit) { rise.animateTo(0f, spring(dampingRatio = 0.86f, stiffness = 420f)) }
    Box(Modifier.fillMaxSize()) {
        // The scrim only puts the sheet away before anything has started.
        Box(
            Modifier
                .fillMaxSize()
                .background(Ink.copy(alpha = 0.38f * (1f - rise.value)))
                .clickable(interactionSource = remember { MutableInteractionSource() }, indication = null) {
                    if (s.phase in setOf(UpdatePhase.OFFER, UpdatePhase.FAILED, UpdatePhase.READY, UpdatePhase.RESTART)) {
                        AppUpdater.later(context)
                    }
                },
        )
        Column(
            Modifier
                .align(Alignment.BottomCenter)
                .fillMaxWidth()
                .graphicsLayer { translationY = size.height * rise.value }
                .background(Color.White, RoundedCornerShape(topStart = 22.dp, topEnd = 22.dp))
                .clickable(interactionSource = remember { MutableInteractionSource() }, indication = null) {}
                .navigationBarsPadding()
                .padding(start = 24.dp, end = 24.dp, top = 10.dp, bottom = 16.dp),
        ) {
            Box(
                Modifier
                    .align(Alignment.CenterHorizontally)
                    .size(width = 36.dp, height = 4.dp)
                    .background(Track, CircleShape),
            )
            Spacer(Modifier.height(18.dp))
            UpdateBody(s, activity)
        }
    }
}

@Composable
private fun RequiredScreen(s: UpdateState, activity: Activity?) {
    BackHandler { activity?.moveTaskToBack(true) }
    Box(
        Modifier
            .fillMaxSize()
            .background(Color.White)
            .clickable(interactionSource = remember { MutableInteractionSource() }, indication = null) {}
            .statusBarsPadding()
            .navigationBarsPadding()
            .padding(horizontal = 24.dp, vertical = 20.dp),
    ) {
        Text(
            AppUpdater.appLabel.uppercase(),
            Modifier.align(Alignment.TopStart),
            color = Muted,
            fontSize = 11.sp,
            letterSpacing = 2.sp,
            fontWeight = FontWeight.SemiBold,
        )
        Column(Modifier.align(Alignment.BottomCenter).fillMaxWidth()) {
            UpdateBody(s, activity)
        }
    }
}

@Composable
private fun UpdateBody(s: UpdateState, activity: Activity?) {
    val context = LocalContext.current
    val info = s.info
    val version = info.latestVersion

    Text(
        when {
            info.required && version != null -> "Update to version $version"
            info.required -> "Update needed"
            version != null -> "Version $version is ready"
            else -> "An update is ready"
        },
        color = Ink,
        fontSize = 22.sp,
        lineHeight = 28.sp,
        fontWeight = FontWeight.SemiBold,
    )
    if (info.message.isNotBlank()) {
        Spacer(Modifier.height(6.dp))
        Text(info.message, color = Muted, fontSize = 14.sp, lineHeight = 20.sp)
    }
    if (info.whatsNew.isNotEmpty()) {
        Spacer(Modifier.height(16.dp))
        Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
            info.whatsNew.take(6).forEach { line ->
                Row {
                    Box(
                        Modifier
                            .padding(top = 7.dp)
                            .size(5.dp)
                            .background(Blue, CircleShape),
                    )
                    Spacer(Modifier.width(10.dp))
                    Text(line, color = Ink, fontSize = 14.sp, lineHeight = 20.sp)
                }
            }
        }
    }

    Spacer(Modifier.height(24.dp))
    ProgressDots(s)
    Spacer(Modifier.height(10.dp))
    Row(verticalAlignment = Alignment.CenterVertically) {
        Text(
            statusLine(s),
            Modifier.weight(1f),
            color = if (s.phase == UpdatePhase.FAILED) Red else Muted,
            fontSize = 13.sp,
            lineHeight = 18.sp,
        )
        sizeLine(s)?.let {
            Spacer(Modifier.width(12.dp))
            Text(it, color = Ink, fontSize = 13.sp, fontWeight = FontWeight.Medium)
        }
    }

    Spacer(Modifier.height(20.dp))
    Actions(s, activity, context)
}

@Composable
private fun Actions(s: UpdateState, activity: Activity?, context: Context) {
    val view = LocalView.current
    val optional = !s.info.required
    val primary: Pair<String, () -> Unit>? = when (s.phase) {
        UpdatePhase.OFFER -> "Update" to { activity?.let(AppUpdater::start); Unit }
        UpdatePhase.PERMISSION -> "Open settings" to { AppUpdater.openInstallPermission(context) }
        UpdatePhase.READY -> "Install" to { AppUpdater.install(context) }
        UpdatePhase.INSTALLING -> "Install" to { AppUpdater.install(context) }
        UpdatePhase.RESTART -> "Restart" to { AppUpdater.restart() }
        UpdatePhase.FAILED -> if (s.canRetry) {
            "Try again" to { activity?.let(AppUpdater::start); Unit }
        } else {
            "Open download page" to { AppUpdater.openDownloadPage(context) }
        }
        UpdatePhase.DOWNLOADING, UpdatePhase.CHECKING -> null
    }
    val secondary = when {
        !optional -> null
        s.phase == UpdatePhase.DOWNLOADING || s.phase == UpdatePhase.CHECKING -> "Hide"
        else -> "Later"
    }

    Row(Modifier.fillMaxWidth().height(50.dp), verticalAlignment = Alignment.CenterVertically) {
        if (secondary != null) {
            TextButton(
                onClick = { tick(view); AppUpdater.later(context) },
                modifier = if (primary == null) Modifier.weight(1f) else Modifier,
            ) { Text(secondary, color = Muted, fontSize = 15.sp, fontWeight = FontWeight.Medium) }
            if (primary != null) Spacer(Modifier.width(8.dp))
        }
        if (primary != null) {
            Button(
                onClick = { tick(view); primary.second() },
                modifier = Modifier.weight(1f).height(50.dp),
                shape = RoundedCornerShape(14.dp),
                colors = ButtonDefaults.buttonColors(containerColor = Blue, contentColor = Color.White),
            ) { Text(primary.first, fontSize = 15.sp, fontWeight = FontWeight.SemiBold) }
        }
    }
}

/**
 * The progress: a row of dots that fill left to right. The dot being downloaded grows from
 * its centre with the bytes; when it fills it pops once and settles. Checking the file and
 * waiting on Android's installer run a single highlight along the row: work with no bytes to
 * count. Nothing moves while the sheet is only offering.
 */
@Composable
private fun ProgressDots(s: UpdateState) {
    val view = LocalView.current
    val target = when (s.phase) {
        UpdatePhase.OFFER, UpdatePhase.PERMISSION -> if (s.bytes > 0) 1f else 0f
        UpdatePhase.DOWNLOADING -> s.fraction ?: 0f
        UpdatePhase.FAILED -> s.fraction ?: 0f
        else -> 1f
    }
    val shown by animateFloatAsState(target, tween(360, easing = LinearOutSlowInEasing), label = "dots")
    val filled = floor(shown * DOTS + 0.0001f).toInt().coerceIn(0, DOTS)

    // Pops run on their own scope so the next dot filling can't cut the last one short.
    val pops = remember { List(DOTS) { Animatable(1f) } }
    val popScope = rememberCoroutineScope()
    val seen = remember { mutableIntStateOf(filled) }
    LaunchedEffect(filled) {
        val before = seen.intValue
        if (filled > before && s.phase == UpdatePhase.DOWNLOADING) {
            for (i in before until filled) {
                popScope.launch {
                    pops[i].snapTo(1f)
                    pops[i].animateTo(1.32f, tween(90))
                    pops[i].animateTo(1f, spring(dampingRatio = 0.42f, stiffness = 520f))
                }
            }
            // A light tick at each quarter, felt rather than counted.
            if ((before * 4 / DOTS) != (filled * 4 / DOTS)) tick(view)
        }
        seen.intValue = filled
    }

    val indeterminate = s.phase == UpdatePhase.DOWNLOADING && s.fraction == null
    val sweeping = s.phase == UpdatePhase.CHECKING || s.phase == UpdatePhase.INSTALLING
    val travel = if (indeterminate || sweeping) {
        val t = rememberInfiniteTransition(label = "travel")
        t.animateFloat(
            initialValue = -2f,
            targetValue = DOTS + 1f,
            animationSpec = infiniteRepeatable(tween(if (sweeping) 1100 else 1500, easing = LinearEasing), RepeatMode.Restart),
            label = "pos",
        ).value
    } else {
        null
    }
    val fill = if (s.phase == UpdatePhase.FAILED) Red else Blue

    Canvas(Modifier.fillMaxWidth().height(16.dp)) {
        val r = 4.5.dp.toPx()
        val gap = (size.width - DOTS * 2 * r) / (DOTS - 1)
        val cy = size.height / 2
        for (i in 0 until DOTS) {
            val cx = r + i * (2 * r + gap)
            val center = Offset(cx, cy)
            drawCircle(Track, r, center)
            when {
                indeterminate && travel != null -> {
                    val near = (1f - abs(i - travel) / 2.2f).coerceIn(0f, 1f)
                    if (near > 0f) drawCircle(fill, r * near, center)
                }
                else -> {
                    val local = (shown * DOTS - i).coerceIn(0f, 1f)
                    if (local > 0f) {
                        var scale = pops[i].value
                        if (sweeping && travel != null) scale *= 1f + 0.28f * (1f - abs(i - travel) / 1.6f).coerceIn(0f, 1f)
                        drawCircle(fill, r * local * scale, center)
                    }
                }
            }
        }
    }
}

private fun statusLine(s: UpdateState): String = when (s.phase) {
    UpdatePhase.OFFER -> "You have version ${AppUpdater.currentVersion}"
    UpdatePhase.DOWNLOADING -> "Downloading"
    UpdatePhase.CHECKING -> "Checking the file"
    UpdatePhase.PERMISSION -> "Android needs your OK once. Turn on “Allow from this source”, then come back."
    UpdatePhase.READY -> "Downloaded"
    UpdatePhase.INSTALLING -> "Confirm the update on Android’s screen"
    UpdatePhase.RESTART -> "Downloaded. Restart the app to finish."
    UpdatePhase.FAILED -> s.error ?: "The update didn’t finish."
}

private fun sizeLine(s: UpdateState): String? = when (s.phase) {
    UpdatePhase.DOWNLOADING -> if (s.total > 0) "${mb(s.bytes)} of ${mb(s.total)}" else if (s.bytes > 0) mb(s.bytes) else null
    UpdatePhase.READY, UpdatePhase.CHECKING, UpdatePhase.INSTALLING -> if (s.total > 0) mb(s.total) else null
    else -> null
}

private fun mb(bytes: Long): String = String.format(java.util.Locale.US, "%.1f MB", bytes / 1_048_576.0)

private fun tick(view: View) {
    view.performHapticFeedback(HapticFeedbackConstants.CLOCK_TICK)
}

private fun confirmBuzz(view: View) {
    view.performHapticFeedback(if (Build.VERSION.SDK_INT >= 30) HapticFeedbackConstants.CONFIRM else HapticFeedbackConstants.VIRTUAL_KEY)
}

private fun rejectBuzz(view: View) {
    view.performHapticFeedback(if (Build.VERSION.SDK_INT >= 30) HapticFeedbackConstants.REJECT else HapticFeedbackConstants.LONG_PRESS)
}

private tailrec fun Context.findActivity(): Activity? = when (this) {
    is Activity -> this
    is ContextWrapper -> baseContext.findActivity()
    else -> null
}
