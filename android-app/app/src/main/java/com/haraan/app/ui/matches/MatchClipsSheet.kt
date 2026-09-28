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
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import androidx.compose.foundation.lazy.grid.items
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.fillMaxSize
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
import androidx.compose.material.icons.automirrored.filled.KeyboardArrowLeft
import androidx.compose.material.icons.automirrored.filled.KeyboardArrowRight
import androidx.compose.material.icons.filled.CenterFocusStrong
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.Pause
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.foundation.gestures.awaitEachGesture
import androidx.compose.foundation.gestures.awaitFirstDown
import androidx.compose.foundation.layout.offset
import androidx.compose.ui.input.pointer.pointerInput
import kotlinx.coroutines.flow.collectLatest
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.viewinterop.AndroidView
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import com.haraan.app.data.MatchClip
import com.haraan.app.data.MatchDeviceRepository
import com.haraan.app.data.TokenStore
import androidx.compose.animation.AnimatedContent
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.togetherWith
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.PathEffect
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.Stroke
import coil.ImageLoader
import coil.compose.AsyncImage
import coil.decode.VideoFrameDecoder
import coil.request.ImageRequest
import coil.request.videoFrameMillis
import com.haraan.app.data.DeliveryEvidence
import com.haraan.app.data.DeliveryReview
import com.haraan.app.data.ReviewStatus
import com.haraan.app.ui.pressable
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.delay
import kotlinx.coroutines.withContext
import kotlinx.coroutines.launch
import com.haraan.app.ui.theme.HaraanColors

// ─────────────────────────────────────────────────────────────────────────────
//  THE FOOTAGE
//
//  What the paired cameras sent back, for the person who has to make the call.
//
//  This screen shows a clip, says which delivery it is, and — when somebody asks —
//  what a model could see in the footage, factor by factor.
//
//  The read is revealed broadcast-style (see ReviewReveal.kt), but it is the CAMERA'S
//  read, not a decision. One uncalibrated phone at 30fps cannot adjudicate an LBW, so
//  it only says OUT when every question came back for out and certain, and says so
//  plainly when it cannot tell — which, on ground-level footage shot from wherever
//  somebody could stand, is often. The umpire's judgement is still the feature.
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

                // A GRID, not a list.
                //
                // A list of identical rows says only "there are clips". A grid of frames
                // says which delivery each one is before anybody taps anything — and
                // finding the ball you are arguing about is the entire job of this screen.
                else -> LazyVerticalGrid(
                    columns = GridCells.Fixed(3),
                    modifier = Modifier.heightIn(max = 460.dp),
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                    verticalArrangement = Arrangement.spacedBy(8.dp),
                ) {
                    items(list, key = { it.id }) { clip ->
                        ClipTile(clip) { playing = clip }
                    }
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

    playing?.let { clip -> ClipPlayer(clip, matchId) { playing = null } }
}

/**
 * REVIEW on the scorer's keypad: the clip of the ball just bowled, and nothing else.
 *
 * Found by the BALL number the camera stamped on it, not by being newest — the upload of
 * this ball's clip is often still crossing ground Wi-Fi when the scorer taps, and the
 * newest file on the server is then the ball BEFORE, which is exactly the wrong clip to
 * settle an argument with. So it waits, saying so, and plays the right one when it lands.
 */
@Composable
fun LastBallReviewSheet(matchId: String, onDismiss: () -> Unit) {
    val ctx = LocalContext.current
    val repo = remember { MatchDeviceRepository() }
    var clip by remember { mutableStateOf<MatchClip?>(null) }
    var message by remember { mutableStateOf("Getting the last ball's clip…") }
    var waiting by remember { mutableStateOf(true) }
    var showAll by remember { mutableStateOf(false) }

    LaunchedEffect(matchId) {
        val token = TokenStore.getSignedInToken(ctx)
        if (token == null) {
            message = "Sign in to review clips."
            waiting = false
            return@LaunchedEffect
        }
        val started = System.currentTimeMillis()
        while (System.currentTimeMillis() - started < REVIEW_WAIT_MS) {
            val page = repo.clipsPage(token, matchId)
            when {
                page == null -> message = "Can't reach the server. Trying again…"
                page.lastBallSeq == 0 -> {
                    message = "No ball has been bowled with BALL yet."
                    waiting = false
                    return@LaunchedEffect
                }
                page.lastBallClip != null -> {
                    clip = page.lastBallClip
                    return@LaunchedEffect
                }
                page.lastBallCancelled -> {
                    message = "The last ball was called dead, so its clip was not kept."
                    waiting = false
                    return@LaunchedEffect
                }
                page.ballInPlay -> message = "The ball is still being bowled. Enter the result first."
                else -> message = "The camera is sending the clip…"
            }
            kotlinx.coroutines.delay(REVIEW_POLL_MS)
        }
        message = "The camera hasn't sent this ball's clip. It may have missed the ball, or lost signal."
        waiting = false
    }

    clip?.let { found ->
        ClipPlayer(
            found,
            matchId,
            // REVIEW means "show me the read" — no second button to press. Not for the
            // side-on camera, whose review is a read of the bowling action, not an appeal.
            autoReview = found.role != com.haraan.app.data.MatchDeviceRole.BOWLER_ANALYSIS,
            onClose = onDismiss,
        )
        return
    }
    if (showAll) {
        MatchClipsSheet(matchId = matchId, onDismiss = onDismiss)
        return
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
            Text("Review last ball", color = Ink, fontSize = 19.sp, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(14.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                if (waiting) {
                    CircularProgressIndicator(color = Accent, strokeWidth = 2.dp, modifier = Modifier.size(15.dp))
                    Spacer(Modifier.width(10.dp))
                }
                Text(message, color = Ink2, fontSize = 14.sp, lineHeight = 19.sp)
            }
            Spacer(Modifier.height(18.dp))
            Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                Row(
                    Modifier
                        .weight(1f)
                        .clip(RoundedCornerShape(12.dp))
                        .background(Well)
                        .pressable(onClick = { showAll = true })
                        .padding(vertical = 13.dp),
                    horizontalArrangement = Arrangement.Center,
                ) {
                    Text("All footage", color = Ink, fontSize = 15.sp, fontWeight = FontWeight.SemiBold)
                }
                Row(
                    Modifier
                        .weight(1f)
                        .clip(RoundedCornerShape(12.dp))
                        .background(Well)
                        .pressable(onClick = onDismiss)
                        .padding(vertical = 13.dp),
                    horizontalArrangement = Arrangement.Center,
                ) {
                    Text("Close", color = Ink, fontSize = 15.sp, fontWeight = FontWeight.Bold)
                }
            }
        }
    }
}

/** Long enough for a 10s clip to finish and cross slow ground Wi-Fi. */
private const val REVIEW_WAIT_MS = 45_000L
private const val REVIEW_POLL_MS = 2_000L

/**
 * One delivery in the grid: a frame from the clip, the over it belongs to, its length.
 *
 * The thumbnail is decoded from the video itself rather than stored server-side, because
 * generating one on the server needs ffmpeg, which is not installed — and Coil already
 * caches decoded frames, so a grid that scrolls does not decode twice.
 *
 * The frame is taken a second in rather than at zero: the first frame of a delivery clip
 * is usually the bowler still walking back, and a grid of identical run-up stills is no
 * more useful than the list this replaced.
 */
@Composable
private fun ClipTile(clip: MatchClip, onPlay: () -> Unit) {
    val context = LocalContext.current
    val request = remember(clip.url) {
        ImageRequest.Builder(context)
            .data(clip.url)
            .videoFrameMillis(1_000)
            .crossfade(true)
            .build()
    }

    Column(
        Modifier
            .clip(RoundedCornerShape(10.dp))
            .background(Well)
            .pressable(onClick = onPlay),
    ) {
        Box(
            Modifier
                .fillMaxWidth()
                .aspectRatio(3f / 4f)
                .background(Color.Black),
            contentAlignment = Alignment.Center,
        ) {
            AsyncImage(
                model = request,
                contentDescription = if (clip.overBall.isNotBlank()) {
                    "Delivery at over ${clip.overBall}"
                } else {
                    "Unmarked delivery"
                },
                imageLoader = videoImageLoader(context),
                contentScale = ContentScale.Crop,
                modifier = Modifier.fillMaxSize(),
            )
            Box(
                Modifier.size(30.dp).clip(CircleShape).background(Color.Black.copy(alpha = 0.45f)),
                contentAlignment = Alignment.Center,
            ) {
                Icon(Icons.Filled.PlayArrow, null, tint = Color.White, modifier = Modifier.size(17.dp))
            }
            // A reviewed delivery is marked, so a scorer can see at a glance which balls
            // have already been looked at.
            if (clip.review != null) {
                Box(
                    Modifier
                        .align(Alignment.TopEnd)
                        .padding(6.dp)
                        .size(7.dp)
                        .clip(CircleShape)
                        .background(Color(0xFF4ADE80)),
                )
            }
        }
        Column(Modifier.padding(horizontal = 8.dp, vertical = 7.dp)) {
            Text(
                if (clip.overBall.isNotBlank()) "Over ${clip.overBall}" else "Unmarked",
                color = Ink,
                fontSize = 12.5.sp,
                fontWeight = FontWeight.SemiBold,
                maxLines = 1,
                style = TextStyle(fontFeatureSettings = "tnum"),
            )
            if (clip.durationMs > 0) {
                Text(
                    "${clip.durationMs / 1000}s",
                    color = Ink3,
                    fontSize = 10.5.sp,
                    style = TextStyle(fontFeatureSettings = "tnum"),
                )
            }
        }
    }
}

/**
 * A loader that can decode a video frame.
 *
 * Built once and remembered: an ImageLoader carries its own caches, and creating one per
 * tile would defeat the caching this exists for.
 */
@Composable
private fun videoImageLoader(context: android.content.Context): ImageLoader = remember {
    ImageLoader.Builder(context)
        .components { add(VideoFrameDecoder.Factory()) }
        .build()
}

/**
 * Playback.
 *
 * A plain [VideoView] rather than a media library: this is one short mp4 off our own
 * server, and pulling in a player stack for it would be a dependency the app carries
 * everywhere to serve one sheet.
 */
@Composable
private fun ClipPlayer(clip: MatchClip, matchId: String, autoReview: Boolean = false, onClose: () -> Unit) {
    val ctx = LocalContext.current
    val repo = remember { MatchDeviceRepository() }
    val scope = rememberCoroutineScope()
    var review by remember(clip.id) { mutableStateOf(clip.review) }
    var status by remember(clip.id) { mutableStateOf(clip.reviewStatus) }
    var failure by remember(clip.id) { mutableStateOf(clip.reviewError) }
    // A decode failure is about this handset, never about the review.
    var playbackFailed by remember(clip.id) { mutableStateOf(false) }

    /*
     * FRAME MODE.
     *
     * Playback and frame-stepping are genuinely different things and cannot share one
     * surface. A VideoView seeks to the nearest keyframe, which on an 8-second clip can be
     * a second away — useless when the question is whether the ball hit pad or bat first.
     *
     * So stepping switches to still frames pulled with MediaMetadataRetriever at
     * OPTION_CLOSEST, which is frame-accurate, and the video surface is hidden while that
     * is showing. Slow motion stays on the video: 0.25x through the real decoder looks far
     * better than flicking stills at four a second.
     */
    var frame by remember(clip.id) { mutableStateOf<android.graphics.Bitmap?>(null) }
    var framePositionMs by remember(clip.id) { mutableStateOf(0L) }
    var speed by remember(clip.id) { mutableStateOf(1f) }
    var player by remember(clip.id) { mutableStateOf<android.media.MediaPlayer?>(null) }

    // Where the playhead is while the video runs, read off the player every frame.
    var playPositionMs by remember(clip.id) { mutableStateOf(0L) }
    // Non-null while a finger is on the timeline: the position under it, drawn at once,
    // with the (slower) frame decode catching up behind.
    var scrubMs by remember(clip.id) { mutableStateOf<Long?>(null) }
    val view = androidx.compose.ui.platform.LocalView.current

    val retriever = remember(clip.url) { android.media.MediaMetadataRetriever() }
    var retrieverReady by remember(clip.url) { mutableStateOf(false) }
    LaunchedEffect(clip.url) {
        retrieverReady = withContext(Dispatchers.IO) {
            runCatching { retriever.setDataSource(clip.url, HashMap<String, String>()) }.isSuccess
        }
    }
    DisposableEffect(clip.url) {
        onDispose { runCatching { retriever.release() } }
    }

    // Pull one frame at a position. Off the main thread — decoding a 1080p frame is not
    // something to do while the UI is trying to stay at sixty.
    suspend fun stepTo(positionMs: Long) {
        if (!retrieverReady) return
        val clamped = positionMs.coerceIn(0L, if (clip.durationMs > 0) clip.durationMs else 10_000L)
        val bitmap = withContext(Dispatchers.IO) {
            runCatching {
                retriever.getFrameAtTime(
                    clamped * 1000L,
                    android.media.MediaMetadataRetriever.OPTION_CLOSEST,
                )
            }.getOrNull()
        }
        if (bitmap != null) {
            player?.let { runCatching { it.pause() } }
            framePositionMs = clamped
            frame = bitmap
        }
    }

    val durationMs: Long = clip.durationMs.takeIf { it > 0 }
        ?: player?.let { runCatching { it.duration.toLong() }.getOrNull() }?.takeIf { it > 0 }
        ?: 10_000L

    // The playhead follows the video frame by frame while it plays. Stops in frame mode,
    // where the position is whatever frame is on screen.
    LaunchedEffect(player, frame) {
        val mp = player ?: return@LaunchedEffect
        if (frame != null) return@LaunchedEffect
        while (true) {
            androidx.compose.runtime.withFrameMillis { }
            runCatching { mp.currentPosition.toLong() }.getOrNull()?.let { playPositionMs = it }
        }
    }

    // Scrubbing decodes only the LATEST position asked for. A finger crosses dozens of
    // positions a second; decoding each in turn would leave the picture lagging far
    // behind the thumb.
    LaunchedEffect(retrieverReady) {
        if (!retrieverReady) return@LaunchedEffect
        androidx.compose.runtime.snapshotFlow { scrubMs }
            .collectLatest { ms -> if (ms != null) stepTo(ms) }
    }

    // Poll while the queued review runs.
    //
    // Keyed on the status so it starts when the state becomes unsettled and stops the
    // moment it settles — no loop left spinning behind a closed dialog, and no polling at
    // all for the clips nobody has asked about, which is nearly all of them.
    //
    // The ceiling is a real outcome, not a giveup: a review still unfinished after two
    // minutes has gone wrong somewhere the scorer cannot see, and saying so beats a
    // spinner that never stops.
    LaunchedEffect(clip.id, status) {
        if (status != ReviewStatus.PENDING && status != ReviewStatus.PROCESSING) {
            return@LaunchedEffect
        }
        val token = TokenStore.getToken(ctx)
        if (!TokenStore.isSignedIn(token)) return@LaunchedEffect

        var waited = 0L
        while (waited < 120_000) {
            delay(2_000)
            waited += 2_000
            val state = runCatching { repo.reviewStatus(token!!, matchId, clip.id) }.getOrNull()
                ?: continue
            if (state.status.settled) {
                review = state.review
                failure = state.error
                status = state.status
                return@LaunchedEffect
            }
        }
        status = ReviewStatus.FAILED
        failure = "The review is taking longer than expected. Try again."
    }

    // Asks the server for the model's read. Status goes PENDING first, which starts the
    // poll above and puts "Reading…" in the reveal's rows while it runs.
    fun startReview() {
        failure = null
        status = ReviewStatus.PENDING
        scope.launch {
            val token = TokenStore.getToken(ctx)
            val state = if (TokenStore.isSignedIn(token)) {
                runCatching { repo.requestReview(token!!, matchId, clip.id) }.getOrNull()
            } else {
                null
            }
            review = state?.review
            failure = state?.error
            // Null means the call itself never landed; FAILED stops the poll PENDING started.
            status = state?.status ?: ReviewStatus.FAILED
        }
    }

    // REVIEW opened this: start the read at once. Only for a clip nobody has asked about —
    // a finished or running review is shown as it is, never bought twice.
    LaunchedEffect(clip.id) {
        if (autoReview && review == null && status == ReviewStatus.NONE) startReview()
    }

    Dialog(onDismissRequest = onClose, properties = DialogProperties(usePlatformDefaultWidth = false)) {
        Column(
            Modifier
                .padding(horizontal = 14.dp, vertical = 24.dp)
                .fillMaxWidth()
                .clip(RoundedCornerShape(20.dp))
                .background(Color.Black)
                .border(1.dp, Color.White.copy(alpha = 0.08f), RoundedCornerShape(20.dp))
                // The review runs to five factors, a note and a disclaimer. A portrait
                // video at 9:16 already fills the screen, so without this the whole read
                // was drawn below the bottom edge and could not be reached at all.
                .verticalScroll(rememberScrollState()),
        ) {
            // What this footage IS, before the footage: the ball, the camera, and a close
            // control where every video viewer puts one.
            Row(
                Modifier.fillMaxWidth().padding(start = 16.dp, end = 8.dp, top = 12.dp, bottom = 10.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Column(Modifier.weight(1f)) {
                    Text(
                        if (clip.overBall.isNotBlank()) "Over ${clip.overBall}" else "Unmarked delivery",
                        color = Color.White,
                        fontSize = 16.sp,
                        fontWeight = FontWeight.Bold,
                        style = TextStyle(fontFeatureSettings = "tnum"),
                    )
                    Text(clip.roleLabel, color = Color.White.copy(alpha = 0.55f), fontSize = 12.sp)
                }
                Box(
                    Modifier
                        .size(38.dp)
                        .clip(CircleShape)
                        .background(Color.White.copy(alpha = 0.12f))
                        .pressable(onClick = onClose),
                    contentAlignment = Alignment.Center,
                ) {
                    Icon(Icons.Filled.Close, "Close", tint = Color.White, modifier = Modifier.size(20.dp))
                }
            }
            Box(
                Modifier
                    .fillMaxWidth()
                    // Capped rather than free: the clip keeps its shape, but it stops
                    // taking the entire screen and pushing the answer out of sight.
                    .heightIn(max = 340.dp)
                    .aspectRatio(9f / 16f),
                contentAlignment = Alignment.Center,
            ) {
                if (frame != null) {
                    // Frame mode: a still, exactly where the scrubber says it is.
                    androidx.compose.foundation.Image(
                        bitmap = frame!!.asImageBitmap(),
                        contentDescription = "Frame at ${framePositionMs} ms",
                        contentScale = ContentScale.Fit,
                        modifier = Modifier.fillMaxSize(),
                    )
                } else if (!playbackFailed) {
                    AndroidView(
                        modifier = Modifier.fillMaxSize(),
                        factory = { context ->
                            VideoView(context).apply {
                                setVideoPath(clip.url)
                                // Loops, because reviewing a dismissal means watching it
                                // again, and again — the entire point of the footage.
                                setOnPreparedListener { mp ->
                                    mp.isLooping = true
                                    player = mp
                                    // Applied on prepare as well as on change, so a speed
                                    // chosen before the clip loaded is not silently lost.
                                    runCatching {
                                        mp.playbackParams = mp.playbackParams.setSpeed(speed)
                                    }
                                    start()
                                }
                                // Handled HERE rather than left to the system.
                                //
                                // Returning true suppresses Android's own "Can't play
                                // this video." dialog, which otherwise appears ON TOP of
                                // the review, swallows every tap meant for it, and leaves
                                // a black rectangle behind with no explanation. A codec
                                // this device cannot decode says nothing about whether
                                // the delivery can be reviewed — the analysis runs on the
                                // server and does not care what the handset can play.
                                setOnErrorListener { _, _, _ -> playbackFailed = true; true }
                            }
                        },
                    )
                } else {
                    Column(
                        horizontalAlignment = Alignment.CenterHorizontally,
                        modifier = Modifier.padding(horizontal = 28.dp),
                    ) {
                        Text(
                            "This phone can't play this clip",
                            color = Color.White.copy(alpha = 0.85f),
                            fontSize = 15.sp,
                            fontWeight = FontWeight.SemiBold,
                        )
                        Spacer(Modifier.height(8.dp))
                        Text(
                            "The format isn't supported here. You can still review the "
                                + "delivery — the analysis runs on the server.",
                            color = Color.White.copy(alpha = 0.5f),
                            fontSize = 12.5.sp,
                            lineHeight = 18.sp,
                            textAlign = TextAlign.Center,
                        )
                    }
                }
            }
            // TRANSPORT. A timeline you can drag, frame steps that click like a dial, and
            // the speeds a review actually uses.
            if (!playbackFailed) {
                val shownMs = scrubMs ?: if (frame != null) framePositionMs else playPositionMs
                ClipTimeline(
                    positionMs = shownMs,
                    durationMs = durationMs,
                    onScrub = { ms ->
                        // A detent every 100ms of footage: the thumb feels the clip pass
                        // under it, and can count its way to the moment of impact.
                        val before = (scrubMs ?: shownMs) / 100
                        if (ms / 100 != before) {
                            view.performHapticFeedback(android.view.HapticFeedbackConstants.CLOCK_TICK)
                        }
                        scrubMs = ms
                    },
                    onScrubEnd = {
                        scrubMs?.let { framePositionMs = it }
                        scrubMs = null
                    },
                    modifier = Modifier.padding(horizontal = 16.dp).padding(top = 12.dp),
                )
                Row(
                    Modifier.fillMaxWidth().padding(horizontal = 16.dp).padding(top = 4.dp, bottom = 12.dp),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    StepButton(Icons.AutoMirrored.Filled.KeyboardArrowLeft, "Previous frame") {
                        view.performHapticFeedback(android.view.HapticFeedbackConstants.CLOCK_TICK)
                        val from = if (frame != null) framePositionMs else playPositionMs
                        scope.launch { stepTo(from - FRAME_STEP_MS) }
                    }
                    Spacer(Modifier.width(6.dp))
                    // Play / pause. Pausing lands in frame mode at the exact frame on
                    // screen, so the steps either side carry on from there.
                    val playing = frame == null
                    Box(
                        Modifier
                            .size(46.dp)
                            .clip(CircleShape)
                            .background(Color.White)
                            .pressable(onClick = {
                                view.performHapticFeedback(android.view.HapticFeedbackConstants.VIRTUAL_KEY)
                                if (playing) {
                                    scope.launch { stepTo(playPositionMs) }
                                } else {
                                    frame = null
                                    player?.let { mp ->
                                        runCatching { mp.seekTo(framePositionMs.toInt()); mp.start() }
                                    }
                                }
                            }),
                        contentAlignment = Alignment.Center,
                    ) {
                        Icon(
                            if (playing) Icons.Filled.Pause else Icons.Filled.PlayArrow,
                            if (playing) "Pause" else "Play",
                            tint = Color.Black,
                            modifier = Modifier.size(24.dp),
                        )
                    }
                    Spacer(Modifier.width(6.dp))
                    StepButton(Icons.AutoMirrored.Filled.KeyboardArrowRight, "Next frame") {
                        view.performHapticFeedback(android.view.HapticFeedbackConstants.CLOCK_TICK)
                        val from = if (frame != null) framePositionMs else playPositionMs
                        scope.launch { stepTo(from + FRAME_STEP_MS) }
                    }
                    Spacer(Modifier.weight(1f))
                    SpeedSelector(
                        speeds = listOf(1f, 0.5f, 0.25f),
                        selected = speed,
                    ) { option ->
                        view.performHapticFeedback(android.view.HapticFeedbackConstants.CLOCK_TICK)
                        speed = option
                        // A speed means "watch it like this" — from a still, that resumes.
                        frame = null
                        player?.let { mp ->
                            runCatching {
                                mp.playbackParams = mp.playbackParams.setSpeed(option)
                                // setPlaybackParams starts a paused player on some
                                // devices; keep the intent explicit.
                                if (!mp.isPlaying) {
                                    mp.seekTo(framePositionMs.toInt())
                                    mp.start()
                                }
                            }
                        }
                    }
                }
            }

            // THE FLIGHT, SIDE-ON — only from the camera standing side-on, the one angle
            // where the picture's vertical is the ball's height. From behind the bowler's
            // arm the same drawing would be a guess, so it is not offered there.
            if (clip.role == com.haraan.app.data.MatchDeviceRole.BOWLER_ANALYSIS) {
                Column(Modifier.fillMaxWidth().padding(horizontal = 16.dp).padding(bottom = 18.dp)) {
                    Box(Modifier.fillMaxWidth().height(1.dp).background(Color.White.copy(alpha = 0.1f)))
                    Spacer(Modifier.height(16.dp))
                    val track = clip.track
                    if (track != null) {
                        SideOnFlightView(track)
                    } else {
                        Text(
                            "The side-on camera didn't pick up the ball in this clip, so there's no flight to draw.",
                            color = Color.White.copy(alpha = 0.45f),
                            fontSize = 12.5.sp,
                            lineHeight = 18.sp,
                        )
                    }
                }
            }

            // THE REVIEW.
            //
            // Under the footage, never instead of it. Seeing the ball again is most of
            // the value on a ground that has never had a replay at all; the read is what
            // you reach for when watching it four times has not settled the argument.
            Column(
                Modifier.fillMaxWidth().padding(horizontal = 16.dp).padding(bottom = 18.dp),
            ) {
                Box(Modifier.fillMaxWidth().height(1.dp).background(Color.White.copy(alpha = 0.1f)))
                Spacer(Modifier.height(16.dp))

                val current = review
                val reading = status == ReviewStatus.PENDING || status == ReviewStatus.PROCESSING
                val cameraWickets = clip.track?.wickets
                if (current != null || reading || cameraWickets != null) {
                    DeliveryReviewPanel(
                        review = current,
                        wickets = cameraWickets,
                        reading = reading,
                    )
                }
                if (current == null && !reading) {
                    if (cameraWickets != null) Spacer(Modifier.height(16.dp))
                    Column {
                        Row(
                            Modifier
                                .fillMaxWidth()
                                .clip(RoundedCornerShape(14.dp))
                                .background(Accent)
                                .pressable(
                                    onClick = {
                                        view.performHapticFeedback(android.view.HapticFeedbackConstants.CONFIRM)
                                        startReview()
                                    },
                                )
                                .padding(vertical = 15.dp),
                            horizontalArrangement = Arrangement.Center,
                            verticalAlignment = Alignment.CenterVertically,
                        ) {
                            Icon(
                                if (status == ReviewStatus.FAILED) Icons.Filled.Refresh else Icons.Filled.CenterFocusStrong,
                                null, tint = Color.White, modifier = Modifier.size(18.dp),
                            )
                            Spacer(Modifier.width(8.dp))
                            Text(
                                when {
                                    status == ReviewStatus.FAILED -> "Try again"
                                    cameraWickets != null -> "Read pitching and impact"
                                    else -> "Review this ball"
                                },
                                color = Color.White,
                                fontSize = 14.5.sp,
                                fontWeight = FontWeight.Bold,
                            )
                        }
                        failure?.let { message ->
                            Spacer(Modifier.height(10.dp))
                            // The server's own words. Written to be shown: never an
                            // exception, never an upstream body, never a stack trace.
                            Text(
                                message,
                                color = Color.White.copy(alpha = 0.55f),
                                fontSize = 12.5.sp,
                                lineHeight = 18.sp,
                            )
                        }
                    }
                }
            }
        }
    }
}

/** A frame step. Deliberately large targets — this gets tapped repeatedly. */
@Composable
private fun StepButton(icon: androidx.compose.ui.graphics.vector.ImageVector, label: String, onClick: () -> Unit) {
    Box(
        Modifier
            .size(42.dp)
            .clip(CircleShape)
            .background(Color.White.copy(alpha = 0.12f))
            .pressable(onClick = onClick),
        contentAlignment = Alignment.Center,
    ) {
        Icon(icon, label, tint = Color.White, modifier = Modifier.size(24.dp))
    }
}

/**
 * The speeds as ONE control with a thumb that slides between them — three loose chips
 * read as three unrelated buttons, when this is a single setting with three positions.
 */
@Composable
private fun SpeedSelector(speeds: List<Float>, selected: Float, onPick: (Float) -> Unit) {
    val index = speeds.indexOf(selected).coerceAtLeast(0)
    val slot = 44.dp
    val thumb by androidx.compose.animation.core.animateDpAsState(
        targetValue = slot * index,
        animationSpec = androidx.compose.animation.core.spring(dampingRatio = 0.7f, stiffness = 700f),
        label = "speedThumb",
    )
    Box(
        Modifier
            .clip(RoundedCornerShape(50))
            .background(Color.White.copy(alpha = 0.12f))
            .padding(3.dp),
    ) {
        Box(
            Modifier
                .offset(x = thumb)
                .size(width = slot, height = 32.dp)
                .clip(RoundedCornerShape(50))
                .background(Color.White),
        )
        Row {
            speeds.forEach { option ->
                val on = option == selected
                Box(
                    Modifier
                        .size(width = slot, height = 32.dp)
                        .clip(RoundedCornerShape(50))
                        .pressable(onClick = { onPick(option) }),
                    contentAlignment = Alignment.Center,
                ) {
                    Text(
                        if (option == 1f) "1×" else "${option}×".replace("0.", "."),
                        color = if (on) Color.Black else Color.White.copy(alpha = 0.75f),
                        fontSize = 12.5.sp,
                        fontWeight = if (on) FontWeight.Bold else FontWeight.Medium,
                        style = TextStyle(fontFeatureSettings = "tnum"),
                    )
                }
            }
        }
    }
}

/**
 * The clip's length as a track you can put a finger on. Tap to jump, drag to scrub; the
 * thumb swells while held so it's clear the clip is under your control, and the time
 * reads out beside it to the hundredth — the unit an lbw argument is settled in.
 */
@Composable
private fun ClipTimeline(
    positionMs: Long,
    durationMs: Long,
    onScrub: (Long) -> Unit,
    onScrubEnd: () -> Unit,
    modifier: Modifier = Modifier,
) {
    var held by remember { mutableStateOf(false) }
    // The gesture outlives recompositions; it must always call the CURRENT callbacks.
    val scrub = androidx.compose.runtime.rememberUpdatedState(onScrub)
    val end = androidx.compose.runtime.rememberUpdatedState(onScrubEnd)
    val thumbScale by androidx.compose.animation.core.animateFloatAsState(
        if (held) 1.5f else 1f, label = "scrubThumb"
    )
    val fraction = (positionMs.toFloat() / durationMs.coerceAtLeast(1)).coerceIn(0f, 1f)
    Row(modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
        Box(
            Modifier
                .weight(1f)
                .height(28.dp)
                .pointerInput(durationMs) {
                    fun at(x: Float) = ((x / size.width).coerceIn(0f, 1f) * durationMs).toLong()
                    // One gesture for tap and drag alike: down jumps, movement scrubs, up
                    // lets go. Two detectors on one track fight over who owns the finger.
                    awaitEachGesture {
                        val down = awaitFirstDown()
                        down.consume()
                        held = true
                        scrub.value(at(down.position.x))
                        while (true) {
                            val change = awaitPointerEvent().changes.firstOrNull { it.id == down.id } ?: break
                            if (!change.pressed) break
                            change.consume()
                            scrub.value(at(change.position.x))
                        }
                        held = false
                        end.value()
                    }
                },
            contentAlignment = Alignment.CenterStart,
        ) {
            Canvas(Modifier.fillMaxWidth().height(28.dp)) {
                val y = size.height / 2f
                val h = 4.dp.toPx()
                drawRoundRect(
                    Color.White.copy(alpha = 0.18f),
                    topLeft = Offset(0f, y - h / 2), size = Size(size.width, h),
                    cornerRadius = CornerRadius(h / 2),
                )
                drawRoundRect(
                    Accent,
                    topLeft = Offset(0f, y - h / 2), size = Size(size.width * fraction, h),
                    cornerRadius = CornerRadius(h / 2),
                )
                drawCircle(Color.White, radius = 7.dp.toPx() * thumbScale, center = Offset(size.width * fraction, y))
            }
        }
        Spacer(Modifier.width(10.dp))
        Text(
            "%.2fs".format(positionMs / 1000.0),
            color = Color.White.copy(alpha = 0.75f),
            fontSize = 12.sp,
            style = TextStyle(fontFeatureSettings = "tnum"),
            modifier = Modifier.width(46.dp),
            textAlign = TextAlign.End,
        )
    }
}

/** One frame at 30fps. Close enough for every camera this app records with. */
private const val FRAME_STEP_MS = 33L

/**
 * The wait, made to feel like the machine is doing the thing it says it is doing.
 *
 * A review takes several seconds — the clip is prepared, sent, and watched by a model —
 * and a spinner beside the words "please wait" spends that time telling the scorer
 * nothing. Somebody is standing on a field mid-argument about an appeal, and the screen
 * should look like it is working on their question.
 *
 * So: a strip of frames with a light sweeping across it, which is a picture of footage
 * being read, and a line of text naming the stage the pipeline is genuinely at.
 *
 * There is deliberately NO percentage and no progress bar that fills. We cannot know how
 * far through a Vertex call we are, and a bar creeping to 90% and sitting there is a
 * small lie told every single time. The sweep repeats because the work is ongoing; it
 * never pretends to measure it.
 */
@Composable
private fun ReviewInProgress() {
    val transition = rememberInfiniteTransition(label = "reviewSweep")
    val sweep by transition.animateFloat(
        initialValue = -0.25f,
        targetValue = 1.25f,
        animationSpec = infiniteRepeatable(
            animation = tween(1700, easing = LinearEasing),
        ),
        label = "sweepX",
    )

    // The three stages the pipeline actually moves through, named honestly. They advance
    // on a timer rather than on real events because the server reports one status for the
    // whole job — so the wording stays true to what happens, in order, without claiming
    // to know which step is running right now.
    val stages = listOf(
        "Preparing the footage",
        "Watching the delivery",
        "Reading line and impact",
    )
    var stage by remember { mutableStateOf(0) }
    LaunchedEffect(Unit) {
        while (true) {
            delay(2400)
            stage = (stage + 1) % stages.size
        }
    }

    Column(Modifier.fillMaxWidth()) {
        // The filmstrip. Bars stand for frames; the sweep is the read passing over them.
        Canvas(
            Modifier
                .fillMaxWidth()
                .height(34.dp),
        ) {
            val bars = 26
            val gap = size.width / bars
            val barWidth = gap * 0.42f
            repeat(bars) { i ->
                val centre = (i + 0.5f) / bars
                // Distance from the sweep decides brightness, so the light appears to
                // travel THROUGH the strip rather than sitting on top of it.
                val distance = kotlin.math.abs(centre - sweep)
                val glow = (1f - (distance / 0.18f)).coerceIn(0f, 1f)
                val height = size.height * (0.34f + 0.66f * glow)
                drawRoundRect(
                    color = Color.White.copy(alpha = 0.12f + 0.72f * glow),
                    topLeft = Offset(i * gap + (gap - barWidth) / 2f, (size.height - height) / 2f),
                    size = Size(barWidth, height),
                    cornerRadius = CornerRadius(barWidth / 2f, barWidth / 2f),
                )
            }
        }

        Spacer(Modifier.height(16.dp))
        // Crossfaded so the stage changes read as one process moving on, not as text
        // being swapped out underneath the reader.
        AnimatedContent(
            targetState = stage,
            transitionSpec = { fadeIn(tween(400)) togetherWith fadeOut(tween(400)) },
            label = "reviewStage",
        ) { index ->
            Text(
                stages[index],
                color = Color.White.copy(alpha = 0.82f),
                fontSize = 14.sp,
                fontWeight = FontWeight.Medium,
            )
        }
        Spacer(Modifier.height(6.dp))
        Text(
            "This usually takes a few seconds.",
            color = Color.White.copy(alpha = 0.45f),
            fontSize = 12.sp,
        )
    }
}

/**
 * The read of one delivery, under the footage it came from.
 *
 * Order matters and is cricket's, not the JSON's: pitched, impact, bat, height, stumps —
 * the sequence an umpire actually decides in. A shuffled list of the same five facts reads
 * as a data dump; in this order it reads as somebody working through an appeal.
 *
 * The factors are shown as [ReviewReveal]: built one at a time, coloured by which way each
 * points, ending in the camera's read. That read is deliberately hard to push to OUT — see
 * [cameraReadOf] — and the line at the bottom still says the call stays with the players.
 */
@Composable
private fun DeliveryReviewPanel(
    review: DeliveryReview?,
    modifier: Modifier = Modifier,
    wickets: com.haraan.app.data.CameraWickets? = null,
    reading: Boolean = false,
) {
    Column(modifier.fillMaxWidth()) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text(
                "WHAT THE CAMERA SAW",
                color = Color.White.copy(alpha = 0.5f),
                fontSize = 9.5.sp,
                fontWeight = FontWeight.ExtraBold,
                letterSpacing = 1.4.sp,
                modifier = Modifier.weight(1f),
            )
            if (review != null) {
                Text(
                    visibilityLabel(review.visibility).uppercase(),
                    color = if (review.visibility == "good") {
                        Color.White.copy(alpha = 0.7f)
                    } else {
                        Color(0xFFF5A623)
                    },
                    fontSize = 9.5.sp,
                    fontWeight = FontWeight.ExtraBold,
                    letterSpacing = 1.1.sp,
                )
            }
        }
        Spacer(Modifier.height(14.dp))

        // The read, built a question at a time. Replays on a tap, because the people round
        // the phone ask to see it again every time.
        var generation by remember { mutableStateOf(0) }
        ReviewReveal(review = review, generation = generation, wickets = wickets, reading = reading)
        wickets?.note?.takeIf { it.isNotBlank() }?.let { note ->
            Spacer(Modifier.height(8.dp))
            // The camera's own sentence under its answer: the centimetres, or what it
            // needed and did not have.
            Text(
                "Wickets, measured by the camera: " + note,
                color = Color.White.copy(alpha = 0.55f),
                fontSize = 12.sp,
                lineHeight = 17.sp,
            )
        }
        Spacer(Modifier.height(12.dp))
        Row(
            Modifier
                .clip(RoundedCornerShape(999.dp))
                .background(Color.White.copy(alpha = 0.1f))
                .pressable(onClick = { generation++ })
                .padding(horizontal = 14.dp, vertical = 8.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Icon(Icons.Filled.Refresh, null, tint = Color.White, modifier = Modifier.size(15.dp))
            Spacer(Modifier.width(6.dp))
            Text("Show the read again", color = Color.White, fontSize = 12.5.sp, fontWeight = FontWeight.SemiBold)
        }

        review?.notes?.let { note ->
            Spacer(Modifier.height(16.dp))
            Text(
                note,
                color = Color.White.copy(alpha = 0.5f),
                fontSize = 12.sp,
                lineHeight = 18.sp,
            )
        }

        review?.evidence?.let { evidence ->
            Spacer(Modifier.height(20.dp))
            DeliveryMap(evidence)
        }

        Spacer(Modifier.height(16.dp))
        Box(Modifier.fillMaxWidth().height(1.dp).background(Color.White.copy(alpha = 0.1f)))
        Spacer(Modifier.height(14.dp))
        // The line that keeps the feature honest. It is not fine print and it is not
        // styled like fine print: anyone who reads the five rows above reads this too.
        Text(
            "This is not a decision. One camera cannot judge an LBW — it only reports what "
                + "the footage shows. The call stays with the players.",
            color = Color.White.copy(alpha = 0.55f),
            fontSize = 12.sp,
            lineHeight = 18.sp,
        )
    }
}

/**
 * THE DELIVERY MAP — the 2D foundation the future 2.5D/3D view is built on.
 *
 * Drawn in FRAME SPACE, and that choice is the whole point. The coordinates behind this
 * are normalised positions in the video: x across the picture, y down it. They are not
 * positions on a pitch, because nothing in the pipeline is calibrated — no stump height,
 * no crease reference, no camera pose.
 *
 * So this deliberately does NOT draw a cricket pitch. A pitch diagram with dots on it
 * says "we know where on the ground this happened", and we do not. A camera frame with
 * dots on it says "this is where it appeared in the picture", which is exactly what we
 * have. The day a calibration step exists, the same data can be projected onto a real
 * pitch and this becomes the tactical view; until then the honest drawing is the frame.
 *
 * DETECTED is solid. PROJECTED is dashed. That distinction is drawn, keyed and never
 * mixed, because a prediction rendered like an observation is the one thing that would
 * make this feature untrustworthy.
 */
@Composable
private fun DeliveryMap(evidence: DeliveryEvidence) {
    Text(
        "WHERE IT HAPPENED IN FRAME",
        color = Color.White.copy(alpha = 0.5f),
        fontSize = 9.5.sp,
        fontWeight = FontWeight.ExtraBold,
        letterSpacing = 1.4.sp,
    )
    Spacer(Modifier.height(10.dp))

    if (evidence.isEmpty) {
        // Nothing was detected. Saying so beats an empty rectangle that looks broken —
        // and on ground-level phone footage this is the common case, not the error case.
        Text(
            "The camera did not fix a position for the ball, the bounce or the impact in "
                + "this clip, so there is nothing to plot.",
            color = Color.White.copy(alpha = 0.45f),
            fontSize = 12.5.sp,
            lineHeight = 18.sp,
        )
        return
    }

    val track = evidence.ballPoints
    Canvas(
        Modifier
            .fillMaxWidth()
            .aspectRatio(16f / 9f)
            .clip(RoundedCornerShape(8.dp))
            .background(Color.White.copy(alpha = 0.05f)),
    ) {
        // A faint grid: this is a picture, and the grid says so.
        val step = size.width / 6f
        for (i in 1..5) {
            drawLine(
                Color.White.copy(alpha = 0.06f),
                Offset(i * step, 0f),
                Offset(i * step, size.height),
                strokeWidth = 1f,
            )
        }
        val vStep = size.height / 3f
        for (i in 1..2) {
            drawLine(
                Color.White.copy(alpha = 0.06f),
                Offset(0f, i * vStep),
                Offset(size.width, i * vStep),
                strokeWidth = 1f,
            )
        }

        fun px(x: Float, y: Float) = Offset(x * size.width, y * size.height)

        // The tracked path — solid, because every one of these was seen.
        if (track.size >= 2) {
            val path = Path()
            track.forEachIndexed { i, p ->
                val o = px(p.x, p.y)
                if (i == 0) path.moveTo(o.x, o.y) else path.lineTo(o.x, o.y)
            }
            drawPath(
                path,
                Color(0xFF6E9BF5),
                style = Stroke(width = 2.5.dp.toPx(), cap = StrokeCap.Round),
            )
        }
        track.forEach { p ->
            drawCircle(Color(0xFF6E9BF5), radius = 4.dp.toPx(), center = px(p.x, p.y))
        }

        // The bounce and the impact, when the camera actually fixed them.
        evidence.pitching?.takeIf { it.detected && it.x != null && it.y != null }?.let { m ->
            val o = px(m.x!!, m.y!!)
            drawCircle(Color(0xFF4ADE80), radius = 7.dp.toPx(), center = o)
            drawCircle(Color.Black, radius = 3.dp.toPx(), center = o)
        }
        evidence.impact?.takeIf { it.detected && it.x != null && it.y != null }?.let { m ->
            val o = px(m.x!!, m.y!!)
            drawCircle(Color(0xFFF5A623), radius = 7.dp.toPx(), center = o)
            drawCircle(Color.Black, radius = 3.dp.toPx(), center = o)
        }

        // The projection — DASHED, and only ever dashed.
        evidence.projection?.takeIf { it.predicted && it.x != null && it.y != null }?.let { proj ->
            val from = track.lastOrNull()?.let { px(it.x, it.y) }
                ?: evidence.impact?.takeIf { it.x != null }?.let { px(it.x!!, it.y!!) }
            if (from != null) {
                drawLine(
                    color = Color(0xFFF97066),
                    start = from,
                    end = px(proj.x!!, proj.y!!),
                    strokeWidth = 2.dp.toPx(),
                    cap = StrokeCap.Round,
                    pathEffect = PathEffect.dashPathEffect(
                        floatArrayOf(9.dp.toPx(), 7.dp.toPx()),
                    ),
                )
            }
            drawCircle(
                Color(0xFFF97066),
                radius = 5.dp.toPx(),
                center = px(proj.x!!, proj.y!!),
                style = Stroke(width = 2.dp.toPx()),
            )
        }
    }

    Spacer(Modifier.height(12.dp))
    Row(Modifier.fillMaxWidth()) {
        if (track.isNotEmpty()) MapKey(Color(0xFF6E9BF5), "Ball seen", dashed = false)
        evidence.pitching?.takeIf { it.detected }?.let { MapKey(Color(0xFF4ADE80), "Bounce", false) }
        evidence.impact?.takeIf { it.detected }?.let { MapKey(Color(0xFFF5A623), "Impact", false) }
        evidence.projection?.takeIf { it.predicted }?.let { MapKey(Color(0xFFF97066), "Projected", true) }
    }

    Spacer(Modifier.height(10.dp))
    Text(
        // Names the space out loud. Without this line a reader assumes a pitch map.
        "Positions in the camera frame, not on the pitch — the camera is not calibrated.",
        color = Color.White.copy(alpha = 0.4f),
        fontSize = 11.5.sp,
        lineHeight = 17.sp,
    )
}

/** One key entry. Dashed swatches mean predicted, solid mean seen. */
@Composable
private fun MapKey(colour: Color, label: String, dashed: Boolean) {
    Row(
        verticalAlignment = Alignment.CenterVertically,
        modifier = Modifier.padding(end = 16.dp),
    ) {
        Canvas(Modifier.size(width = 14.dp, height = 8.dp)) {
            drawLine(
                color = colour,
                start = Offset(0f, size.height / 2f),
                end = Offset(size.width, size.height / 2f),
                strokeWidth = 2.5.dp.toPx(),
                cap = StrokeCap.Round,
                pathEffect = if (dashed) {
                    PathEffect.dashPathEffect(floatArrayOf(4.dp.toPx(), 3.dp.toPx()))
                } else {
                    null
                },
            )
        }
        Spacer(Modifier.width(6.dp))
        Text(
            label,
            color = Color.White.copy(alpha = 0.65f),
            fontSize = 11.sp,
        )
    }
}

private fun factorLabel(key: String): String = when (key) {
    "pitching" -> "PITCHED"
    "impact" -> "IMPACT"
    "bat_involved" -> "BAT"
    "height" -> "HEIGHT"
    "line" -> "STUMPS"
    else -> key.uppercase()
}

/** Cricket's words for each reading — what a player would say, not the wire value. */
private fun readingLabel(key: String, reading: String): String = when (reading) {
    "cannot_tell" -> "Can't tell from this angle"
    "in_line" -> "In line"
    "outside_off" -> "Outside off"
    "outside_leg" -> "Outside leg"
    "bat_first" -> "Bat first"
    "pad_first" -> "Pad first"
    "no_bat" -> "No bat"
    "below_stumps" -> "Below the stumps"
    "above_stumps" -> "Over the stumps"
    "would_hit" -> "Looks like hitting"
    "would_miss" -> "Looks like missing"
    else -> reading.replace('_', ' ').replaceFirstChar { it.uppercase() }
}

private fun visibilityLabel(visibility: String): String = when (visibility) {
    "good" -> "Clear view"
    "partial" -> "Partial view"
    else -> "Poor view"
}

