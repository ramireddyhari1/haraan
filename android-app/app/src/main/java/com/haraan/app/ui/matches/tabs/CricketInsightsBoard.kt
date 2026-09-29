package com.haraan.app.ui.matches.tabs

import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.animateDpAsState
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.animation.expandVertically
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.shrinkVertically
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.gestures.detectDragGesturesAfterLongPress
import androidx.compose.foundation.gestures.detectTapGestures
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.*
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Rect
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.PathEffect
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.drawscope.rotate
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.drawText
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.rememberTextMeasurer
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil.compose.AsyncImage
import com.haraan.app.data.InningsInsight
import com.haraan.app.data.SquadMember
import com.haraan.app.data.Stand
import com.haraan.app.ui.animations.pressScale
import com.haraan.app.ui.matches.CrexColors
import com.haraan.app.ui.matches.InningsCard
import com.haraan.app.ui.matches.MatchUiState
import com.haraan.app.ui.matches.Thud
import com.haraan.app.ui.matches.cricketThud
import kotlinx.coroutines.launch
import kotlin.math.ceil
import kotlin.math.max

// ─────────────────────────────────────────────────────────────────────────────
//  THE INSIGHTS BOARD
//
//  One grammar for every section, so the tab reads as one designed page: a title a
//  player can read from arm's length, a team switch where the section is per side,
//  and ONE visual on a white card. No prose, no captions explaining the chart —
//  if a picture needs a paragraph under it, it is the wrong picture.
//
//  The visuals are cricket's own objects, drawn: a partnership between two people
//  at two ends, wickets as the way they actually fell, runs as the balls they were.
//  Every one of them answers a thumb — it lifts, it knocks, it opens.
// ─────────────────────────────────────────────────────────────────────────────

private val Ink = Color(0xFF0B1B33)
private val Muted = Color(0xFF7A8699)
private val Hair = Color(0xFFE8ECF2)
private val Track = Color(0xFFEEF1F5)
private val EndA = Color(0xFF2563EB)
private val EndB = Color(0xFF0D9488)

/** The page the cards sit on — a shade off white, so a white card is an object. */
internal val BoardPage = Color(0xFFF4F6F9)

// ── Grammar ─────────────────────────────────────────────────────────────────

@Composable
internal fun SectionHead(title: String, modifier: Modifier = Modifier) {
    Text(
        title,
        color = Ink,
        fontSize = 20.sp,
        fontWeight = FontWeight.Bold,
        letterSpacing = (-0.4).sp,
        modifier = modifier.padding(top = 8.dp, bottom = 12.dp),
    )
}

@Composable
internal fun BoardCard(
    modifier: Modifier = Modifier,
    padding: Dp = 16.dp,
    content: @Composable ColumnScope.() -> Unit,
) {
    Column(
        modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(20.dp))
            .background(Color.White)
            .border(1.dp, Hair, RoundedCornerShape(20.dp))
            .padding(padding),
        content = content,
    )
}

/**
 * A segmented switch whose thumb slides across on a spring and lands with a tick.
 * One control for every "which one" on the tab — side, chart, filter.
 */
@Composable
internal fun PillToggle(labels: List<String>, index: Int, onPick: (Int) -> Unit) {
    val ctx = LocalContext.current
    val scope = rememberCoroutineScope()
    BoxWithConstraints(
        Modifier
            .fillMaxWidth()
            .height(42.dp)
            .clip(RoundedCornerShape(21.dp))
            .background(Track)
            .padding(3.dp),
    ) {
        val w = maxWidth / labels.size.coerceAtLeast(1)
        val x by animateDpAsState(w * index, spring(dampingRatio = 0.72f, stiffness = 520f), label = "thumb")
        Box(
            Modifier
                .offset(x = x)
                .width(w)
                .fillMaxHeight()
                .shadow(2.dp, RoundedCornerShape(18.dp))
                .clip(RoundedCornerShape(18.dp))
                .background(Color.White),
        )
        Row(Modifier.fillMaxSize()) {
            labels.forEachIndexed { i, label ->
                Box(
                    Modifier
                        .weight(1f)
                        .fillMaxHeight()
                        .clickable(indication = null, interactionSource = remember { MutableInteractionSource() }) {
                            if (i != index) {
                                onPick(i)
                                scope.launch { cricketThud(ctx, Thud.TICK) }
                            }
                        },
                    contentAlignment = Alignment.Center,
                ) {
                    Text(
                        label,
                        color = if (i == index) Ink else Muted,
                        fontSize = 13.5.sp,
                        fontWeight = if (i == index) FontWeight.Bold else FontWeight.Medium,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                        modifier = Modifier.padding(horizontal = 10.dp),
                    )
                }
            }
        }
    }
}

/** A person: their photo when they have an account, their initials in the side's tint when not. */
@Composable
internal fun PlayerDot(name: String, avatar: String?, tint: Color, size: Dp = 40.dp) {
    Box(
        Modifier.size(size).clip(CircleShape).background(tint.copy(alpha = 0.14f)),
        contentAlignment = Alignment.Center,
    ) {
        Text(
            initials(name),
            color = darken(tint, 0.25f),
            fontSize = (size.value * 0.36f).sp,
            fontWeight = FontWeight.Bold,
        )
        if (!avatar.isNullOrBlank()) {
            AsyncImage(
                model = avatar,
                contentDescription = name,
                contentScale = ContentScale.Crop,
                modifier = Modifier.matchParentSize().clip(CircleShape),
            )
        }
    }
}

private fun initials(name: String): String =
    name.trim().split(Regex("\\s+")).filter { it.isNotEmpty() }.take(2)
        .joinToString("") { it.first().uppercase() }.ifBlank { "?" }

private fun avatarOf(name: String, squad: List<SquadMember>): String? =
    squad.firstOrNull { it.name.trim().equals(name.trim(), ignoreCase = true) }?.avatar

private fun sideColour(team: Int, state: MatchUiState): Color =
    if (team == 2) state.team2Color else state.team1Color

private fun sideName(team: Int, state: MatchUiState): String =
    if (team == 2) state.team2FullName.ifBlank { state.team2 } else state.team1FullName.ifBlank { state.team1 }

/** Opens on the side batting now, or last. */
@Composable
private fun rememberSide(key: String, count: Int) =
    rememberSaveable(key) { mutableIntStateOf((count - 1).coerceAtLeast(0)) }

@Composable
private fun SideSwitch(innings: List<InningsInsight>, state: MatchUiState, side: Int, onPick: (Int) -> Unit) {
    if (innings.size < 2) return
    PillToggle(innings.map { sideName(it.battingTeam, state) }, side, onPick)
    Spacer(Modifier.height(12.dp))
}

// ── 1. Match progress ───────────────────────────────────────────────────────

@Composable
internal fun MatchProgressSection(matchId: String, innings: List<InningsInsight>, state: MatchUiState) {
    var mode by rememberSaveable(matchId) { mutableIntStateOf(0) }
    var side by rememberSide("progress-$matchId", innings.size)
    val colours = innings.map { sideColour(it.battingTeam, state) }

    SectionHead("Match progress")
    BoardCard {
        PillToggle(listOf("Manhattan", "Worm", "Run rate"), mode) { mode = it }
        Spacer(Modifier.height(16.dp))
        if (mode == 0 && innings.size > 1) {
            SideLegend(innings, state, colours, side) { side = it }
            Spacer(Modifier.height(12.dp))
        } else if (mode != 0) {
            SideLegend(innings, state, colours, -1) {}
            Spacer(Modifier.height(12.dp))
        }
        when (mode) {
            0 -> innings.getOrNull(side)?.let { OverSkyline(it, colours[side]) }
            1 -> OverLines(
                innings.map { inn -> inn.progress.map { it.total.toFloat() } },
                innings.map { inn -> inn.progress.map { it.wickets } },
                colours,
                decimals = false,
            )
            else -> OverLines(
                innings.map { inn -> inn.progress.map { it.total.toFloat() / it.over.coerceAtLeast(1) } },
                innings.map { inn -> inn.progress.map { it.wickets } },
                colours,
                decimals = true,
            )
        }
    }
}

/** The sides as a legend — and, where the chart is per side, as the switch. */
@Composable
private fun SideLegend(
    innings: List<InningsInsight>,
    state: MatchUiState,
    colours: List<Color>,
    selected: Int,
    onPick: (Int) -> Unit,
) {
    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
        innings.forEachIndexed { i, inn ->
            val on = selected < 0 || selected == i
            val source = remember { MutableInteractionSource() }
            Row(
                Modifier
                    .pressScale(source)
                    .clip(RoundedCornerShape(14.dp))
                    .background(if (selected == i) colours[i].copy(alpha = 0.1f) else Color.Transparent)
                    .clickable(interactionSource = source, indication = null) { onPick(i) }
                    .padding(horizontal = 10.dp, vertical = 6.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Box(Modifier.size(9.dp).clip(CircleShape).background(colours[i].copy(alpha = if (on) 1f else 0.35f)))
                Spacer(Modifier.width(7.dp))
                Text(
                    "${sideName(inn.battingTeam, state)}  ${inn.runs}/${inn.wickets}",
                    color = if (on) Ink else Muted,
                    fontSize = 12.5.sp,
                    fontWeight = if (selected == i) FontWeight.Bold else FontWeight.Medium,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                )
            }
        }
    }
}

private fun niceCeil(v: Float, steps: Int = 4): Float {
    if (v <= 0f) return steps.toFloat()
    val raw = v / steps
    val mag = Math.pow(10.0, kotlin.math.floor(kotlin.math.log10(raw.toDouble()))).toFloat()
    val unit = listOf(1f, 2f, 2.5f, 5f, 10f).map { it * mag }.first { it >= raw }
    return unit * steps
}

/**
 * Runs per over as a skyline. Wickets stand above their over as knocked stumps; touch or
 * slide along the chart and each over lifts in turn, ticking under your thumb, with its
 * balls laid out beneath.
 */
@Composable
private fun OverSkyline(inn: InningsInsight, colour: Color) {
    val overs = inn.progress
    if (overs.isEmpty()) {
        Text("No overs bowled yet", color = Muted, fontSize = 13.sp, modifier = Modifier.padding(vertical = 24.dp))
        return
    }
    val ctx = LocalContext.current
    val scope = rememberCoroutineScope()
    val measurer = rememberTextMeasurer()
    val grow = remember(inn.battingName, overs.size) { Animatable(0f) }
    LaunchedEffect(inn.battingName, overs.size) {
        grow.animateTo(1f, spring(dampingRatio = 0.62f, stiffness = 90f))
    }
    val opening = if (inn.bestOverNumber > 0) inn.bestOverNumber else overs.last().over
    var selected by remember(inn.battingName, overs.size) { mutableIntStateOf(opening) }
    val lift = remember { Animatable(1f) }
    val slots = max(overs.size, 6)
    val top = niceCeil(overs.maxOf { it.runs }.toFloat().coerceAtLeast(4f))

    fun pick(x: Float, left: Float, width: Float, feel: Thud) {
        val i = (((x - left) / width) * slots).toInt()
        val o = overs.getOrNull(i) ?: return
        if (o.over == selected) return
        selected = o.over
        scope.launch { cricketThud(ctx, feel) }
        scope.launch {
            lift.snapTo(0f)
            lift.animateTo(1f, spring(dampingRatio = 0.4f, stiffness = 500f))
        }
    }

    val axisW = with(androidx.compose.ui.platform.LocalDensity.current) { 26.dp.toPx() }
    Canvas(
        Modifier
            .fillMaxWidth()
            .height(190.dp)
            .pointerInput(overs.size) {
                detectTapGestures { p -> pick(p.x, axisW, size.width - axisW, Thud.RUN) }
            }
            .pointerInput(overs.size) {
                detectDragGesturesAfterLongPress(
                    onDragStart = { p -> pick(p.x, axisW, size.width - axisW, Thud.RUN) },
                    onDrag = { change, _ ->
                        change.consume()
                        pick(change.position.x, axisW, size.width - axisW, Thud.TICK)
                    },
                )
            },
    ) {
        val bottom = size.height - 22.dp.toPx()
        val chartTop = 22.dp.toPx()
        val width = size.width - axisW
        val labelStyle = TextStyle(color = Muted, fontSize = 10.sp)

        // Grid, labelled on the left.
        for (k in 0..4) {
            val v = top * k / 4f
            val y = bottom - (bottom - chartTop) * (k / 4f)
            drawLine(
                if (k == 0) Hair.copy(alpha = 1f) else Hair,
                Offset(axisW, y), Offset(size.width, y), 1.dp.toPx(),
                pathEffect = if (k == 0) null else PathEffect.dashPathEffect(floatArrayOf(4.dp.toPx(), 4.dp.toPx())),
            )
            val t = measurer.measure("${v.toInt()}", labelStyle)
            drawText(t, topLeft = Offset(axisW - t.size.width - 6.dp.toPx(), y - t.size.height / 2f))
        }

        val slotW = width / slots
        val barW = (slotW * 0.56f).coerceAtMost(22.dp.toPx())
        val every = (slots / 7).coerceAtLeast(1)
        overs.forEachIndexed { i, o ->
            val cx = axisW + slotW * (i + 0.5f)
            val isSel = o.over == selected
            val h = (bottom - chartTop) * (o.runs / top) * grow.value
            val w = barW * (if (isSel) 1f + 0.16f * lift.value else 1f)
            val fill = if (isSel) colour else colour.copy(alpha = 0.42f)
            if (h > 0f) {
                drawRoundRect(
                    Brush.verticalGradient(listOf(lighten(fill, 0.2f), fill), startY = bottom - h, endY = bottom),
                    Offset(cx - w / 2f, bottom - h),
                    Size(w, h),
                    CornerRadius(4.dp.toPx(), 4.dp.toPx()),
                )
            }
            if (o.wickets > 0) {
                val sw = 9.dp.toPx()
                val sh = 13.dp.toPx()
                repeat(o.wickets.coerceAtMost(3)) { k ->
                    val y = bottom - h - sh - 5.dp.toPx() - k * (sh + 3.dp.toPx())
                    drawStumps(
                        Rect(Offset(cx - sw / 2f, y), Size(sw, sh)),
                        knocked = grow.value.coerceIn(0f, 1f),
                        wood = Color(0xFFFDECEC),
                        edge = CrexColors.WicketBall,
                    )
                }
            }
            if (isSel && o.wickets == 0) {
                val t = measurer.measure("${o.runs}", TextStyle(color = Ink, fontSize = 11.sp, fontWeight = FontWeight.Bold))
                drawText(t, topLeft = Offset(cx - t.size.width / 2f, bottom - h - t.size.height - 3.dp.toPx()))
            }
            if (i == 0 || i == overs.lastIndex || o.over % every == 0) {
                val t = measurer.measure("${o.over}", labelStyle.copy(color = if (isSel) Ink else Muted))
                drawText(t, topLeft = Offset(cx - t.size.width / 2f, bottom + 6.dp.toPx()))
            }
        }
    }

    overs.firstOrNull { it.over == selected }?.let { o ->
        Spacer(Modifier.height(12.dp))
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text("Over ${o.over}", color = Ink, fontSize = 14.sp, fontWeight = FontWeight.Bold)
            Spacer(Modifier.width(8.dp))
            Text(
                "${o.runs} run${if (o.runs == 1) "" else "s"}" + if (o.wickets > 0) " · ${o.wickets} wkt" else "",
                color = if (o.wickets > 0) CrexColors.WicketBall else Muted,
                fontSize = 13.sp,
            )
        }
        if (o.balls.isNotEmpty()) {
            Spacer(Modifier.height(10.dp))
            Row(
                Modifier.horizontalScroll(rememberScrollState()).padding(vertical = 3.dp, horizontal = 1.dp),
                horizontalArrangement = Arrangement.spacedBy(6.dp),
            ) { o.balls.forEach { BallChip(it, 30.dp) } }
        }
    }
}

/** Both innings as lines over the same overs, wickets ringed where they fell. */
@Composable
private fun OverLines(
    series: List<List<Float>>,
    wickets: List<List<Int>>,
    colours: List<Color>,
    decimals: Boolean,
) {
    val points = series.maxOfOrNull { it.size } ?: 0
    if (points == 0) {
        Text("No overs bowled yet", color = Muted, fontSize = 13.sp, modifier = Modifier.padding(vertical = 24.dp))
        return
    }
    val measurer = rememberTextMeasurer()
    val draw = remember(series.map { it.size }) { Animatable(0f) }
    LaunchedEffect(series.map { it.size }) { draw.animateTo(1f, tween(700)) }
    val top = niceCeil(series.flatten().maxOrNull()?.coerceAtLeast(4f) ?: 4f)

    Canvas(Modifier.fillMaxWidth().height(190.dp)) {
        val axisW = 26.dp.toPx()
        val bottom = size.height - 22.dp.toPx()
        val chartTop = 10.dp.toPx()
        val width = size.width - axisW - 8.dp.toPx()
        val labelStyle = TextStyle(color = Muted, fontSize = 10.sp)
        for (k in 0..4) {
            val v = top * k / 4f
            val y = bottom - (bottom - chartTop) * (k / 4f)
            drawLine(
                Hair, Offset(axisW, y), Offset(size.width, y), 1.dp.toPx(),
                pathEffect = if (k == 0) null else PathEffect.dashPathEffect(floatArrayOf(4.dp.toPx(), 4.dp.toPx())),
            )
            val label = if (decimals) String.format("%.1f", v) else "${v.toInt()}"
            val t = measurer.measure(label, labelStyle)
            drawText(t, topLeft = Offset(axisW - t.size.width - 6.dp.toPx(), y - t.size.height / 2f))
        }
        val stepX = if (points > 1) width / (points - 1) else width
        fun at(i: Int, v: Float) = Offset(axisW + 4.dp.toPx() + stepX * i, bottom - (bottom - chartTop) * (v / top))

        series.forEachIndexed { s, values ->
            if (values.isEmpty()) return@forEachIndexed
            val colour = colours.getOrElse(s) { EndA }
            val shown = ((values.size - 1) * draw.value)
            val path = Path()
            values.forEachIndexed { i, v ->
                if (i > shown + 0.001f) return@forEachIndexed
                val p = at(i, v)
                if (i == 0) path.moveTo(p.x, p.y) else path.lineTo(p.x, p.y)
            }
            drawPath(path, colour, style = Stroke(2.6.dp.toPx(), cap = StrokeCap.Round))
            values.forEachIndexed { i, v ->
                if (i > shown + 0.001f) return@forEachIndexed
                val p = at(i, v)
                val w = wickets.getOrNull(s)?.getOrNull(i) ?: 0
                if (w > 0) {
                    drawCircle(Color.White, 6.dp.toPx(), p)
                    drawCircle(CrexColors.WicketBall, 6.dp.toPx(), p, style = Stroke(2.dp.toPx()))
                    if (w > 1) {
                        val t = measurer.measure("$w", TextStyle(color = CrexColors.WicketBall, fontSize = 8.sp, fontWeight = FontWeight.Bold))
                        drawText(t, topLeft = p - Offset(t.size.width / 2f, t.size.height / 2f))
                    }
                } else {
                    drawCircle(colour, 2.6.dp.toPx(), p)
                }
            }
        }
        val every = (points / 7).coerceAtLeast(1)
        for (i in 0 until points) {
            if (i == 0 || i == points - 1 || (i + 1) % every == 0) {
                val t = measurer.measure("${i + 1}", labelStyle)
                drawText(t, topLeft = Offset(at(i, 0f).x - t.size.width / 2f, bottom + 6.dp.toPx()))
            }
        }
    }
}

// ── 2. Partnerships ─────────────────────────────────────────────────────────

@Composable
internal fun PartnershipsSection(matchId: String, innings: List<InningsInsight>, state: MatchUiState) {
    if (innings.none { it.partnerships.isNotEmpty() }) return
    var side by rememberSide("stands-$matchId", innings.size)
    val inn = innings.getOrNull(side) ?: return
    val squad = if (inn.battingTeam == 2) state.awaySquad else state.homeSquad
    val worth = inn.partnerships.filter { it.runs > 0 || it.balls > 2 }
    val peak = (worth.maxOfOrNull { it.runs } ?: 1).coerceAtLeast(1)
    var open by remember(inn.battingName) { mutableIntStateOf(-1) }

    SectionHead("Partnerships")
    SideSwitch(innings, state, side) { side = it; open = -1 }
    BoardCard(padding = 0.dp) {
        if (worth.isEmpty()) {
            Text("No stand has scored yet", color = Muted, fontSize = 13.sp, modifier = Modifier.padding(16.dp))
        }
        worth.forEachIndexed { i, st ->
            if (i > 0) Box(Modifier.fillMaxWidth().padding(horizontal = 16.dp).height(1.dp).background(Hair))
            StandRow(st, peak, squad, open == i) { open = if (open == i) -1 else i }
        }
    }
}

@Composable
private fun StandRow(st: Stand, peak: Int, squad: List<SquadMember>, open: Boolean, onToggle: () -> Unit) {
    val ctx = LocalContext.current
    val scope = rememberCoroutineScope()
    val source = remember { MutableInteractionSource() }
    val names = st.batters.split(" & ").map { it.trim() }
    val a = st.split.getOrNull(0) ?: names.getOrNull(0)?.let { com.haraan.app.data.StandShare(it, -1, -1) }
    val b = st.split.getOrNull(1) ?: names.getOrNull(1)?.let { com.haraan.app.data.StandShare(it, -1, -1) }
    val grow = remember(st.runs) { Animatable(0f) }
    LaunchedEffect(st.runs) { grow.animateTo(1f, spring(dampingRatio = 0.7f, stiffness = 80f)) }

    Column(
        Modifier
            .fillMaxWidth()
            .pressScale(source)
            .clickable(interactionSource = source, indication = null) {
                onToggle()
                scope.launch { cricketThud(ctx, if (st.unbroken) Thud.RUN else Thud.WICKET) }
            }
            .padding(horizontal = 16.dp, vertical = 14.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Row(Modifier.weight(1f), verticalAlignment = Alignment.CenterVertically) {
                a?.let { PlayerDot(it.name, avatarOf(it.name, squad), EndA, 36.dp) }
                Spacer(Modifier.width(9.dp))
                a?.let { ShareText(it, Alignment.Start) }
            }
            Column(Modifier.width(64.dp), horizontalAlignment = Alignment.CenterHorizontally) {
                Text(
                    "${st.runs}",
                    color = Ink,
                    fontSize = 20.sp,
                    fontFamily = com.haraan.app.theme.ArchivoDisplay,
                    style = TextStyle(fontFeatureSettings = "tnum"),
                )
                Text(
                    if (st.unbroken) "(${st.balls})*" else "(${st.balls})",
                    color = Muted,
                    fontSize = 11.5.sp,
                    style = TextStyle(fontFeatureSettings = "tnum"),
                )
            }
            Row(Modifier.weight(1f), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.End) {
                b?.let { ShareText(it, Alignment.End) }
                Spacer(Modifier.width(9.dp))
                b?.let { PlayerDot(it.name, avatarOf(it.name, squad), EndB, 36.dp) }
            }
        }
        Spacer(Modifier.height(12.dp))
        // Who carried it: the stand grows out from the middle, each batter's runs toward
        // their own end, measured against the biggest stand of the innings.
        Canvas(Modifier.fillMaxWidth().height(6.dp)) {
            val mid = size.width / 2f
            val half = size.width / 2f
            val h = size.height
            drawRoundRect(Track, Offset.Zero, size, CornerRadius(h / 2f))
            val g = grow.value
            val aRuns = a?.runs ?: -1
            val bRuns = b?.runs ?: -1
            if (aRuns >= 0 && bRuns >= 0) {
                val la = half * (aRuns / peak.toFloat()) * g
                val lb = half * (bRuns / peak.toFloat()) * g
                drawRoundRect(EndA, Offset(mid - la, 0f), Size(la, h), CornerRadius(h / 2f))
                drawRoundRect(EndB, Offset(mid, 0f), Size(lb, h), CornerRadius(h / 2f))
            } else {
                val l = half * (st.runs / peak.toFloat()) * g
                drawRoundRect(Ink, Offset(mid - l, 0f), Size(l * 2f, h), CornerRadius(h / 2f))
            }
        }
        AnimatedVisibility(
            visible = open && st.start != null && st.end != null,
            enter = fadeIn(tween(160)) + expandVertically(spring(dampingRatio = 0.8f, stiffness = 420f)),
            exit = fadeOut(tween(100)) + shrinkVertically(tween(160)),
        ) {
            val s = st.start
            val e = st.end
            if (s != null && e != null) StandJourney(s, e, st.unbroken)
        }
    }
}

@Composable
private fun ShareText(share: com.haraan.app.data.StandShare, align: Alignment.Horizontal) {
    Column(horizontalAlignment = align) {
        Text(
            share.name,
            color = Ink,
            fontSize = 14.sp,
            fontWeight = FontWeight.SemiBold,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
        )
        if (share.runs >= 0) {
            Text(
                "${share.runs} (${share.balls})",
                color = Muted,
                fontSize = 12.5.sp,
                style = TextStyle(fontFeatureSettings = "tnum"),
            )
        }
    }
}

/** Where the innings was when the pair came together, and when they were parted. */
@Composable
private fun StandJourney(start: com.haraan.app.data.ScoreAt, end: com.haraan.app.data.ScoreAt, unbroken: Boolean) {
    val knock = remember { Animatable(0f) }
    LaunchedEffect(Unit) { knock.animateTo(1f, spring(dampingRatio = 0.5f, stiffness = 160f)) }
    Row(Modifier.fillMaxWidth().padding(top = 14.dp), verticalAlignment = Alignment.CenterVertically) {
        ScoreStop("Start", start, Alignment.Start, Modifier.weight(1f))
        Canvas(Modifier.width(70.dp).height(34.dp)) {
            val h = 8.dp.toPx()
            val y = size.height - h - 2.dp.toPx()
            val stripW = size.width - 14.dp.toPx()
            drawRoundRect(
                Brush.verticalGradient(listOf(lighten(ClayStrip, 0.3f), ClayStrip), startY = y, endY = y + h),
                Offset(0f, y), Size(stripW * knock.value.coerceIn(0f, 1f), h), CornerRadius(h / 2f),
            )
            drawStumps(
                Rect(Offset(stripW + 2.dp.toPx(), size.height - 26.dp.toPx()), Size(11.dp.toPx(), 24.dp.toPx())),
                knocked = if (unbroken) 0f else knock.value.coerceIn(0f, 1f),
            )
        }
        ScoreStop(if (unbroken) "Now" else "End", end, Alignment.End, Modifier.weight(1f))
    }
}

@Composable
private fun ScoreStop(label: String, at: com.haraan.app.data.ScoreAt, align: Alignment.Horizontal, modifier: Modifier) {
    Column(
        modifier.clip(RoundedCornerShape(12.dp)).background(Track).padding(horizontal = 12.dp, vertical = 9.dp),
        horizontalAlignment = align,
    ) {
        Text(label, color = Muted, fontSize = 11.sp)
        Row(verticalAlignment = Alignment.Bottom) {
            Text(
                "${at.runs}/${at.wickets}",
                color = Ink,
                fontSize = 17.sp,
                fontWeight = FontWeight.Bold,
                style = TextStyle(fontFeatureSettings = "tnum"),
            )
            Text(" ${at.overs} ov", color = Muted, fontSize = 11.5.sp, modifier = Modifier.padding(bottom = 2.dp))
        }
    }
}

// ── 3. How the wickets fell ─────────────────────────────────────────────────

internal enum class HowOut(val label: String, val tint: Color) {
    BOWLED("Bowled", Color(0xFFDC2626)),
    CAUGHT("Caught", Color(0xFF0D9488)),
    CAUGHT_BOWLED("Caught and bowled", Color(0xFF0E7490)),
    LBW("LBW", Color(0xFF7C3AED)),
    RUN_OUT("Run out", Color(0xFFEA580C)),
    STUMPED("Stumped", Color(0xFF2563EB)),
    HIT_WICKET("Hit wicket", Color(0xFFB45309)),
    RETIRED("Retired out", Color(0xFF64748B)),
    OTHER("Out", Color(0xFF64748B)),
}

/** Read from the scorecard's own dismissal line — "c Ravi b Imran", "lbw b Imran", "run out". */
internal fun howOut(dismissal: String): HowOut? {
    val s = dismissal.trim().lowercase()
    return when {
        s.isEmpty() || s == "not out" || s.startsWith("retired hurt") -> null
        s.startsWith("c & b") || s.startsWith("c&b") -> HowOut.CAUGHT_BOWLED
        s.startsWith("c ") || s == "caught" -> HowOut.CAUGHT
        s.startsWith("lbw") -> HowOut.LBW
        s.startsWith("run out") -> HowOut.RUN_OUT
        s.startsWith("st ") || s == "st" || s.startsWith("stumped") -> HowOut.STUMPED
        s.startsWith("hit wicket") -> HowOut.HIT_WICKET
        s.startsWith("retired") -> HowOut.RETIRED
        s.startsWith("b ") || s == "bowled" -> HowOut.BOWLED
        else -> HowOut.OTHER
    }
}

@Composable
internal fun WicketsSection(matchId: String, cards: List<InningsCard>, state: MatchUiState) {
    val withOuts = cards.filter { c -> c.batters.any { it.out } }
    if (withOuts.isEmpty()) return
    var side by rememberSaveable("wkts-$matchId") { mutableIntStateOf(withOuts.lastIndex) }
    val card = withOuts.getOrNull(side) ?: return
    val groups = card.batters.filter { it.out }
        .mapNotNull { b -> howOut(b.dismissal)?.let { it to b.name } }
        .groupBy({ it.first }, { it.second })
        .toList()
        .sortedByDescending { it.second.size }
    val total = groups.sumOf { it.second.size }

    SectionHead("How the wickets fell")
    if (withOuts.size > 1) {
        PillToggle(withOuts.map { sideName(it.battingTeam, state) }, side) { side = it }
        Spacer(Modifier.height(12.dp))
    }
    BoardCard {
        // The whole innings' wickets as one bar, in the colours of how they fell.
        Row(Modifier.fillMaxWidth().height(8.dp).clip(RoundedCornerShape(4.dp))) {
            groups.forEachIndexed { i, (how, names) ->
                Box(
                    Modifier
                        .weight(names.size.toFloat())
                        .fillMaxHeight()
                        .padding(end = if (i == groups.lastIndex) 0.dp else 2.dp)
                        .background(how.tint),
                )
            }
        }
        Spacer(Modifier.height(6.dp))
        groups.forEachIndexed { i, (how, names) ->
            if (i > 0) Box(Modifier.fillMaxWidth().height(1.dp).background(Hair))
            HowOutRow(how, names, total)
        }
    }
}

@Composable
private fun HowOutRow(how: HowOut, names: List<String>, total: Int) {
    val ctx = LocalContext.current
    val scope = rememberCoroutineScope()
    val source = remember { MutableInteractionSource() }
    val play = remember(how, names.size) { Animatable(0f) }
    LaunchedEffect(how, names.size) { play.animateTo(1f, tween(750)) }
    Row(
        Modifier
            .fillMaxWidth()
            .pressScale(source)
            .clickable(interactionSource = source, indication = null) {
                scope.launch { cricketThud(ctx, Thud.WICKET) }
                scope.launch { play.snapTo(0f); play.animateTo(1f, tween(750)) }
            }
            .padding(vertical = 12.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(
            Modifier.size(56.dp).clip(RoundedCornerShape(16.dp)).background(how.tint.copy(alpha = 0.08f)),
            contentAlignment = Alignment.Center,
        ) {
            Canvas(Modifier.size(46.dp)) { drawHowOut(how, play.value) }
        }
        Spacer(Modifier.width(14.dp))
        Column(Modifier.weight(1f)) {
            Text(how.label, color = Ink, fontSize = 15.sp, fontWeight = FontWeight.SemiBold)
            Spacer(Modifier.height(2.dp))
            Text(
                names.joinToString(", "),
                color = Muted,
                fontSize = 12.5.sp,
                lineHeight = 17.sp,
                maxLines = 2,
                overflow = TextOverflow.Ellipsis,
            )
        }
        Spacer(Modifier.width(10.dp))
        Column(horizontalAlignment = Alignment.End) {
            Text(
                "${names.size}",
                color = how.tint,
                fontSize = 24.sp,
                fontFamily = com.haraan.app.theme.ArchivoDisplay,
                style = TextStyle(fontFeatureSettings = "tnum"),
            )
            Text("of $total", color = Muted, fontSize = 11.sp)
        }
    }
}

private val Glove = Color(0xFFF8FAFC)
private val GloveEdge = Color(0xFF94A3B8)
private val GlovePalm = Color(0xFFD9BE8F)

/** A keeper's glove, fingers up, in [box]. */
private fun DrawScope.drawGlove(box: Rect) {
    val stroke = Stroke(1.2.dp.toPx())
    val fingerW = box.width * 0.17f
    val palmTop = box.top + box.height * 0.42f
    for (k in 0 until 4) {
        val x = box.left + box.width * 0.12f + k * (fingerW + box.width * 0.03f)
        val tip = box.top + box.height * (if (k == 1 || k == 2) 0.02f else 0.1f)
        drawRoundRect(Glove, Offset(x, tip), Size(fingerW, palmTop - tip + fingerW), CornerRadius(fingerW / 2f))
        drawRoundRect(GloveEdge, Offset(x, tip), Size(fingerW, palmTop - tip + fingerW), CornerRadius(fingerW / 2f), style = stroke)
    }
    val palm = Rect(Offset(box.left + box.width * 0.08f, palmTop), Size(box.width * 0.8f, box.height * 0.5f))
    drawRoundRect(Glove, palm.topLeft, palm.size, CornerRadius(box.width * 0.2f))
    drawRoundRect(GloveEdge, palm.topLeft, palm.size, CornerRadius(box.width * 0.2f), style = stroke)
    drawRoundRect(
        GlovePalm,
        Offset(palm.left + palm.width * 0.18f, palm.top + palm.height * 0.15f),
        Size(palm.width * 0.64f, palm.height * 0.45f),
        CornerRadius(palm.width * 0.2f),
    )
    rotate(-38f, Offset(palm.right - palm.width * 0.05f, palm.top + palm.height * 0.45f)) {
        drawRoundRect(Glove, Offset(palm.right - fingerW, palm.top), Size(fingerW * 1.1f, box.height * 0.36f), CornerRadius(fingerW / 2f))
        drawRoundRect(GloveEdge, Offset(palm.right - fingerW, palm.top), Size(fingerW * 1.1f, box.height * 0.36f), CornerRadius(fingerW / 2f), style = stroke)
    }
}

/** A batting pad, cane ribs and knee roll. */
private fun DrawScope.drawPad(box: Rect) {
    val stroke = Stroke(1.2.dp.toPx())
    drawRoundRect(Glove, box.topLeft, box.size, CornerRadius(box.width * 0.35f))
    drawRoundRect(GloveEdge, box.topLeft, box.size, CornerRadius(box.width * 0.35f), style = stroke)
    for (k in 1..3) {
        val x = box.left + box.width * k / 4f
        drawLine(GloveEdge.copy(alpha = 0.7f), Offset(x, box.top + box.height * 0.32f), Offset(x, box.bottom - box.height * 0.08f), 1.dp.toPx())
    }
    drawLine(GloveEdge, Offset(box.left + 2f, box.top + box.height * 0.28f), Offset(box.right - 2f, box.top + box.height * 0.28f), 1.2.dp.toPx())
}

/**
 * Each way out, drawn as the moment itself. [t] plays it: the ball travels, the bails go.
 */
internal fun DrawScope.drawHowOut(how: HowOut, t: Float) {
    val w = size.width
    val h = size.height
    val ground = h * 0.94f
    val r = w * 0.085f
    val stumps = Rect(Offset(w * 0.58f, h * 0.34f), Size(w * 0.24f, ground - h * 0.34f))
    val hit = ((t - 0.55f) / 0.45f).coerceIn(0f, 1f)
    val travel = (t / 0.55f).coerceIn(0f, 1f)
    drawLine(Color(0xFFCBD5E1), Offset(0f, ground), Offset(w, ground), 1.2.dp.toPx())

    when (how) {
        HowOut.BOWLED -> {
            drawStumps(stumps, knocked = hit)
            val bx = w * 0.06f + (stumps.left - r - w * 0.06f) * travel
            drawLeatherBall(Offset(bx, ground - r - 1f), r, LeatherRed, shadowAt = Offset(bx, ground - r - 1f))
        }
        HowOut.CAUGHT, HowOut.CAUGHT_BOWLED -> {
            val glove = Rect(Offset(w * 0.5f, h * 0.36f), Size(w * 0.44f, h * 0.56f))
            drawGlove(glove)
            val u = travel
            val from = Offset(w * 0.08f, h * 0.2f)
            val to = Offset(glove.center.x, glove.top + glove.height * 0.62f)
            val x = from.x + (to.x - from.x) * u
            val y = from.y + (to.y - from.y) * u - h * 0.28f * 4f * u * (1f - u)
            drawLeatherBall(Offset(x, y), r, LeatherRed)
        }
        HowOut.LBW -> {
            drawStumps(Rect(Offset(w * 0.7f, h * 0.4f), Size(w * 0.2f, ground - h * 0.4f)), wood = Color(0xFFF1F5F9), edge = Color(0xFFCBD5E1))
            drawPad(Rect(Offset(w * 0.46f, h * 0.18f), Size(w * 0.24f, ground - h * 0.2f)))
            val bx = w * 0.04f + (w * 0.46f - r - w * 0.04f) * travel
            drawLeatherBall(Offset(bx, h * 0.62f), r, LeatherRed)
        }
        HowOut.RUN_OUT -> {
            drawStumps(stumps, knocked = hit)
            val crease = stumps.left - w * 0.1f
            drawLine(Color.White, Offset(crease, ground - h * 0.02f), Offset(crease, ground - h * 0.3f), 2.dp.toPx())
            drawLine(Color(0xFF94A3B8), Offset(crease, ground - h * 0.02f), Offset(crease, ground - h * 0.3f), 1.dp.toPx())
            // The bat, reaching and falling just short.
            val reach = w * 0.02f + (crease - w * 0.06f - w * 0.5f) * travel
            drawBat(Offset(reach, ground - h * 0.1f), w * 0.5f, -90f, Color(0xFF334155))
        }
        HowOut.STUMPED -> {
            drawStumps(stumps, knocked = hit)
            drawGlove(Rect(Offset(stumps.left - w * 0.46f + w * 0.12f * travel, h * 0.42f), Size(w * 0.36f, h * 0.46f)))
        }
        HowOut.HIT_WICKET -> {
            drawStumps(stumps, knocked = hit)
            drawBat(Offset(w * 0.22f, h * 0.1f), h * 0.8f, -28f * travel, Color(0xFF334155))
        }
        HowOut.RETIRED, HowOut.OTHER -> drawStumps(stumps, knocked = hit)
    }
}

// ── 4. Scoring breakdown ────────────────────────────────────────────────────

@Composable
internal fun ScoringSection(matchId: String, innings: List<InningsInsight>, state: MatchUiState) {
    var side by rememberSide("scoring-$matchId", innings.size)
    val inn = innings.getOrNull(side) ?: return
    val b = inn.breakdown
    data class Kind(val label: String, val count: Int, val colour: Color, val thud: Thud)
    val kinds = listOf(
        Kind("Dots", b.dots, Color(0xFFB8C2CF), Thud.TICK),
        Kind("1s", b.ones, Color(0xFF475569), Thud.RUN),
        Kind("2s", b.twos, Color(0xFF64748B), Thud.RUN),
        Kind("3s", b.threes, Color(0xFF94A3B8), Thud.RUN),
        Kind("4s", b.fours, CrexColors.FourBall, Thud.FOUR),
        Kind("6s", b.sixes, CrexColors.SixBall, Thud.SIX),
        Kind("Extras", b.extras, Color(0xFFCBD5E1), Thud.EXTRA),
    )
    val peak = (kinds.maxOf { it.count }).coerceAtLeast(1)
    val ctx = LocalContext.current
    val scope = rememberCoroutineScope()
    val grow = remember(inn.battingName, b) { Animatable(0f) }
    LaunchedEffect(inn.battingName, b) { grow.animateTo(1f, spring(dampingRatio = 0.6f, stiffness = 90f)) }
    var lifted by remember(inn.battingName) { mutableIntStateOf(-1) }
    val hop = remember { Animatable(0f) }

    SectionHead("Scoring breakdown")
    SideSwitch(innings, state, side) { side = it }
    BoardCard {
        Row(Modifier.fillMaxWidth().height(170.dp), verticalAlignment = Alignment.Bottom) {
            kinds.forEachIndexed { i, k ->
                val source = remember { MutableInteractionSource() }
                Column(
                    Modifier
                        .weight(1f)
                        .fillMaxHeight()
                        .clickable(interactionSource = source, indication = null) {
                            lifted = i
                            scope.launch { cricketThud(ctx, k.thud) }
                            scope.launch {
                                hop.snapTo(0f)
                                hop.animateTo(1f, tween(90))
                                hop.animateTo(0f, spring(dampingRatio = 0.4f, stiffness = 420f))
                            }
                        },
                    horizontalAlignment = Alignment.CenterHorizontally,
                    verticalArrangement = Arrangement.Bottom,
                ) {
                    val up = if (lifted == i) hop.value * 8f else 0f
                    Text(
                        "${k.count}",
                        color = if (k.count == 0) Muted else Ink,
                        fontSize = 13.sp,
                        fontWeight = FontWeight.Bold,
                        style = TextStyle(fontFeatureSettings = "tnum"),
                        modifier = Modifier.offset(y = (-up).dp),
                    )
                    Spacer(Modifier.height(4.dp))
                    Box(
                        Modifier
                            .offset(y = (-up).dp)
                            .fillMaxWidth(0.62f)
                            .fillMaxHeight((0.78f * k.count / peak * grow.value).coerceIn(0.012f, 0.78f))
                            .clip(RoundedCornerShape(topStart = 6.dp, topEnd = 6.dp))
                            .background(Brush.verticalGradient(listOf(lighten(k.colour, 0.2f), k.colour))),
                    )
                }
            }
        }
        Box(Modifier.fillMaxWidth().height(1.dp).background(Hair))
        Spacer(Modifier.height(8.dp))
        Row(Modifier.fillMaxWidth()) {
            kinds.forEach { k ->
                Text(
                    k.label,
                    color = Muted,
                    fontSize = 11.sp,
                    maxLines = 1,
                    modifier = Modifier.weight(1f),
                    textAlign = androidx.compose.ui.text.style.TextAlign.Center,
                )
            }
        }
    }
}

// ── Shots ───────────────────────────────────────────────────────────────────

/**
 * Every stroke the scorer named, drawn as the batter playing it.
 *
 * The biggest stroke leads at full width; the rest sit two to a row. Each figure opens in
 * stance and swings into its shot as it arrives, and plays it again under your thumb with
 * the knock that stroke's best ball made — twice for a six, once for a four.
 */
@Composable
internal fun ShotsSection(matchId: String, innings: List<InningsInsight>, state: MatchUiState) {
    var side by rememberSide("shots-$matchId", innings.size)
    val inn = innings.getOrNull(side) ?: return
    val strokes = inn.shotTypes.mapNotNull { s -> com.haraan.app.ui.matches.ShotKind.of(s.type)?.let { it to s } }
    val colour = sideColour(inn.battingTeam, state)

    SectionHead("Shots")
    SideSwitch(innings, state, side) { side = it }
    if (strokes.isEmpty()) {
        BoardCard {
            Row(verticalAlignment = Alignment.CenterVertically) {
                com.haraan.app.ui.matches.ShotFigure(
                    com.haraan.app.ui.matches.ShotKind.COVER_DRIVE, Color(0xFFB8C2CF), Modifier.size(64.dp),
                )
                Spacer(Modifier.width(14.dp))
                Column(Modifier.weight(1f)) {
                    Text("No shots named yet", color = Ink, fontSize = 14.sp, fontWeight = FontWeight.Bold)
                    Spacer(Modifier.height(3.dp))
                    Text(
                        "The scorer names the stroke behind each four and six.",
                        color = Muted, fontSize = 12.sp, lineHeight = 17.sp,
                    )
                }
            }
        }
        return
    }
    val totalRuns = inn.runs.coerceAtLeast(1)
    val (topKind, top) = strokes.first()
    StrokeCard(topKind, top, colour, featured = true, share = top.runs * 100 / totalRuns)
    val rest = strokes.drop(1)
    rest.chunked(2).forEach { pair ->
        Spacer(Modifier.height(10.dp))
        Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
            pair.forEach { (kind, stat) ->
                StrokeCard(kind, stat, colour, featured = false, share = 0, modifier = Modifier.weight(1f))
            }
            if (pair.size == 1) Spacer(Modifier.weight(1f))
        }
    }
}

@Composable
private fun StrokeCard(
    kind: com.haraan.app.ui.matches.ShotKind,
    stat: com.haraan.app.data.ShotTypeStat,
    colour: Color,
    featured: Boolean,
    share: Int,
    modifier: Modifier = Modifier,
) {
    val ctx = LocalContext.current
    val scope = rememberCoroutineScope()
    val source = remember { MutableInteractionSource() }
    var replay by remember { mutableIntStateOf(0) }
    val ink = if (featured) colour else Color(0xFF64748B)
    Box(
        modifier
            .fillMaxWidth()
            .pressScale(source)
            .clip(RoundedCornerShape(18.dp))
            .background(Color.White)
            .border(1.dp, Hair, RoundedCornerShape(18.dp))
            .clickable(interactionSource = source, indication = null) {
                replay++
                scope.launch { cricketThud(ctx, if (stat.sixes > 0) Thud.SIX else Thud.FOUR) }
            },
    ) {
        if (featured) {
            Row(Modifier.padding(16.dp), verticalAlignment = Alignment.CenterVertically) {
                Box(
                    Modifier.size(112.dp).clip(RoundedCornerShape(16.dp)).background(colour.copy(alpha = 0.07f)),
                    contentAlignment = Alignment.Center,
                ) {
                    com.haraan.app.ui.matches.ShotFigure(kind, ink, Modifier.size(100.dp), replay)
                }
                Spacer(Modifier.width(16.dp))
                Column(Modifier.weight(1f)) {
                    Text("Best stroke", color = Muted, fontSize = 12.sp)
                    Text(kind.label, color = Ink, fontSize = 19.sp, fontWeight = FontWeight.Bold, maxLines = 1)
                    Spacer(Modifier.height(6.dp))
                    Row(verticalAlignment = Alignment.Bottom) {
                        Text(
                            "${stat.runs}",
                            color = Ink,
                            fontSize = 30.sp,
                            fontFamily = com.haraan.app.theme.ArchivoDisplay,
                            style = TextStyle(fontFeatureSettings = "tnum"),
                        )
                        Text(" runs", color = Muted, fontSize = 13.sp, modifier = Modifier.padding(bottom = 5.dp))
                    }
                    Text(
                        strokeLine(stat) + if (share > 0) " · $share% of the innings" else "",
                        color = Muted,
                        fontSize = 12.sp,
                        maxLines = 2,
                    )
                }
            }
        } else {
            Row(Modifier.padding(12.dp), verticalAlignment = Alignment.CenterVertically) {
                com.haraan.app.ui.matches.ShotFigure(kind, ink, Modifier.size(60.dp), replay)
                Spacer(Modifier.width(10.dp))
                Column(Modifier.weight(1f)) {
                    Text(
                        kind.label,
                        color = Ink,
                        fontSize = 13.5.sp,
                        fontWeight = FontWeight.SemiBold,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                    )
                    Text(
                        "${stat.runs} runs",
                        color = Ink,
                        fontSize = 13.sp,
                        fontWeight = FontWeight.Bold,
                        style = TextStyle(fontFeatureSettings = "tnum"),
                    )
                    Text(strokeLine(stat), color = Muted, fontSize = 11.sp, maxLines = 1)
                }
            }
        }
    }
}

private fun strokeLine(s: com.haraan.app.data.ShotTypeStat): String = buildList {
    if (s.fours > 0) add("${s.fours} four${if (s.fours == 1) "" else "s"}")
    if (s.sixes > 0) add("${s.sixes} six${if (s.sixes == 1) "" else "es"}")
    val other = s.shots - s.fours - s.sixes
    if (other > 0) add("$other other")
}.joinToString(" · ")

// ── 5. Wagon wheel ──────────────────────────────────────────────────────────

@Composable
internal fun WagonSection(matchId: String, innings: List<InningsInsight>, state: MatchUiState) {
    var side by rememberSide("wagon-$matchId", innings.size)
    val inn = innings.getOrNull(side) ?: return
    var filter by rememberSaveable("wagon-filter-$matchId") { mutableIntStateOf(0) }

    SectionHead("Wagon wheel")
    SideSwitch(innings, state, side) { side = it }
    BoardCard {
        if (inn.shots.isNotEmpty()) {
            PillToggle(listOf("All", "Fours", "Sixes"), filter) { filter = it }
            Spacer(Modifier.height(14.dp))
        }
        WagonWheel(inn, only = when (filter) { 1 -> 4; 2 -> 6; else -> 0 })
    }
}
