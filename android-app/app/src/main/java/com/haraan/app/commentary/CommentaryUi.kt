package com.haraan.app.commentary

import android.Manifest
import android.content.pm.PackageManager
import android.widget.Toast
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.ExperimentalFoundationApi
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.combinedClickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.VolumeMute
import androidx.compose.material.icons.automirrored.outlined.VolumeMute
import androidx.compose.material.icons.outlined.Check
import androidx.compose.material.icons.outlined.Lock
import androidx.compose.material.icons.outlined.Mic
import androidx.compose.material.icons.outlined.MicOff
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.Text
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.core.content.ContextCompat
import com.haraan.app.data.membership.MembershipNav

private val Blue = Color(0xFF2563EB)
private val Red = Color(0xFFDC2626)
private val Ink = Color(0xFF0F172A)
private val Muted = Color(0xFF64748B)
private val Line = Color(0xFFE2E8F0)

// ───────────────────────────── Scorer side ─────────────────────────────

/**
 * The mic in the scorer's header. Tap: ask for the mic (first time), then go on air. On air it
 * turns solid red; the strip under the header carries Mute and End. A plan without commentary
 * shows a small lock and opens the Hero sheet instead of asking for the mic.
 */
@Composable
fun ScorerMicButton(matchId: String, matchTitle: String, background: Color, ink: Color, size: androidx.compose.ui.unit.Dp = 40.dp) {
    val ctx = LocalContext.current
    val state by CommentaryBroadcaster.state.collectAsState()
    var access by remember { mutableStateOf<LiveCommentaryAccess.State?>(null) }
    var showHero by remember { mutableStateOf(false) }
    var heroLine by remember { mutableStateOf("") }

    LaunchedEffect(Unit) { access = LiveCommentaryAccess.check(ctx) }

    val askMic = rememberLauncherForActivityResult(ActivityResultContracts.RequestPermission()) { granted ->
        if (granted) {
            CommentaryBroadcaster.start(ctx, matchId, matchTitle)
        } else {
            Toast.makeText(ctx, "Allow the microphone to commentate.", Toast.LENGTH_LONG).show()
        }
    }

    // One-off lines: refusals and endings, in the server's words.
    LaunchedEffect(state) {
        when (val s = state) {
            is CommentaryBroadcaster.State.Locked -> if (s.matchId == matchId) {
                heroLine = s.message
                showHero = true
                access = LiveCommentaryAccess.State.LOCKED
                CommentaryBroadcaster.acknowledge()
            }
            is CommentaryBroadcaster.State.Ended -> if (s.matchId == matchId) {
                Toast.makeText(ctx, s.message, Toast.LENGTH_LONG).show()
                CommentaryBroadcaster.acknowledge()
            }
            else -> Unit
        }
    }

    val onAir = (state as? CommentaryBroadcaster.State.OnAir)?.matchId == matchId
    val starting = (state as? CommentaryBroadcaster.State.Starting)?.matchId == matchId
    val locked = access == LiveCommentaryAccess.State.LOCKED

    Box(
        modifier = Modifier
            .size(size)
            .clip(CircleShape)
            .background(if (onAir) Red else background)
            .clickable(enabled = !starting) {
                when {
                    onAir -> CommentaryBroadcaster.toggleMute()
                    locked -> { heroLine = ""; showHero = true }
                    ContextCompat.checkSelfPermission(ctx, Manifest.permission.RECORD_AUDIO) == PackageManager.PERMISSION_GRANTED ->
                        CommentaryBroadcaster.start(ctx, matchId, matchTitle)
                    else -> askMic.launch(Manifest.permission.RECORD_AUDIO)
                }
            },
        contentAlignment = Alignment.Center,
    ) {
        when {
            starting -> CircularProgressIndicator(color = ink, strokeWidth = 2.dp, modifier = Modifier.size(18.dp))
            onAir -> {
                val muted = (state as CommentaryBroadcaster.State.OnAir).muted
                Icon(if (muted) Icons.Outlined.MicOff else Icons.Outlined.Mic, contentDescription = if (muted) "Unmute commentary" else "Mute commentary", tint = Color.White, modifier = Modifier.size(20.dp))
            }
            else -> {
                Icon(Icons.Outlined.Mic, contentDescription = "Commentate live", tint = ink, modifier = Modifier.size(20.dp))
                if (locked) {
                    Box(
                        Modifier.align(Alignment.BottomEnd).offset((-5).dp, (-5).dp).size(14.dp)
                            .clip(CircleShape).background(Color.White).border(1.dp, Line, CircleShape),
                        contentAlignment = Alignment.Center,
                    ) {
                        Icon(Icons.Outlined.Lock, contentDescription = null, tint = Muted, modifier = Modifier.size(9.dp))
                    }
                }
            }
        }
    }

    if (showHero) {
        HeroCommentarySheet(matchTitle = matchTitle, refusal = heroLine, onDismiss = { showHero = false })
    }
}

/** A Free or Pro scorer tapped the mic: what it is, and the way to it. */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun HeroCommentarySheet(matchTitle: String, refusal: String, onDismiss: () -> Unit) {
    ModalBottomSheet(
        onDismissRequest = onDismiss,
        sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true),
        containerColor = Color.White,
    ) {
        Column(
            Modifier.fillMaxWidth().padding(horizontal = 24.dp).padding(bottom = 20.dp).navigationBarsPadding(),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            Box(
                Modifier.size(56.dp).clip(CircleShape).border(1.dp, Line, CircleShape),
                contentAlignment = Alignment.Center,
            ) {
                Icon(Icons.Outlined.Mic, contentDescription = null, tint = Blue, modifier = Modifier.size(26.dp))
            }
            Spacer(Modifier.height(14.dp))
            Text("Call the match live", color = Ink, fontSize = 19.sp, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(6.dp))
            Text(
                "Everyone watching ${matchTitle.ifBlank { "this match" }} hears you, ball by ball. " +
                    refusal.ifBlank { "Live commentary is part of Hero." },
                color = Muted, fontSize = 14.sp, textAlign = TextAlign.Center, lineHeight = 20.sp,
            )
            Spacer(Modifier.height(16.dp))
            Column(Modifier.fillMaxWidth(), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                Perk("Live to everyone on the match screen")
                Perk("Listening is free for them, no app plan needed")
                Perk("See how many are listening")
                Perk("Keeps going when your screen locks")
            }
            Spacer(Modifier.height(20.dp))
            Text(
                "See Hero",
                color = Color.White, fontSize = 16.sp, fontWeight = FontWeight.Bold, textAlign = TextAlign.Center,
                modifier = Modifier
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(16.dp))
                    .background(Blue)
                    .clickable { onDismiss(); MembershipNav.open() }
                    .padding(vertical = 16.dp),
            )
            Text(
                "Not now",
                color = Muted, fontSize = 14.sp, fontWeight = FontWeight.SemiBold,
                modifier = Modifier.clip(RoundedCornerShape(10.dp)).clickable(onClick = onDismiss).padding(horizontal = 16.dp, vertical = 12.dp),
            )
        }
    }
}

@Composable
private fun Perk(text: String) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        Icon(Icons.Outlined.Check, contentDescription = null, tint = Color(0xFF16A34A), modifier = Modifier.size(16.dp))
        Spacer(Modifier.width(8.dp))
        Text(text, color = Ink, fontSize = 14.sp)
    }
}

// ───────────────────────────── Viewer side ─────────────────────────────

/**
 * The match screen provides the speaker once; each sport's own header (the shared sport top
 * bar, football's hero) draws it where it fits. Null when there is nothing to draw. The Boolean
 * says whether it sits on a dark background.
 */
val LocalCommentarySpeaker = androidx.compose.runtime.staticCompositionLocalOf<(@Composable (onDark: Boolean) -> Unit)?> { null }

/**
 * The speaker in the match detail header. Shown only while someone is on air: outline speaker
 * with a red live dot = tap to listen; solid blue with waves moving with the voice = listening,
 * tap to stop. Long-press while listening to report the commentary. Never autoplays.
 */
@OptIn(ExperimentalFoundationApi::class)
@Composable
fun CommentarySpeakerButton(
    matchId: String,
    code: String,
    matchTitle: String,
    onAir: Boolean,
    by: String,
    /** On a dark hero (football): translucent white chip instead of the light slate one. */
    onDark: Boolean = false,
) {
    val ctx = LocalContext.current
    val state by CommentaryListener.state.collectAsState()
    val broadcasting by CommentaryBroadcaster.state.collectAsState()
    var showReport by remember { mutableStateOf(false) }

    val mine = CommentaryListener.matchOf(state) == matchId
    val playing = mine && state is CommentaryListener.State.Playing
    val connecting = mine && state is CommentaryListener.State.Connecting

    LaunchedEffect(state) {
        val s = state
        if (s is CommentaryListener.State.Failed && s.matchId == matchId) {
            Toast.makeText(ctx, s.message, Toast.LENGTH_SHORT).show()
            CommentaryListener.acknowledge()
        }
    }

    // The commentator's own phone never offers to listen to itself (it would echo).
    val selfOnAir = (broadcasting as? CommentaryBroadcaster.State.OnAir)?.matchId == matchId
    if ((!onAir && !playing && !connecting) || selfOnAir) return

    val level = (state as? CommentaryListener.State.Playing)?.takeIf { mine }?.level ?: 0f
    val shown by animateFloatAsState(level, tween(110), label = "level")
    val label = when {
        playing -> "Stop live commentary"
        by.isNotBlank() -> "Listen to $by's live commentary"
        else -> "Listen to live commentary"
    }

    Box(
        modifier = Modifier
            // On the football hero its neighbours are 34dp circles; in the light bars, 38dp tiles.
            .size(if (onDark) 34.dp else 38.dp)
            .clip(if (onDark) CircleShape else RoundedCornerShape(12.dp))
            .background(if (playing) Blue else if (onDark) Color.White.copy(alpha = 0.16f) else Color(0xFFF1F5FA))
            .then(if (onDark) Modifier else Modifier.border(1.dp, if (playing) Blue else Color(0xFFE4EAF1), RoundedCornerShape(12.dp)))
            .combinedClickable(
                onClickLabel = label,
                onClick = { CommentaryListener.toggle(ctx, matchId, code, matchTitle) },
                onLongClick = { if (playing) showReport = true },
            ),
        contentAlignment = Alignment.Center,
    ) {
        when {
            connecting -> CircularProgressIndicator(color = Blue, strokeWidth = 2.dp, modifier = Modifier.size(16.dp))
            playing -> {
                Icon(Icons.AutoMirrored.Filled.VolumeMute, contentDescription = label, tint = Color.White, modifier = Modifier.size(20.dp).offset((-4).dp, 0.dp))
                SoundWaves(shown, Color.White, Modifier.size(12.dp, 18.dp).align(Alignment.Center).offset(7.dp, 0.dp))
            }
            else -> {
                val idleTint = if (onDark) Color.White else Color(0xFF334155)
                Icon(Icons.AutoMirrored.Outlined.VolumeMute, contentDescription = label, tint = idleTint, modifier = Modifier.size(20.dp).offset((-3).dp, 0.dp))
                SoundWaves(0.15f, idleTint, Modifier.size(12.dp, 18.dp).align(Alignment.Center).offset(7.dp, 0.dp))
                Box(Modifier.align(Alignment.TopEnd).offset((-5).dp, 5.dp).size(7.dp).clip(CircleShape).background(Red).border(1.dp, Color.White, CircleShape))
            }
        }
    }

    if (showReport) {
        ReportCommentarySheet(matchId = matchId, by = by, onDismiss = { showReport = false })
    }
}

/** Two arcs beside the speaker; their reach and strength follow the voice. */
@Composable
private fun SoundWaves(level: Float, color: Color, modifier: Modifier) {
    Canvas(modifier) {
        val l = level.coerceIn(0f, 1f)
        val stroke = Stroke(width = 1.8.dp.toPx(), cap = StrokeCap.Round)
        val cy = size.height / 2f
        listOf(0.45f to 0.55f, 0.95f to 0.25f).forEach { (reach, threshold) ->
            val r = size.width * reach * (0.75f + 0.25f * l)
            val alpha = (0.35f + 0.65f * ((l - threshold * 0.4f) / (1f - threshold * 0.4f)).coerceIn(0f, 1f))
            drawArc(
                color = color.copy(alpha = alpha),
                startAngle = -50f, sweepAngle = 100f, useCenter = false,
                topLeft = Offset(-r, cy - r), size = Size(r * 2, r * 2), style = stroke,
            )
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun ReportCommentarySheet(matchId: String, by: String, onDismiss: () -> Unit) {
    val ctx = LocalContext.current
    val reasons = listOf(
        "abusive" to "Abusive or hateful",
        "sexual" to "Sexual content",
        "spam" to "Ads or spam",
        "not_match" to "Not about the match",
        "other" to "Something else",
    )
    ModalBottomSheet(onDismissRequest = onDismiss, containerColor = Color.White) {
        Column(Modifier.fillMaxWidth().padding(horizontal = 20.dp).padding(bottom = 16.dp).navigationBarsPadding()) {
            Text("Report this commentary", color = Ink, fontSize = 18.sp, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(4.dp))
            Text(
                (if (by.isNotBlank()) "$by won't see who reported. " else "") + "Haraan reviews every report.",
                color = Muted, fontSize = 13.sp,
            )
            Spacer(Modifier.height(10.dp))
            reasons.forEach { (key, label) ->
                Text(
                    label, color = Ink, fontSize = 15.sp,
                    modifier = Modifier
                        .fillMaxWidth()
                        .clip(RoundedCornerShape(10.dp))
                        .clickable {
                            val app = ctx.applicationContext
                            onDismiss()
                            CommentaryListener.report(ctx, matchId, key) { ok ->
                                val line = if (ok) "Thanks. Haraan will look at it." else "Couldn’t send the report. Try again."
                                Toast.makeText(app, line, Toast.LENGTH_SHORT).show()
                            }
                        }
                        .padding(vertical = 13.dp, horizontal = 4.dp),
                )
                Box(Modifier.fillMaxWidth().height(1.dp).background(Line))
            }
        }
    }
}
