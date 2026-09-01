package com.haraan.app.ui.matches.tabs

import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.tween
import androidx.compose.animation.expandVertically
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.shrinkVertically
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
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
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.rotate
import androidx.compose.ui.layout.layout
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.data.CricketIq
import com.haraan.app.data.IqFinding
import com.haraan.app.data.MatchRepository
import com.haraan.app.ui.matches.CrexColors

/**
 * CRICKET IQ — a player's innings, explained.
 *
 * The rest of the tab answers "what did the match do". This answers "what did I do", and
 * that is the whole point of it: a scorecard already tells somebody they made 128 off 27.
 * What it never tells them is that the innings turned in the sixth over, that they took
 * ten balls to get going and then trebled their rate, or that almost none of it came in
 * ones.
 *
 * The order on screen is the argument:
 *
 *      the written read  →  the moment  →  two verdicts  →  the numbers behind them
 *
 * Prose is the least trustworthy thing here, so it is the smallest commitment: it sits at
 * the top because that is where a reader starts, but every claim under it is a rule
 * applied to a figure, and every figure is one tap away under "Show me the data". Nothing
 * on this screen asks to be believed.
 *
 * THREE FINDINGS, THREE TREATMENTS — and that is deliberate.
 *
 * The first version drew all three the same way: an identical hairline bar, an identical
 * micro-caps label, an identical bold line, three times. Three identical rows is what a
 * `forEach` looks like when nobody has decided what the items MEAN, and a reader feels
 * that instantly even if they could not name it. A turning point is a moment, and gets
 * the size of one. A strength and a gap are a matched pair, and get a surface each so
 * they read as two sides of the same judgement rather than two more rows.
 */
@Composable
fun CricketIqSection(matchId: String, repo: MatchRepository) {
    var iq by remember(matchId) { mutableStateOf<CricketIq?>(null) }
    var chosen by remember(matchId) { mutableStateOf<String?>(null) }
    var open by remember(matchId) { mutableStateOf(false) }

    LaunchedEffect(matchId, chosen) {
        iq = repo.fetchIq(matchId, chosen)
    }

    // Nothing to say before a ball has been faced, and an empty section is worse than no
    // section — so this one simply is not there yet.
    val data = iq ?: return

    // One clock for the block, so the read, the moment and the verdicts arrive as phases
    // of a single move rather than four things that happen to start together.
    val reveal = remember(data.player, data.balls) { Animatable(0f) }
    LaunchedEffect(data.player, data.balls) {
        reveal.animateTo(1f, tween(durationMillis = 850, easing = FastOutSlowInEasing))
    }
    val t = reveal.value

    Column(Modifier.fillMaxWidth()) {
        Box(Modifier.fillMaxWidth().height(1.dp).background(CrexColors.Border.copy(alpha = 0.55f)))
        Spacer(Modifier.height(18.dp))
        Text(
            "CRICKET IQ",
            color = CrexColors.TextMuted,
            fontSize = 9.5.sp,
            fontWeight = FontWeight.ExtraBold,
            letterSpacing = 1.4.sp,
        )

        // ── Who, and what they made ──────────────────────────────────────────
        //
        // The runs carry display weight here rather than sitting in a run-on line of
        // middot-separated figures. A player looking for themselves should find their
        // score, not parse a sentence.
        Spacer(Modifier.height(14.dp))
        Text(
            data.player,
            color = CrexColors.TextPrimary,
            fontSize = 22.sp,
            fontWeight = FontWeight.Bold,
            letterSpacing = (-0.6).sp,
        )
        Spacer(Modifier.height(7.dp))
        Row(verticalAlignment = Alignment.Bottom) {
            Text(
                "${data.runs}",
                color = CrexColors.TextPrimary,
                fontSize = 30.sp,
                fontFamily = com.haraan.app.theme.ArchivoDisplay,
                letterSpacing = (-1).sp,
                style = TextStyle(fontFeatureSettings = "tnum"),
            )
            Text(
                " (${data.balls})",
                color = CrexColors.TextSecondary,
                fontSize = 15.sp,
                modifier = Modifier.padding(bottom = 3.dp),
                style = TextStyle(fontFeatureSettings = "tnum"),
            )
            Spacer(Modifier.width(14.dp))
            Text(
                "SR ${data.strikeRate}   ·   ${data.fours}×4  ${data.sixes}×6",
                color = CrexColors.TextMuted,
                fontSize = 12.5.sp,
                modifier = Modifier.padding(bottom = 4.dp),
                style = TextStyle(fontFeatureSettings = "tnum"),
            )
        }

        if (data.batters.size > 1) {
            Spacer(Modifier.height(15.dp))
            BatterPicker(data.batters, data.player) { chosen = it }
        }

        // ── The read ─────────────────────────────────────────────────────────
        //
        // Set as an editorial standfirst — larger than body, open leading, hung off a
        // short rule. It was plain 15sp paragraph text under a grey caption, which is
        // exactly how generated filler looks.
        data.narrative?.let { line ->
            Spacer(Modifier.height(20.dp))
            Rise(t, after = 0.05f) {
                Row {
                    Box(
                        Modifier
                            .padding(top = 9.dp)
                            .width(22.dp)
                            .height(2.dp)
                            .background(CrexColors.TextPrimary),
                    )
                    Spacer(Modifier.width(14.dp))
                    Column {
                        Text(
                            line,
                            color = CrexColors.TextPrimary,
                            fontSize = 16.5.sp,
                            lineHeight = 26.sp,
                            letterSpacing = (-0.2).sp,
                        )
                        Spacer(Modifier.height(8.dp))
                        // Said plainly, because a reader deserves to know which sentence
                        // on this screen was written and which were counted.
                        Text(
                            "Written by Haraan from the figures below",
                            color = CrexColors.TextMuted,
                            fontSize = 10.5.sp,
                        )
                    }
                }
            }
        }

        data.note?.let {
            Spacer(Modifier.height(14.dp))
            Text(it, color = CrexColors.TextSecondary, fontSize = 13.sp, lineHeight = 19.sp)
        }

        val moment = data.findings.firstOrNull { it.kind == "turning" }
        val strength = data.findings.firstOrNull { it.kind == "strength" }
        val gap = data.findings.firstOrNull { it.kind == "opportunity" }

        // ── The moment ───────────────────────────────────────────────────────
        moment?.let {
            Spacer(Modifier.height(24.dp))
            Rise(t, after = 0.2f) { Moment(it, t) }
        }

        // ── The pair ─────────────────────────────────────────────────────────
        if (strength != null || gap != null) {
            Spacer(Modifier.height(18.dp))
            strength?.let {
                Rise(t, after = 0.34f) { Verdict(it, "STRENGTH", Color(0xFF16A34A)) }
            }
            if (strength != null && gap != null) Spacer(Modifier.height(10.dp))
            gap?.let {
                Rise(t, after = 0.44f) { Verdict(it, "TO WORK ON", Color(0xFFD97706)) }
            }
        }

        // ── The evidence ─────────────────────────────────────────────────────
        if (data.evidence.isNotEmpty()) {
            Spacer(Modifier.height(20.dp))
            Box(Modifier.fillMaxWidth().height(1.dp).background(CrexColors.Border.copy(alpha = 0.5f)))
            Row(
                Modifier
                    .fillMaxWidth()
                    .clickable(
                        indication = null,
                        interactionSource = remember { MutableInteractionSource() },
                    ) { open = !open }
                    .padding(vertical = 14.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Text(
                    if (open) "Hide the data" else "Show me the data",
                    color = CrexColors.AccentBlue,
                    fontSize = 13.5.sp,
                    fontWeight = FontWeight.Bold,
                    modifier = Modifier.weight(1f),
                )
                // A caret drawn from two rules rather than an icon: it rotates with the
                // state and never has to be sourced, tinted or scaled.
                Caret(open)
            }
            AnimatedVisibility(
                visible = open,
                enter = fadeIn(tween(180)) + expandVertically(tween(220)),
                exit = fadeOut(tween(120)) + shrinkVertically(tween(180)),
            ) {
                Column(Modifier.fillMaxWidth().padding(bottom = 6.dp)) {
                    data.evidence.forEachIndexed { i, row ->
                        if (i > 0) {
                            Box(
                                Modifier
                                    .fillMaxWidth()
                                    .height(1.dp)
                                    .background(CrexColors.Border.copy(alpha = 0.4f)),
                            )
                        }
                        Row(
                            Modifier.fillMaxWidth().padding(vertical = 10.dp),
                            verticalAlignment = Alignment.CenterVertically,
                        ) {
                            Text(
                                row.label,
                                color = CrexColors.TextSecondary,
                                fontSize = 13.sp,
                                modifier = Modifier.weight(1f),
                            )
                            Text(
                                row.value,
                                color = CrexColors.TextPrimary,
                                fontSize = 13.sp,
                                fontWeight = FontWeight.Bold,
                                style = TextStyle(fontFeatureSettings = "tnum"),
                            )
                        }
                    }
                }
            }
        }

        Spacer(Modifier.height(6.dp))
    }
}

/**
 * The over that turned this player's innings — as a moment, not a row.
 *
 * The runs are the subject, so the runs are the size of the subject. The over label sits
 * above them as a location, and the arithmetic that justifies calling it a turning point
 * sits below. No surface: this is the loudest thing in the section and it does not need a
 * box to say so.
 */
@Composable
private fun Moment(f: IqFinding, t: Float) {
    // "Over 6 — 36 runs" arrives as one string; the parts are worth different sizes, so
    // the number is lifted out. If the shape ever changes the whole string still prints.
    val runs = Regex("""—\s*(\d+)""").find(f.value)?.groupValues?.get(1)
    val over = Regex("""Over\s+(\d+)""").find(f.value)?.groupValues?.get(1)

    Column(Modifier.fillMaxWidth()) {
        Text(
            "THE OVER THAT TURNED YOUR INNINGS",
            color = CrexColors.TextMuted,
            fontSize = 9.sp,
            fontWeight = FontWeight.ExtraBold,
            letterSpacing = 1.2.sp,
        )
        Spacer(Modifier.height(10.dp))
        if (runs != null && over != null) {
            Row(verticalAlignment = Alignment.Bottom) {
                Text(
                    "OVER $over",
                    color = CrexColors.AccentBlue,
                    fontSize = 12.sp,
                    fontWeight = FontWeight.ExtraBold,
                    letterSpacing = 1.sp,
                    modifier = Modifier.padding(bottom = 12.dp),
                )
                Spacer(Modifier.width(14.dp))
                val shown = (runs.toInt() * ((t - 0.2f) / 0.5f).coerceIn(0f, 1f)).toInt()
                Text(
                    "$shown",
                    color = CrexColors.TextPrimary,
                    fontSize = 48.sp,
                    fontFamily = com.haraan.app.theme.ArchivoDisplay,
                    letterSpacing = (-2).sp,
                    style = TextStyle(fontFeatureSettings = "tnum"),
                )
                Spacer(Modifier.width(7.dp))
                Text(
                    "runs",
                    color = CrexColors.TextSecondary,
                    fontSize = 14.sp,
                    modifier = Modifier.padding(bottom = 9.dp),
                )
            }
        } else {
            Text(
                f.value,
                color = CrexColors.TextPrimary,
                fontSize = 22.sp,
                fontWeight = FontWeight.Bold,
            )
        }
        Spacer(Modifier.height(8.dp))
        Text(f.why, color = CrexColors.TextSecondary, fontSize = 13.5.sp, lineHeight = 20.sp)
    }
}

/**
 * A strength or a gap, on its own surface.
 *
 * The tint is what gives these two physical presence and tells them apart at a glance —
 * and it is faint on purpose. At any real saturation a page with one green panel and one
 * amber panel becomes a traffic light, which is both louder than the finding deserves and
 * the exact "rainbow dashboard" the rest of this tab avoids.
 *
 * Amber rather than red for the gap. A weakness in an innings is not an error, and a
 * player opening this after a game should not be met with the colour of a failure.
 */
@Composable
private fun Verdict(f: IqFinding, tag: String, accent: Color) {
    Row(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(14.dp))
            .background(
                Brush.horizontalGradient(
                    listOf(accent.copy(alpha = 0.085f), accent.copy(alpha = 0.035f)),
                )
            )
            .heightIn(min = 76.dp),
    ) {
        // A bled edge rather than a floating pill: the colour belongs to the surface, so
        // it runs the full height of it and touches both sides.
        Box(Modifier.width(3.dp).fillMaxHeight().background(accent.copy(alpha = 0.75f)))
        Column(Modifier.weight(1f).padding(horizontal = 15.dp, vertical = 13.dp)) {
            Text(
                tag,
                color = accent,
                fontSize = 9.sp,
                fontWeight = FontWeight.ExtraBold,
                letterSpacing = 1.2.sp,
            )
            Spacer(Modifier.height(5.dp))
            Text(
                f.value,
                color = CrexColors.TextPrimary,
                fontSize = 17.5.sp,
                fontWeight = FontWeight.Bold,
                letterSpacing = (-0.3).sp,
            )
            Spacer(Modifier.height(5.dp))
            Text(f.why, color = CrexColors.TextSecondary, fontSize = 13.sp, lineHeight = 19.sp)
        }
    }
}

/** Two short rules meeting at a point, rotated by state. */
@Composable
private fun Caret(open: Boolean) {
    val rot by animateFloatAsState(if (open) 180f else 0f, tween(220), label = "caret")
    Canvas(Modifier.width(11.dp).height(7.dp)) {
        rotate(rot) {
            val w = size.width
            val h = size.height
            val stroke = 2.dp.toPx()
            drawLine(
                color = accentBlue,
                start = Offset(0f, h * 0.2f),
                end = Offset(w / 2f, h * 0.85f),
                strokeWidth = stroke,
                cap = StrokeCap.Round,
            )
            drawLine(
                color = accentBlue,
                start = Offset(w, h * 0.2f),
                end = Offset(w / 2f, h * 0.85f),
                strokeWidth = stroke,
                cap = StrokeCap.Round,
            )
        }
    }
}

private val accentBlue = Color(0xFF2563EB)

/**
 * Whose innings. Everyone who actually faced a ball, best first.
 *
 * The unselected chips lost their filled pill: five identical grey capsules is a filter
 * bar, and this is a list of people. Only the one being read carries a surface.
 */
@Composable
private fun BatterPicker(batters: List<String>, selected: String, onPick: (String) -> Unit) {
    val scroll = rememberScrollState()
    Row(
        Modifier.fillMaxWidth().horizontalScroll(scroll),
        horizontalArrangement = Arrangement.spacedBy(6.dp),
    ) {
        batters.forEach { name ->
            val on = name == selected
            val bg by animateColorAsState(
                if (on) CrexColors.AccentBlue.copy(alpha = 0.1f) else Color.Transparent,
                tween(200),
                label = "iqBg",
            )
            val fg by animateColorAsState(
                if (on) CrexColors.AccentBlue else CrexColors.TextMuted,
                tween(200),
                label = "iqFg",
            )
            Box(
                Modifier
                    .clip(RoundedCornerShape(999.dp))
                    .background(bg)
                    .clickable(
                        indication = null,
                        interactionSource = remember { MutableInteractionSource() },
                    ) { onPick(name) }
                    .padding(horizontal = 13.dp, vertical = 7.dp),
            ) {
                Text(
                    name,
                    color = fg,
                    fontSize = 12.5.sp,
                    fontWeight = if (on) FontWeight.Bold else FontWeight.Medium,
                    maxLines = 1,
                )
            }
        }
    }
}

/**
 * Arrives from slightly below, once, on the block's own clock.
 *
 * Movement is the cheapest way to make flat elements feel like objects with weight, and
 * the most expensive way to make them feel gimmicky — so this is eight pixels and it
 * happens once.
 */
@Composable
private fun Rise(t: Float, after: Float, content: @Composable () -> Unit) {
    val p = ((t - after) / 0.4f).coerceIn(0f, 1f)
    Box(
        Modifier
            .alpha(p)
            .layout { measurable, constraints ->
                val placeable = measurable.measure(constraints)
                layout(placeable.width, placeable.height) {
                    placeable.placeRelative(0, ((1f - p) * 8.dp.toPx()).toInt())
                }
            },
    ) { content() }
}
