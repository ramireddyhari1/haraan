package com.haraan.app.ui.matches.tabs

import androidx.compose.foundation.Canvas
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.*
import androidx.compose.ui.res.painterResource
import com.haraan.app.R
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.horizontalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.Verified
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.draw.drawBehind
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.drawscope.rotate
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.runtime.remember
import coil.compose.AsyncImage
import com.haraan.app.data.ApiConfig
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.text.withStyle
import androidx.compose.ui.text.SpanStyle
import androidx.compose.ui.text.PlatformTextStyle
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.LineHeightStyle
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.ui.matches.CommentaryLine
import com.haraan.app.ui.matches.CrexColors
import com.haraan.app.ui.matches.MatchUiState
import com.haraan.app.ui.matches.RecentOver
import com.haraan.app.ui.theme.HaraanColors
import com.haraan.app.ui.theme.premiumCardShadow

// SIX → green, FOUR → blue, WICKET → solid red. Dots/singles stay neutral grey.
private val SixGreen = HaraanColors.Success
private val FourBlue = HaraanColors.EventsBlue

// The wicket card is WHITE with one red edge and a drawn scene, not a dark or red slab —
// a filled slab is the loudest "generated UI" tell there is, and it read as cheap.
private val WicketRed = Color(0xFFE5484D)
// One amber for every extra (wd/nb/b/lb) instead of a pair of raw hex literals.
private val ExtraAmber = Color(0xFFB45309)

/**
 * A dp that grows with the phone's font-size setting, the way sp text already does — so a
 * ball chip or a face never ends up smaller than the text beside it on a phone set to large
 * text. Capped so the biggest accessibility scales don't blow the feed apart.
 */
@Composable
private fun Dp.fontScaled(): Dp = this * LocalDensity.current.fontScale.coerceIn(1f, 1.5f)

@Composable
fun BallCircle(ball: String) {
    val isW = ball == "W"
    val accent = when (ball) {
        "6" -> SixGreen
        "4" -> FourBlue
        else -> null
    }
    Box(
        modifier = Modifier
            .size(22.dp)
            .clip(CircleShape)
            .then(
                when {
                    isW -> Modifier.background(CrexColors.AccentRed)
                    accent != null -> Modifier.background(accent.copy(alpha = 0.15f)).border(1.5.dp, accent, CircleShape)
                    else -> Modifier.background(CrexColors.Background).border(1.dp, CrexColors.Border, CircleShape)
                }
            ),
        contentAlignment = Alignment.Center
    ) {
        Text(
            text = ball,
            color = if (isW) Color.White else accent ?: CrexColors.TextSecondary,
            fontSize = 10.sp,
            fontWeight = if (isW || accent != null) FontWeight.Bold else FontWeight.Medium,
            textAlign = TextAlign.Center,
            // Strip the default font padding / line spacing so a single glyph sits dead
            // centre in the small circle instead of riding high.
            style = TextStyle(
                platformStyle = PlatformTextStyle(includeFontPadding = false),
                lineHeightStyle = LineHeightStyle(
                    alignment = LineHeightStyle.Alignment.Center,
                    trim = LineHeightStyle.Trim.Both
                )
            )
        )
    }
}

/**
 * A player's real face — the one thing on these cards that no generic template could
 * produce, and the reason they stop reading as coloured boxes. Falls back to the initial
 * on a tinted disc: never a stock silhouette, and never a letter floating in a
 * translucent circle, which is the placeholder look these cards had before they carried
 * a photo at all. The ring is a real ring (the disc underneath), so the photo is inset
 * by it rather than painted over it.
 */
@Composable
private fun PlayerFace(
    photoUrl: String,
    name: String,
    size: Dp,
    ring: Color,
    ringWidth: Dp,
    faceBg: Color,
    initialColor: Color,
) {
    val url = remember(photoUrl) { ApiConfig.mediaUrl(photoUrl) }
    val inner = size - ringWidth * 2
    Box(
        modifier = Modifier.size(size).clip(CircleShape).background(ring),
        contentAlignment = Alignment.Center
    ) {
        Box(
            modifier = Modifier.size(inner).clip(CircleShape).background(faceBg),
            contentAlignment = Alignment.Center
        ) {
            if (url != null) {
                AsyncImage(
                    model = url,
                    contentDescription = name,
                    contentScale = ContentScale.Crop,
                    modifier = Modifier.size(inner).clip(CircleShape)
                )
            } else {
                Image(
                    painter = painterResource(id = R.drawable.ic_default_player_avatar),
                    contentDescription = name,
                    contentScale = ContentScale.Crop,
                    modifier = Modifier.size(inner).clip(CircleShape)
                )
            }
        }
    }
}

@Composable
fun CommentaryTab(state: MatchUiState, modifier: Modifier = Modifier) {
    LazyColumn(
        modifier = modifier
            .fillMaxSize()
            .background(CrexColors.Background),
        contentPadding = PaddingValues(bottom = 80.dp)
    ) {
        // Result banner — only once the match is over (the header already shows LIVE while
        // it's in progress, so we don't repeat a "Live" line here).
        if (!state.isLive && state.status.isNotBlank()) {
            item {
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .background(CrexColors.Background)
                        .padding(bottom = 6.dp)
                ) {
                    // A status is a statement, not a zone — so it's a centred pill, not
                    // another full-bleed tinted band competing with the feed.
                    Box(modifier = Modifier.fillMaxWidth(), contentAlignment = Alignment.Center) {
                        Text(
                            text = state.status,
                            color = ExtraAmber,
                            fontSize = 11.sp,
                            fontWeight = FontWeight.SemiBold,
                            modifier = Modifier
                                .clip(RoundedCornerShape(999.dp))
                                .background(CrexColors.AccentYellow.copy(alpha = 0.12f))
                                .padding(horizontal = 14.dp, vertical = 5.dp)
                        )
                    }
                }
            }
        }

        // Over Tracker — premium scrolling over-chips
        item {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .background(CrexColors.Background)
                    .horizontalScroll(androidx.compose.foundation.rememberScrollState())
                    .padding(start = 16.dp, end = 16.dp, top = 10.dp, bottom = 6.dp),
                horizontalArrangement = Arrangement.spacedBy(10.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                // recentOvers already includes the in-progress over (last item), so just
                // render it and mark the last one as current — no separate "this over" chip
                // (that was the duplicate). Fall back to thisOver only if there's no history.
                if (state.recentOvers.isNotEmpty()) {
                    state.recentOvers.forEachIndexed { i, over ->
                        OverChip(
                            label = over.label, balls = over.balls, runs = over.runs,
                            current = state.isLive && i == state.recentOvers.lastIndex
                        )
                    }
                } else if (state.thisOver.isNotEmpty()) {
                    val currentOverNum = ((state.overs.toDoubleOrNull() ?: 0.0).toInt() + 1).toString()
                    val thisOverRuns = state.thisOver.sumOf { ball ->
                        val r = ball.toIntOrNull()
                        if (r != null) r else if (ball.startsWith("wd", ignoreCase = true) || ball.startsWith("nb", ignoreCase = true)) 1 else 0
                    }
                    OverChip(label = currentOverNum, balls = state.thisOver, runs = thisOverRuns, current = true)
                }
            }
        }

        // Batter + Partnership + Bowler unified into a single card so the whole
        // "mini-scorecard" reads as one consistent surface (not two floating strips).
        item {
            val pShipText = state.partnership?.takeIf { it.balls > 0 || it.runs > 0 }?.let { "P'Ship: ${it.runs} (${it.balls})" }
            val lastWktText = state.lastWicket?.takeIf { it.name.isNotBlank() }?.let { "Last wkt: ${it.name} ${it.runs} (${it.balls})" }

            Column(
                modifier = Modifier
                    .padding(start = 12.dp, end = 12.dp, top = 2.dp, bottom = 8.dp)
                    .clip(RoundedCornerShape(16.dp))
                    .background(CrexColors.Surface)
                    .border(1.dp, CrexColors.Border, RoundedCornerShape(16.dp))
            ) {
                // ── Batter ──
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .drawBehind { drawLine(color = CrexColors.Border, start = Offset(0f, size.height), end = Offset(size.width, size.height), strokeWidth = 1.dp.toPx()) }
                        .padding(horizontal = 16.dp, vertical = 6.dp),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Text("BATTER", color = CrexColors.TextMuted, fontSize = 10.sp, fontWeight = FontWeight.Medium, letterSpacing = 1.sp)
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Text("R", color = CrexColors.TextMuted, fontSize = 10.sp, fontWeight = FontWeight.Medium, modifier = Modifier.width(38.dp), textAlign = TextAlign.Center)
                        Text("B", color = CrexColors.TextMuted, fontSize = 10.sp, fontWeight = FontWeight.Medium, modifier = Modifier.width(34.dp), textAlign = TextAlign.Center)
                        Text("4S", color = CrexColors.TextMuted, fontSize = 10.sp, fontWeight = FontWeight.Medium, modifier = Modifier.width(30.dp), textAlign = TextAlign.Center)
                        Text("6S", color = CrexColors.TextMuted, fontSize = 10.sp, fontWeight = FontWeight.Medium, modifier = Modifier.width(30.dp), textAlign = TextAlign.Center)
                        Spacer(modifier = Modifier.width(24.dp))
                    }
                }

                val allSquad = remember(state.homeSquad, state.awaySquad) { state.homeSquad + state.awaySquad }
                fun findPhoto(pName: String): String {
                    val clean = pName.trimEnd('*', ' ').trim()
                    return allSquad.firstOrNull { it.name.equals(clean, ignoreCase = true) }?.avatar.orEmpty()
                }

                if (state.striker.isNotEmpty()) {
                    val stats = state.strikerStats
                    val runs = stats?.runs?.toString() ?: "0"
                    val balls = stats?.balls?.toString() ?: "0"
                    val fours = stats?.fours?.toString() ?: "0"
                    val sixes = stats?.sixes?.toString() ?: "0"
                    val sr = if (stats != null && stats.balls > 0) {
                        String.format("%.2f", (stats.runs.toFloat() / stats.balls) * 100)
                    } else "0.00"
                    BatterRow(name = state.striker + " *", runs = runs, balls = balls, fours = fours, sixes = sixes, sr = sr, photoUrl = findPhoto(state.striker))
                }
                if (state.nonStriker.isNotEmpty()) {
                    val stats = state.nonStrikerStats
                    val runs = stats?.runs?.toString() ?: "0"
                    val balls = stats?.balls?.toString() ?: "0"
                    val fours = stats?.fours?.toString() ?: "0"
                    val sixes = stats?.sixes?.toString() ?: "0"
                    val sr = if (stats != null && stats.balls > 0) {
                        String.format("%.2f", (stats.runs.toFloat() / stats.balls) * 100)
                    } else "0.00"
                    BatterRow(name = state.nonStriker, runs = runs, balls = balls, fours = fours, sixes = sixes, sr = sr, photoUrl = findPhoto(state.nonStriker))
                }

                // ── Partnership / last wicket — only real values ──
                if (pShipText != null || lastWktText != null) {
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .background(CrexColors.Background)
                            .drawBehind { drawLine(color = CrexColors.Border, start = Offset(0f, size.height), end = Offset(size.width, size.height), strokeWidth = 1.dp.toPx()) }
                            .padding(horizontal = 16.dp, vertical = 10.dp),
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Text(pShipText ?: "", color = CrexColors.TextSecondary, fontSize = 10.sp, fontWeight = FontWeight.Medium)
                        Text(lastWktText ?: "", color = CrexColors.TextSecondary, fontSize = 10.sp, fontWeight = FontWeight.Medium)
                    }
                }

                // ── Bowler ──
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .drawBehind { drawLine(color = CrexColors.Border, start = Offset(0f, size.height), end = Offset(size.width, size.height), strokeWidth = 1.dp.toPx()) }
                        .padding(horizontal = 16.dp, vertical = 8.dp),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Text("BOWLER", color = CrexColors.TextMuted, fontSize = 10.sp, fontWeight = FontWeight.Medium, letterSpacing = 1.sp)
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Text("W-R", color = CrexColors.TextMuted, fontSize = 10.sp, fontWeight = FontWeight.Medium, modifier = Modifier.width(48.dp), textAlign = TextAlign.Center)
                        Text("OV", color = CrexColors.TextMuted, fontSize = 10.sp, fontWeight = FontWeight.Medium, modifier = Modifier.width(36.dp), textAlign = TextAlign.Center)
                        Text("ECON", color = CrexColors.TextMuted, fontSize = 10.sp, fontWeight = FontWeight.Medium, modifier = Modifier.width(48.dp), textAlign = TextAlign.Center)
                        Spacer(modifier = Modifier.width(24.dp))
                    }
                }

                if (state.bowler.isNotEmpty()) {
                    val stats = state.bowlerStats
                    val wickets = stats?.wickets ?: 0
                    val runs = stats?.runs ?: 0
                    val balls = stats?.balls ?: 0
                    val oversDecimal = "${balls / 6}.${balls % 6}"
                    val econ = if (balls > 0) {
                        String.format("%.2f", (runs.toFloat() / balls) * 6)
                    } else "0.00"
                    BowlerRow(name = state.bowler, figures = "$wickets-$runs", overs = oversDecimal, econ = econ, photoUrl = findPhoto(state.bowler))
                }
            }
        }

        // ── Ball-by-ball commentary feed ──
        if (state.commentary.isNotEmpty()) {
            item {
                Text(
                    "COMMENTARY",
                    color = CrexColors.TextMuted, fontSize = 10.sp, fontWeight = FontWeight.Bold, letterSpacing = 1.sp,
                    modifier = Modifier.fillMaxWidth().background(CrexColors.Background).padding(start = 16.dp, top = 16.dp, bottom = 4.dp)
                )
            }
            items(state.commentary) { line ->
                when {
                    line.kind == "header" -> CommentaryHeader(line.text)
                    line.kind == "milestone" -> MilestoneBanner(line)
                    line.kind == "batter_in" -> NewBatterRow(line)
                    line.wicket -> WicketBanner(line, state)
                    else -> CommentaryRow(line)
                }
            }
        }

        // Nothing-yet state — shown only when there's no real scoring data at all.
        if (state.commentary.isEmpty() && state.striker.isBlank() && state.thisOver.isEmpty() && state.recentOvers.isEmpty()) {
            item {
                Box(
                    modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 32.dp),
                    contentAlignment = Alignment.Center
                ) {
                    Text(
                        "No commentary yet — it'll appear here once scoring begins.",
                        color = CrexColors.TextMuted, fontSize = 13.sp, textAlign = TextAlign.Center
                    )
                }
            }
        }
    }
}

/** An innings/over divider — a titled rule, not another full-width tinted band. */
@Composable
private fun CommentaryHeader(text: String) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .background(CrexColors.Background)
            .padding(start = 16.dp, end = 16.dp, top = 14.dp, bottom = 8.dp),
        verticalAlignment = Alignment.CenterVertically
    ) {
        Text(
            text.uppercase(),
            color = CrexColors.TextSecondary, fontSize = 10.sp,
            fontWeight = FontWeight.ExtraBold, letterSpacing = 1.2.sp
        )
        Spacer(Modifier.width(10.dp))
        Box(Modifier.weight(1f).height(1.dp).background(CrexColors.Border))
    }
}

/**
 * Premium red WICKET banner shown inline in the feed for every dismissal — the out
 * batter's name + figures, how they went, and the score at the fall. Name/figures/score
 * are cross-referenced from the replayed innings (fall-of-wickets → batting card) so it's
 * all real; if a live wicket hasn't landed in the card yet it degrades to the dismissal
 * line alone.
 */
@Composable
private fun WicketBanner(line: CommentaryLine, state: MatchUiState) {
    val card = state.inningsCards.firstOrNull { it.number == line.innings }
    val fow = card?.fallOfWickets?.firstOrNull { it.over == line.over }
    val batterName = fow?.batter?.takeIf { it.isNotBlank() }
    val bat = batterName?.let { name -> card?.batters?.firstOrNull { it.name == name } }
    val figures = bat?.let { "${it.runs} (${it.balls})" }
    // The feed text reads "<bowler> to <batter>, OUT! <how>" — the card already carries
    // WICKET, the batter and the figures, so only the "<how>" tail is new information.
    // (removePrefix alone missed it: the OUT! sits mid-string, not at the front.) Falls
    // back to the whole line if a source ever phrases a dismissal without the marker.
    val dismissal = line.text
        .substringAfter("OUT!", line.text)
        .trim().removePrefix(",").trim()
        .ifBlank { "out" }
    val scoreAtFall = fow?.let { "${it.score}-${it.wicketNo}" }

    val faceSize = 62.dp.fontScaled()
    val shape = RoundedCornerShape(18.dp)

    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 12.dp, vertical = 8.dp)
            .premiumCardShadow(radius = 18.dp, ambient = 10.dp, contact = 2.dp)
            .clip(shape)
            .background(CrexColors.Surface)
            .border(1.dp, WicketRed.copy(alpha = 0.22f), shape)
            // One red edge on a white card — the colour marks the moment, the card stays
            // in the same family as every other row in the feed.
            .drawBehind { drawRect(WicketRed, size = Size(4.dp.toPx(), size.height)) }
            .padding(start = 16.dp, end = 12.dp, top = 14.dp, bottom = 14.dp),
        verticalAlignment = Alignment.CenterVertically
    ) {
        // The player is the subject of a dismissal, not the ball — so their face leads
        // and the W rides it as a badge, cut out of the photo by a white collar.
        Box(contentAlignment = Alignment.BottomEnd) {
            PlayerFace(
                photoUrl = line.photoUrl,
                name = batterName ?: line.battingName,
                size = faceSize,
                ring = WicketRed,
                ringWidth = 2.5.dp,
                faceBg = WicketTint,
                initialColor = WicketRed
            )
            Box(
                modifier = Modifier
                    .offset(x = 4.dp, y = 3.dp)
                    .size(24.dp.fontScaled()).clip(CircleShape).background(CrexColors.Surface),
                contentAlignment = Alignment.Center
            ) {
                Box(
                    modifier = Modifier.size(19.dp.fontScaled()).clip(CircleShape).background(WicketRed),
                    contentAlignment = Alignment.Center
                ) {
                    Text(
                        "W", color = Color.White, fontSize = 10.sp, fontWeight = FontWeight.Bold,
                        style = TextStyle(platformStyle = PlatformTextStyle(includeFontPadding = false))
                    )
                }
            }
        }
        Spacer(Modifier.width(14.dp))
        Column(Modifier.weight(1f)) {
            // "WICKET" alone. The dismissal line below already says how.
            Text("WICKET", color = WicketRed, fontSize = 11.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = 1.5.sp)
            Spacer(Modifier.height(2.dp))
            Text(
                batterName ?: line.battingName.ifBlank { "Batter" },
                color = CrexColors.TextPrimary, fontSize = 18.sp, lineHeight = 22.sp,
                fontFamily = com.haraan.app.theme.ArchivoDisplay,
                maxLines = 2,
                overflow = androidx.compose.ui.text.style.TextOverflow.Ellipsis
            )
            if (figures != null) {
                Text(
                    figures,
                    color = CrexColors.TextSecondary, fontSize = 15.sp,
                    fontFamily = com.haraan.app.theme.ArchivoDisplay,
                    style = TextStyle(fontFeatureSettings = "tnum")
                )
            }
            Spacer(Modifier.height(4.dp))
            Text(
                dismissal, color = CrexColors.TextSecondary,
                fontSize = 13.5.sp, lineHeight = 18.sp, maxLines = 3,
                overflow = androidx.compose.ui.text.style.TextOverflow.Ellipsis
            )
        }
        Spacer(Modifier.width(10.dp))
        // The picture of the wicket, then what it cost — stacked, so neither sits on the other.
        Column(horizontalAlignment = Alignment.CenterHorizontally) {
            Canvas(Modifier.size(width = 70.dp.fontScaled(), height = 58.dp.fontScaled())) { drawBowledScene() }
            if (scoreAtFall != null) {
                Text(
                    scoreAtFall, color = CrexColors.TextPrimary, fontSize = 20.sp,
                    fontFamily = com.haraan.app.theme.ArchivoDisplay,
                    style = TextStyle(fontFeatureSettings = "tnum")
                )
            }
            if (line.over.isNotBlank()) {
                Text("${line.over} ov", color = CrexColors.TextMuted, fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
            }
        }
    }
}

// Illustration palette for the bowled scene — real wood and real leather, not brand flat.
private val WicketTint = Color(0xFFFDECEC)
private val WoodFill = Color(0xFFE6C79A)
private val WoodLine = Color(0xFF9A7240)
private val WoodLight = Color(0xFFF6E3C4)
private val SceneLeather = Color(0xFFC62828)
private val SceneLeatherDark = Color(0xFF8E1B1B)

/**
 * A hand-drawn "bowled" moment: a crease and ground shadow, leg and middle stumps standing,
 * the off stump knocked back, both bails flying, and the ball with its seam coming through
 * on a short motion trail. Outlined wood with a highlight stripe so it reads as an
 * illustration with a hand behind it, not a flat icon.
 */
private fun DrawScope.drawBowledScene() {
    val w = size.width
    val h = size.height
    val baseY = h * 0.90f
    val stumpH = h * 0.62f
    val stumpW = w * 0.075f
    val gap = w * 0.15f
    val midX = w * 0.50f
    val outline = 1.2.dp.toPx()

    // Pale red disc behind the scene — the card's one patch of colour.
    drawCircle(WicketRed.copy(alpha = 0.08f), radius = h * 0.48f, center = Offset(midX, h * 0.52f))
    // Ground shadow + crease.
    drawOval(Color.Black.copy(alpha = 0.07f), topLeft = Offset(midX - gap * 1.9f, baseY - h * 0.035f), size = Size(gap * 3.8f, h * 0.07f))
    drawLine(WicketRed.copy(alpha = 0.35f), Offset(w * 0.08f, baseY), Offset(w * 0.92f, baseY), strokeWidth = 1.5.dp.toPx(), cap = StrokeCap.Round)

    fun stump(x: Float) {
        val tl = Offset(x - stumpW / 2, baseY - stumpH)
        val sz = Size(stumpW, stumpH)
        drawRoundRect(WoodFill, tl, sz, CornerRadius(stumpW / 2))
        drawRoundRect(WoodLight, Offset(tl.x + stumpW * 0.22f, tl.y + stumpW * 0.6f), Size(stumpW * 0.22f, stumpH * 0.7f), CornerRadius(stumpW))
        drawRoundRect(WoodLine, tl, sz, CornerRadius(stumpW / 2), style = Stroke(outline))
    }
    stump(midX - gap)   // leg
    stump(midX)         // middle
    rotate(degrees = 24f, pivot = Offset(midX + gap, baseY)) { stump(midX + gap) }  // off, knocked back

    // Bails mid-flight, each with its own spin.
    val bailW = w * 0.17f
    val bailH = h * 0.065f
    fun bail(c: Offset, deg: Float) = rotate(deg, pivot = c) {
        val tl = Offset(c.x - bailW / 2, c.y - bailH / 2)
        drawRoundRect(WoodFill, tl, Size(bailW, bailH), CornerRadius(bailH / 2))
        drawRoundRect(WoodLine, tl, Size(bailW, bailH), CornerRadius(bailH / 2), style = Stroke(outline))
    }
    bail(Offset(midX - gap * 0.9f, baseY - stumpH - h * 0.16f), -32f)
    bail(Offset(midX + gap * 1.7f, baseY - stumpH - h * 0.06f), 40f)

    // The ball, through the gate, with a short trail behind it.
    val r = h * 0.11f
    val ball = Offset(midX + gap * 2.25f, baseY - stumpH * 0.42f)
    val trail = Stroke(width = 1.5.dp.toPx(), cap = StrokeCap.Round)
    for ((i, dy) in listOf(-0.45f, 0f, 0.45f).withIndex()) {
        val len = r * (2.6f - i % 2 * 0.8f)
        drawLine(
            SceneLeather.copy(alpha = 0.30f),
            Offset(ball.x - r * 1.5f - len, ball.y + r * dy),
            Offset(ball.x - r * 1.5f, ball.y + r * dy),
            strokeWidth = trail.width, cap = StrokeCap.Round
        )
    }
    drawCircle(SceneLeather, radius = r, center = ball)
    drawCircle(SceneLeatherDark, radius = r, center = ball, style = Stroke(outline))
    rotate(-35f, pivot = ball) {
        drawLine(Color.White.copy(alpha = 0.9f), Offset(ball.x, ball.y - r * 0.85f), Offset(ball.x, ball.y + r * 0.85f), strokeWidth = r * 0.22f, cap = StrokeCap.Round)
    }
    drawCircle(Color.White.copy(alpha = 0.35f), radius = r * 0.28f, center = Offset(ball.x - r * 0.38f, ball.y - r * 0.38f))
}

/** A small leather ball with its stitched seam — the dot-ball mark. */
private fun DrawScope.drawCricketBall(color: Color) {
    val r = size.minDimension / 2
    val c = center
    drawCircle(color.copy(alpha = 0.55f), radius = r, center = c)
    // The seam: two parallel arcs across the ball, slightly tilted, in the chip's light.
    val seam = Stroke(width = r * 0.16f, cap = StrokeCap.Round)
    rotate(-30f, pivot = c) {
        for (dx in listOf(-r * 0.16f, r * 0.16f)) {
            val path = Path().apply {
                moveTo(c.x + dx - r * 0.12f, c.y - r * 0.92f)
                quadraticBezierTo(c.x + dx + r * 0.32f, c.y, c.x + dx - r * 0.12f, c.y + r * 0.92f)
            }
            drawPath(path, Color.White.copy(alpha = 0.9f), style = seam)
        }
    }
}

/**
 * A batter walking in is a fact, not a moment — so it's a feed ROW, sharing the over
 * gutter and the ball column with every other delivery, and only the wicket above it
 * gets a card. That difference in FORM is what stops the pair reading as one template
 * printed in two colours, and it costs one row instead of two cards per dismissal.
 */
/**
 * A milestone, given the room a milestone deserves.
 *
 * Everything else in this feed is a line of text. A fifty rendered as another line of text
 * is the same failure the hero card had before the boundary burst: the biggest moment of
 * an innings drawn exactly like the smallest. So this is a BANNER — full width, the
 * event's own colour, the number set enormous behind the words — and it is the only thing
 * in the feed that looks like this, which is what makes it register while scrolling.
 *
 * Colour carries the rank: a hundred is gold, a fifty is the brand blue, a stand is green,
 * a chase won is the winning red. No emoji anywhere near it — a trophy glyph would make it
 * look like a mobile game, not a scoreboard.
 */
@Composable
private fun MilestoneBanner(line: CommentaryLine) {
    val accent = when (line.milestoneKind) {
        "century" -> Color(0xFFD97706)
        "partnership" -> Color(0xFF15803D)
        "target" -> Color(0xFFDC2626)
        else -> CrexColors.AccentBlue
    }
    val eyebrow = when (line.milestoneKind) {
        "century" -> "MILESTONE"
        "partnership" -> "PARTNERSHIP"
        "target" -> "MATCH WON"
        else -> "MILESTONE"
    }

    Box(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 12.dp, vertical = 7.dp)
            .clip(RoundedCornerShape(16.dp))
            .background(
                Brush.horizontalGradient(
                    listOf(accent.copy(alpha = 0.16f), accent.copy(alpha = 0.05f))
                )
            )
            .border(1.dp, accent.copy(alpha = 0.35f), RoundedCornerShape(16.dp))
    ) {
        // The number, enormous and faint, bled off the right edge. It reads as texture at a
        // glance and as the figure itself when you look — the same trick the MVP card uses,
        // so the two feel like one product.
        Text(
            line.label,
            color = accent.copy(alpha = 0.13f),
            fontSize = 112.sp,
            fontWeight = FontWeight.Black,
            maxLines = 1,
            modifier = Modifier.align(Alignment.CenterEnd).padding(end = 4.dp),
            style = TextStyle(fontFeatureSettings = "tnum")
        )

        Row(
            modifier = Modifier.padding(horizontal = 16.dp, vertical = 18.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            // ZONE 1 — the player. Large enough to be the reason you stop scrolling. A
            // partnership belongs to two people, so rather than picking one of them to
            // portray it shows the number itself at the same size: the column is never
            // empty, and the card keeps one shape whatever kind of milestone it is.
            if (line.milestoneKind == "fifty" || line.milestoneKind == "century") {
                MilestoneFace(line.text, line.photoUrl, accent)
            } else {
                MilestoneDisc(line.label, accent)
            }
            Spacer(Modifier.width(16.dp))

            // ZONE 2 — what happened, in three descending weights: what kind of moment,
            // then the sentence, then the figures behind it.
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    eyebrow,
                    color = accent,
                    fontSize = 10.sp,
                    fontWeight = FontWeight.ExtraBold,
                    letterSpacing = 1.5.sp
                )
                Spacer(Modifier.height(6.dp))
                Text(
                    line.text,
                    color = CrexColors.TextPrimary,
                    fontSize = 18.sp,
                    fontWeight = FontWeight.Black,
                    lineHeight = 22.sp,
                    maxLines = 3,
                    overflow = androidx.compose.ui.text.style.TextOverflow.Ellipsis
                )
                if (line.detail.isNotBlank()) {
                    Spacer(Modifier.height(7.dp))
                    Text(
                        line.detail,
                        color = CrexColors.TextSecondary,
                        fontSize = 12.5.sp,
                        fontWeight = FontWeight.SemiBold
                    )
                }
            }
        }
    }
}

/**
 * Zone 1 for a milestone with no single owner — a stand, or a chase won.
 *
 * The number at portrait size keeps every milestone card the same shape, so the feed does
 * not visibly reflow between a fifty and a partnership. Filling the slot with a generic
 * icon instead would say nothing that the words beside it do not already say.
 */
@Composable
private fun MilestoneDisc(label: String, accent: Color) {
    Box(
        modifier = Modifier
            .size(84.dp)
            .clip(CircleShape)
            .background(accent.copy(alpha = 0.16f))
            .border(2.5.dp, accent.copy(alpha = 0.6f), CircleShape),
        contentAlignment = Alignment.Center
    ) {
        Text(
            label,
            color = accent,
            fontSize = if (label.length > 3) 18.sp else 26.sp,
            fontWeight = FontWeight.Black,
            style = TextStyle(fontFeatureSettings = "tnum")
        )
    }
}

/** The milestone-maker's face; initials in the event colour when they have no photo. */
@Composable
private fun MilestoneFace(text: String, photoUrl: String, accent: Color) {
    Box(
        modifier = Modifier
            .size(84.dp)
            .clip(CircleShape)
            .background(accent.copy(alpha = 0.16f))
            .border(2.5.dp, accent.copy(alpha = 0.6f), CircleShape),
        contentAlignment = Alignment.Center
    ) {
        if (photoUrl.isNotBlank()) {
            AsyncImage(
                model = photoUrl,
                contentDescription = null,
                contentScale = androidx.compose.ui.layout.ContentScale.Crop,
                modifier = Modifier.fillMaxSize().clip(CircleShape),
            )
        } else {
            Image(
                painter = painterResource(id = R.drawable.ic_default_player_avatar),
                contentDescription = text,
                contentScale = androidx.compose.ui.layout.ContentScale.Crop,
                modifier = Modifier.fillMaxSize().clip(CircleShape),
            )
        }
    }
}

@Composable
private fun NewBatterRow(line: CommentaryLine) {
    // Only a career with real deliveries behind it counts — an all-zero row is the same
    // as having none, and printing zeroes would invent a career not yet played.
    val c = line.career?.takeIf { it.innings > 0 || it.balls > 0 }
    val name = line.text.ifBlank { "New batter" }
    // ONE true line, not a six-column stat strip. What you want to know about a batter
    // walking in is whether they can bat, and runs + strike rate answer that.
    val note = when {
        c == null -> "first innings"
        c.sr != null -> "${c.runs} runs · SR ${String.format("%.0f", c.sr)}"
        c.highScore > 0 -> "${c.runs} runs · HS ${c.highScore}"
        else -> "${c.runs} runs"
    }

    Row(
        modifier = Modifier
            .fillMaxWidth()
            .background(CrexColors.Surface)
            .drawBehind { drawLine(color = CrexColors.Border, start = Offset(0f, size.height), end = Offset(size.width, size.height), strokeWidth = 1.dp.toPx()) }
            .padding(horizontal = 16.dp, vertical = 12.dp),
        verticalAlignment = Alignment.CenterVertically
    ) {
        Text(
            line.over,
            color = CrexColors.TextMuted, fontSize = 12.sp, fontWeight = FontWeight.Bold,
            style = TextStyle(fontFeatureSettings = "tnum"),
            modifier = Modifier.width(38.dp.fontScaled())
        )
        // A walk-in is about the person — the face is bigger than a ball chip on purpose.
        PlayerFace(
            photoUrl = line.photoUrl,
            name = name,
            size = 44.dp.fontScaled(),
            ring = CrexColors.AccentBlue.copy(alpha = 0.55f),
            ringWidth = 1.5.dp,
            faceBg = CrexColors.Background,
            initialColor = CrexColors.TextSecondary
        )
        Spacer(Modifier.width(12.dp))
        Column(Modifier.weight(1f)) {
            Text(
                name, color = CrexColors.TextPrimary, fontSize = 15.sp,
                fontWeight = FontWeight.SemiBold, maxLines = 1,
                overflow = androidx.compose.ui.text.style.TextOverflow.Ellipsis
            )
            Text("walks in · $note", color = CrexColors.TextSecondary, fontSize = 13.sp)
        }
    }
}

@Composable
private fun CommentaryRow(line: CommentaryLine) {
    val (bg, fg) = when {
        line.wicket -> CrexColors.AccentRed to Color.White
        line.boundary -> CrexColors.SixBall.copy(alpha = 0.15f) to CrexColors.SixBall
        line.label == "0" -> CrexColors.Background to CrexColors.TextMuted
        line.label.lowercase() in setOf("wd", "nb", "b", "lb") -> ExtraAmber.copy(alpha = 0.12f) to ExtraAmber
        else -> CrexColors.Background to CrexColors.TextSecondary
    }
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .background(CrexColors.Surface)
            .drawBehind { drawLine(color = CrexColors.Border, start = Offset(0f, size.height), end = Offset(size.width, size.height), strokeWidth = 1.dp.toPx()) }
            .padding(horizontal = 16.dp, vertical = 14.dp),
        verticalAlignment = Alignment.CenterVertically
    ) {
        Text(
            line.over,
            color = CrexColors.TextMuted, fontSize = 12.sp, fontWeight = FontWeight.Bold,
            style = TextStyle(fontFeatureSettings = "tnum"),
            modifier = Modifier.width(38.dp.fontScaled())
        )
        val chip = 34.dp.fontScaled()
        Box(
            modifier = Modifier.size(chip).clip(CircleShape).background(bg),
            contentAlignment = Alignment.Center
        ) {
            if (line.label == "0") {
                // A dot ball IS the ball coming back untouched — so draw the ball.
                Canvas(Modifier.size(chip * 0.5f)) { drawCricketBall(CrexColors.TextMuted) }
            } else {
                Text(
                    line.label,
                    color = fg, fontSize = 13.sp, fontWeight = FontWeight.Bold,
                    textAlign = TextAlign.Center,
                    style = TextStyle(platformStyle = PlatformTextStyle(includeFontPadding = false))
                )
            }
        }
        Spacer(Modifier.width(14.dp))
        Text(
            line.text,
            color = if (line.wicket) CrexColors.AccentRed else CrexColors.TextPrimary,
            fontSize = 15.sp,
            lineHeight = 21.sp,
            fontWeight = if (line.wicket || line.boundary) FontWeight.Bold else FontWeight.Normal,
            modifier = Modifier.weight(1f)
        )
    }
}

@Composable
fun BatterRow(
    name: String,
    runs: String,
    balls: String,
    fours: String,
    sixes: String,
    sr: String,
    photoUrl: String = "",
) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .drawBehind { drawLine(color = CrexColors.Border, start = Offset(0f, size.height), end = Offset(size.width, size.height), strokeWidth = 1.dp.toPx()) }
            .padding(horizontal = 16.dp, vertical = 12.dp),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically
    ) {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
            val url = remember(photoUrl) { if (photoUrl.isNotBlank()) ApiConfig.mediaUrl(photoUrl) else null }
            Box(
                modifier = Modifier
                    .size(40.dp)
                    .clip(CircleShape)
                    .background(CrexColors.Background),
                contentAlignment = Alignment.Center
            ) {
                if (url != null) {
                    AsyncImage(
                        model = url,
                        contentDescription = name,
                        contentScale = ContentScale.Crop,
                        modifier = Modifier.fillMaxSize().clip(CircleShape)
                    )
                } else {
                    Image(
                        painter = painterResource(id = R.drawable.ic_default_player_avatar),
                        contentDescription = name,
                        contentScale = ContentScale.Crop,
                        modifier = Modifier.fillMaxSize().clip(CircleShape)
                    )
                }
            }
            Column {
                Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                    Text(name, color = CrexColors.TextPrimary, fontSize = 14.sp, fontWeight = FontWeight.Medium)
                }
                Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                    Text("SR $sr", color = CrexColors.TextSecondary, fontSize = 10.sp, letterSpacing = 1.sp)
                    XpChip((runs.toIntOrNull() ?: 0) + (fours.toIntOrNull() ?: 0) + (sixes.toIntOrNull() ?: 0) * 2)
                }
            }
        }
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text(runs, color = CrexColors.TextPrimary, fontSize = 14.sp, fontWeight = FontWeight.SemiBold, maxLines = 1, softWrap = false, modifier = Modifier.width(38.dp), textAlign = TextAlign.Center)
            Text(balls, color = CrexColors.TextSecondary, fontSize = 14.sp, maxLines = 1, softWrap = false, modifier = Modifier.width(34.dp), textAlign = TextAlign.Center)
            Text(fours, color = CrexColors.TextSecondary, fontSize = 14.sp, maxLines = 1, softWrap = false, modifier = Modifier.width(30.dp), textAlign = TextAlign.Center)
            Text(sixes, color = CrexColors.TextSecondary, fontSize = 14.sp, maxLines = 1, softWrap = false, modifier = Modifier.width(30.dp), textAlign = TextAlign.Center)
            Spacer(modifier = Modifier.width(24.dp))
        }
    }
}

@Composable
fun BowlerRow(
    name: String,
    figures: String,
    overs: String,
    econ: String,
    photoUrl: String = "",
) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .drawBehind { drawLine(color = CrexColors.Border, start = Offset(0f, size.height), end = Offset(size.width, size.height), strokeWidth = 1.dp.toPx()) }
            .padding(horizontal = 16.dp, vertical = 12.dp),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically
    ) {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
            val url = remember(photoUrl) { if (photoUrl.isNotBlank()) ApiConfig.mediaUrl(photoUrl) else null }
            Box(
                modifier = Modifier
                    .size(40.dp)
                    .clip(CircleShape)
                    .background(CrexColors.Background),
                contentAlignment = Alignment.Center
            ) {
                if (url != null) {
                    AsyncImage(
                        model = url,
                        contentDescription = name,
                        contentScale = ContentScale.Crop,
                        modifier = Modifier.fillMaxSize().clip(CircleShape)
                    )
                } else {
                    Image(
                        painter = painterResource(id = R.drawable.ic_default_player_avatar),
                        contentDescription = name,
                        contentScale = ContentScale.Crop,
                        modifier = Modifier.fillMaxSize().clip(CircleShape)
                    )
                }
            }
            Column {
                Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                    Text(name, color = CrexColors.TextPrimary, fontSize = 14.sp, fontWeight = FontWeight.Medium)
                    Icon(
                        imageVector = Icons.Outlined.Verified,
                        contentDescription = "Verified",
                        tint = CrexColors.TextSecondary,
                        modifier = Modifier.size(10.dp)
                    )
                }
                Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                    Text("BOWLER", color = CrexColors.TextSecondary, fontSize = 10.sp, letterSpacing = 1.sp)
                    XpChip((figures.split("-").getOrNull(0)?.toIntOrNull() ?: 0) * 20 + 5)
                }
            }
        }
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text(figures, color = CrexColors.TextPrimary, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, maxLines = 1, softWrap = false, modifier = Modifier.width(48.dp), textAlign = TextAlign.Center)
            Text(overs, color = CrexColors.TextSecondary, fontSize = 13.sp, maxLines = 1, softWrap = false, modifier = Modifier.width(36.dp), textAlign = TextAlign.Center)
            Text(econ, color = CrexColors.TextSecondary, fontSize = 13.sp, maxLines = 1, softWrap = false, modifier = Modifier.width(48.dp), textAlign = TextAlign.Center)
            Spacer(modifier = Modifier.width(24.dp))
        }
    }
}

/** Premium over summary: OVER label · ball circles · runs pill. Highlighted when current. */
@Composable
private fun OverChip(label: String, balls: List<String>, runs: Int, current: Boolean) {
    Row(
        modifier = Modifier
            .height(IntrinsicSize.Min)
            .clip(RoundedCornerShape(12.dp))
            .background(if (current) CrexColors.AccentBlue.copy(alpha = 0.06f) else CrexColors.Surface)
            .border(
                1.dp,
                if (current) CrexColors.AccentBlue.copy(alpha = 0.40f) else CrexColors.Border,
                RoundedCornerShape(12.dp)
            )
            .padding(horizontal = 10.dp, vertical = 7.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(9.dp)
    ) {
        // Fixed-width label so the divider lands in the same place for "1" and "10",
        // and the number sits centred beside the full-height divider line.
        Column(
            modifier = Modifier.widthIn(min = 26.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.Center
        ) {
            Text("OVER", color = CrexColors.TextMuted, fontSize = 7.sp, fontWeight = FontWeight.Bold, letterSpacing = 0.5.sp)
            Text(label, color = CrexColors.TextPrimary, fontSize = 13.sp, fontWeight = FontWeight.Black)
        }
        Box(Modifier.width(1.dp).fillMaxHeight().background(CrexColors.Border))
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(5.dp)) {
            balls.forEach { BallCircle(ball = it) }
        }
        // Over total — plain black "= N", not a coloured pill.
        Text(
            "= $runs",
            color = CrexColors.TextPrimary,
            fontSize = 13.sp,
            fontWeight = FontWeight.Black
        )
    }
}

/** Small green XP credit chip shown on batter/bowler rows. */
@Composable
private fun XpChip(xp: Int) {
    Box(
        modifier = Modifier
            .clip(RoundedCornerShape(6.dp))
            .background(CrexColors.AccentGreen.copy(alpha = 0.12f))
            .padding(horizontal = 6.dp, vertical = 1.dp)
    ) {
        Text("+$xp XP", color = CrexColors.AccentGreen, fontSize = 9.sp, fontWeight = FontWeight.Bold)
    }
}
