@file:OptIn(androidx.compose.foundation.ExperimentalFoundationApi::class)

package com.haraan.app.ui.matches

import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateDpAsState
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.animateIntAsState
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyListScope
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.SportsBasketball
import androidx.compose.material.icons.filled.SportsHandball
import androidx.compose.material.icons.filled.SportsTennis
import androidx.compose.material.icons.filled.SportsVolleyball
import androidx.compose.material.icons.outlined.MoreVert
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
import androidx.compose.ui.draw.drawWithContent
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.graphics.nativeCanvas
import androidx.compose.ui.graphics.toArgb
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.text.SpanStyle
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.text.withStyle
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp

/**
 * Cricket's match detail, made wearable by the other five sports.
 *
 * The five new sports shipped with their own chrome — a dark coloured hero, a flat tab strip —
 * and next to the cricket screen they read as a different, cheaper app. Cricket is the screen
 * that feels expensive, and the reasons are specific and portable: a light page, a real app bar
 * instead of a coloured banner, a hero that is a CARD INSIDE A CARD with a word-mark crawling
 * around the seam between them, display-cut numerals with tabular figures, and tabs that pin
 * under the hero with an underline that slides.
 *
 * None of that is cricket-specific, so it lives here as furniture the five sports share. What
 * they do NOT share is content: a volleyball set list, a basketball box score, a kabaddi raid
 * ledger and a tennis ladder stay five different objects on five different screens. The hero's
 * middle is a slot for exactly that reason.
 *
 * Each sport gets its own colour theme so two of them can never be mistaken at a glance, and
 * so none of them is mistaken for cricket.
 */
data class SportTheme(
    val label: String,
    val icon: ImageVector,
    /** The hero card's gradient — light, in the sport's own family. */
    val cardTop: Color,
    val cardBottom: Color,
    /** The big numerals, and the sliding tab underline. */
    val deep: Color,
    /** The second side's numerals — present, but yielding to the leader's colour. */
    val soft: Color,
    /** The one bright note: the crawling ribbon and the Score button's icon. */
    val spark: Color,
)

fun sportThemeFor(sport: String, fallbackLabel: String = ""): SportTheme = when (sport.lowercase()) {
    // Deep sea — the indoor-court blue-green nothing else on the platform wears.
    "volleyball" -> SportTheme(
        label = "Volleyball",
        icon = Icons.Filled.SportsVolleyball,
        cardTop = Color(0xFFD6F1F7), cardBottom = Color(0xFFA7DCEA),
        deep = Color(0xFF0B5E72), soft = Color(0xFF4F8494), spark = Color(0xFF06B6D4),
    )
    // Hardwood and leather.
    "basketball" -> SportTheme(
        label = "Basketball",
        icon = Icons.Filled.SportsBasketball,
        cardTop = Color(0xFFFCE8D2), cardBottom = Color(0xFFF5CBA0),
        deep = Color(0xFF9A3412), soft = Color(0xFFA9744E), spark = Color(0xFFF97316),
    )
    // Mat violet — kabaddi is played on a coloured mat, and reads as nothing else.
    "kabaddi" -> SportTheme(
        label = "Kabaddi",
        icon = Icons.Filled.SportsHandball,
        cardTop = Color(0xFFE7E2FB), cardBottom = Color(0xFFC7BEF4),
        deep = Color(0xFF4C1D95), soft = Color(0xFF7C6BA8), spark = Color(0xFF8B5CF6),
    )
    // Clay and grass.
    "tennis" -> SportTheme(
        label = "Tennis",
        icon = Icons.Filled.SportsTennis,
        cardTop = Color(0xFFE9F5D6), cardBottom = Color(0xFFC7E4A3),
        deep = Color(0xFF3F6212), soft = Color(0xFF6E8B4A), spark = Color(0xFF84CC16),
    )
    "badminton" -> SportTheme(
        label = "Badminton",
        icon = Icons.Filled.SportsTennis,
        cardTop = Color(0xFFFBE4EA), cardBottom = Color(0xFFF2C2CE),
        deep = Color(0xFF9F1239), soft = Color(0xFFAB6A7E), spark = Color(0xFFF43F5E),
    )
    else -> SportTheme(
        label = fallbackLabel.ifBlank { "Table tennis" },
        icon = Icons.Filled.SportsTennis,
        cardTop = Color(0xFFE2E5F6), cardBottom = Color(0xFFBFC5E8),
        deep = Color(0xFF312E81), soft = Color(0xFF6B6FA0), spark = Color(0xFF6366F1),
    )
}

/**
 * The app bar over the hero — cricket's, to the pixel.
 *
 * The five sports painted this bar in the sport's own colour, which is the single biggest
 * reason they looked like a different product: a coloured banner is what a template does; a
 * white bar carrying the fixture is what a scores app does.
 */
@Composable
fun CrexBoardTopBar(
    state: MatchUiState,
    theme: SportTheme,
    watching: Int,
    onBack: () -> Unit,
    onScore: () -> Unit,
    /** Null unless this viewer may see who is watching. */
    onWatchers: (() -> Unit)? = null,
) {
    Row(
        modifier = Modifier.fillMaxWidth().padding(horizontal = 12.dp, vertical = 8.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        CrexBarIcon(Icons.AutoMirrored.Filled.ArrowBack, "Back", onClick = onBack)
        Spacer(Modifier.width(12.dp))
        Row(modifier = Modifier.weight(1f), verticalAlignment = Alignment.CenterVertically) {
            Text(
                "${state.team1} vs ${state.team2}",
                color = CrexColors.TextPrimary,
                fontSize = 16.sp,
                fontWeight = FontWeight.Bold,
                letterSpacing = (-0.2).sp,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
                modifier = Modifier.weight(1f, fill = false),
            )
            if (state.isLive) {
                Spacer(Modifier.width(8.dp))
                CrexLivePill()
                if (watching > 0) {
                    Spacer(Modifier.width(6.dp))
                    WatchingPill(watching, onClick = onWatchers)
                }
            }
        }
        Spacer(Modifier.width(12.dp))
        if (state.canScore) {
            CrexScoreButton(theme, onScore)
            Spacer(Modifier.width(8.dp))
        }
        CrexBarIcon(theme.icon, theme.label)
        Spacer(Modifier.width(8.dp))
        CrexBarIcon(Icons.Outlined.MoreVert, "More")
    }
    Box(Modifier.fillMaxWidth().height(1.dp).background(Color(0xFFEAEEF3)))
}

@Composable
private fun CrexBarIcon(icon: ImageVector, desc: String, onClick: (() -> Unit)? = null) {
    Box(
        modifier = Modifier
            .size(38.dp)
            .clip(RoundedCornerShape(12.dp))
            .background(Color(0xFFF1F5FA))
            .border(1.dp, Color(0xFFE4EAF1), RoundedCornerShape(12.dp))
            .then(if (onClick != null) Modifier.clickable(onClick = onClick) else Modifier),
        contentAlignment = Alignment.Center,
    ) {
        Icon(icon, contentDescription = desc, tint = Color(0xFF334155), modifier = Modifier.size(19.dp))
    }
}

@Composable
private fun CrexLivePill() {
    val pulse = rememberInfiniteTransition(label = "live")
    val dotAlpha by pulse.animateFloat(
        initialValue = 1f, targetValue = 0.35f,
        animationSpec = infiniteRepeatable(tween(850), RepeatMode.Reverse), label = "dot",
    )
    Row(
        modifier = Modifier.clip(RoundedCornerShape(7.dp)).background(Color(0xFFFCE6E6))
            .padding(horizontal = 7.dp, vertical = 4.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(5.dp),
    ) {
        Box(Modifier.size(6.dp).clip(CircleShape).background(CrexColors.LivePulse.copy(alpha = dotAlpha)))
        Text(
            "LIVE", color = CrexColors.LivePulse, fontSize = 10.sp,
            fontWeight = FontWeight.ExtraBold, letterSpacing = 0.5.sp,
        )
    }
}

@Composable
private fun CrexScoreButton(theme: SportTheme, onClick: () -> Unit) {
    val view = LocalView.current
    Row(
        modifier = Modifier.clip(RoundedCornerShape(19.dp)).background(Color(0xFF0F1F33))
            .clickable { hapticConfirm(view); onClick() }
            .padding(start = 12.dp, end = 14.dp, top = 8.dp, bottom = 8.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(6.dp),
    ) {
        Icon(theme.icon, contentDescription = null, tint = theme.spark, modifier = Modifier.size(15.dp))
        Text("Score", color = Color.White, fontSize = 13.sp, fontWeight = FontWeight.SemiBold)
    }
}

/**
 * The hero: a white band with the sport's gradient card inset inside it, and the event
 * word-mark crawling around the seam between the two.
 *
 * `meta` is the line above the teams; `content` is the sport's own middle — the part that has
 * to differ, because a set score and a quarter-by-quarter total are not the same object.
 */
@Composable
fun CrexBoardHero(
    theme: SportTheme,
    meta: String,
    /** What the ribbon shouts, and the key that makes it surge — the newest recorded moment. */
    ribbonWord: String,
    ribbonColor: Color,
    ribbonKey: Any?,
    content: @Composable ColumnScope.() -> Unit,
) {
    // A scoring moment flashes the card and gives it a small heartbeat, the same way a
    // boundary does on the cricket hero. Keyed to the newest event, so it fires exactly once.
    val pulse = remember { Animatable(0f) }
    var first by remember { mutableStateOf(true) }
    LaunchedEffect(ribbonKey) {
        if (first) { first = false; return@LaunchedEffect }
        pulse.snapTo(1f)
        pulse.animateTo(0f, tween(900, easing = FastOutSlowInEasing))
    }
    val popScale = 1f + pulse.value * 0.04f

    Column(
        modifier = Modifier.fillMaxWidth().background(CrexColors.Background)
            .padding(horizontal = 14.dp, vertical = 8.dp),
    ) {
        val band = 18.dp
        Box(modifier = Modifier.fillMaxWidth()) {
            // The white label band behind the card — host for the crawling word-mark.
            Spacer(
                modifier = Modifier.matchParentSize()
                    .clip(RoundedCornerShape(26.dp))
                    .background(Color.White)
                    .border(1.dp, Color(0xFFE2E8F0), RoundedCornerShape(26.dp)),
            )
            Column(
                modifier = Modifier.fillMaxWidth().padding(band)
                    .graphicsLayer { scaleX = popScale; scaleY = popScale }
                    .clip(RoundedCornerShape(12.dp))
                    .background(Brush.verticalGradient(listOf(theme.cardTop, theme.cardBottom)))
                    .drawWithContent {
                        drawContent()
                        if (pulse.value > 0f) drawRect(ribbonColor.copy(alpha = pulse.value * 0.22f))
                    }
                    .padding(horizontal = 18.dp, vertical = 14.dp),
            ) {
                Text(
                    meta,
                    color = Color(0xFF334155).copy(alpha = 0.78f),
                    fontSize = 11.sp,
                    fontWeight = FontWeight.SemiBold,
                    textAlign = TextAlign.Center,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                    modifier = Modifier.fillMaxWidth(),
                )
                Spacer(Modifier.height(10.dp))
                content()
            }
            CrexSeamRibbon(
                modifier = Modifier.matchParentSize(),
                word = ribbonWord,
                color = ribbonColor,
                band = band,
                surgeKey = ribbonKey,
            )
        }
    }
}

/**
 * The word-mark crawling around the seam between the white band and the card.
 *
 * Drawn on a rounded-rect path so it turns each corner instead of being four straight strips,
 * and it SURGES on a new point before settling back to its calm crawl. This is the detail that
 * makes the cricket hero feel alive rather than printed, and it costs the five sports nothing
 * to inherit.
 */
@Composable
private fun CrexSeamRibbon(
    modifier: Modifier,
    word: String,
    color: Color,
    band: Dp,
    surgeKey: Any?,
) {
    val transition = rememberInfiniteTransition(label = "seam")
    val basePhase by transition.animateFloat(
        initialValue = 0f, targetValue = 1f,
        animationSpec = infiniteRepeatable(tween(4500, easing = LinearEasing), RepeatMode.Restart),
        label = "phase",
    )
    val boost = remember { Animatable(0f) }
    var first by remember { mutableStateOf(true) }
    LaunchedEffect(surgeKey) {
        if (first) { first = false; return@LaunchedEffect }
        boost.animateTo(boost.value + 2.2f, tween(950, easing = FastOutSlowInEasing))
        boost.snapTo(boost.value.mod(1f))
    }
    val argb = color.toArgb()

    Canvas(modifier = modifier) {
        val center = band.toPx() / 2f
        val radius = (26.dp.toPx() - center).coerceAtLeast(0f)
        val paint = android.graphics.Paint().apply {
            isAntiAlias = true
            this.color = argb
            textSize = 10.5.dp.toPx()
            letterSpacing = 0.12f
            typeface = android.graphics.Typeface.create(
                android.graphics.Typeface.DEFAULT_BOLD, android.graphics.Typeface.BOLD,
            )
        }
        val segment = "$word        "
        val perimeter = 2f * ((size.width - 2 * center) + (size.height - 2 * center))
        // Make the pattern tile the loop EXACTLY. A repeat that doesn't divide into the
        // perimeter leaves a part-glyph sitting on the join, which renders as a small dark
        // block on the left edge — the one flaw on an otherwise clean card. Stretching the
        // letter spacing by a fraction of a pixel per character makes the repeat periodic,
        // so the text meets itself with no seam at all.
        val rawWidth = paint.measureText(segment).coerceAtLeast(1f)
        val segs = Math.round(perimeter / rawWidth).coerceIn(2, 80)
        val target = perimeter / segs
        paint.letterSpacing += (target - rawWidth) / segment.length / paint.textSize
        val segWidth = paint.measureText(segment).coerceAtLeast(1f)
        val count = segs + 2
        val path = android.graphics.Path().apply {
            addRoundRect(
                center, center, size.width - center, size.height - center,
                radius, radius, android.graphics.Path.Direction.CW,
            )
        }
        val fm = paint.fontMetrics
        val vOffset = -(fm.ascent + fm.descent) / 2f
        val phase = (basePhase + boost.value).mod(1f)
        drawContext.canvas.nativeCanvas.drawTextOnPath(
            segment.repeat(count), path, -phase * segWidth, vOffset, paint,
        )
    }
}

/**
 * The tab strip — cricket's sliding underline, in the sport's own accent.
 *
 * Pinned as a sticky header so it stays put while the content runs under it. That layer is
 * what the five sports were missing: their tabs scrolled away with the hero, so once you were
 * reading a feed there was no way back without scrolling to the top.
 */
@Composable
fun CrexBoardTabs(
    tabs: List<String>,
    selectedTabIndex: Int,
    accent: Color,
    onTabSelected: (Int) -> Unit,
    modifier: Modifier = Modifier,
    /** Index that carries the pulsing live dot, or -1. */
    liveTab: Int = -1,
    liveActive: Boolean = false,
) {
    val view = LocalView.current
    val pulse = rememberInfiniteTransition(label = "livePulse")
    val dotAlpha by pulse.animateFloat(
        initialValue = 1f, targetValue = 0.25f,
        animationSpec = infiniteRepeatable(tween(900), RepeatMode.Reverse), label = "liveDot",
    )
    val indicatorWidth = 22.dp

    BoxWithConstraints(modifier = modifier.fillMaxWidth().background(CrexColors.Background)) {
        val tabWidth = maxWidth / tabs.size
        val indicatorOffset by animateDpAsState(
            targetValue = tabWidth * selectedTabIndex + (tabWidth - indicatorWidth) / 2,
            animationSpec = tween(260),
            label = "tabIndicator",
        )
        Column(modifier = Modifier.fillMaxWidth()) {
            Row(modifier = Modifier.fillMaxWidth()) {
                tabs.forEachIndexed { index, title ->
                    val isSelected = selectedTabIndex == index
                    val textColor by animateColorAsState(
                        targetValue = if (isSelected) CrexColors.TextPrimary else CrexColors.TextSecondary,
                        label = "tabText",
                    )
                    Box(
                        contentAlignment = Alignment.Center,
                        modifier = Modifier.weight(1f)
                            .clickable {
                                if (index != selectedTabIndex) hapticTick(view)
                                onTabSelected(index)
                            }
                            .padding(vertical = 12.dp),
                    ) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            if (index == liveTab && liveActive) {
                                Box(
                                    Modifier.size(6.dp)
                                        .graphicsLayer { alpha = dotAlpha }
                                        .clip(CircleShape)
                                        .background(CrexColors.AccentRed),
                                )
                                Spacer(Modifier.width(5.dp))
                            }
                            Text(title, color = textColor, fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
                        }
                    }
                }
            }
            Box(modifier = Modifier.fillMaxWidth().height(2.dp)) {
                Box(
                    modifier = Modifier.fillMaxWidth().height(1.dp)
                        .align(Alignment.BottomCenter).background(CrexColors.Border),
                )
                Box(
                    modifier = Modifier.offset(x = indicatorOffset).width(indicatorWidth).height(2.dp)
                        .clip(RoundedCornerShape(2.dp)).background(accent),
                )
            }
        }
    }
}

/**
 * What the seam ribbon says.
 *
 * Cricket's band is calm almost all the time and only shouts on a four, a six or a wicket —
 * which is exactly why the shout lands. A band that yells FREE THROW at every free throw is
 * just a texture, and a repeated word around a card is the most obvious tell that nobody chose
 * it. So: a notable moment gets the sport's own word and a surge, and everything else gets the
 * house strip, unchanged, the way it looks between wickets.
 *
 * The third value is the surge key. It is null when nothing notable happened, so the card
 * neither flashes nor kicks the crawl — silence is the default state, not an oversight.
 */
fun crexRibbonFor(board: SportBoard, theme: SportTheme): Triple<String, Color, Any?> {
    val calm = Triple("HARAAN  LIVE", Color(0xFF64748B).copy(alpha = 0.55f), null)
    val last = board.feed.firstOrNull() ?: return calm

    return when (board.sport.lowercase()) {
        // A three changes a game; a free throw is bookkeeping.
        "basketball" -> if (last.value >= 3) {
            Triple("THREE", theme.deep, last.sequence)
        } else {
            calm
        }
        "kabaddi" -> when (last.detail.lowercase()) {
            "all_out" -> Triple("ALL OUT", Color(0xFFB91C1C), last.sequence)
            "super_raid" -> Triple("SUPER RAID", Color(0xFFD97706), last.sequence)
            else -> calm
        }
        // Rally sports: a point is a point. What is worth shouting is the SITUATION — one
        // rally from taking the set — which the board can prove rather than guess.
        else -> {
            val cur = board.current
            val setPoint = cur != null && board.target > 0 &&
                (cur.first >= board.target - 1 || cur.second >= board.target - 1)
            if (setPoint) {
                Triple("${board.setNoun.uppercase()} POINT", Color(0xFFD97706), last.sequence)
            } else {
                calm
            }
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────────
//  Hero vocabulary
//
//  Not a hero layout — the PIECES a hero is built from. Cricket's hero is a specific
//  arrangement of exactly this material (display numerals, a crest tag, a chip), and each
//  sport below arranges the same material into its own object: a set ladder, a quarter
//  table, a mat, a tennis grid, a serve meter. Shared material, five different screens.
// ─────────────────────────────────────────────────────────────────────────────────

/** The display numeral: tabular figures, tight tracking, rolling to its new value. */
@Composable
fun HeroNumeral(value: Int, color: Color, size: Int, roll: Boolean = true) {
    val shown by animateIntAsState(
        targetValue = value,
        animationSpec = tween(if (roll) 550 else 0, easing = FastOutSlowInEasing),
        label = "heroNumeral",
    )
    Text(
        "$shown",
        color = color,
        fontSize = size.sp,
        fontFamily = com.haraan.app.theme.ArchivoDisplay,
        letterSpacing = (-1).sp,
        maxLines = 1,
        style = TextStyle(fontFeatureSettings = "tnum"),
    )
}

/** The same numeral for scores that are words — tennis' 40 and AD, a set label. */
@Composable
fun HeroNumeralText(text: String, color: Color, size: Int) {
    Text(
        text,
        color = color,
        fontSize = size.sp,
        fontFamily = com.haraan.app.theme.ArchivoDisplay,
        letterSpacing = (-1).sp,
        maxLines = 1,
        style = TextStyle(fontFeatureSettings = "tnum"),
    )
}

/** Crest and code, with a lit ring when this side is serving or raiding. */
@Composable
fun HeroSideTag(
    name: String,
    logo: String,
    active: Boolean,
    modifier: Modifier = Modifier,
    alignEnd: Boolean = false,
    crest: Int = 34,
    /** What to print beside the crest. Blank falls back to the short code. */
    label: String = "",
    activeColor: Color = Color(0xFFF59E0B),
) {
    val code = teamShortCode(name)
    // A crest that fell back to a monogram, printed next to that same monogram, reads as a
    // bug. Where there is room the tag carries the team's real name instead.
    val shown = label.ifBlank { code }
    Row(
        modifier = modifier,
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = if (alignEnd) Arrangement.End else Arrangement.Start,
    ) {
        if (alignEnd) {
            Text(
                shown, color = Color(0xFF1E293B), fontSize = 13.sp, fontWeight = FontWeight.Bold,
                maxLines = 1, overflow = TextOverflow.Ellipsis,
                modifier = Modifier.weight(1f, fill = false),
            )
            Spacer(Modifier.width(8.dp))
        }
        Box(contentAlignment = Alignment.Center) {
            if (active) {
                Box(
                    Modifier.size((crest + 8).dp).clip(CircleShape)
                        .background(activeColor.copy(alpha = 0.24f)),
                )
            }
            HeroCrest(code, Modifier.size(crest.dp), iconRef = logo)
        }
        if (!alignEnd) {
            Spacer(Modifier.width(8.dp))
            Text(
                shown, color = Color(0xFF1E293B), fontSize = 13.sp, fontWeight = FontWeight.Bold,
                maxLines = 1, overflow = TextOverflow.Ellipsis,
                modifier = Modifier.weight(1f, fill = false),
            )
        }
    }
}

/** A dark token on the hero card — a period, a half, a game number. */
@Composable
fun HeroChip(text: String, background: Color = Color(0xFF1E293B), contentColor: Color = Color.White) {
    Box(
        Modifier.clip(RoundedCornerShape(9.dp)).background(background)
            .padding(horizontal = 10.dp, vertical = 5.dp),
    ) {
        Text(text, color = contentColor, fontSize = 11.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = 0.4.sp, maxLines = 1)
    }
}

/** A small caps label used inside the hero card. */
@Composable
fun HeroLabel(text: String, modifier: Modifier = Modifier, align: TextAlign? = null) {
    Text(
        text.uppercase(),
        color = Color(0xFF334155).copy(alpha = 0.55f),
        fontSize = 9.sp,
        fontWeight = FontWeight.Bold,
        letterSpacing = 1.4.sp,
        maxLines = 1,
        textAlign = align,
        modifier = modifier,
    )
}

/** The hairline that divides rows inside a hero card — light enough to belong to it. */
@Composable
fun HeroRule(modifier: Modifier = Modifier) {
    Box(modifier.fillMaxWidth().height(1.dp).background(Color.White.copy(alpha = 0.45f)))
}

/**
 * A content row on a board screen: the page's side margins, plus the entrance.
 *
 * The margins live here rather than in the list's contentPadding because the hero and the
 * pinned tab strip have to run edge to edge — a tab underline inset by 14dp stops looking
 * like part of the page.
 */
fun LazyListScope.crexItem(tab: Int, content: @Composable () -> Unit) = item {
    Column(
        Modifier.fillMaxWidth().boardTabEnter(tab).padding(horizontal = 14.dp, vertical = 6.dp),
    ) { content() }
}
