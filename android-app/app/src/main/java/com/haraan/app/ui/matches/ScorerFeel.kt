package com.haraan.app.ui.matches

import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.animateOffsetAsState
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.interaction.collectIsPressedAsState
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxScope
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.drawBehind
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Rect
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.drawscope.clipRect
import androidx.compose.ui.graphics.drawscope.rotate
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.graphics.lerp
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.coroutineScope
import kotlinx.coroutines.launch
import kotlin.math.PI
import kotlin.math.sin

/*
 * THE SCORER'S HAND FEEL — shared by the football scorer and the rally / basketball / kabaddi one.
 *
 * Three rules, learnt from the screens these replaced:
 *
 *  · A drawing earns its place only by showing something TRUE about this match — who is serving
 *    and from which box, how many defenders are still on the mat, how many team fouls are up,
 *    who has the ball. A pretty court that says nothing is scenery, and scenery is what made
 *    earlier passes read as generated.
 *  · A key is a key: it has depth, travels when pressed and springs back. A flat tinted box
 *    under a finger feels like a web form.
 *  · One event, one haptic, carried in the COUNT of knocks (see [Thud]) so a scorer watching
 *    the game, not the phone, can tell a three from a free throw.
 */

// ─────────────────────────────── haptics ───────────────────────────────

/**
 * What an event should feel like. Ordinary points are one click; the moments a crowd reacts to
 * (a three, an ace, a super raid) are one firm knock; the ones that swing a match (a super
 * tackle) get the pair. Stoppages are the long swell, so a timeout never feels like a point.
 */
internal fun thudFor(sport: String, e: ScorerEvent, board: SportBoard): Thud {
    val detail = e.detail.orEmpty()
    return when (e.kind) {
        "point" -> when (sport) {
            "basketball" -> when (detail) {
                "3" -> Thud.FOUR
                "1" -> Thud.TICK
                else -> Thud.RUN
            }
            "kabaddi" -> when {
                detail.startsWith("raid:") -> if (kabaddiRaidPoints(detail) >= 3) Thud.SIX else Thud.RUN
                detail == "tackle" -> {
                    val on = board.mat?.let { if (e.side == "home") it.home else it.away } ?: 7
                    if (on <= 3) Thud.SIX else Thud.FOUR
                }
                detail == "dod_out" -> Thud.FOUR
                else -> Thud.EXTRA
            }
            else -> if (detail == "ace") Thud.FOUR else Thud.RUN
        }
        "serve" -> Thud.TICK
        "raid" -> Thud.EXTRA
        "timeout", "period" -> Thud.DELIVERY
        else -> Thud.TICK
    }
}

/** "raid:2:b" → 3. */
internal fun kabaddiRaidPoints(detail: String): Int {
    val parts = detail.split(':')
    return (parts.getOrNull(1)?.toIntOrNull() ?: 0) + if (parts.getOrNull(2) == "b") 1 else 0
}

/**
 * What a tap is worth, shown as a ghost above the score while the server settles it. Null when
 * the event moves no score (a timeout, a rebound) — nothing to promise.
 */
internal fun pendingLabelFor(sport: String, e: ScorerEvent, board: SportBoard): String? {
    // Tennis counts 15-30-40, so "+1" would be a lie about the figure it sits on.
    if (e.kind != "point" || e.side == null || sport == "tennis") return null
    val detail = e.detail.orEmpty()
    val n = when (sport) {
        "basketball" -> detail.toIntOrNull() ?: 2
        "kabaddi" -> when {
            detail.startsWith("raid:") -> kabaddiRaidPoints(detail)
            detail == "tackle" -> if ((board.mat?.let { if (e.side == "home") it.home else it.away } ?: 7) <= 3) 2 else 1
            else -> 1
        }
        else -> 1
    }
    return "+$n"
}

// ─────────────────────────────── the key ───────────────────────────────

/**
 * A key with a lip under it. Pressed, the face travels down onto the lip and the lip
 * disappears; released, it springs back a touch past rest. The press is felt by the eye
 * before the finger lifts, which is what a bare `clickable` never gives.
 *
 * Deliberately no haptic of its own: the caller plays the event's [Thud], and two buzzes on
 * one tap read as one smeared buzz.
 */
@Composable
internal fun ScoreKey(
    color: Color,
    enabled: Boolean,
    modifier: Modifier = Modifier,
    height: Dp = 58.dp,
    onClick: () -> Unit,
    content: @Composable BoxScope.() -> Unit,
) {
    val interaction = remember { MutableInteractionSource() }
    val pressed by interaction.collectIsPressedAsState()
    val travel by animateFloatAsState(
        targetValue = if (pressed && enabled) 1f else 0f,
        animationSpec = spring(dampingRatio = 0.5f, stiffness = 1500f),
        label = "keyTravel",
    )
    val lip = 4.dp
    val shape = RoundedCornerShape(14.dp)
    val face = if (enabled) color else lerp(color, Color.White, 0.55f)
    val under = lerp(face, Color.Black, 0.3f)
    Box(
        modifier
            .height(height)
            .clickable(interactionSource = interaction, indication = null, enabled = enabled, onClick = onClick),
    ) {
        Box(Modifier.matchParentSize().clip(shape).background(under))
        Box(
            Modifier
                .fillMaxWidth()
                .height(height - lip)
                .graphicsLayer { translationY = travel * lip.toPx() }
                .clip(shape)
                .background(Brush.verticalGradient(listOf(lerp(face, Color.White, 0.1f), face)))
                // A hairline of light along the top edge — the face catching the room, not a gloss.
                .drawBehind {
                    drawLine(
                        Color.White.copy(alpha = 0.28f),
                        Offset(14.dp.toPx(), 1.dp.toPx()),
                        Offset(size.width - 14.dp.toPx(), 1.dp.toPx()),
                        strokeWidth = 1.dp.toPx(),
                    )
                },
            contentAlignment = Alignment.Center,
            content = content,
        )
    }
}

// ─────────────────────────────── the score landing ───────────────────────────────

/**
 * The score as it lands: the digits roll (see [RollingFigure]), the figure gives a short bump
 * and a ring opens behind it in the side's colour. An undo dips instead of bumping, so a
 * correction never looks like a point. Nothing moves on the first composition.
 */
@Composable
internal fun LandingFigure(text: String, color: Color, accent: Color, size: Int, modifier: Modifier = Modifier) {
    val bump = remember { Animatable(1f) }
    val ring = remember { Animatable(0f) }
    var last by remember { mutableStateOf(text) }
    LaunchedEffect(text) {
        val before = last.toIntOrNull()
        val now = text.toIntOrNull()
        last = text
        if (before == null || now == null || before == now) return@LaunchedEffect
        if (now > before) {
            ring.snapTo(0f)
            bump.snapTo(1.16f)
            coroutineScope {
                launch { bump.animateTo(1f, spring(dampingRatio = 0.42f, stiffness = 520f)) }
                launch { ring.animateTo(1f, tween(620, easing = FastOutSlowInEasing)) }
            }
        } else {
            bump.snapTo(0.9f)
            bump.animateTo(1f, spring(dampingRatio = 0.7f, stiffness = 600f))
        }
    }
    Box(
        modifier.drawBehind {
            val p = ring.value
            if (p > 0f && p < 1f) {
                drawCircle(
                    accent.copy(alpha = 0.32f * (1f - p)),
                    radius = this.size.minDimension * (0.35f + 0.6f * p),
                    style = Stroke(width = (3.dp.toPx() * (1f - p)).coerceAtLeast(0.5f)),
                )
            }
        },
        contentAlignment = Alignment.Center,
    ) {
        RollingFigure(
            text, color, size,
            modifier = Modifier.graphicsLayer { scaleX = bump.value; scaleY = bump.value },
        )
    }
}

// ─────────────────────────────── the court ───────────────────────────────

/**
 * The court from above, home's half on the left as on the board above it, with only what the
 * server's replay knows drawn onto it. Returns nothing for a sport with no court to draw.
 */
@Composable
internal fun ScorerCourt(sport: String, board: SportBoard, state: MatchUiState, modifier: Modifier = Modifier) {
    when (sport) {
        "volleyball" -> VolleyballCourt(board, modifier)
        "badminton" -> BadmintonCourt(board, modifier)
        "tennis" -> TennisCourt(board, modifier)
        "table_tennis" -> TableTennisTable(board, modifier)
        "basketball" -> BasketballCourt(board, state, modifier)
        "kabaddi" -> KabaddiMatDrawing(board, state, modifier)
    }
}

private val CourtLine = Color.White.copy(alpha = 0.92f)
private val BallYellow = Color(0xFFF5E663)
private val CourtHeight = 86.dp

/** A ball with a short soft shadow under it — the one bit of depth on a flat court. */
private fun DrawScope.ball(at: Offset, r: Float, fill: Color, rim: Color = Color(0x55000000)) {
    drawCircle(Color.Black.copy(alpha = 0.18f), r * 1.05f, at + Offset(r * 0.35f, r * 0.55f))
    drawCircle(fill, r, at)
    drawCircle(rim, r, at, style = Stroke(r * 0.18f))
}

/** Rotation-free serve position: the server stands behind THEIR baseline, on their right. */
@Composable
private fun animatedServe(target: Offset): Offset {
    val pos by animateOffsetAsState(target, tween(520, easing = FastOutSlowInEasing), label = "servePos")
    return pos
}

@Composable
private fun VolleyballCourt(board: SportBoard, modifier: Modifier) {
    // Home faces +x, so their right hand is the bottom of the drawing; away's is the top.
    val target = when (board.serving) {
        "home" -> Offset(0.035f, 0.78f)
        "away" -> Offset(0.965f, 0.22f)
        else -> Offset(0.5f, 0.5f)
    }
    val pos = animatedServe(target)
    val showBall by animateFloatAsState(if (board.serving != null) 1f else 0f, tween(300), label = "vbBall")
    Canvas(modifier.fillMaxWidth().height(CourtHeight).clip(RoundedCornerShape(12.dp))) {
        val w = size.width
        val h = size.height
        drawRect(Color(0xFF2E7F96))                               // free zone
        val court = Rect(w * 0.07f, h * 0.12f, w * 0.93f, h * 0.88f)
        drawRect(Color(0xFFE8925C), court.topLeft, court.size)    // playing court
        val s = 1.6.dp.toPx()
        drawRect(CourtLine, court.topLeft, court.size, style = Stroke(s))
        val mid = court.center.x
        val attack = court.width * (3f / 18f)                     // 3 m lines on an 18 m court
        drawLine(CourtLine, Offset(mid - attack, court.top), Offset(mid - attack, court.bottom), s)
        drawLine(CourtLine, Offset(mid + attack, court.top), Offset(mid + attack, court.bottom), s)
        // The net, a touch proud of the floor.
        drawLine(Color.Black.copy(alpha = 0.2f), Offset(mid + 1.5f, court.top - 4.dp.toPx()), Offset(mid + 1.5f, court.bottom + 4.dp.toPx()), 3.dp.toPx())
        drawLine(Color(0xFF1F2937), Offset(mid, court.top - 4.dp.toPx()), Offset(mid, court.bottom + 4.dp.toPx()), 2.4.dp.toPx())
        if (showBall > 0f) ball(Offset(pos.x * w, pos.y * h), 5.dp.toPx() * showBall, Color.White)
    }
}

@Composable
private fun BadmintonCourt(board: SportBoard, modifier: Modifier) {
    // In each half "right" is the box on the server's right as they face the net.
    val target = when {
        board.serving == "home" && board.serviceCourt == "right" -> Offset(0.3f, 0.7f)
        board.serving == "home" -> Offset(0.3f, 0.3f)
        board.serving == "away" && board.serviceCourt == "right" -> Offset(0.7f, 0.3f)
        board.serving == "away" -> Offset(0.7f, 0.7f)
        else -> Offset(0.5f, 0.5f)
    }
    val pos = animatedServe(target)
    val lit by animateFloatAsState(if (board.serving != null) 1f else 0f, tween(300), label = "bdLit")
    Canvas(modifier.fillMaxWidth().height(CourtHeight).clip(RoundedCornerShape(12.dp))) {
        val w = size.width
        val h = size.height
        drawRect(Color(0xFF23664B))
        val c = Rect(w * 0.06f, h * 0.1f, w * 0.94f, h * 0.9f)
        drawRect(Color(0xFF2F8A63), c.topLeft, c.size)
        val s = 1.4.dp.toPx()
        val mid = c.center.x
        val shortLine = c.width * (1.98f / 13.4f)                  // short service line from the net
        val longDouble = c.width * (0.76f / 13.4f)                 // doubles long service line
        val sideAlley = c.height * (0.46f / 6.1f)
        // The box the server stands in, lit.
        if (lit > 0f && board.serving != null) {
            val left = if (pos.x < 0.5f) c.left + longDouble else mid + shortLine
            val right = if (pos.x < 0.5f) mid - shortLine else c.right - longDouble
            val top = if (pos.y < 0.5f) c.top + sideAlley else c.center.y
            val bottom = if (pos.y < 0.5f) c.center.y else c.bottom - sideAlley
            drawRect(Color.White.copy(alpha = 0.16f * lit), Offset(left, top), Size(right - left, bottom - top))
        }
        drawRect(CourtLine, c.topLeft, c.size, style = Stroke(s))
        drawLine(CourtLine, Offset(c.left, c.top + sideAlley), Offset(c.right, c.top + sideAlley), s)
        drawLine(CourtLine, Offset(c.left, c.bottom - sideAlley), Offset(c.right, c.bottom - sideAlley), s)
        listOf(mid - shortLine, mid + shortLine, c.left + longDouble, c.right - longDouble).forEach {
            drawLine(CourtLine, Offset(it, c.top), Offset(it, c.bottom), s)
        }
        drawLine(CourtLine, Offset(c.left, c.center.y), Offset(mid - shortLine, c.center.y), s)
        drawLine(CourtLine, Offset(mid + shortLine, c.center.y), Offset(c.right, c.center.y), s)
        drawLine(Color(0xFF111827), Offset(mid, c.top - 3.dp.toPx()), Offset(mid, c.bottom + 3.dp.toPx()), 2.dp.toPx())
        if (board.serving != null) shuttle(Offset(pos.x * w, pos.y * h), 6.5.dp.toPx() * lit, if (pos.x < 0.5f) 1f else -1f)
    }
}

/** A shuttle: the cork, and the skirt flaring away from where it will be hit. */
private fun DrawScope.shuttle(at: Offset, r: Float, facing: Float) {
    val skirt = Path().apply {
        moveTo(at.x, at.y - r * 0.55f)
        lineTo(at.x - facing * r * 1.9f, at.y - r * 1.1f)
        lineTo(at.x - facing * r * 1.9f, at.y + r * 1.1f)
        lineTo(at.x, at.y + r * 0.55f)
        close()
    }
    drawPath(skirt, Color.White.copy(alpha = 0.95f))
    drawPath(skirt, Color(0x33000000), style = Stroke(0.8.dp.toPx()))
    drawCircle(Color(0xFFE5E7EB), r * 0.75f, at)
    drawCircle(Color(0xFF9CA3AF), r * 0.75f, at, style = Stroke(0.8.dp.toPx()))
}

private val TennisSteps = mapOf("0" to 0, "15" to 1, "30" to 2, "40" to 3, "AD" to 4, "A" to 4)

@Composable
private fun TennisCourt(board: SportBoard, modifier: Modifier) {
    // Points played this game decide the court: an even count serves from the deuce (right)
    // court, odd from the ad court. Tie-break points are already counts.
    val played = board.points?.let { (a, b) ->
        val pa = a.toIntOrNull()?.takeIf { board.tiebreak } ?: TennisSteps[a.uppercase()]
        val pb = b.toIntOrNull()?.takeIf { board.tiebreak } ?: TennisSteps[b.uppercase()]
        if (pa == null || pb == null) null else pa + pb
    }
    val deuceCourt = (played ?: 0) % 2 == 0
    // Home faces +x: their right (deuce) is the bottom. The serve goes cross-court.
    val target = when (board.serving) {
        "home" -> Offset(0.04f, if (deuceCourt) 0.68f else 0.32f)
        "away" -> Offset(0.96f, if (deuceCourt) 0.32f else 0.68f)
        else -> Offset(0.5f, 0.5f)
    }
    val pos = animatedServe(target)
    val lit by animateFloatAsState(if (board.serving != null) 1f else 0f, tween(300), label = "tnLit")
    Canvas(modifier.fillMaxWidth().height(CourtHeight).clip(RoundedCornerShape(12.dp))) {
        val w = size.width
        val h = size.height
        drawRect(Color(0xFF4F7F3E))
        val c = Rect(w * 0.08f, h * 0.1f, w * 0.92f, h * 0.9f)
        drawRect(Color(0xFF3E7CB1), c.topLeft, c.size)
        val s = 1.4.dp.toPx()
        val mid = c.center.x
        val alley = c.height * (1.37f / 10.97f)
        val service = c.width * (6.4f / 23.77f)
        val inTop = c.top + alley
        val inBottom = c.bottom - alley
        // The box the serve must land in — the receiver's, diagonally across.
        if (board.serving != null && lit > 0f) {
            val receiverLeft = board.serving == "away"
            val x0 = if (receiverLeft) mid - service else mid
            val upper = if (board.serving == "home") deuceCourt else !deuceCourt
            val y0 = if (upper) inTop else c.center.y
            drawRect(BallYellow.copy(alpha = 0.22f * lit), Offset(x0, y0), Size(service, (inBottom - inTop) / 2f))
        }
        drawRect(CourtLine, c.topLeft, c.size, style = Stroke(s))
        drawLine(CourtLine, Offset(c.left, inTop), Offset(c.right, inTop), s)
        drawLine(CourtLine, Offset(c.left, inBottom), Offset(c.right, inBottom), s)
        drawLine(CourtLine, Offset(mid - service, inTop), Offset(mid - service, inBottom), s)
        drawLine(CourtLine, Offset(mid + service, inTop), Offset(mid + service, inBottom), s)
        drawLine(CourtLine, Offset(mid - service, c.center.y), Offset(mid + service, c.center.y), s)
        drawLine(Color.Black.copy(alpha = 0.22f), Offset(mid + 1.5f, c.top - 3.dp.toPx()), Offset(mid + 1.5f, c.bottom + 3.dp.toPx()), 3.dp.toPx())
        drawLine(Color.White, Offset(mid, c.top - 3.dp.toPx()), Offset(mid, c.bottom + 3.dp.toPx()), 2.dp.toPx())
        if (board.serving != null) ball(Offset(pos.x * w, pos.y * h), 4.5.dp.toPx() * lit, BallYellow)
    }
}

@Composable
private fun TableTennisTable(board: SportBoard, modifier: Modifier) {
    val target = when (board.serving) {
        "home" -> Offset(0.1f, 0.5f)
        "away" -> Offset(0.9f, 0.5f)
        else -> Offset(0.5f, 0.5f)
    }
    val pos = animatedServe(target)
    val lit by animateFloatAsState(if (board.serving != null) 1f else 0f, tween(300), label = "ttLit")
    Canvas(modifier.fillMaxWidth().height(CourtHeight).clip(RoundedCornerShape(12.dp))) {
        val w = size.width
        val h = size.height
        drawRect(Color(0xFFE5E7EB))
        val t = Rect(w * 0.16f, h * 0.16f, w * 0.84f, h * 0.84f)
        // The table's own thickness, then its top.
        drawRoundRect(Color(0xFF0F2F5C), t.topLeft + Offset(0f, 4.dp.toPx()), t.size, CornerRadius(3.dp.toPx()))
        drawRoundRect(Color(0xFF1E4F91), t.topLeft, t.size, CornerRadius(3.dp.toPx()))
        val s = 1.6.dp.toPx()
        drawRoundRect(CourtLine, t.topLeft, t.size, CornerRadius(3.dp.toPx()), style = Stroke(s))
        drawLine(CourtLine.copy(alpha = 0.7f), Offset(t.left, t.center.y), Offset(t.right, t.center.y), 1.dp.toPx())
        drawLine(Color.Black.copy(alpha = 0.25f), Offset(t.center.x + 2f, t.top - 6.dp.toPx()), Offset(t.center.x + 2f, t.bottom + 6.dp.toPx()), 3.dp.toPx())
        drawLine(Color(0xFF111827), Offset(t.center.x, t.top - 6.dp.toPx()), Offset(t.center.x, t.bottom + 6.dp.toPx()), 2.dp.toPx())
        if (board.serving != null) {
            val at = Offset(pos.x * w, pos.y * h)
            ball(at, 4.dp.toPx() * lit, Color(0xFFFFF7ED))
            // Serves left for this server — two dots for the pair, one at deuce.
            val left = board.servesLeft ?: 0
            repeat(2) { i ->
                val c = at + Offset(0f, (if (i == 0) -1f else 1f) * 13.dp.toPx())
                val on = i < left
                drawCircle(if (on) Color(0xFF1E4F91) else Color.Transparent, 2.6.dp.toPx(), c)
                drawCircle(Color(0xFF1E4F91), 2.6.dp.toPx(), c, style = Stroke(1.dp.toPx()))
            }
        }
    }
}

@Composable
private fun BasketballCourt(board: SportBoard, state: MatchUiState, modifier: Modifier) {
    val fouls = board.teamFouls ?: (0 to 0)
    val homeLit = fouls.first.coerceAtMost(5)
    val awayLit = fouls.second.coerceAtMost(5)
    val homeAnim by animateFloatAsState(homeLit.toFloat(), tween(380), label = "foulH")
    val awayAnim by animateFloatAsState(awayLit.toFloat(), tween(380), label = "foulA")
    Canvas(modifier.fillMaxWidth().height(CourtHeight).clip(RoundedCornerShape(12.dp))) {
        val w = size.width
        val h = size.height
        drawRect(Color(0xFFD9A066))
        // Planks — the grain runs the length of the court.
        val plank = 7.dp.toPx()
        var y = plank
        while (y < h) { drawLine(Color(0xFFC98E55), Offset(0f, y), Offset(w, y), 0.8.dp.toPx()); y += plank }
        val c = Rect(w * 0.04f, h * 0.08f, w * 0.96f, h * 0.92f)
        val s = 1.5.dp.toPx()
        val keyW = c.width * (5.8f / 28f)
        val keyH = c.height * (4.9f / 15f)
        listOf(
            Triple(c.left, 1f, state.team1Color),
            Triple(c.right, -1f, state.team2Color),
        ).forEach { (base, dir, color) ->
            val x1 = base + dir * keyW
            val keyLeft = minOf(base, x1)
            drawRect(color.copy(alpha = 0.55f), Offset(keyLeft, c.center.y - keyH / 2), Size(keyW, keyH))
            drawRect(CourtLine, Offset(keyLeft, c.center.y - keyH / 2), Size(keyW, keyH), style = Stroke(s))
            // Three-point arc around the hoop.
            val hoop = Offset(base + dir * c.width * (1.6f / 28f), c.center.y)
            val r = c.width * (6.75f / 28f)
            clipRect(c.left, c.top, c.right, c.bottom) {
                drawArc(
                    CourtLine, startAngle = if (dir > 0) -90f else 90f, sweepAngle = 180f, useCenter = false,
                    topLeft = hoop - Offset(r, r), size = Size(r * 2, r * 2), style = Stroke(s),
                )
            }
            drawCircle(Color(0xFFEA580C), 2.4.dp.toPx(), hoop, style = Stroke(1.4.dp.toPx()))
        }
        drawRect(CourtLine, c.topLeft, c.size, style = Stroke(s))
        drawLine(CourtLine, Offset(c.center.x, c.top), Offset(c.center.x, c.bottom), s)
        drawCircle(CourtLine, c.height * 0.12f, c.center, style = Stroke(s))
        // Team-foul lamps — five per side along the far sideline; the fifth puts the other
        // side in the bonus.
        fun lamps(lit: Float, startX: Float, dir: Float, color: Color) {
            repeat(5) { i ->
                val at = Offset(startX + dir * i * 9.dp.toPx(), c.top + 6.dp.toPx())
                val on = (lit - i).coerceIn(0f, 1f)
                drawCircle(Color.Black.copy(alpha = 0.28f), 3.dp.toPx(), at)
                if (on > 0f) drawCircle(lerp(color, Color(0xFFDC2626), if (lit >= 5f) 1f else 0f).copy(alpha = on), 3.dp.toPx() * on, at)
            }
        }
        lamps(homeAnim, c.left + 10.dp.toPx(), 1f, Color(0xFFFFE08A))
        lamps(awayAnim, c.right - 10.dp.toPx(), -1f, Color(0xFFFFE08A))
    }
}

@Composable
private fun KabaddiMatDrawing(board: SportBoard, state: MatchUiState, modifier: Modifier) {
    val mat = board.mat
    val matSize = mat?.size ?: 7
    val homeOn = mat?.home ?: matSize
    val awayOn = mat?.away ?: matSize
    val raiding = board.raiding
    val raid = rememberInfiniteTransition(label = "raid")
    // The raider working the midline: a slow, live sway — the only thing on the mat that keeps
    // moving, because a raid in progress is the one thing still happening.
    val sway by raid.animateFloat(
        0f, 1f, infiniteRepeatable(tween(1800, easing = LinearEasing), RepeatMode.Restart), label = "sway",
    )
    val homeLit = remember { Animatable(homeOn.toFloat()) }
    val awayLit = remember { Animatable(awayOn.toFloat()) }
    LaunchedEffect(homeOn) { homeLit.animateTo(homeOn.toFloat(), tween(520, easing = FastOutSlowInEasing)) }
    LaunchedEffect(awayOn) { awayLit.animateTo(awayOn.toFloat(), tween(520, easing = FastOutSlowInEasing)) }
    val danger = board.doOrDie
    Canvas(modifier.fillMaxWidth().height(CourtHeight).clip(RoundedCornerShape(12.dp))) {
        val w = size.width
        val h = size.height
        drawRect(Color(0xFF3B2F7A))                                     // lobbies / surround
        val m = Rect(w * 0.05f, h * 0.14f, w * 0.95f, h * 0.86f)
        drawRect(Color(0xFFE9893F), m.topLeft, m.size)                   // the mat
        val s = 1.5.dp.toPx()
        drawRect(CourtLine, m.topLeft, m.size, style = Stroke(s))
        val mid = m.center.x
        val baulk = m.width * (3.75f / 13f)
        val bonus = m.width * (4.75f / 13f)
        drawLine(CourtLine, Offset(mid, m.top), Offset(mid, m.bottom), 2.2.dp.toPx())
        listOf(-baulk, baulk).forEach { drawLine(CourtLine, Offset(mid + it, m.top), Offset(mid + it, m.bottom), s) }
        listOf(-bonus, bonus).forEach {
            drawLine(CourtLine.copy(alpha = 0.6f), Offset(mid + it, m.top), Offset(mid + it, m.bottom), 1.dp.toPx(), cap = StrokeCap.Round,
                pathEffect = androidx.compose.ui.graphics.PathEffect.dashPathEffect(floatArrayOf(5f, 5f)))
        }
        // Defenders in an arc deep in each half; the ones out go hollow from the far end.
        fun side(lit: Float, home: Boolean, color: Color) {
            repeat(matSize) { i ->
                val t = if (matSize == 1) 0.5f else i / (matSize - 1f)
                // A horseshoe opening to the midline: corners stand forward, the middle deep.
                val y = m.top + m.height * (0.12f + 0.76f * t)
                val depth = 0.3f - 0.14f * sin(t * PI).toFloat()
                val x = if (home) m.left + m.width * depth else m.right - m.width * depth
                val on = (lit - i).coerceIn(0f, 1f)
                val r = 4.2.dp.toPx()
                drawCircle(Color.Black.copy(alpha = 0.16f * on), r, Offset(x + 1.5f, y + 2f))
                drawCircle(color.copy(alpha = 0.25f + 0.75f * on), r * (0.8f + 0.2f * on), Offset(x, y))
                drawCircle(Color.White.copy(alpha = 0.9f), r * (0.8f + 0.2f * on), Offset(x, y), style = Stroke(1.dp.toPx()))
            }
        }
        side(homeLit.value, true, state.team1Color)
        side(awayLit.value, false, state.team2Color)
        if (raiding != null) {
            val dir = if (raiding == "home") 1f else -1f
            val reach = (bonus * 0.9f) * (0.5f - 0.5f * kotlin.math.cos(sway * 2 * PI).toFloat())
            val at = Offset(mid + dir * (6.dp.toPx() + reach), m.center.y + 6.dp.toPx() * sin(sway * 4 * PI).toFloat())
            val color = if (danger) Color(0xFFDC2626) else if (raiding == "home") state.team1Color else state.team2Color
            drawCircle(Color.Black.copy(alpha = 0.2f), 5.6.dp.toPx(), at + Offset(1.5f, 2.5f))
            drawCircle(color, 5.6.dp.toPx(), at)
            drawCircle(Color.White, 5.6.dp.toPx(), at, style = Stroke(1.6.dp.toPx()))
        }
    }
}

// ─────────────────────────────── glyphs ───────────────────────────────

/** Where on the floor a basket was scored from: inside the arc, beyond it, or the free-throw line. */
@Composable
internal fun ShotSpotGlyph(points: Int, tint: Color, modifier: Modifier = Modifier) {
    Canvas(modifier.size(width = 30.dp, height = 18.dp)) {
        val w = size.width
        val h = size.height
        val s = 1.3.dp.toPx()
        val hoop = Offset(w / 2, 2.dp.toPx())
        drawLine(tint.copy(alpha = 0.55f), Offset(0f, 0.6f), Offset(w, 0.6f), s)
        drawArc(tint.copy(alpha = 0.55f), 0f, 180f, false, Offset(hoop.x - w * 0.42f, hoop.y - w * 0.42f), Size(w * 0.84f, w * 0.84f), style = Stroke(s))
        drawRect(tint.copy(alpha = 0.55f), Offset(w / 2 - w * 0.13f, 0f), Size(w * 0.26f, h * 0.55f), style = Stroke(s))
        val spot = when (points) {
            3 -> Offset(w * 0.12f, h * 0.86f)
            1 -> Offset(w / 2, h * 0.55f)
            else -> Offset(w * 0.64f, h * 0.36f)
        }
        drawCircle(tint, 2.8.dp.toPx(), spot)
    }
}

/** A defender figure — standing while on the mat, toppled when touched out. */
@Composable
internal fun DefenderFigure(out: Boolean, color: Color, modifier: Modifier = Modifier) {
    val fall by animateFloatAsState(
        if (out) 1f else 0f,
        spring(dampingRatio = 0.55f, stiffness = 380f),
        label = "fall",
    )
    Canvas(modifier.size(width = 30.dp, height = 40.dp)) {
        val w = size.width
        val h = size.height
        val body = lerp(color, Color(0xFFCBD5E1), fall * 0.7f)
        // Shadow stays on the floor as the figure tips over.
        drawOval(Color.Black.copy(alpha = 0.12f), Offset(w * 0.18f, h * 0.88f), Size(w * (0.64f + 0.5f * fall), h * 0.1f))
        rotate(80f * fall, pivot = Offset(w * 0.3f, h * 0.92f)) {
            drawCircle(body, w * 0.17f, Offset(w * 0.5f, h * 0.2f))
            drawRoundRect(body, Offset(w * 0.3f, h * 0.36f), Size(w * 0.4f, h * 0.32f), CornerRadius(w * 0.12f))
            drawLine(body, Offset(w * 0.4f, h * 0.66f), Offset(w * 0.32f, h * 0.9f), w * 0.13f, cap = StrokeCap.Round)
            drawLine(body, Offset(w * 0.6f, h * 0.66f), Offset(w * 0.68f, h * 0.9f), w * 0.13f, cap = StrokeCap.Round)
            drawLine(body, Offset(w * 0.32f, h * 0.42f), Offset(w * 0.12f, h * 0.56f), w * 0.11f, cap = StrokeCap.Round)
            drawLine(body, Offset(w * 0.68f, h * 0.42f), Offset(w * 0.88f, h * 0.56f), w * 0.11f, cap = StrokeCap.Round)
        }
    }
}

/** A row of defenders to tap. Tapping the third marks three touched; tapping it again marks two. */
@Composable
internal fun DefenderRow(onMat: Int, touched: Int, color: Color, onTouched: (Int) -> Unit) {
    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceEvenly) {
        repeat(onMat.coerceIn(0, 7)) { i ->
            val n = i + 1
            DefenderFigure(
                out = n <= touched,
                color = color,
                modifier = Modifier
                    .clickable(interactionSource = remember { MutableInteractionSource() }, indication = null) {
                        onTouched(if (touched == n) n - 1 else n)
                    },
            )
        }
    }
}

/** A football in the back of a net — for the Goal key. */
@Composable
internal fun GoalGlyph(tint: Color, modifier: Modifier = Modifier) {
    Canvas(modifier.size(width = 26.dp, height = 20.dp)) {
        val w = size.width
        val h = size.height
        val s = 1.2.dp.toPx()
        // Net mesh, then the frame.
        var x = w * 0.1f
        while (x < w * 0.9f) { drawLine(tint.copy(alpha = 0.35f), Offset(x, h * 0.12f), Offset(x, h * 0.95f), 0.8.dp.toPx()); x += 3.5.dp.toPx() }
        var y = h * 0.3f
        while (y < h) { drawLine(tint.copy(alpha = 0.35f), Offset(w * 0.08f, y), Offset(w * 0.92f, y), 0.8.dp.toPx()); y += 3.5.dp.toPx() }
        drawLine(tint, Offset(w * 0.06f, h), Offset(w * 0.06f, h * 0.1f), s * 1.6f, cap = StrokeCap.Round)
        drawLine(tint, Offset(w * 0.06f, h * 0.1f), Offset(w * 0.94f, h * 0.1f), s * 1.6f, cap = StrokeCap.Round)
        drawLine(tint, Offset(w * 0.94f, h * 0.1f), Offset(w * 0.94f, h), s * 1.6f, cap = StrokeCap.Round)
        drawCircle(Color.White, h * 0.24f, Offset(w * 0.62f, h * 0.66f))
        drawCircle(Color(0xFF111827), h * 0.24f, Offset(w * 0.62f, h * 0.66f), style = Stroke(s))
        drawCircle(Color(0xFF111827), h * 0.08f, Offset(w * 0.62f, h * 0.66f))
    }
}

/** A booking card, tilted the way a referee holds it up. */
@Composable
internal fun BookingCardGlyph(color: Color, modifier: Modifier = Modifier) {
    Canvas(modifier.size(width = 16.dp, height = 20.dp)) {
        rotate(12f) {
            drawRoundRect(Color.Black.copy(alpha = 0.15f), Offset(size.width * 0.24f, size.height * 0.12f), Size(size.width * 0.6f, size.height * 0.8f), CornerRadius(2.dp.toPx()))
            drawRoundRect(color, Offset(size.width * 0.18f, size.height * 0.06f), Size(size.width * 0.6f, size.height * 0.8f), CornerRadius(2.dp.toPx()))
        }
    }
}

// ─────────────────────────────── football pitch ───────────────────────────────

/**
 * The pitch from above, home attacking left to right. The ball drifts to whoever has it
 * (centre spot when it's dead or untracked), and the net on the far end shakes when a goal
 * goes in — [homeGoals]/[awayGoals] going up is the trigger, never the first composition.
 */
@Composable
internal fun FootballPitch(possession: String?, homeGoals: Int, awayGoals: Int, running: Boolean, modifier: Modifier = Modifier) {
    val target = when (possession) {
        "home" -> Offset(0.64f, 0.46f)
        "away" -> Offset(0.36f, 0.54f)
        else -> Offset(0.5f, 0.5f)
    }
    val pos by animateOffsetAsState(target, tween(900, easing = FastOutSlowInEasing), label = "fbBall")
    val homeNet = remember { Animatable(0f) }   // the net home scores into: the right-hand goal
    val awayNet = remember { Animatable(0f) }
    var seen by remember { mutableStateOf(homeGoals to awayGoals) }
    LaunchedEffect(homeGoals, awayGoals) {
        val (h0, a0) = seen
        seen = homeGoals to awayGoals
        if (homeGoals > h0) { homeNet.snapTo(1f); homeNet.animateTo(0f, tween(1600, easing = FastOutSlowInEasing)) }
        if (awayGoals > a0) { awayNet.snapTo(1f); awayNet.animateTo(0f, tween(1600, easing = FastOutSlowInEasing)) }
    }
    val idle = rememberInfiniteTransition(label = "fbIdle")
    val roll by idle.animateFloat(0f, 1f, infiniteRepeatable(tween(2400, easing = LinearEasing)), label = "roll")
    Canvas(modifier.fillMaxWidth().height(74.dp).clip(RoundedCornerShape(12.dp))) {
        val w = size.width
        val h = size.height
        // Mown stripes.
        val stripes = 10
        repeat(stripes) { i ->
            drawRect(if (i % 2 == 0) Color(0xFF3F8F4A) else Color(0xFF37803F), Offset(w * i / stripes, 0f), Size(w / stripes + 1f, h))
        }
        val p = Rect(w * 0.06f, h * 0.1f, w * 0.94f, h * 0.9f)
        val s = 1.4.dp.toPx()
        drawRect(CourtLine, p.topLeft, p.size, style = Stroke(s))
        drawLine(CourtLine, Offset(p.center.x, p.top), Offset(p.center.x, p.bottom), s)
        drawCircle(CourtLine, p.height * 0.2f, p.center, style = Stroke(s))
        drawCircle(CourtLine, 1.6.dp.toPx(), p.center)
        val boxW = p.width * 0.14f
        val boxH = p.height * 0.56f
        val sixW = p.width * 0.05f
        val sixH = p.height * 0.28f
        drawRect(CourtLine, Offset(p.left, p.center.y - boxH / 2), Size(boxW, boxH), style = Stroke(s))
        drawRect(CourtLine, Offset(p.right - boxW, p.center.y - boxH / 2), Size(boxW, boxH), style = Stroke(s))
        drawRect(CourtLine, Offset(p.left, p.center.y - sixH / 2), Size(sixW, sixH), style = Stroke(s))
        drawRect(CourtLine, Offset(p.right - sixW, p.center.y - sixH / 2), Size(sixW, sixH), style = Stroke(s))
        // Goals, with the net bulging back when one goes in.
        fun goal(x: Float, dir: Float, shake: Float) {
            val gh = p.height * 0.3f
            val depth = 7.dp.toPx() + 3.dp.toPx() * shake       // the net billows back
            val top = p.center.y - gh / 2
            val back = x + dir * depth
            val left = minOf(x, back)
            // Mesh, then the frame.
            drawRect(Color.White.copy(alpha = 0.18f + 0.3f * shake), Offset(left, top), Size(depth, gh))
            var yy = top + 3.dp.toPx()
            while (yy < top + gh) { drawLine(Color.White.copy(alpha = 0.45f), Offset(x, yy), Offset(back, yy), 0.7.dp.toPx()); yy += 3.dp.toPx() }
            drawLine(Color.White, Offset(x, top), Offset(back, top), 1.6.dp.toPx())
            drawLine(Color.White, Offset(back, top), Offset(back, top + gh), 1.6.dp.toPx())
            drawLine(Color.White, Offset(x, top + gh), Offset(back, top + gh), 1.6.dp.toPx())
            drawLine(Color.White, Offset(x, top), Offset(x, top + gh), 2.4.dp.toPx())   // posts on the line
            if (shake > 0f) {
                // A ring opening from the goal mouth, and the ball sitting in the net.
                drawCircle(Color.White.copy(alpha = 0.55f * shake), gh * (1.9f - 1.3f * shake), Offset(x, p.center.y), style = Stroke(2.dp.toPx() * shake))
                val inNet = Offset(x + dir * depth * 0.6f, p.center.y + gh * 0.12f)
                drawCircle(Color.White, 4.dp.toPx(), inNet)
                drawCircle(Color(0xFF111827), 4.dp.toPx(), inNet, style = Stroke(0.9.dp.toPx()))
            }
        }
        goal(p.left, -1f, awayNet.value)
        goal(p.right, 1f, homeNet.value)
        val at = Offset(pos.x * w, pos.y * h)
        drawCircle(Color.Black.copy(alpha = 0.22f), 4.6.dp.toPx(), at + Offset(1.8f, 2.6f))
        drawCircle(Color.White, 4.6.dp.toPx(), at)
        // A panel on the ball turning while play runs — the ball is alive, not a dot.
        val turn = if (running && possession != null) roll * 360f else 0f
        rotate(turn, pivot = at) {
            drawCircle(Color(0xFF111827), 1.6.dp.toPx(), at + Offset(1.6.dp.toPx(), 0f))
        }
        drawCircle(Color(0xFF111827), 4.6.dp.toPx(), at, style = Stroke(0.9.dp.toPx()))
    }
}
