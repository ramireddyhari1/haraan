package com.haraan.app.camera

import android.widget.VideoView
import androidx.activity.compose.BackHandler
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.spring
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.interaction.collectIsPressedAsState
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.displayCutoutPadding
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import androidx.compose.foundation.lazy.grid.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
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
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.viewinterop.AndroidView
import coil.ImageLoader
import coil.compose.AsyncImage
import coil.decode.VideoFrameDecoder
import coil.request.ImageRequest
import coil.request.videoFrameMillis
import com.haraan.app.ui.Feel

// ─────────────────────────────────────────────────────────────────────────────
//  THE CAMERA PHONE'S GALLERY
//
//  The clips this phone filmed, on this phone. Where every camera app keeps the last
//  shot — bottom corner, opposite the tools — so the person holding it can check a
//  delivery came out without walking to the scorer.
//
//  Waiting clips are here too, marked as waiting: they are the ones that most need
//  checking, and a gallery that hid them until they uploaded would hide exactly the
//  clip somebody is worried about. Sent clips stay for the hours an admin sets in
//  /control, then go.
// ─────────────────────────────────────────────────────────────────────────────

private val GInk = Color(0xFFF8FAFC)
private val GScrim = Color(0x99000000)
private val GWarn = Color(0xFFF59E0B)
private val GGood = Color(0xFF16A34A)
private val GRec = Color(0xFFDC2626)

@Composable
private fun rememberVideoLoader(): ImageLoader {
    val context = LocalContext.current
    return remember {
        ImageLoader.Builder(context.applicationContext)
            .components { add(VideoFrameDecoder.Factory()) }
            .build()
    }
}

@Composable
private fun ClipThumb(clip: GalleryClip, modifier: Modifier = Modifier) {
    val context = LocalContext.current
    val loader = rememberVideoLoader()
    val request = remember(clip.file.absolutePath, clip.state) {
        ImageRequest.Builder(context)
            .data(clip.file)
            // A second in: frame zero is usually the bowler still walking back.
            .videoFrameMillis(1_000)
            .crossfade(true)
            .build()
    }
    AsyncImage(
        model = request,
        imageLoader = loader,
        contentDescription = null,
        contentScale = ContentScale.Crop,
        modifier = modifier.background(Color(0xFF111827)),
    )
}

/**
 * The corner button: the latest clip as a thumbnail, like every camera app.
 *
 * When a new clip lands it pops — the shot "dropping into" the gallery, the feedback every
 * camera gives that the thing just filmed was kept. A count on it says how many are still
 * waiting to reach the scorer.
 */
@Composable
fun GalleryButton(
    latest: GalleryClip?,
    waitingCount: Int,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
) {
    val view = LocalView.current
    val pop = remember { Animatable(1f) }
    LaunchedEffect(latest?.id) {
        if (latest != null) {
            pop.snapTo(0.72f)
            pop.animateTo(1f, spring(dampingRatio = 0.45f, stiffness = 520f))
        }
    }
    val press = remember { MutableInteractionSource() }
    val pressed by press.collectIsPressedAsState()
    val pressScale by animateFloatAsState(if (pressed) 0.88f else 1f, spring(dampingRatio = 0.5f, stiffness = 800f), label = "galleryPress")

    Column(modifier, horizontalAlignment = Alignment.CenterHorizontally) {
        Box(
            Modifier
                .graphicsLayer {
                    val s = pop.value * pressScale
                    scaleX = s
                    scaleY = s
                }
                .size(48.dp),
        ) {
            Box(
                Modifier
                    .fillMaxSize()
                    .clip(RoundedCornerShape(12.dp))
                    .background(GScrim)
                    .border(2.dp, Color.White.copy(alpha = 0.92f), RoundedCornerShape(12.dp))
                    .clickable(interactionSource = press, indication = null) {
                        view.performHapticFeedback(Feel.SELECT)
                        onClick()
                    }
                    .semantics { contentDescription = "Clips on this phone" },
                contentAlignment = Alignment.Center,
            ) {
                if (latest != null) {
                    ClipThumb(latest, Modifier.fillMaxSize().padding(2.dp).clip(RoundedCornerShape(10.dp)))
                } else {
                    GalleryGlyph(Modifier.size(20.dp), Color.White.copy(alpha = 0.9f))
                }
            }
            if (waitingCount > 0) {
                Box(
                    Modifier
                        .align(Alignment.TopEnd)
                        .offset(x = 6.dp, y = (-6).dp)
                        .size(20.dp)
                        .clip(CircleShape)
                        .background(GWarn)
                        .border(1.5.dp, Color.Black, CircleShape),
                    contentAlignment = Alignment.Center,
                ) {
                    Text(
                        if (waitingCount > 9) "9+" else waitingCount.toString(),
                        color = Color.Black,
                        fontSize = 10.sp,
                        fontWeight = FontWeight.Black,
                    )
                }
            }
        }
        Spacer(Modifier.height(5.dp))
        Text(
            "Clips",
            color = Color.White.copy(alpha = 0.9f),
            fontSize = 11.sp,
            fontWeight = FontWeight.SemiBold,
            style = androidx.compose.ui.text.TextStyle(
                shadow = androidx.compose.ui.graphics.Shadow(Color.Black.copy(alpha = 0.6f), Offset(0f, 1f), 6f),
            ),
        )
    }
}

/** Two stacked frames: the plain picture for "the clips you took". */
@Composable
private fun GalleryGlyph(modifier: Modifier, tint: Color) {
    Canvas(modifier) {
        val w = size.width
        val h = size.height
        val s = 1.6.dp.toPx()
        drawRoundRect(
            tint,
            topLeft = Offset(w * 0.1f, h * 0.28f),
            size = androidx.compose.ui.geometry.Size(w * 0.68f, h * 0.6f),
            cornerRadius = androidx.compose.ui.geometry.CornerRadius(2.5.dp.toPx()),
            style = androidx.compose.ui.graphics.drawscope.Stroke(s),
        )
        drawLine(tint, Offset(w * 0.26f, h * 0.14f), Offset(w * 0.9f, h * 0.14f), s, StrokeCap.Round)
        drawLine(tint, Offset(w * 0.9f, h * 0.14f), Offset(w * 0.9f, h * 0.7f), s, StrokeCap.Round)
    }
}

private fun verdictLabel(v: String?): Pair<String, Color>? = when (v) {
    "HITTING" -> "Hitting" to GRec
    "MISSING" -> "Missing" to GGood
    "UMPIRES_CALL" -> "Umpire's call" to Color(0xFFD97706)
    else -> null
}

private fun timeOf(ms: Long): String =
    java.text.SimpleDateFormat("h:mm a", java.util.Locale.getDefault()).format(java.util.Date(ms))

/**
 * The gallery, over the viewfinder. The camera keeps running underneath, so a BALL from the
 * scorer still films — and the gallery steps aside for it (the caller closes it).
 */
@Composable
fun ClipGalleryOverlay(
    clips: List<GalleryClip>,
    keepHours: Int,
    onDelete: (GalleryClip) -> Unit,
    onClose: () -> Unit,
) {
    var playing by remember { mutableStateOf<GalleryClip?>(null) }
    BackHandler { if (playing != null) playing = null else onClose() }

    Box(Modifier.fillMaxSize().background(Color(0xFF05080F)).clickable(enabled = false) {}) {
        Column(
            Modifier
                .fillMaxSize()
                .statusBarsPadding()
                .navigationBarsPadding()
                .displayCutoutPadding()
                .padding(horizontal = 16.dp),
        ) {
            Row(Modifier.fillMaxWidth().padding(top = 10.dp, bottom = 4.dp), verticalAlignment = Alignment.CenterVertically) {
                Column(Modifier.weight(1f)) {
                    Text("Clips on this phone", color = GInk, fontSize = 20.sp, fontWeight = FontWeight.Bold)
                    Spacer(Modifier.height(3.dp))
                    Text(
                        if (keepHours > 0) {
                            "Sent clips stay here for $keepHours h, then this phone deletes them."
                        } else {
                            "Sent clips are deleted from this phone straight away."
                        },
                        color = GInk.copy(alpha = 0.55f),
                        fontSize = 12.5.sp,
                    )
                }
                CloseButton(onClose)
            }
            Spacer(Modifier.height(12.dp))
            if (clips.isEmpty()) {
                Box(Modifier.fillMaxWidth().weight(1f), contentAlignment = Alignment.Center) {
                    Text(
                        "Nothing yet. Each ball this phone films appears here.",
                        color = GInk.copy(alpha = 0.5f),
                        fontSize = 14.sp,
                        textAlign = TextAlign.Center,
                    )
                }
            } else {
                LazyVerticalGrid(
                    columns = GridCells.Adaptive(minSize = 108.dp),
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                    verticalArrangement = Arrangement.spacedBy(8.dp),
                    modifier = Modifier.weight(1f),
                ) {
                    items(clips, key = { it.id }) { clip -> GalleryTile(clip) { playing = clip } }
                }
            }
        }

        playing?.let { clip ->
            GalleryPlayer(
                clip = clip,
                onDelete = if (clip.state == GalleryClip.State.SENT) ({ onDelete(clip); playing = null }) else null,
                onClose = { playing = null },
            )
        }
    }
}

@Composable
private fun GalleryTile(clip: GalleryClip, onOpen: () -> Unit) {
    val view = LocalView.current
    val press = remember { MutableInteractionSource() }
    val pressed by press.collectIsPressedAsState()
    val scale by animateFloatAsState(if (pressed) 0.94f else 1f, spring(dampingRatio = 0.55f, stiffness = 700f), label = "tile")
    Column(
        Modifier
            .graphicsLayer { scaleX = scale; scaleY = scale }
            .clickable(interactionSource = press, indication = null) {
                view.performHapticFeedback(Feel.SELECT)
                onOpen()
            },
    ) {
        Box(Modifier.fillMaxWidth().aspectRatio(3f / 4f).clip(RoundedCornerShape(10.dp))) {
            ClipThumb(clip, Modifier.fillMaxSize())
            // Upload state, top-left: the one thing to know before tapping.
            val (word, tone) = when (clip.state) {
                GalleryClip.State.SENT -> "Sent" to GGood
                GalleryClip.State.SENDING -> "Sending" to Color(0xFF2563EB)
                GalleryClip.State.WAITING -> "Waiting" to GWarn
            }
            Row(
                Modifier
                    .align(Alignment.TopStart)
                    .padding(6.dp)
                    .clip(RoundedCornerShape(999.dp))
                    .background(GScrim)
                    .padding(horizontal = 7.dp, vertical = 3.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Box(Modifier.size(6.dp).clip(CircleShape).background(tone))
                Spacer(Modifier.width(5.dp))
                Text(word, color = Color.White, fontSize = 10.sp, fontWeight = FontWeight.Bold)
            }
            if (clip.durationMs > 0) {
                Text(
                    "%.0fs".format(clip.durationMs / 1000f),
                    color = Color.White,
                    fontSize = 10.sp,
                    fontWeight = FontWeight.SemiBold,
                    modifier = Modifier
                        .align(Alignment.BottomEnd)
                        .padding(6.dp)
                        .clip(RoundedCornerShape(4.dp))
                        .background(GScrim)
                        .padding(horizontal = 5.dp, vertical = 2.dp),
                )
            }
            verdictLabel(clip.wicketsVerdict)?.let { (w, c) ->
                Text(
                    w,
                    color = Color.White,
                    fontSize = 10.sp,
                    fontWeight = FontWeight.Black,
                    modifier = Modifier
                        .align(Alignment.BottomStart)
                        .padding(6.dp)
                        .clip(RoundedCornerShape(4.dp))
                        .background(c)
                        .padding(horizontal = 5.dp, vertical = 2.dp),
                )
            }
        }
        Spacer(Modifier.height(5.dp))
        Text(
            clip.overBall?.let { "Over $it" } ?: "Filmed here",
            color = GInk,
            fontSize = 12.5.sp,
            fontWeight = FontWeight.SemiBold,
        )
        Text(timeOf(clip.recordedAtMs), color = GInk.copy(alpha = 0.5f), fontSize = 11.sp)
    }
}

/** One clip, playing on a loop. Tap the picture to pause; delete only once it is sent. */
@Composable
private fun GalleryPlayer(clip: GalleryClip, onDelete: (() -> Unit)?, onClose: () -> Unit) {
    val view = LocalView.current
    var paused by remember(clip.id) { mutableStateOf(false) }
    var videoView by remember(clip.id) { mutableStateOf<VideoView?>(null) }
    var failed by remember(clip.id) { mutableStateOf(false) }

    Box(Modifier.fillMaxSize().background(Color.Black).clickable(enabled = false) {}) {
        if (!failed) {
            AndroidView(
                modifier = Modifier
                    .fillMaxSize()
                    .clickable(indication = null, interactionSource = remember { MutableInteractionSource() }) {
                        view.performHapticFeedback(Feel.SELECT)
                        videoView?.let { v -> if (v.isPlaying) { v.pause(); paused = true } else { v.start(); paused = false } }
                    },
                factory = { ctx ->
                    VideoView(ctx).apply {
                        setVideoPath(clip.file.absolutePath)
                        setOnPreparedListener { mp -> mp.isLooping = true; start() }
                        setOnErrorListener { _, _, _ -> failed = true; true }
                        videoView = this
                    }
                },
                onRelease = { it.stopPlayback() },
            )
        } else {
            Text(
                "This clip can't be played on this phone.",
                color = GInk.copy(alpha = 0.7f),
                modifier = Modifier.align(Alignment.Center),
            )
        }
        if (paused) {
            Box(
                Modifier.align(Alignment.Center).size(64.dp).clip(CircleShape).background(GScrim),
                contentAlignment = Alignment.Center,
            ) {
                Canvas(Modifier.size(22.dp)) {
                    val path = androidx.compose.ui.graphics.Path().apply {
                        moveTo(size.width * 0.2f, size.height * 0.1f)
                        lineTo(size.width * 0.9f, size.height * 0.5f)
                        lineTo(size.width * 0.2f, size.height * 0.9f)
                        close()
                    }
                    drawPath(path, Color.White)
                }
            }
        }
        Row(
            Modifier
                .fillMaxWidth()
                .align(Alignment.TopCenter)
                .statusBarsPadding()
                .displayCutoutPadding()
                .padding(horizontal = 14.dp, vertical = 10.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Column(
                Modifier
                    .weight(1f)
                    .clip(RoundedCornerShape(12.dp))
                    .background(GScrim)
                    .padding(horizontal = 12.dp, vertical = 7.dp),
            ) {
                Text(clip.overBall?.let { "Over $it" } ?: "Filmed here", color = GInk, fontSize = 14.sp, fontWeight = FontWeight.Bold)
                Text(
                    buildString {
                        append(timeOf(clip.recordedAtMs))
                        append(" · ")
                        append(
                            when (clip.state) {
                                GalleryClip.State.SENT -> "sent to the scorer"
                                GalleryClip.State.SENDING -> "sending now"
                                GalleryClip.State.WAITING -> "waiting to send"
                            },
                        )
                        verdictLabel(clip.wicketsVerdict)?.let { append(" · wickets: ${it.first.lowercase()}") }
                    },
                    color = GInk.copy(alpha = 0.65f),
                    fontSize = 11.5.sp,
                )
            }
            Spacer(Modifier.width(12.dp))
            CloseButton(onClose)
        }
        if (onDelete != null) {
            // Two taps, three seconds apart at most: a clip is the evidence for an appeal,
            // and one stray thumb should not be able to throw it away.
            var armed by remember(clip.id) { mutableStateOf(false) }
            LaunchedEffect(armed) {
                if (armed) {
                    kotlinx.coroutines.delay(3_000)
                    armed = false
                }
            }
            Text(
                if (armed) "Tap again to delete" else "Delete from this phone",
                color = if (armed) Color.White else Color(0xFFFF8A8A),
                fontSize = 13.5.sp,
                fontWeight = FontWeight.SemiBold,
                modifier = Modifier
                    .align(Alignment.BottomCenter)
                    .navigationBarsPadding()
                    .padding(bottom = 22.dp)
                    .clip(RoundedCornerShape(999.dp))
                    .background(if (armed) GRec else GScrim)
                    .clickable {
                        if (armed) {
                            view.performHapticFeedback(Feel.REMOVE)
                            onDelete()
                        } else {
                            view.performHapticFeedback(Feel.TICK)
                            armed = true
                        }
                    }
                    .padding(horizontal = 18.dp, vertical = 11.dp),
            )
        }
    }
}

@Composable
private fun CloseButton(onClick: () -> Unit) {
    val view = LocalView.current
    Box(
        Modifier
            .size(40.dp)
            .clip(CircleShape)
            .background(Color.White.copy(alpha = 0.12f))
            .clickable {
                view.performHapticFeedback(Feel.SELECT)
                onClick()
            }
            .semantics { contentDescription = "Close" },
        contentAlignment = Alignment.Center,
    ) {
        Canvas(Modifier.size(13.dp)) {
            val s = 1.8.dp.toPx()
            drawLine(Color.White, Offset(0f, 0f), Offset(size.width, size.height), s, StrokeCap.Round)
            drawLine(Color.White, Offset(size.width, 0f), Offset(0f, size.height), s, StrokeCap.Round)
        }
    }
}
