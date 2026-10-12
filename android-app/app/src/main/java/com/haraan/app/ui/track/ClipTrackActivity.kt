package com.haraan.app.ui.track

import android.content.Context
import android.content.Intent
import android.graphics.Bitmap
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.view.HapticFeedbackConstants
import androidx.activity.ComponentActivity
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.compose.setContent
import androidx.activity.result.PickVisualMediaRequest
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.animation.AnimatedContent
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.togetherWith
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.horizontalScroll
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
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.ArrowBack
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.ImageBitmap
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.camera.DeliveryReplaySequence
import com.haraan.app.theme.ThannaTheme
import com.haraan.app.vision.BounceLength
import com.haraan.app.vision.ClipAnalysis
import com.haraan.app.vision.ClipDelivery
import com.haraan.app.vision.ClipDeliveryAnalyzer
import com.haraan.app.vision.ClipProblem
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext

/**
 * TRACK A CLIP: a delivery filmed on any phone, through the same ball tracking as the match
 * camera — every ball found, its speed, where it pitched, whether it was hitting, and the
 * replay with the 3D stadium. Opened from the Join sheet with a video the person picked.
 */
class ClipTrackActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        val clip = intent?.data
        setContent {
            ThannaTheme {
                ClipTrackScreen(initial = clip, onExit = { finish() })
            }
        }
    }

    companion object {
        fun open(context: Context, video: Uri) {
            context.startActivity(
                Intent(context, ClipTrackActivity::class.java)
                    .setData(video)
                    .addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION),
            )
        }
    }
}

private val Ink = Color(0xFF0F172A)
private val Muted = Color(0xFF64748B)
private val Hairline = Color(0xFFE2E8F0)
private val Page = Color(0xFFF8FAFC)
private val Brand = Color(0xFF2563EB)
private val Hit = Color(0xFFDC2626)
private val Miss = Color(0xFF16A34A)

private sealed interface Stage {
    data class Working(val fraction: Float, val found: Int, val preview: ImageBitmap?) : Stage
    data class Done(val analysis: ClipAnalysis) : Stage
    data class Failed(val why: String) : Stage
    /** Not on a plan that includes Track a clip (Hero at launch). */
    data object Locked : Stage
}

@Composable
private fun ClipTrackScreen(initial: Uri?, onExit: () -> Unit) {
    val view = LocalView.current
    var clip by remember { mutableStateOf(initial) }
    var stage by remember { mutableStateOf<Stage>(Stage.Working(0f, 0, null)) }
    var playing by remember { mutableStateOf<ClipDelivery?>(null) }
    val ballPrefs = androidx.compose.ui.platform.LocalContext.current.getSharedPreferences("camera_ball", android.content.Context.MODE_PRIVATE)
    var ball by remember {
        mutableStateOf(com.haraan.app.vision.BallDrag.fromServer(ballPrefs.getString("ball", null)) ?: com.haraan.app.vision.BallDrag.LEATHER)
    }
    var autoPlayed by remember { mutableStateOf(false) }
    val picker = rememberLauncherForActivityResult(ActivityResultContracts.PickVisualMedia()) { uri ->
        if (uri != null) {
            clip = uri
            stage = Stage.Working(0f, 0, null)
            autoPlayed = false
        }
    }
    val context = androidx.compose.ui.platform.LocalContext.current

    LaunchedEffect(clip) {
        val uri = clip ?: run { stage = Stage.Failed("No clip was picked."); return@LaunchedEffect }
        // The Join sheet only opens this for a member whose plan includes it; asked again here
        // because a screen can be reached other ways.
        if (com.haraan.app.data.membership.ClipTrackingAccess.check(context) != com.haraan.app.data.membership.ClipTrackingAccess.State.ALLOWED) {
            stage = Stage.Locked
            return@LaunchedEffect
        }
        var lastFound = 0
        try {
            val result = withContext(Dispatchers.Default) {
                ClipDeliveryAnalyzer.analyse(context, uri) { fraction, found, preview ->
                    val image = preview?.asImageBitmap()
                    // Felt as each ball is found, not only seen.
                    if (found > lastFound) {
                        lastFound = found
                        view.post { view.performHapticFeedback(HapticFeedbackConstants.CLOCK_TICK) }
                    }
                    val prior = stage as? Stage.Working
                    stage = Stage.Working(fraction, found, image ?: prior?.preview)
                }
            }
            view.performHapticFeedback(
                if (Build.VERSION.SDK_INT >= 30) HapticFeedbackConstants.CONFIRM else HapticFeedbackConstants.LONG_PRESS,
            )
            stage = Stage.Done(result)
        } catch (c: CancellationException) {
            throw c
        } catch (p: ClipProblem) {
            stage = Stage.Failed(p.message ?: "This clip couldn't be tracked.")
        } catch (t: Throwable) {
            stage = Stage.Failed("This clip couldn't be tracked.")
        }
    }

    // Back closes a replay, not the screen: leaving here throws the whole analysis away.
    androidx.activity.compose.BackHandler(enabled = playing != null) { playing = null }

    // The first delivery plays itself once, as on the match camera.
    LaunchedEffect(stage) {
        val done = stage as? Stage.Done ?: return@LaunchedEffect
        if (!autoPlayed && done.analysis.deliveries.isNotEmpty()) {
            autoPlayed = true
            kotlinx.coroutines.delay(700)
            playing = done.analysis.deliveries.first()
        }
    }

    Box(Modifier.fillMaxSize().background(Page)) {
        Column(Modifier.fillMaxSize().statusBarsPadding().navigationBarsPadding()) {
            TopBar(
                title = when (val s = stage) {
                    is Stage.Done -> when (val n = s.analysis.deliveries.size) {
                        0 -> "No deliveries found"
                        1 -> "1 delivery"
                        else -> "$n deliveries"
                    }
                    is Stage.Working -> "Tracking your clip"
                    is Stage.Failed -> "Couldn't track this clip"
                    Stage.Locked -> "Track a clip"
                },
                onBack = onExit,
            )
            AnimatedContent(
                targetState = stage::class,
                transitionSpec = { fadeIn(tween(220)) togetherWith fadeOut(tween(140)) },
                label = "clipStage",
                modifier = Modifier.weight(1f),
            ) { _ ->
                when (val s = stage) {
                    is Stage.Working -> WorkingView(s)
                    is Stage.Done -> ResultsView(
                        analysis = s.analysis,
                        ball = ball,
                        onBall = { next ->
                            view.performHapticFeedback(HapticFeedbackConstants.CLOCK_TICK)
                            ball = next
                            ballPrefs.edit().putString("ball", next.serverValue).apply()
                        },
                        onPlay = { view.performHapticFeedback(HapticFeedbackConstants.CLOCK_TICK); playing = it },
                        onAnother = {
                            picker.launch(PickVisualMediaRequest(ActivityResultContracts.PickVisualMedia.VideoOnly))
                        },
                    )
                    is Stage.Failed -> FailedView(s.why) {
                        picker.launch(PickVisualMediaRequest(ActivityResultContracts.PickVisualMedia.VideoOnly))
                    }
                    Stage.Locked -> LockedView {
                        com.haraan.app.data.membership.MembershipNav.open()
                        onExit()
                    }
                }
            }
        }
        playing?.let { d ->
            DeliveryReplaySequence(
                frames = d.frames,
                path = d.path,
                speedKmh = d.speedFor(ball),
                flight = d.flight?.copy(ball = ball),
                stadiumAutoCloseMs = null,
                onDone = { playing = null },
            )
        }
    }
}

@Composable
private fun TopBar(title: String, onBack: () -> Unit) {
    Row(
        Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 10.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(
            Modifier
                .size(38.dp)
                .clip(RoundedCornerShape(12.dp))
                .background(Color.White)
                .border(1.dp, Hairline, RoundedCornerShape(12.dp))
                .clickable(role = Role.Button, onClickLabel = "Back", onClick = onBack),
            contentAlignment = Alignment.Center,
        ) {
            Icon(Icons.AutoMirrored.Outlined.ArrowBack, contentDescription = "Back", tint = Ink, modifier = Modifier.size(18.dp))
        }
        Spacer(Modifier.width(12.dp))
        AnimatedContent(targetState = title, transitionSpec = { fadeIn(tween(180)) togetherWith fadeOut(tween(120)) }, label = "clipTitle") { t ->
            Text(t, color = Ink, fontSize = 20.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = (-0.3).sp)
        }
    }
}

@Composable
private fun WorkingView(s: Stage.Working) {
    val shown by animateFloatAsState(s.fraction, tween(260), label = "clipProgress")
    Column(
        Modifier.fillMaxSize().padding(horizontal = 20.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Spacer(Modifier.height(8.dp))
        // The frame being checked, small, under a drawn delivery that travels with the work.
        Box(
            Modifier
                .fillMaxWidth()
                .height(300.dp)
                .clip(RoundedCornerShape(22.dp))
                .background(Color.White)
                .border(1.dp, Hairline, RoundedCornerShape(22.dp)),
        ) {
            BallTrackScene(progress = shown, lit = if (shown >= 0.999f) 1f else 0f, modifier = Modifier.fillMaxSize())
            s.preview?.let { img ->
                Image(
                    img,
                    contentDescription = null,
                    contentScale = ContentScale.Crop,
                    modifier = Modifier
                        .align(Alignment.TopEnd)
                        .padding(12.dp)
                        .width(70.dp)
                        .aspectRatio(9f / 16f)
                        .shadow(6.dp, RoundedCornerShape(10.dp))
                        .clip(RoundedCornerShape(10.dp)),
                )
            }
        }
        Spacer(Modifier.height(22.dp))
        AnimatedContent(targetState = s.found, label = "clipFound") { n ->
            Text(
                when (n) {
                    0 -> "Looking for the first ball…"
                    1 -> "1 delivery found"
                    else -> "$n deliveries found"
                },
                color = Ink,
                fontSize = 18.sp,
                fontWeight = FontWeight.ExtraBold,
            )
        }
        Spacer(Modifier.height(6.dp))
        Text(
            if (s.fraction < 0.12f) "Finding the stumps first" else "Checking every frame — ${(s.fraction * 100).toInt()}%",
            color = Muted,
            fontSize = 13.sp,
        )
        Spacer(Modifier.height(14.dp))
        Box(
            Modifier
                .fillMaxWidth()
                .height(6.dp)
                .clip(RoundedCornerShape(3.dp))
                .background(Hairline),
        ) {
            Box(Modifier.fillMaxWidth(shown.coerceIn(0f, 1f)).height(6.dp).background(Brand))
        }
        Spacer(Modifier.height(16.dp))
        Text(
            "Keep this screen open. A few seconds of slow-motion video take about a minute.",
            color = Muted,
            fontSize = 12.sp,
            textAlign = TextAlign.Center,
        )
    }
}

@Composable
private fun ResultsView(
    analysis: ClipAnalysis,
    ball: com.haraan.app.vision.BallDrag,
    onBall: (com.haraan.app.vision.BallDrag) -> Unit,
    onPlay: (ClipDelivery) -> Unit,
    onAnother: () -> Unit,
) {
    Column(Modifier.fillMaxSize()) {
        if (analysis.deliveries.isNotEmpty()) BallPicker(ball, onBall)
        if (analysis.deliveries.isEmpty()) {
            NothingFound(analysis.stumpsFound, Modifier.weight(1f))
        } else {
            LazyColumn(
                Modifier.weight(1f).fillMaxWidth(),
                contentPadding = androidx.compose.foundation.layout.PaddingValues(horizontal = 16.dp, vertical = 6.dp),
                verticalArrangement = Arrangement.spacedBy(10.dp),
            ) {
                itemsIndexed(analysis.deliveries) { i, d -> DeliveryRow(i + 1, d, ball) { onPlay(d) } }
                item {
                    Text(
                        "Release speed, as a speed gun reads it, for a ${ball.label.lowercase()} ball. Assumes a full 22-yard pitch, filmed from behind the bowler's stumps.",
                        color = Muted,
                        fontSize = 11.5.sp,
                        modifier = Modifier.padding(horizontal = 6.dp, vertical = 8.dp),
                    )
                }
            }
        }
        PrimaryButton("Track another clip", Modifier.padding(16.dp), onAnother)
    }
}

/** One delivery: where it pitched on a little pitch map, its speed, length and verdict. */
@Composable
private fun DeliveryRow(n: Int, d: ClipDelivery, ball: com.haraan.app.vision.BallDrag, onClick: () -> Unit) {
    val interaction = remember { MutableInteractionSource() }
    val pressed by interaction.collectIsPressedAsState()
    val scale by animateFloatAsState(
        if (pressed) 0.97f else 1f,
        spring(dampingRatio = Spring.DampingRatioMediumBouncy, stiffness = Spring.StiffnessMedium),
        label = "deliveryRowScale",
    )
    Row(
        Modifier
            .fillMaxWidth()
            .graphicsLayer { scaleX = scale; scaleY = scale }
            .clip(RoundedCornerShape(18.dp))
            .background(Color.White)
            .border(1.dp, Hairline, RoundedCornerShape(18.dp))
            .clickable(interactionSource = interaction, indication = null, role = Role.Button, onClickLabel = "Play delivery $n", onClick = onClick)
            .padding(12.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        MiniPitchMap(
            bounceM = d.bounceM,
            acrossM = d.flight?.bounceX,
            hitting = d.hitting,
            modifier = Modifier.width(34.dp).height(84.dp),
        )
        Spacer(Modifier.width(14.dp))
        Column(Modifier.weight(1f)) {
            Text("Ball $n", color = Muted, fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
            Row(verticalAlignment = Alignment.Bottom) {
                // Rolls when the ball is changed: the same flight, bowled at a different speed.
                com.haraan.app.ui.matches.RollingFigure(
                    d.speedFor(ball)?.let { "%.0f".format(it) } ?: "—",
                    Ink,
                    30,
                    display = false,
                    fontWeight = FontWeight.ExtraBold,
                )
                Spacer(Modifier.width(4.dp))
                Text("km/h", color = Muted, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, modifier = Modifier.padding(bottom = 5.dp))
            }
            Text(
                d.bounceM?.let { "Pitched %.1f m from the stumps · %s".format(it, BounceLength.of(it).spoken) } ?: "Pitch not seen",
                color = Ink.copy(alpha = 0.8f),
                fontSize = 13.sp,
                fontWeight = FontWeight.Medium,
            )
        }
        Column(horizontalAlignment = Alignment.End) {
            when (d.hitting) {
                true -> Verdict("Hitting", Hit)
                false -> Verdict("Missing", Miss)
                null -> Verdict("Not judged", Muted)
            }
            Spacer(Modifier.height(10.dp))
            Text("▶ Replay", color = Brand, fontSize = 13.sp, fontWeight = FontWeight.Bold)
        }
    }
}

@Composable
private fun Verdict(word: String, tone: Color) {
    Text(
        word,
        color = tone,
        fontSize = 11.5.sp,
        fontWeight = FontWeight.Bold,
        modifier = Modifier
            .clip(RoundedCornerShape(999.dp))
            .background(tone.copy(alpha = 0.10f))
            .padding(horizontal = 10.dp, vertical = 4.dp),
    )
}

/** Nothing found: say which part failed, and how to film so it works. */
@Composable
private fun NothingFound(stumpsFound: Boolean, modifier: Modifier = Modifier) {
    Column(modifier.fillMaxWidth().padding(horizontal = 20.dp), horizontalAlignment = Alignment.CenterHorizontally) {
        Spacer(Modifier.height(10.dp))
        BallTrackScene(progress = 1f, lit = 0f, modifier = Modifier.fillMaxWidth().height(220.dp).clip(RoundedCornerShape(22.dp)))
        Spacer(Modifier.height(18.dp))
        Text(
            if (stumpsFound) "We saw the stumps but no ball we could follow." else "We couldn't find the batter's stumps.",
            color = Ink,
            fontSize = 16.sp,
            fontWeight = FontWeight.Bold,
            textAlign = TextAlign.Center,
        )
        Spacer(Modifier.height(10.dp))
        for (tip in listOf(
            "Film from behind the bowler's stumps, looking straight down the pitch.",
            "Keep both sets of stumps in the picture, the far ones near the middle.",
            "Use slow motion (120 or 240 fps) and hold the phone still — a tripod is best.",
        )) {
            Row(Modifier.fillMaxWidth().padding(vertical = 4.dp)) {
                Box(Modifier.padding(top = 7.dp).size(6.dp).clip(RoundedCornerShape(3.dp)).background(Brand))
                Spacer(Modifier.width(10.dp))
                Text(tip, color = Muted, fontSize = 13.sp, lineHeight = 18.sp)
            }
        }
    }
}

/** Track a clip is a Hero feature: what it does, and the way to the plans. */
@Composable
private fun LockedView(onSeePlans: () -> Unit) {
    Column(Modifier.fillMaxSize().padding(20.dp), horizontalAlignment = Alignment.CenterHorizontally) {
        Spacer(Modifier.height(8.dp))
        BallTrackScene(progress = 1f, lit = 1f, modifier = Modifier.fillMaxWidth().height(240.dp).clip(RoundedCornerShape(22.dp)))
        Spacer(Modifier.height(20.dp))
        Text("Track a clip is part of Hero", color = Ink, fontSize = 18.sp, fontWeight = FontWeight.ExtraBold, textAlign = TextAlign.Center)
        Spacer(Modifier.height(8.dp))
        Text(
            "Pick any video of your bowling and see every ball tracked — its speed, where it pitched, whether it was hitting, and the 3D replay.",
            color = Muted,
            fontSize = 13.5.sp,
            lineHeight = 19.sp,
            textAlign = TextAlign.Center,
        )
        Spacer(Modifier.weight(1f))
        PrimaryButton("See Hero", Modifier, onSeePlans)
    }
}

@Composable
private fun FailedView(why: String, onPick: () -> Unit) {
    Column(Modifier.fillMaxSize().padding(20.dp), horizontalAlignment = Alignment.CenterHorizontally) {
        Spacer(Modifier.height(30.dp))
        Text(why, color = Ink, fontSize = 16.sp, fontWeight = FontWeight.SemiBold, textAlign = TextAlign.Center)
        Spacer(Modifier.weight(1f))
        PrimaryButton("Pick another clip", Modifier, onPick)
    }
}

@Composable
private fun PrimaryButton(label: String, modifier: Modifier, onClick: () -> Unit) {
    val interaction = remember { MutableInteractionSource() }
    val pressed by interaction.collectIsPressedAsState()
    val scale by animateFloatAsState(if (pressed) 0.97f else 1f, spring(dampingRatio = 0.6f, stiffness = 800f), label = "clipButtonScale")
    Box(
        modifier
            .fillMaxWidth()
            .height(54.dp)
            .graphicsLayer { scaleX = scale; scaleY = scale }
            .shadow(if (pressed) 2.dp else 8.dp, RoundedCornerShape(16.dp), clip = false, spotColor = Brand.copy(alpha = 0.45f))
            .clip(RoundedCornerShape(16.dp))
            .background(Brand)
            .clickable(interactionSource = interaction, indication = null, role = Role.Button, onClick = onClick),
        contentAlignment = Alignment.Center,
    ) {
        Text(label, color = Color.White, fontSize = 16.sp, fontWeight = FontWeight.ExtraBold)
    }
}

/** This delivery's release speed for [ball]; the analyser fitted it once, the ball is a reading. */
private fun ClipDelivery.speedFor(ball: com.haraan.app.vision.BallDrag): Double? =
    flight?.copy(ball = ball)?.speedKmh ?: speedKmh

/**
 * Which ball was bowled. A tennis ball loses pace three times as fast as a leather one, so the
 * same flight was let go ~10 km/h quicker — the speeds above re-read the moment it is changed.
 */
@Composable
private fun BallPicker(ball: com.haraan.app.vision.BallDrag, onBall: (com.haraan.app.vision.BallDrag) -> Unit) {
    Column(Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 4.dp)) {
        Text("Ball", color = Muted, fontSize = 12.sp, fontWeight = FontWeight.SemiBold, modifier = Modifier.padding(start = 4.dp, bottom = 6.dp))
        Row(
            Modifier.fillMaxWidth().horizontalScroll(rememberScrollState()),
            horizontalArrangement = Arrangement.spacedBy(8.dp),
        ) {
            com.haraan.app.vision.BallDrag.CHOICES.forEach { b ->
                val on = b == ball
                Text(
                    b.label,
                    color = if (on) Color.White else Ink,
                    fontSize = 13.sp,
                    fontWeight = if (on) FontWeight.Bold else FontWeight.Medium,
                    modifier = Modifier
                        .clip(RoundedCornerShape(999.dp))
                        .background(if (on) Ink else Color.White)
                        .border(1.dp, if (on) Ink else Hairline, RoundedCornerShape(999.dp))
                        .clickable(role = Role.RadioButton) { if (!on) onBall(b) }
                        .padding(horizontal = 14.dp, vertical = 8.dp),
                )
            }
        }
    }
}
