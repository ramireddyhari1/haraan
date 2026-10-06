package com.haraan.app.vision

import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
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
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.derivedStateOf
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.BlendMode
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.ImageBitmap
import androidx.compose.ui.graphics.ImageShader
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.ShaderBrush
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.TileMode
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.drawscope.clipPath
import androidx.compose.ui.graphics.drawscope.scale
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.graphics.lerp
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.res.painterResource
import com.haraan.app.ui.Feel
import com.haraan.app.ui.matches.Thud
import com.haraan.app.ui.matches.cricketThud
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.R
import com.haraan.app.theme.ArchivoDisplay
import kotlin.math.PI
import kotlin.math.abs
import kotlin.math.cos
import kotlin.math.max
import kotlin.math.sin

/*
 * THE 3D REPLAY.
 *
 * Everything drawn here is the [Flight3d] the speed fit already solved for — the same
 * bounce, the same speeds — so the replay cannot disagree with the numbers under it. Three
 * stretches of the path are drawn three ways, because they are three kinds of claim:
 *
 *   before the ball was first seen  faint   — carried back to the release, drawn not seen
 *   while it was seen               solid   — fitted to real sightings
 *   after it was last seen          dashed  — the projection on to the stumps
 *
 * WHAT MAKES IT READ AS A BROADCAST rather than a diagram, each one deliberate: a long lens
 * from far back (see [ReplayView]), light that falls off into a floodlit haze at the
 * horizon, a vignette, grain in the strip, soft-edged wear instead of rectangles, shaded
 * stumps that throw shadows, and a trail that glows and fades towards its tail with its
 * own shadow on the pitch — the one cue that makes a line on a screen read as a ball in
 * the air.
 */

// The launcher icon's ramp, so the replay is the same blue as the app on the home screen.
private val RampLight = Color(0xFF4D8BFF)
private val RampDeep = Color(0xFF0A2A93)
private val Brand = Color(0xFF2563EB)
private val Night = Color(0xFF040812)
private val GrassFar = Color(0xFF0F2A1C)
private val GrassNear = Color(0xFF1E5434)
private val StripFar = Color(0xFFA8925F)
private val StripNear = Color(0xFFD3BD8C)
private val Chalk = Color(0xFFF4F1E8)
private val Hit = Color(0xFFE11D2E)
private val Miss = Color(0xFF16A34A)
private val Glass = Color(0xD90A1120)

private const val STUMP_X = PitchGeometry.STUMP_SET_WIDTH_M / 2.0 - 0.0175

/** Where the four banks of lights throw a stump's shadow, in metres from its foot. */
private val FLOOD_SHADOWS = listOf(0.42 to 0.42, -0.42 to 0.42, 0.42 to -0.42, -0.42 to -0.42)

/** Speed down the pitch after the bounce, or before it when nothing was seen after. */
private val Flight3d.outVyOrIn: Double get() = outVy ?: inVy

/** Whether a screen polygon misses the canvas entirely: no point building its path. */
internal fun offScreen(points: List<Point2>, w: Double, h: Double): Boolean {
    var minX = Double.MAX_VALUE
    var maxX = -Double.MAX_VALUE
    var minY = Double.MAX_VALUE
    var maxY = -Double.MAX_VALUE
    for (p in points) {
        if (p.x < minX) minX = p.x
        if (p.x > maxX) maxX = p.x
        if (p.y < minY) minY = p.y
        if (p.y > maxY) maxY = p.y
    }
    return maxX < 0 || minX > w || maxY < 0 || minY > h
}

/** Outfield texture: the grain of grass, finer and greener than the strip's. */
internal fun turfBrush(): ShaderBrush {
    val side = 80
    val pixels = IntArray(side * side)
    val random = java.util.Random(5)
    for (i in pixels.indices) {
        val a = random.nextInt(34)
        pixels[i] = if (random.nextInt(3) == 0) (a shl 24) or 0xB8F5C8 else (a shl 24)
    }
    val bitmap = android.graphics.Bitmap.createBitmap(pixels, side, side, android.graphics.Bitmap.Config.ARGB_8888)
    return ShaderBrush(ImageShader(bitmap.asImageBitmap(), TileMode.Repeated, TileMode.Repeated))
}
private const val BALL_SIZE_BOOST = 2.0

@Composable
fun FlightReplayOverlay(
    flight: Flight3d,
    onClose: () -> Unit,
    modifier: Modifier = Modifier,
    /** Debug only: hold the replay at this point, 0..1, instead of playing it. */
    holdAt: Float? = null,
    /** Debug only: open on this view. */
    startView: ReplayView? = null,
    /** Closes itself this long after it has played, for a phone nobody is holding. */
    autoCloseAfterMs: Long? = null,
) {
    var view by remember { mutableStateOf(startView ?: ReplayView.AUTO) }
    var previous by remember { mutableStateOf(startView ?: ReplayView.AUTO) }
    val glide = remember { Animatable(1f) }
    val play = remember { Animatable(0f) }
    val bails = remember { Animatable(0f) }
    var generation by remember { mutableIntStateOf(0) }
    val grain = remember { grainBrush() }
    val crowd = remember { crowdBrush() }
    val turf = remember { turfBrush() }
    val hostView = LocalView.current

    val startMs = flight.releaseMs
    val endMs = flight.stumpsMs?.takeIf { it > startMs } ?: flight.lastSeenMs
    // Slow motion, about three times: a real delivery is over in half a second, and much
    // slower than this the answer takes longer to arrive than the ball did to be bowled.
    val playMs = ((endMs - startMs) * 3.0).coerceIn(1_500.0, 2_600.0).toInt()
    val hits = flight.hitsStumps

    val context = LocalContext.current
    LaunchedEffect(flight, generation) {
        bails.snapTo(0f)
        play.snapTo(0f)
        /*
         * FELT ON THE SAME TIMELINE IT IS SEEN ON. The replay's own clock decides when each
         * knock lands — release, the bounce, the stumps — so the hand feels the ball hit the
         * pitch at the frame the eye sees it. The patterns are the scorer's (see [Thud]):
         * one vocabulary for the whole app.
         */
        fun at(ms: Double) = ((ms - startMs) / (endMs - startMs) * playMs).toLong().coerceIn(0L, playMs.toLong())
        if (holdAt == null) launch { cricketThud(context, Thud.TICK) }
        if (holdAt == null) launch {
            delay(at(flight.bounceMs))
            cricketThud(context, Thud.RUN)
        }
        if (holdAt == null) launch {
            delay(playMs.toLong())
            when (hits) {
                true -> cricketThud(context, Thud.WICKET)
                false -> cricketThud(context, Thud.TICK)
                null -> Unit
            }
        }
        if (holdAt != null) {
            play.snapTo(holdAt.coerceIn(0f, 1f))
            if (holdAt >= 1f && hits == true) bails.snapTo(1f)
            return@LaunchedEffect
        }
        play.animateTo(1f, tween(playMs, easing = LinearEasing))
        if (hits == true) bails.animateTo(1f, tween(550, easing = FastOutSlowInEasing))
        if (autoCloseAfterMs != null) {
            delay(autoCloseAfterMs)
            onClose()
        }
    }
    LaunchedEffect(view) {
        if (previous != view) {
            glide.snapTo(0f)
            glide.animateTo(1f, tween(700, easing = FastOutSlowInEasing))
            previous = view
        }
    }

    /*
     * SMOOTH MEANS ONLY THE PICTURE MOVES. The replay clock is read inside the Canvas, so
     * each frame is a redraw and nothing more; the panel underneath only hears about the two
     * moments it changes — the bounce and the arrival — not sixty times a second.
     */
    val pitched by remember(flight) { derivedStateOf { startMs + (endMs - startMs) * play.value >= flight.bounceMs } }
    val arrived by remember(flight) { derivedStateOf { play.value >= 1f } }
    // Fades up over whatever was on screen — the path on the camera picture, usually — so
    // the hand-off is one movement rather than a cut to black.
    val appear = remember { Animatable(0f) }
    LaunchedEffect(Unit) { appear.animateTo(1f, tween(280, easing = FastOutSlowInEasing)) }
    Box(
        modifier
            .fillMaxSize()
            .graphicsLayer { alpha = appear.value }
            .background(Night)
            .clickable(enabled = false) {},
    ) {
        Canvas(Modifier.fillMaxSize()) {
            val progress = play.value.toDouble()
            val nowMs = startMs + (endMs - startMs) * progress
            val camera = previous.cameraAt(progress).lerp(view.cameraAt(progress), glide.value.toDouble())
            drawScene(camera, flight, startMs, nowMs, grain, crowd, turf, bails.value)
        }

        // ── Lockup and close ──
        Row(
            Modifier
                .align(Alignment.TopStart)
                .statusBarsPadding()
                .padding(start = 20.dp, end = 14.dp, top = 10.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Column(Modifier.weight(1f)) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Image(
                        painter = painterResource(R.drawable.haraan_logo_white),
                        contentDescription = "Haraan",
                        modifier = Modifier.height(15.dp),
                    )
                    Box(
                        Modifier
                            .padding(horizontal = 10.dp)
                            .width(1.dp)
                            .height(14.dp)
                            .background(Color.White.copy(alpha = 0.35f)),
                    )
                    Text(
                        "BALL TRACKING",
                        color = Color.White.copy(alpha = 0.9f),
                        fontSize = 9.5.sp,
                        fontWeight = FontWeight.Bold,
                        letterSpacing = 2.6.sp,
                    )
                }
                Spacer(Modifier.height(5.dp))
                Text(
                    "Fitted from the pitch corners · an estimate",
                    color = Color.White.copy(alpha = 0.5f),
                    fontSize = 11.sp,
                )
            }
            Box(
                Modifier
                    .size(38.dp)
                    .clip(CircleShape)
                    .background(Brand)
                    .clickable {
                        hostView.performHapticFeedback(Feel.SELECT)
                        generation++
                    },
                contentAlignment = Alignment.Center,
            ) {
                ReplayGlyph()
            }
            Spacer(Modifier.width(8.dp))
            Box(
                Modifier
                    .size(38.dp)
                    .clip(CircleShape)
                    .background(Color.White.copy(alpha = 0.10f))
                    .border(1.dp, Color.White.copy(alpha = 0.12f), CircleShape)
                    .clickable(onClick = onClose),
                contentAlignment = Alignment.Center,
            ) {
                Canvas(Modifier.size(11.dp)) {
                    val s = 1.7.dp.toPx()
                    drawLine(Color.White, Offset(0f, 0f), Offset(size.width, size.height), s, StrokeCap.Round)
                    drawLine(Color.White, Offset(size.width, 0f), Offset(0f, size.height), s, StrokeCap.Round)
                }
            }
        }

        // ── The readout, then the controls ──
        Column(
            Modifier
                .align(Alignment.BottomCenter)
                .navigationBarsPadding()
                .padding(bottom = 16.dp, start = 16.dp, end = 16.dp)
                .widthIn(max = 460.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            Readout(
                flight = flight,
                progress = { play.value },
                pitched = pitched,
                arrived = arrived,
            )
            Spacer(Modifier.height(12.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                Row(
                    Modifier
                        .clip(RoundedCornerShape(999.dp))
                        .background(Glass)
                        .border(1.dp, Color.White.copy(alpha = 0.10f), RoundedCornerShape(999.dp))
                        .padding(3.dp),
                ) {
                    ReplayView.entries.forEach { option ->
                        val selected = option == view
                        Text(
                            option.label,
                            color = if (selected) Color.White else Color.White.copy(alpha = 0.6f),
                            fontSize = 12.5.sp,
                            fontWeight = if (selected) FontWeight.Bold else FontWeight.Medium,
                            modifier = Modifier
                                .clip(RoundedCornerShape(999.dp))
                                .background(if (selected) Brand else Color.Transparent)
                                .clickable {
                                    if (option != view) {
                                        hostView.performHapticFeedback(Feel.SELECT)
                                        previous = view
                                        view = option
                                    }
                                }
                                .padding(horizontal = 12.dp, vertical = 8.dp),
                        )
                    }
                }
            }
        }
    }
}

/** Replay: a loop arrow, not a play triangle — it plays itself the first time. */
@Composable
private fun ReplayGlyph() {
    Canvas(Modifier.size(15.dp)) {
        val sw = 1.8.dp.toPx()
        drawArc(
            Color.White,
            startAngle = -60f,
            sweepAngle = 290f,
            useCenter = false,
            topLeft = Offset(sw, sw),
            size = Size(size.width - 2 * sw, size.height - 2 * sw),
            style = Stroke(sw, cap = StrokeCap.Round),
        )
        val tip = Offset(size.width * 0.80f, size.height * 0.16f)
        val head = Path().apply {
            moveTo(tip.x + sw * 1.4f, tip.y - sw * 1.6f)
            lineTo(tip.x + sw * 1.5f, tip.y + sw * 1.6f)
            lineTo(tip.x - sw * 1.7f, tip.y + sw * 0.6f)
            close()
        }
        drawPath(head, Color.White)
    }
}

/**
 * The broadcast's readout, in the order the ball earns each line: speed from release, the
 * length once it has pitched, the verdict once it reaches the stumps. One hero number, not
 * three equal tiles.
 */
@Composable
private fun Readout(flight: Flight3d, progress: () -> Float, pitched: Boolean, arrived: Boolean) {
    val shape = RoundedCornerShape(20.dp)
    val pitchIn = remember { Animatable(0f) }
    val speedIn = remember { Animatable(0f) }
    // The speed is known at release, so it counts up as the ball leaves the hand.
    LaunchedEffect(flight) {
        speedIn.snapTo(0f)
        speedIn.animateTo(1f, tween(650, easing = FastOutSlowInEasing))
    }
    val verdictIn = remember { Animatable(0f) }
    LaunchedEffect(pitched) { if (pitched) pitchIn.animateTo(1f, tween(320)) else pitchIn.snapTo(0f) }
    LaunchedEffect(arrived) { if (arrived) verdictIn.animateTo(1f, tween(380)) else verdictIn.snapTo(0f) }
    val hits = flight.hitsStumps

    Column(
        Modifier
            .fillMaxWidth()
            .clip(shape)
            .background(Glass)
            .border(1.dp, Color.White.copy(alpha = 0.09f), shape),
    ) {
        // Where the replay is, as a hairline along the top edge.
        // Drawn, not laid out, so the moving hairline is a redraw and never a recomposition.
        Canvas(Modifier.fillMaxWidth().height(2.dp)) {
            drawRect(Color.White.copy(alpha = 0.06f))
            drawRect(RampLight, size = Size(size.width * progress().coerceIn(0f, 1f), size.height))
        }
        Row(
            Modifier.padding(start = 18.dp, end = 16.dp, top = 12.dp, bottom = 14.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Column {
                MicroCaps("SPEED")
                Row(verticalAlignment = Alignment.Bottom) {
                    Text(
                        "%.0f".format(flight.speedKmh * speedIn.value),
                        color = Color.White,
                        fontSize = 44.sp,
                        lineHeight = 44.sp,
                        fontFamily = ArchivoDisplay,
                        letterSpacing = (-1).sp,
                    )
                    Spacer(Modifier.width(5.dp))
                    Text(
                        "km/h",
                        color = Color.White.copy(alpha = 0.6f),
                        fontSize = 13.sp,
                        fontWeight = FontWeight.SemiBold,
                        modifier = Modifier.padding(bottom = 7.dp),
                    )
                }
            }
            Spacer(Modifier.weight(1f))
            Box(Modifier.width(1.dp).height(46.dp).background(Color.White.copy(alpha = 0.10f)))
            Spacer(Modifier.width(16.dp))
            Column(Modifier.widthIn(min = 130.dp)) {
                Column(Modifier.graphicsLayer { alpha = 0.25f + 0.75f * pitchIn.value }) {
                    MicroCaps("PITCHED")
                    Text(
                        if (pitched) {
                            "%.1f m · %s".format(flight.bounceY, BounceLength.of(flight.bounceY).spoken)
                        } else {
                            "—"
                        },
                        color = Color.White,
                        fontSize = 14.sp,
                        fontWeight = FontWeight.SemiBold,
                    )
                }
                Spacer(Modifier.height(8.dp))
                val (word, tone) = when (hits) {
                    true -> "HITTING" to Hit
                    false -> "MISSING" to Miss
                    null -> "NOT JUDGED" to Color(0xFF475569)
                }
                Text(
                    word,
                    color = Color.White,
                    fontSize = 11.sp,
                    fontWeight = FontWeight.ExtraBold,
                    letterSpacing = 1.4.sp,
                    modifier = Modifier
                        .graphicsLayer {
                            alpha = verdictIn.value
                            translationX = (1f - verdictIn.value) * -8.dp.toPx()
                        }
                        .clip(RoundedCornerShape(6.dp))
                        .background(tone)
                        .padding(horizontal = 9.dp, vertical = 4.dp),
                )
            }
        }
    }
}

@Composable
private fun MicroCaps(text: String) {
    Text(
        text,
        color = Color.White.copy(alpha = 0.5f),
        fontSize = 9.5.sp,
        fontWeight = FontWeight.Bold,
        letterSpacing = 1.8.sp,
    )
}

/** Fine grain for the strip, made once: a dried pitch is never one flat colour. */
internal fun grainBrush(): ShaderBrush {
    val side = 96
    val pixels = IntArray(side * side)
    val random = java.util.Random(7)
    for (i in pixels.indices) {
        val light = random.nextBoolean()
        val a = random.nextInt(46)
        pixels[i] = if (light) (a shl 24) or 0xFFFFFF else (a shl 24)
    }
    val bitmap = android.graphics.Bitmap.createBitmap(pixels, side, side, android.graphics.Bitmap.Config.ARGB_8888)
    val image: ImageBitmap = bitmap.asImageBitmap()
    return ShaderBrush(ImageShader(image, TileMode.Repeated, TileMode.Repeated))
}

// ── The scene ────────────────────────────────────────────────────────────────

internal fun DrawScope.drawScene(
    camera: ReplayCamera,
    flight: Flight3d,
    startMs: Double,
    nowMs: Double,
    grain: ShaderBrush,
    crowd: ShaderBrush,
    turf: ShaderBrush,
    bailsLift: Float,
) {
    val w = size.width.toDouble()
    val h = size.height.toDouble()

    fun screen(p: Point3): Offset? = camera.project(p, w, h)?.let { Offset(it.x.toFloat(), it.y.toFloat()) }

    fun pathOf(points: List<Point3>): Path? {
        val s = camera.polygon(points, w, h)
        if (s.size < 3 || offScreen(s, w, h)) return null
        return Path().apply {
            moveTo(s[0].x.toFloat(), s[0].y.toFloat())
            for (i in 1 until s.size) lineTo(s[i].x.toFloat(), s[i].y.toFloat())
            close()
        }
    }

    fun ground(x0: Double, y0: Double, x1: Double, y1: Double) =
        listOf(Point3(x0, y0, 0.0), Point3(x1, y0, 0.0), Point3(x1, y1, 0.0), Point3(x0, y1, 0.0))

    fun line(a: Point3, b: Point3, metres: Double, color: Color, minPx: Float = 1f) {
        val pa = screen(a) ?: return
        val pb = screen(b) ?: return
        val mid = Point3((a.x + b.x) / 2, (a.y + b.y) / 2, (a.z + b.z) / 2)
        val px = max(minPx, camera.pixels(metres, mid, w, h).toFloat())
        drawLine(color, pa, pb, px, StrokeCap.Round)
    }

    /** A soft glow lying flat on the ground: a circle squashed by the view's foreshortening. */
    fun groundGlow(at: Point3, radiusM: Double, colors: List<Color>) {
        val c = screen(at) ?: return
        val rx = camera.pixels(radiusM, at, w, h).toFloat()
        if (rx < 0.5f) return
        val near = screen(Point3(at.x, at.y + radiusM, 0.0)) ?: return
        val far = screen(Point3(at.x, at.y - radiusM, 0.0)) ?: return
        val ry = (abs(near.y - far.y) / 2f).coerceAtLeast(0.5f)
        scale(scaleX = 1f, scaleY = (ry / rx).coerceIn(0.05f, 4f), pivot = c) {
            drawCircle(Brush.radialGradient(colors, center = c, radius = rx), rx, c)
        }
    }

    // ── Night sky, and the floodlit haze where the ground meets it ──
    val horizon = camera.project(Point3(0.0, -3000.0, 0.0), w, h)?.y?.toFloat()
        ?: camera.project(Point3(0.0, 3000.0, 0.0), w, h)?.y?.toFloat()
    val skyFoot = (horizon ?: size.height * 0.3f).coerceIn(1f, size.height)
    drawRect(
        Brush.verticalGradient(
            listOf(Night, RampDeep.copy(alpha = 0.5f).compositeOver(Night), RampDeep.copy(alpha = 0.8f).compositeOver(Night)),
            startY = 0f,
            endY = skyFoot,
        ),
    )

    // ── The ground around it: stands, roof, floodlights ──
    drawStadium(camera, crowd)

    // ── The outfield: one sweep of grass inside the boards, light falling off with distance ──
    pathOf(outfield())?.let { grass ->
        val top = (horizon ?: 0f).coerceIn(0f, size.height)
        drawPath(grass, Brush.verticalGradient(listOf(GrassFar, GrassNear), startY = top, endY = size.height))
        drawPath(grass, turf, alpha = 0.5f)
        // Mowing, as a sheen rather than as paint: every other band catches a little light.
        clipPath(grass) {
            var y = -64.0
            var band = 0
            while (y < 84.0) {
                if (band % 2 == 0) pathOf(ground(-80.0, y, 80.0, y + 3.0))?.let { drawPath(it, Color.White.copy(alpha = 0.035f)) }
                y += 3.0
                band++
            }
        }
    }
    drawFieldFurniture(camera)

    // ── The Haraan H, painted into the outfield the way a ground paints its own mark ──
    fun mark(cx: Double, cy: Double, sizeM: Double, alpha: Float) {
        HaraanMarkShapes.pieces.forEach { piece ->
            pathOf(piece.map { (u, v) -> Point3(cx + u * sizeM, cy + v * sizeM, 0.0) })
                ?.let { drawPath(it, Color.White.copy(alpha = alpha)) }
        }
    }
    mark(0.0, -7.0, 5.0, 0.11f)
    mark(-6.0, 10.0, 3.6, 0.08f)
    mark(6.0, 10.0, 3.6, 0.08f)

    // ── The strip ──
    pathOf(ground(-1.525, -1.6, 1.525, 21.72))?.let { strip ->
        val far = screen(Point3(0.0, -1.6, 0.0))?.y ?: 0f
        val near = screen(Point3(0.0, 21.72, 0.0))?.y ?: size.height
        drawPath(strip, Brush.verticalGradient(listOf(StripFar, StripNear), startY = far, endY = near))
        drawPath(strip, grain, alpha = 0.55f)
    }
    // Its edges, where the cut grass meets the rolled strip.
    for (side in listOf(-1.525, 1.525)) {
        line(Point3(side, -1.6, 0.0), Point3(side, 21.72, 0.0), 0.06, Color.Black.copy(alpha = 0.18f))
    }
    // Where the bowlers land and the batters stand: worn, soft-edged.
    for (at in listOf(Point3(0.0, 2.1, 0.0), Point3(0.0, 18.6, 0.0))) {
        groundGlow(at, 1.25, listOf(Color(0xFF6E5A36).copy(alpha = 0.32f), Color.Transparent))
    }

    // ── Creases, both ends ──
    val crease = 0.05
    for (end in listOf(0.0, PitchGeometry.STUMPS_TO_STUMPS_M)) {
        val toward = if (end == 0.0) 1.0 else -1.0
        val popping = end + toward * PitchGeometry.POPPING_CREASE_AHEAD_M
        line(Point3(-1.32, end, 0.0), Point3(1.32, end, 0.0), crease, Chalk.copy(alpha = 0.9f))
        line(Point3(-1.83, popping, 0.0), Point3(1.83, popping, 0.0), crease, Chalk.copy(alpha = 0.9f))
        for (side in listOf(-1.32, 1.32)) {
            line(Point3(side, popping, 0.0), Point3(side, end - toward * 1.22, 0.0), crease, Chalk.copy(alpha = 0.9f))
        }
    }

    // ── Haze over the far ground, under everything that stands up ──
    if (horizon != null) {
        val hazeDepth = size.height * 0.22f
        drawRect(
            Brush.verticalGradient(
                listOf(RampLight.copy(alpha = 0.22f), RampDeep.copy(alpha = 0.10f), Color.Transparent),
                startY = horizon - hazeDepth * 0.35f,
                endY = horizon + hazeDepth,
            ),
        )
    }

    val reachedBounce = nowMs >= flight.bounceMs
    val stumpsMs = flight.stumpsMs
    val reachedStumps = stumpsMs != null && nowMs >= stumpsMs - 1.0

    // ── The pitch mark, once it has landed ──
    if (reachedBounce) {
        val bounce = Point3(flight.bounceX, flight.bounceY, 0.0)
        val since = ((nowMs - flight.bounceMs) / 220.0).coerceIn(0.0, 1.0)
        groundGlow(bounce, 0.45, listOf(RampLight.copy(alpha = 0.45f), RampLight.copy(alpha = 0.10f), Color.Transparent))
        groundGlow(bounce, 0.09, listOf(Color.White.copy(alpha = 0.9f), Color.White.copy(alpha = 0.75f), Color.Transparent))
        if (since < 1.0) {
            val r = 0.1 + 0.6 * since
            pathOf((0 until 28).map { i ->
                val a = 2 * PI * i / 28
                Point3(bounce.x + r * cos(a), bounce.y + r * sin(a), 0.0)
            })?.let { drawPath(it, Color.White.copy(alpha = (0.85 * (1 - since)).toFloat()), style = Stroke(1.6.dp.toPx())) }
        }
    }

    // ── Stumps and ball, far things first ──
    val hitting = flight.hitsStumps == true
    val nowBall = flight.at(nowMs)
    val items = listOf(
        camera.toCamera(Point3(0.0, 0.0, 0.3)).z to {
            stumps(camera, w, h, 0.0, glowing = reachedStumps && hitting, bailsLift = if (hitting) bailsLift else 0f)
        },
        camera.toCamera(Point3(0.0, PitchGeometry.STUMPS_TO_STUMPS_M, 0.3)).z to {
            stumps(camera, w, h, PitchGeometry.STUMPS_TO_STUMPS_M, glowing = false, bailsLift = 0f)
        },
        camera.toCamera(nowBall).z to { ball(camera, flight, startMs, nowMs, w, h) },
    ).sortedByDescending { it.first }
    items.forEach { it.second() }

    // ── Vignette ──
    drawRect(
        Brush.radialGradient(
            listOf(Color.Transparent, Color.Transparent, Night.copy(alpha = 0.65f)),
            center = Offset(size.width / 2f, size.height * 0.52f),
            radius = max(size.width, size.height) * 0.72f,
        ),
    )
}

private fun Color.compositeOver(under: Color): Color = Color(
    red = red * alpha + under.red * (1 - alpha),
    green = green * alpha + under.green * (1 - alpha),
    blue = blue * alpha + under.blue * (1 - alpha),
    alpha = 1f,
)

/** One set of stumps: shaded round, a shadow on the pitch, bails that fly on a hit. */
private fun DrawScope.stumps(
    camera: ReplayCamera,
    w: Double,
    h: Double,
    y: Double,
    glowing: Boolean,
    bailsLift: Float,
) {
    val top = PitchGeometry.STUMP_HEIGHT_M
    fun at(p: Point3) = camera.project(p, w, h)?.let { Offset(it.x.toFloat(), it.y.toFloat()) }

    if (glowing) {
        val c = at(Point3(0.0, y, top * 0.55)) ?: return
        val r = max(30.0, camera.pixels(0.9, Point3(0.0, y, 0.3), w, h)).toFloat()
        drawCircle(Brush.radialGradient(listOf(Hit.copy(alpha = 0.32f), Hit.copy(alpha = 0.07f), Color.Transparent), c, r), r, c)
    }

    for (x in listOf(-STUMP_X, 0.0, STUMP_X)) {
        val base = at(Point3(x, y, 0.0)) ?: continue
        val head = at(Point3(x, y, top)) ?: continue
        val px = max(2.4f, camera.pixels(0.035, Point3(x, y, 0.35), w, h).toFloat())
        // Four faint shadows, one per bank of lights: the look of every night match.
        for ((sx, sy) in FLOOD_SHADOWS) {
            at(Point3(x + sx, y + sy, 0.0))?.let { tip ->
                drawLine(Color.Black.copy(alpha = 0.12f), base, tip, px, StrokeCap.Round)
            }
        }
        val shade = if (glowing) {
            listOf(Color(0xFFFFE3E3), Color(0xFFF2B5B5), Color(0xFFB86A6A))
        } else {
            listOf(Color(0xFFFFF8E8), Color(0xFFE6D8B6), Color(0xFF9C8B66))
        }
        drawLine(
            Brush.horizontalGradient(shade, startX = base.x - px / 2f, endX = base.x + px / 2f),
            base,
            head,
            px,
            StrokeCap.Butt,
        )
    }

    // Bails: seated, or flying apart on a hit.
    val lift = bailsLift.toDouble()
    for (side in listOf(-1.0, 1.0)) {
        val outward = side * 0.16 * lift
        val rise = 0.32 * lift - 0.18 * lift * lift
        val a = at(Point3(side * STUMP_X + outward, y - 0.1 * lift, top + 0.012 + rise)) ?: continue
        val b = at(Point3(0.0 + outward + side * 0.01, y - 0.1 * lift, top + 0.012 + rise + side * 0.05 * lift)) ?: continue
        val px = max(1.6f, camera.pixels(0.022, Point3(0.0, y, top), w, h).toFloat())
        drawLine(Color(0xFFF7EBCB).copy(alpha = (1 - 0.35 * lift).toFloat()), a, b, px, StrokeCap.Round)
    }
}

private fun DrawScope.ball(
    camera: ReplayCamera,
    flight: Flight3d,
    startMs: Double,
    nowMs: Double,
    w: Double,
    h: Double,
) {
    fun at(p: Point3) = camera.project(p, w, h)?.let { Offset(it.x.toFloat(), it.y.toFloat()) }

    // Nothing ball-sized balloons past this, however close it passes the lens: a ball
    // filling half the screen reads as a rendering fault, not as drama.
    val maxBallPx = 15.dp.toPx().toDouble()

    val steps = 72
    val points = (0..steps).map { i -> startMs + (nowMs - startMs) * i / steps }.map { it to flight.at(it) }

    // The path's shadow on the pitch first: what turns a line into a ball in the air.
    for (i in 1 until points.size) {
        val (t, p) = points[i]
        if (t > flight.lastSeenMs) break
        val a = at(Point3(points[i - 1].second.x, points[i - 1].second.y, 0.0)) ?: continue
        val b = at(Point3(p.x, p.y, 0.0)) ?: continue
        // Soft and wide, fading in from the release: a shadow, not a crack in the pitch.
        val px = max(2f, camera.pixels(0.08, p, w, h).toFloat())
        val fade = (i.toFloat() / points.size).coerceIn(0f, 1f)
        drawLine(Color.Black.copy(alpha = 0.13f * (0.4f + 0.6f * fade)), a, b, px, StrokeCap.Round)
    }

    // The trail: a glow pass, then the core, brighter towards the ball.
    for (pass in 0..1) {
        for (i in 1 until points.size) {
            val (t, p) = points[i]
            val a = at(points[i - 1].second) ?: continue
            val b = at(p) ?: continue
            val projected = t > flight.lastSeenMs
            if (projected && (i / 3) % 2 == 0) continue // dashes
            val head = i.toFloat() / steps
            val claim = when {
                t < flight.firstSeenMs -> 0.35f
                projected -> 0.8f
                else -> 1f
            }
            val core = camera.pixels(0.036 * BALL_SIZE_BOOST * 0.75, p, w, h).coerceIn(2.2, maxBallPx * 0.75).toFloat()
            if (pass == 0) {
                if (projected) continue
                drawLine(
                    RampLight.copy(alpha = 0.16f * claim * (0.3f + 0.7f * head)),
                    a,
                    b,
                    core * 3.4f,
                    StrokeCap.Round,
                    blendMode = BlendMode.Plus,
                )
            } else {
                val color = if (projected) {
                    Color.White.copy(alpha = 0.7f)
                } else {
                    lerp(RampLight, Color.White, 0.15f + 0.55f * head).copy(alpha = claim * (0.35f + 0.65f * head))
                }
                drawLine(color, a, b, core, StrokeCap.Round)
            }
        }
    }

    val now = flight.at(nowMs)

    // Its shadow on the grass, softer the higher it is.
    val ground = Point3(now.x, now.y, 0.0)
    at(ground)?.let { s ->
        val r = max(3.0, camera.pixels(0.06, ground, w, h)).toFloat()
        val strength = (0.45 * (1 - (now.z / 2.5).coerceIn(0.0, 1.0))).toFloat()
        drawCircle(Brush.radialGradient(listOf(Color.Black.copy(alpha = strength), Color.Transparent), s, r * 1.6f), r * 1.6f, s)
    }

    // Dust off the pitch where it landed: a few grains thrown up and settling.
    val sinceBounce = (nowMs - flight.bounceMs) / 1000.0
    if (sinceBounce in 0.0..0.45) {
        val life = (sinceBounce / 0.45).toFloat()
        for (k in 0 until 9) {
            val a = 2 * PI * k / 9 + 0.4
            val spread = 0.25 + 0.05 * (k % 3)
            val p = Point3(
                flight.bounceX + cos(a) * spread * sinceBounce * 2.2,
                flight.bounceY + sin(a) * spread * sinceBounce * 2.2 + flight.outVyOrIn * sinceBounce * 0.08,
                (0.9 + 0.25 * (k % 2)) * sinceBounce - 4.0 * sinceBounce * sinceBounce,
            )
            val s = at(Point3(p.x, p.y, max(0.0, p.z))) ?: continue
            val pr = camera.pixels(0.025 + 0.02 * (k % 3), p, w, h).coerceIn(1.2, maxBallPx * 0.35).toFloat()
            drawCircle(Color(0xFFE8D6AE).copy(alpha = 0.55f * (1 - life)), pr, s)
        }
    }

    val c = at(now) ?: return
    val r = camera.pixels(0.036 * BALL_SIZE_BOOST, now, w, h).coerceIn(4.5, maxBallPx).toFloat()
    // Motion blur: where the ball was a few milliseconds ago, smeared behind it.
    at(flight.at(nowMs - 22.0))?.let { back ->
        drawLine(
            Brush.linearGradient(listOf(Color.Transparent, Color.White.copy(alpha = 0.55f)), start = back, end = c),
            back,
            c,
            r * 1.7f,
            StrokeCap.Round,
        )
    }
    drawCircle(RampLight.copy(alpha = 0.30f), r * 2.6f, c, blendMode = BlendMode.Plus)
    drawCircle(
        Brush.radialGradient(
            listOf(Color.White, Color(0xFFE8EEFB), Color(0xFFB4C3E6)),
            center = Offset(c.x - r * 0.35f, c.y - r * 0.4f),
            radius = r * 1.5f,
        ),
        r,
        c,
    )

    // Where the projection meets the stumps.
    flight.atStumps?.let { cross ->
        if (nowMs >= (flight.stumpsMs ?: Double.MAX_VALUE) - 1.0) {
            val p = at(cross) ?: return@let
            val rr = max(7.0, camera.pixels(0.036 * BALL_SIZE_BOOST, cross, w, h)).toFloat()
            val tone = if (flight.hitsStumps == true) Hit else Miss
            drawCircle(tone, rr * 1.5f, p, style = Stroke(2.dp.toPx()))
            drawCircle(tone.copy(alpha = 0.25f), rr * 2.4f, p, blendMode = BlendMode.Plus)
        }
    }
}

/**
 * The brand H as three closed outlines, from the launcher's monochrome vector — never
 * redrawn as one glyph (see the icon notes: the hairline gaps ARE the mark). Centred on
 * the origin and scaled so its height is 1.
 */
internal object HaraanMarkShapes {
    private const val PATH_DATA = "M63.77,32.52L63.74,32.68L63.74,33.26L63.75,42.09L63.66,43.53L63.56,44.08L63.34,44.91L63.16,45.42L62.94,45.95L62.73,46.37L62.30,47.05L61.88,47.56L61.47,47.98L61.09,48.30L60.64,48.62L60.30,48.84L59.78,49.12L59.33,49.31L58.80,49.50L58.21,49.67L57.69,49.79L56.99,49.89L56.21,49.95L51.66,49.96L50.46,50.03L49.35,50.14L48.63,50.25L47.55,50.48L46.16,50.88L45.26,51.21L44.01,51.75L42.99,52.28L41.96,52.94L41.20,53.49L40.43,54.13L39.44,55.07L39.03,55.52L38.59,56.04L37.87,57.03L37.33,57.88L37.00,58.46L36.58,59.35L36.26,60.10L35.94,61.01L35.62,62.14L35.58,62.39L35.51,62.64L35.35,63.63L35.29,63.83L35.28,75.38L35.30,75.49L44.07,75.50L44.18,75.48L44.20,74.87L44.18,65.49L44.25,64.45L44.35,63.80L44.54,63.00L44.71,62.45L45.02,61.72L45.35,61.09L45.79,60.45L46.12,60.06L46.49,59.71L47.00,59.29L47.32,59.07L47.69,58.85L48.09,58.64L48.57,58.42L49.56,58.10L50.59,57.87L51.37,57.78L51.77,57.75L56.21,57.74L57.82,57.62L58.69,57.51L59.84,57.29L61.33,56.87L62.53,56.45L63.76,55.91L64.77,55.37L65.30,55.05L65.94,54.62L66.79,53.97L67.66,53.21L68.62,52.24L69.38,51.32L70.13,50.30L70.67,49.42L71.09,48.59L71.42,47.85L71.84,46.77L72.06,46.08L72.39,44.86L72.42,44.60L72.49,44.35L72.59,43.75L72.65,43.24L72.70,43.04L72.72,42.57L72.71,32.52L72.60,32.50L63.91,32.50L63.77,32.52Z M72.68,50.03L72.62,50.09L72.48,50.46L72.37,50.67L71.83,51.63L71.50,52.15L70.76,53.18L69.79,54.30L69.05,55.03L68.22,55.77L67.69,56.20L66.66,56.94L65.43,57.71L64.59,58.13L64.09,58.35L63.89,58.42L63.77,58.49L63.72,58.55L63.71,75.43L63.72,75.47L63.75,75.49L72.63,75.50L72.71,75.48L72.72,50.19L72.71,50.06L72.68,50.03Z M35.29,32.51L35.28,57.09L35.29,57.22L35.31,57.25L35.38,57.19L35.53,56.80L35.96,55.98L36.50,55.10L37.25,54.08L37.67,53.57L38.55,52.66L39.51,51.80L39.93,51.46L40.49,51.04L41.29,50.50L42.03,50.07L42.45,49.84L43.34,49.42L44.11,49.12L44.20,49.03L44.22,48.91L44.21,33.42L44.20,32.65L44.18,32.53L44.12,32.50L35.29,32.51Z"

    val pieces: List<List<Pair<Double, Double>>> by lazy {
        val raw = PATH_DATA.split('Z').map { it.trim() }.filter { it.isNotEmpty() }.map { piece ->
            Regex("""-?\d+(?:\.\d+)?""").findAll(piece).map { it.value.toDouble() }.toList()
                .chunked(2).filter { it.size == 2 }.map { it[0] to it[1] }
        }
        val all = raw.flatten()
        val minX = all.minOf { it.first }
        val maxX = all.maxOf { it.first }
        val minY = all.minOf { it.second }
        val maxY = all.maxOf { it.second }
        val cx = (minX + maxX) / 2
        val cy = (minY + maxY) / 2
        val scale = maxY - minY
        raw.map { piece -> piece.map { (x, y) -> (x - cx) / scale to (y - cy) / scale } }
    }

    /** The same outlines with two points in three dropped: plenty at board and ground size. */
    val coarse: List<List<Pair<Double, Double>>> by lazy {
        pieces.map { piece -> piece.filterIndexed { i, _ -> i % 3 == 0 || i == piece.lastIndex } }
    }
}
