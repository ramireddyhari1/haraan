package com.haraan.app.ui.matches

import android.widget.VideoView
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.PlayArrow
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.viewinterop.AndroidView
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import com.haraan.app.data.MatchClip
import com.haraan.app.data.MatchDeviceRepository
import com.haraan.app.data.TokenStore
import com.haraan.app.ui.pressable
import com.haraan.app.ui.theme.HaraanColors

// ─────────────────────────────────────────────────────────────────────────────
//  THE FOOTAGE
//
//  What the paired cameras sent back, for the person who has to make the call.
//
//  This screen shows a clip and says which delivery it is. It does not say what
//  the clip MEANS: no verdict, no line, no projected path. One uncalibrated phone
//  at 30fps cannot adjudicate an LBW, and a screen that offered an answer here
//  would be believed — the umpire's judgement is the feature, and the footage is
//  what it is made from.
// ─────────────────────────────────────────────────────────────────────────────

private val Panel = HaraanColors.Surface
private val Well = HaraanColors.Background
private val Ink = HaraanColors.TextPrimary
private val Ink2 = HaraanColors.TextSecondary
private val Ink3 = HaraanColors.TextMuted
private val Accent = HaraanColors.EventsBlue

@Composable
fun MatchClipsSheet(matchId: String, onDismiss: () -> Unit) {
    val ctx = LocalContext.current
    val repo = remember { MatchDeviceRepository() }

    var clips by remember { mutableStateOf<List<MatchClip>?>(null) }
    var playing by remember { mutableStateOf<MatchClip?>(null) }

    LaunchedEffect(matchId) {
        val token = TokenStore.getToken(ctx)
        clips = if (TokenStore.isSignedIn(token)) {
            runCatching { repo.clips(token!!, matchId) }.getOrDefault(emptyList())
        } else {
            emptyList()
        }
    }

    Dialog(onDismissRequest = onDismiss, properties = DialogProperties(usePlatformDefaultWidth = false)) {
        Column(
            Modifier
                .padding(horizontal = 18.dp)
                .fillMaxWidth()
                .clip(RoundedCornerShape(22.dp))
                .background(Panel)
                .padding(22.dp),
        ) {
            Text("Match footage", color = Ink, fontSize = 19.sp, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(6.dp))
            Text(
                "Clips your paired cameras sent, newest first.",
                color = Ink2,
                fontSize = 13.sp,
                lineHeight = 18.sp,
            )
            Spacer(Modifier.height(18.dp))

            val list = clips
            when {
                list == null -> Row(verticalAlignment = Alignment.CenterVertically) {
                    CircularProgressIndicator(color = Accent, strokeWidth = 2.dp, modifier = Modifier.size(15.dp))
                    Spacer(Modifier.width(10.dp))
                    Text("Loading…", color = Ink2, fontSize = 14.sp)
                }

                list.isEmpty() -> Text(
                    "Nothing yet. Pair a camera with + and tap record when the bowler runs in.",
                    color = Ink3,
                    fontSize = 13.sp,
                    lineHeight = 19.sp,
                )

                else -> Column(
                    Modifier.heightIn(max = 420.dp).verticalScroll(rememberScrollState()),
                    verticalArrangement = Arrangement.spacedBy(10.dp),
                ) {
                    list.forEach { clip -> ClipRow(clip) { playing = clip } }
                }
            }

            Spacer(Modifier.height(18.dp))
            Row(
                Modifier
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(12.dp))
                    .background(Well)
                    .pressable(onClick = onDismiss)
                    .padding(vertical = 13.dp),
                horizontalArrangement = Arrangement.Center,
            ) {
                Text("Done", color = Ink, fontSize = 15.sp, fontWeight = FontWeight.Bold)
            }
        }
    }

    playing?.let { clip -> ClipPlayer(clip) { playing = null } }
}

@Composable
private fun ClipRow(clip: MatchClip, onPlay: () -> Unit) {
    Row(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(14.dp))
            .background(Well)
            .pressable(onClick = onPlay)
            .padding(horizontal = 14.dp, vertical = 12.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(
            Modifier.size(38.dp).clip(CircleShape).background(Accent.copy(alpha = 0.12f)),
            contentAlignment = Alignment.Center,
        ) {
            Icon(Icons.Filled.PlayArrow, null, tint = Accent, modifier = Modifier.size(20.dp))
        }
        Spacer(Modifier.width(13.dp))
        Column(Modifier.weight(1f)) {
            Text(
                // The delivery is the thing a scorer looks a clip up BY, so it leads.
                if (clip.overBall.isNotBlank()) "Over ${clip.overBall}" else "Unmarked delivery",
                color = Ink,
                fontSize = 14.5.sp,
                fontWeight = FontWeight.SemiBold,
                style = TextStyle(fontFeatureSettings = "tnum"),
            )
            Spacer(Modifier.height(3.dp))
            Text(clip.roleLabel, color = Ink3, fontSize = 11.5.sp, maxLines = 1)
        }
        if (clip.durationMs > 0) {
            Text(
                "${clip.durationMs / 1000}s",
                color = Ink2,
                fontSize = 12.5.sp,
                style = TextStyle(fontFeatureSettings = "tnum"),
            )
        }
    }
}

/**
 * Playback.
 *
 * A plain [VideoView] rather than a media library: this is one short mp4 off our own
 * server, and pulling in a player stack for it would be a dependency the app carries
 * everywhere to serve one sheet.
 */
@Composable
private fun ClipPlayer(clip: MatchClip, onClose: () -> Unit) {
    Dialog(onDismissRequest = onClose, properties = DialogProperties(usePlatformDefaultWidth = false)) {
        Column(
            Modifier
                .padding(horizontal = 14.dp)
                .fillMaxWidth()
                .clip(RoundedCornerShape(20.dp))
                .background(Color.Black)
                .border(1.dp, Color.White.copy(alpha = 0.08f), RoundedCornerShape(20.dp)),
        ) {
            AndroidView(
                modifier = Modifier.fillMaxWidth().aspectRatio(9f / 16f),
                factory = { context ->
                    VideoView(context).apply {
                        setVideoPath(clip.url)
                        // Loops, because reviewing a dismissal means watching it again,
                        // and again — which is the entire point of having the footage.
                        setOnPreparedListener { it.isLooping = true; start() }
                    }
                },
            )
            Row(
                Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 14.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Column(Modifier.weight(1f)) {
                    Text(
                        if (clip.overBall.isNotBlank()) "Over ${clip.overBall}" else "Unmarked delivery",
                        color = Color.White,
                        fontSize = 15.sp,
                        fontWeight = FontWeight.Bold,
                        style = TextStyle(fontFeatureSettings = "tnum"),
                    )
                    Spacer(Modifier.height(2.dp))
                    Text(clip.roleLabel, color = Color.White.copy(alpha = 0.55f), fontSize = 12.sp)
                }
                Text(
                    "Close",
                    color = Color.White,
                    fontSize = 14.sp,
                    fontWeight = FontWeight.SemiBold,
                    modifier = Modifier.pressable(onClick = onClose).padding(8.dp),
                )
            }
        }
    }
}
