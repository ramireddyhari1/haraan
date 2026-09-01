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
import com.haraan.app.data.DeliveryEvidence
import com.haraan.app.data.DeliveryReview
import com.haraan.app.data.ReviewStatus
import com.haraan.app.ui.pressable
import kotlinx.coroutines.delay
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
//  It still never says what the clip MEANS: no verdict, no line, no projected path.
//  One uncalibrated phone at 30fps cannot adjudicate an LBW, and a screen that
//  offered an answer here would be believed. So the review reports observations and
//  is required to say when it cannot tell — which, on ground-level footage shot from
//  wherever somebody could stand, is most of the time. The umpire's judgement is the
//  feature; the footage and the read are what it is made from.
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

    playing?.let { clip -> ClipPlayer(clip, matchId) { playing = null } }
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
private fun ClipPlayer(clip: MatchClip, matchId: String, onClose: () -> Unit) {
    val ctx = LocalContext.current
    val repo = remember { MatchDeviceRepository() }
    val scope = rememberCoroutineScope()
    var review by remember(clip.id) { mutableStateOf(clip.review) }
    var status by remember(clip.id) { mutableStateOf(clip.reviewStatus) }
    var failure by remember(clip.id) { mutableStateOf(clip.reviewError) }
    // A decode failure is about this handset, never about the review.
    var playbackFailed by remember(clip.id) { mutableStateOf(false) }

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
            Box(
                Modifier
                    .fillMaxWidth()
                    // Capped rather than free: the clip keeps its shape, but it stops
                    // taking the entire screen and pushing the answer out of sight.
                    .heightIn(max = 340.dp)
                    .aspectRatio(9f / 16f),
                contentAlignment = Alignment.Center,
            ) {
                if (!playbackFailed) {
                    AndroidView(
                        modifier = Modifier.fillMaxSize(),
                        factory = { context ->
                            VideoView(context).apply {
                                setVideoPath(clip.url)
                                // Loops, because reviewing a dismissal means watching it
                                // again, and again — the entire point of the footage.
                                setOnPreparedListener { it.isLooping = true; start() }
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
                when {
                    current != null -> DeliveryReviewPanel(current)

                    status == ReviewStatus.PENDING || status == ReviewStatus.PROCESSING ->
                        ReviewInProgress()

                    else -> Column {
                        Row(
                            Modifier
                                .fillMaxWidth()
                                .clip(RoundedCornerShape(12.dp))
                                .background(Color.White.copy(alpha = 0.1f))
                                .pressable(
                                    onClick = {
                                        failure = null
                                        status = ReviewStatus.PENDING
                                        scope.launch {
                                            val token = TokenStore.getToken(ctx)
                                            val state = if (TokenStore.isSignedIn(token)) {
                                                runCatching {
                                                    repo.requestReview(token!!, matchId, clip.id)
                                                }.getOrNull()
                                            } else {
                                                null
                                            }
                                            review = state?.review
                                            failure = state?.error
                                            // Null means the call itself never landed;
                                            // FAILED stops the poll that PENDING started.
                                            status = state?.status ?: ReviewStatus.FAILED
                                        }
                                    },
                                )
                                .padding(vertical = 13.dp),
                            horizontalArrangement = Arrangement.Center,
                        ) {
                            Text(
                                if (status == ReviewStatus.FAILED) "Try again" else "Review this ball",
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
 * There is no verdict line and no colour coding of good or bad. Green for "hitting" and
 * red for "missing" would be a decision rendered in paint, and this screen does not get to
 * make one — a factor the camera could not settle is simply set in grey and says so.
 */
@Composable
private fun DeliveryReviewPanel(
    review: DeliveryReview,
    modifier: Modifier = Modifier,
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
        Spacer(Modifier.height(14.dp))

        review.factors.forEachIndexed { i, factor ->
            if (i > 0) {
                Spacer(Modifier.height(9.dp))
                Box(
                    Modifier
                        .fillMaxWidth()
                        .height(1.dp)
                        .background(Color.White.copy(alpha = 0.07f)),
                )
                Spacer(Modifier.height(9.dp))
            }
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                    factorLabel(factor.key),
                    color = Color.White.copy(alpha = 0.45f),
                    fontSize = 10.sp,
                    fontWeight = FontWeight.ExtraBold,
                    letterSpacing = 1.1.sp,
                    modifier = Modifier.width(86.dp),
                )
                Text(
                    readingLabel(factor.key, factor.reading),
                    // An unresolved factor is deliberately quiet. It is still information —
                    // knowing the footage cannot answer is worth more than a confident
                    // guess — but it must never carry the weight of one that is settled.
                    color = if (factor.unknown) {
                        Color.White.copy(alpha = 0.38f)
                    } else {
                        Color.White
                    },
                    fontSize = 14.5.sp,
                    fontWeight = if (factor.unknown) FontWeight.Normal else FontWeight.SemiBold,
                    modifier = Modifier.weight(1f),
                )
                // Only a factor the model called unambiguous gets a mark, and the mark is
                // a dot rather than a tick: a tick reads as "correct", and nothing here
                // has been checked against anything.
                if (!factor.unknown && factor.certain) {
                    Box(
                        Modifier
                            .size(6.dp)
                            .clip(CircleShape)
                            .background(Color(0xFF4ADE80)),
                    )
                }
            }
        }

        review.notes?.let { note ->
            Spacer(Modifier.height(16.dp))
            Text(
                note,
                color = Color.White.copy(alpha = 0.5f),
                fontSize = 12.sp,
                lineHeight = 18.sp,
            )
        }

        review.evidence?.let { evidence ->
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

