package com.haraan.app.ui.matches

import androidx.compose.animation.core.*
import androidx.compose.foundation.*
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.*
import androidx.compose.foundation.shape.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.*
import androidx.compose.ui.draw.*
import androidx.compose.ui.geometry.*
import androidx.compose.ui.graphics.*
import androidx.compose.ui.graphics.drawscope.*
import androidx.compose.ui.text.font.*
import androidx.compose.ui.text.style.*
import androidx.compose.ui.text.withStyle
import androidx.compose.ui.text.SpanStyle
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.unit.*
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.graphics.nativeCanvas
import android.graphics.BitmapFactory

// ─────────────────────────────────────────────
//  DESIGN TOKENS
// ─────────────────────────────────────────────
object CrexColors {
    val Background      = Color(0xFFF4F7FB) // Custom premium slate-tinted background (was 0xFF0A0E14)
    val Surface         = Color(0xFFFFFFFF) // Pure white cards (was 0xFF1C2229)
    val SurfaceElevated = Color(0xFFFFFFFF) 
    val Border          = Color(0xFFE2E8F0) // Clean divider borders (was 0x1AFFFFFF)
    val AccentGreen     = Color(0xFF2563EB) // Haraan Green/Mint
    val AccentYellow    = Color(0xFFF59E0B) // Gold/Amber
    val AccentRed       = Color(0xFFEF4444) // Red/Coral
    val AccentBlue      = Color(0xFF2563EB) // Brand blue
    val TextPrimary     = Color(0xFF0F172A) // Midnight slate (was 0xFFFFFFFF)
    val TextSecondary   = Color(0xFF475569) // Cool grey (was 0xFFA0A5AD)
    val TextMuted       = Color(0xFF94A3B8) // Slate 400 (was 0xFF76777D)
    val LivePulse       = Color(0xFFEF4444)
    val SixBall         = Color(0xFFF59E0B)
    val FourBall        = Color(0xFF2563EB)
    val WicketBall      = Color(0xFFEF4444)
    val DotBall         = Color(0xFFE2E8F0)
    val NormalBall      = Color(0xFFCBD5E1)
}

@Composable
fun TeamLogo(team: String, logoUrl: String, modifier: Modifier = Modifier) {
    // logoUrl may be an uploaded image URL, a default emblem key (action1..4), or blank.
    val emblemRes = com.haraan.app.ui.matches.create.emblemDrawableFor(logoUrl)
    // Frame uploaded photos / emblems in a white roundel with a defined ring, so a logo with
    // a light background reads as a distinct crest against the card instead of blending in.
    if (logoUrl.startsWith("http", ignoreCase = true)) {
        Box(
            modifier = modifier
                .clip(CircleShape)
                .background(Color.White)
                .border(1.dp, Color(0xFFCBD5E1), CircleShape),
            contentAlignment = Alignment.Center,
        ) {
            coil.compose.AsyncImage(
                model = logoUrl,
                contentDescription = team,
                contentScale = androidx.compose.ui.layout.ContentScale.Crop,
                modifier = Modifier.fillMaxSize().clip(CircleShape),
            )
        }
        return
    }
    if (emblemRes != null) {
        Box(
            modifier = modifier
                .clip(CircleShape)
                .background(Color.White)
                .border(1.dp, Color(0xFFCBD5E1), CircleShape),
            contentAlignment = Alignment.Center,
        ) {
            androidx.compose.foundation.Image(
                painter = androidx.compose.ui.res.painterResource(emblemRes),
                contentDescription = team,
                contentScale = androidx.compose.ui.layout.ContentScale.Crop,
                modifier = Modifier.fillMaxSize().clip(CircleShape),
            )
        }
        return
    }
    val (bgColor, textColor) = when (team.uppercase()) {
        "GT"  -> Color(0xFF1B3F9E) to Color.White
        "RCB" -> Color(0xFF8B0000) to Color(0xFFFFD700)
        "RR"  -> Color(0xFF862D86) to Color(0xFFFFC0CB)
        "MI"  -> Color(0xFF005DA0) to Color(0xFFFFD700)
        "CSK" -> Color(0xFFF5A623) to Color(0xFF003E7E)
        "SRH" -> Color(0xFFEF5C1B) to Color.White
        "KKR" -> Color(0xFF3B1F6B) to Color(0xFFFFD700)
        "DC"  -> Color(0xFF004C97) to Color(0xFFEF1C21)
        "PBKS"-> Color(0xFFCC0001) to Color(0xFFFDB913)
        "PAK" -> Color(0xFF115740) to Color.White
        "AUS" -> Color(0xFF00843D) to Color(0xFFFFCD00)
        "IND" -> Color(0xFF003580) to Color(0xFFFF9933)
        else  -> Color(0xFF1B3F9E) to Color.White
    }
    Box(
        modifier = modifier
            .clip(CircleShape)
            .background(bgColor)
            .border(1.dp, Color(0x22FFFFFF), CircleShape),
        contentAlignment = Alignment.Center
    ) {
        Text(
            text = team.take(3),
            color = textColor,
            fontSize = 11.sp,
            fontWeight = FontWeight.ExtraBold
        )
    }
}

/**
 * Guard against impossible scores reaching the hero (demo runaways / bad backend rows).
 * Clamps wickets to 0..10 so "504/30" can only ever render as "504/10".
 */
fun sanitizeScore(raw: String): String {
    val slash = raw.indexOf('/')
    if (slash < 0) return raw
    val runs = raw.substring(0, slash).trim()
    // Keep any trailing suffix (e.g. " (19.2)") that may follow the wickets.
    val rest = raw.substring(slash + 1).trim()
    val wktsStr = rest.takeWhile { it.isDigit() }
    val wkts = wktsStr.toIntOrNull() ?: return raw
    val suffix = rest.removePrefix(wktsStr)
    return "$runs/${wkts.coerceAtMost(10)}$suffix"
}

/**
 * Compact team code for the hero card, derived from whatever name the backend sends.
 * Multi-word names → initials ("Royal Strikers" → "RS"). Single mashed names get split
 * on common South-Indian place suffixes so e.g. "keerthipalle" → "KP", "payasampalle" →
 * "PP". Anything already short (≤4 chars, all caps) is passed through untouched, so a
 * code the server already shortened stays as-is.
 */
fun teamShortCode(raw: String): String {
    val name = raw.trim()
    if (name.isEmpty()) return "?"
    if (name.length <= 4 && name == name.uppercase()) return name

    val words = name.split(Regex("[\\s\\-_]+")).filter { it.isNotBlank() }
    if (words.size >= 2) {
        return words.take(4).joinToString("") { it.first().uppercaseChar().toString() }
    }

    val w = words.first().lowercase().filter { it.isLetterOrDigit() }
    val suffixes = listOf(
        "palle", "palli", "pally", "halli", "nagaram", "nagar", "puram", "palem",
        "valasa", "cherla", "konda", "gudem", "peta", "pet", "wada", "vada",
        "giri", "puri", "pur", "bad"
    )
    for (suf in suffixes) {
        if (w.length > suf.length + 1 && w.endsWith(suf)) {
            val stem = w.dropLast(suf.length)
            return (stem.first().uppercaseChar().toString() + suf.first().uppercaseChar()).take(3)
        }
    }
    return w.take(3).uppercase()
}

/** Real team crest from bundled assets (logos/{code}.png) on a white roundel; monogram fallback. */
@Composable
fun HeroCrest(monogram: String, modifier: Modifier = Modifier, iconRef: String = "") {
    val context = LocalContext.current
    // A team icon chosen at create time wins: a default emblem key (action1..4) maps to a
    // bundled image; an uploaded logo arrives as an http URL.
    val emblemRes = com.haraan.app.ui.matches.create.emblemDrawableFor(iconRef)
    val code = monogram.lowercase().take(3)
    val bitmap = remember(code) {
        runCatching {
            context.assets.open("logos/$code.png").use { BitmapFactory.decodeStream(it) }?.asImageBitmap()
        }.getOrNull()
    }
    Box(
        modifier = modifier
            .clip(CircleShape)
            .background(Color.White)
            .border(1.5.dp, Color(0xFFCBD5E1), CircleShape),
        contentAlignment = Alignment.Center
    ) {
        when {
            emblemRes != null -> androidx.compose.foundation.Image(
                painter = androidx.compose.ui.res.painterResource(emblemRes),
                contentDescription = monogram,
                modifier = Modifier.fillMaxSize().clip(CircleShape),
                contentScale = ContentScale.Crop
            )
            iconRef.startsWith("http", ignoreCase = true) -> coil.compose.AsyncImage(
                model = iconRef,
                contentDescription = monogram,
                modifier = Modifier.fillMaxSize().clip(CircleShape),
                contentScale = ContentScale.Crop
            )
            bitmap != null -> androidx.compose.foundation.Image(
                bitmap = bitmap,
                contentDescription = monogram,
                modifier = Modifier.fillMaxSize().padding(7.dp),
                contentScale = ContentScale.Fit
            )
            else -> Text(monogram.take(3), color = CrexColors.AccentBlue, fontSize = 13.sp, fontWeight = FontWeight.ExtraBold)
        }
    }
}

@Composable
private fun BattingScore(
    modifier: Modifier,
    name: String,
    logo: String,
    score: String,
    overs: String,
    stats: List<Pair<String, String>>,
    batting: Boolean = true,
) {
    val code = teamShortCode(name)
    Column(modifier = modifier) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            HeroCrest(code, Modifier.size(26.dp), iconRef = logo)
            Spacer(Modifier.width(8.dp))
            Text(code, color = HeroInk, fontSize = 15.sp, fontWeight = FontWeight.Bold, letterSpacing = 0.4.sp)
            // Once the match is over nobody is batting — the line just names the side.
            if (batting) Text("  ·  batting", color = HeroInk.copy(alpha = 0.6f), fontSize = 11.5.sp, fontWeight = FontWeight.Medium)
        }
        Spacer(Modifier.height(4.dp))
        val slash = score.indexOf('/')
        val runs = if (slash >= 0) score.substring(0, slash) else score
        val wkts = if (slash >= 0) score.substring(slash) else ""
        // Roll the runs up to the new total instead of hard-swapping, so a boundary reads
        // as the number *climbing*. Non-numeric scores fall through as-is.
        val runsInt = runs.toIntOrNull()
        val animatedRuns by androidx.compose.animation.core.animateIntAsState(
            targetValue = runsInt ?: 0,
            animationSpec = androidx.compose.animation.core.tween(550, easing = androidx.compose.animation.core.FastOutSlowInEasing),
            label = "runsRoll"
        )
        val tnum = androidx.compose.ui.text.TextStyle(fontFeatureSettings = "tnum")
        Row(verticalAlignment = Alignment.Bottom) {
            Text(
                if (runsInt != null) animatedRuns.toString() else runs,
                color = HeroInk, fontSize = 46.sp,
                fontFamily = com.haraan.app.theme.ArchivoDisplay,
                letterSpacing = (-1.5).sp, style = tnum,
            )
            if (wkts.isNotEmpty()) {
                Text(
                    wkts, color = HeroInk.copy(alpha = 0.55f), fontSize = 24.sp,
                    fontFamily = com.haraan.app.theme.ArchivoDisplay,
                    modifier = Modifier.padding(bottom = 6.dp), style = tnum,
                )
            }
            if (overs.isNotBlank()) {
                Text(
                    "  ($overs)", color = HeroInk.copy(alpha = 0.75f), fontSize = 15.sp,
                    fontWeight = FontWeight.SemiBold,
                    modifier = Modifier.padding(bottom = 9.dp), style = tnum,
                )
            }
        }
        if (stats.isNotEmpty()) {
            Spacer(Modifier.height(2.dp))
            Row(horizontalArrangement = Arrangement.spacedBy(14.dp)) {
                stats.forEach { (label, value) -> HeroStat(label, value) }
            }
        }
    }
}

/** The fielding side on one quiet line: crest, code, and its score or "Yet to bat". */
@Composable
private fun FieldingLine(name: String, logo: String, score: String) {
    val code = teamShortCode(name)
    Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
        HeroCrest(code, Modifier.size(20.dp), iconRef = logo)
        Spacer(Modifier.width(8.dp))
        Text(code, color = HeroInk.copy(alpha = 0.82f), fontSize = 13.sp, fontWeight = FontWeight.SemiBold, letterSpacing = 0.4.sp)
        Spacer(Modifier.weight(1f))
        Text(
            score.ifBlank { "Yet to bat" },
            color = HeroInk.copy(alpha = if (score.isBlank()) 0.6f else 0.85f),
            fontSize = 13.sp, fontWeight = FontWeight.SemiBold,
            style = androidx.compose.ui.text.TextStyle(fontFeatureSettings = "tnum"),
        )
    }
}

private fun overBallInk(b: String): Color = when {
    b == "4" -> CrexColors.AccentBlue
    b == "6" -> Color(0xFF15803D)
    b.startsWith("W") -> CrexColors.WicketBall
    b == "•" || b == "0" -> Color(0xFF94A3B8)
    else -> Color(0xFF0F172A)
}

/**
 * The over so far, one disc per delivery and an empty ring for each ball still to come —
 * the same strip a scorer keeps in the book, so a viewer can read the over at a glance.
 */
@Composable
private fun ThisOverStrip(balls: List<String>) {
    val shown = balls.takeLast(7)
    val slots = maxOf(6, shown.size)
    Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
        Text("THIS OVER", color = HeroInk.copy(alpha = 0.6f), fontSize = 9.sp, fontWeight = FontWeight.Bold, letterSpacing = 1.2.sp)
        Spacer(Modifier.width(10.dp))
        Row(horizontalArrangement = Arrangement.spacedBy(5.dp)) {
            for (i in 0 until slots) {
                val b = shown.getOrNull(i)
                if (b == null) {
                    Box(Modifier.size(22.dp).border(1.dp, HeroInk.copy(alpha = 0.28f), CircleShape))
                } else {
                    val ink = overBallInk(b)
                    val boundary = b == "4" || b == "6" || b.startsWith("W")
                    Box(
                        contentAlignment = Alignment.Center,
                        modifier = Modifier
                            .size(22.dp)
                            .clip(CircleShape)
                            .background(Color.White)
                            .border(if (boundary) 1.5.dp else 1.dp, if (boundary) ink else HeroHairline, CircleShape),
                    ) {
                        Text(b, color = ink, fontSize = if (b.length > 2) 7.5.sp else 9.5.sp, fontWeight = FontWeight.Bold, maxLines = 1)
                    }
                }
            }
        }
    }
}

@Composable
private fun HeroLastBall(state: MatchUiState) {
    Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        modifier = Modifier.padding(horizontal = 6.dp)
    ) {
        val inPlay = state.ballInPlay && state.isLive
        if (inPlay) {
            Text(
                "BOWLING", color = HeroInk,
                fontSize = 9.sp, fontWeight = FontWeight.Bold, letterSpacing = 1.5.sp
            )
            Spacer(Modifier.height(8.dp))
        }
        val lastBall = state.thisOver.lastOrNull() ?: "•"
        // Identity of the delivery, not its value: the screen refetches on a poll and on
        // every realtime nudge, and keyed on "6" a six would replay its burst every few
        // seconds. Overs advance per legal ball and the over's ball list grows per
        // delivery, so the pair moves exactly once per ball and never on a refetch.
        val ballKey = state.overs + "|" + state.thisOver.size + "|" + lastBall
        var burst by remember { mutableStateOf<BurstKind?>(null) }
        val fired = rememberBurst(ballKey = ballKey, lastBall = lastBall, isLive = state.isLive)
        // Keyed on the EVENT (kind + ball), not the kind: back-to-back sixes are two
        // moments, and keying on "SIX" would leave the second one silent.
        LaunchedEffect(fired?.ballKey) { if (fired != null) burst = fired.kind }

        // The burst tracker above stays composed through the delivery on purpose: if it
        // left with the glyph, it would re-seed on return and the FOUR that ends the
        // animation would land silently.
        Box(contentAlignment = Alignment.Center) {
            if (inPlay) {
                // The scorer tapped BALL: the bowler is running in. Loops until the result
                // lands, then the slot hands back to the last-ball glyph and its burst.
                DeliveryInPlay(Modifier.size(width = 84.dp, height = 66.dp))
            } else {
                // The umpire gives the signal for the last ball; the caption under him
                // names it, or reads the runs when there is no signal to give.
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    UmpireSignal(
                        ball = lastBall,
                        ballKey = ballKey,
                        holdMs = HeroFlashMs,
                        modifier = Modifier.size(width = 100.dp, height = 118.dp),
                    )
                    val call = UmpireCall.of(lastBall)
                    val caption = when {
                        state.thisOver.isEmpty() -> "LAST BALL"
                        call != UmpireCall.STAND -> call.caption
                        lastBall == "•" || lastBall == "0" -> "DOT BALL"
                        lastBall == "1" -> "1 RUN"
                        else -> "$lastBall RUNS"
                    }
                    Text(
                        caption, color = HeroInk,
                        fontSize = 15.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = 1.6.sp, maxLines = 1,
                    )
                }
            }
            // Overlaid, so the burst can spill past the centre column without moving any
            // of the hero's layout — a score that shifted sideways on every six would be
            // worse than no animation.
            BoundaryBurst(
                kind = burst,
                modifier = Modifier.size(190.dp),
                onFinished = { burst = null },
            )
        }
    }
}

/**
 * A ball being bowled, seen from behind the bowler: a pitch strip narrowing to the far
 * stumps, the ball released big and near, dipping to pitch two-thirds of the way down and
 * carrying on to the batter. Loops until the result arrives.
 *
 * Drawn, not a Lottie or an emoji — it has to sit inside the hero's centre column at
 * 84dp and read at a glance, and it takes the board's own colours.
 */
@Composable
private fun DeliveryInPlay(modifier: Modifier = Modifier) {
    val loop = rememberInfiniteTransition(label = "delivery")
    val t by loop.animateFloat(
        initialValue = 0f, targetValue = 1f,
        animationSpec = infiniteRepeatable(tween(1300, easing = LinearEasing), RepeatMode.Restart),
        label = "t"
    )
    val pitch = Color(0xFFC8A96B)
    val crease = HeroInk.copy(alpha = 0.55f)
    val stump = HeroInk.copy(alpha = 0.8f)
    val ballRed = Color(0xFFC62828)

    Canvas(modifier = modifier) {
        val w = size.width
        val h = size.height
        // Pitch: a trapezoid, far end (batter) at the top, near end (bowler) at the bottom.
        val farHalf = w * 0.14f
        val nearHalf = w * 0.34f
        val top = h * 0.12f
        val bottom = h * 0.98f
        val cx = w / 2f
        val strip = androidx.compose.ui.graphics.Path().apply {
            moveTo(cx - farHalf, top); lineTo(cx + farHalf, top)
            lineTo(cx + nearHalf, bottom); lineTo(cx - nearHalf, bottom); close()
        }
        drawPath(strip, pitch.copy(alpha = 0.9f))
        // Popping creases at both ends.
        drawLine(crease, androidx.compose.ui.geometry.Offset(cx - farHalf * 1.15f, top + h * 0.06f),
            androidx.compose.ui.geometry.Offset(cx + farHalf * 1.15f, top + h * 0.06f), strokeWidth = 1.2f * density)
        drawLine(crease, androidx.compose.ui.geometry.Offset(cx - nearHalf * 0.95f, bottom - h * 0.08f),
            androidx.compose.ui.geometry.Offset(cx + nearHalf * 0.95f, bottom - h * 0.08f), strokeWidth = 1.5f * density)
        // Far stumps — the batter's end, where the ball is headed.
        for (i in -1..1) {
            val x = cx + i * farHalf * 0.28f
            drawLine(stump, androidx.compose.ui.geometry.Offset(x, top - h * 0.10f),
                androidx.compose.ui.geometry.Offset(x, top + h * 0.04f), strokeWidth = 1.6f * density)
        }

        // The delivery occupies the first 80% of the loop; the rest is the run-up pause.
        val p = (t / 0.8f).coerceAtMost(1f)
        if (t < 0.8f) {
            val bounceAt = 0.62f
            val releaseY = bottom - h * 0.16f
            val stumpsY = top + h * 0.01f
            val groundY = releaseY + (stumpsY - releaseY) * p
            // Height above the pitch: falls from the hand to pitch, then rises to bail height.
            val lift = if (p < bounceAt) {
                val q = p / bounceAt
                (1f - q * q) * h * 0.30f
            } else {
                val q = (p - bounceAt) / (1f - bounceAt)
                (q * (2f - q)) * h * 0.10f
            }
            val y = groundY - lift
            // Perspective: the ball shrinks as it travels away.
            val r = (5.2f - 2.4f * p) * density
            // Shadow on the pitch, so the bounce reads as a bounce.
            drawOval(
                Color.Black.copy(alpha = 0.18f),
                topLeft = androidx.compose.ui.geometry.Offset(cx - r, groundY - r * 0.35f),
                size = androidx.compose.ui.geometry.Size(r * 2f, r * 0.7f)
            )
            // A dust puff where it pitches.
            if (p in bounceAt..(bounceAt + 0.14f)) {
                val k = (p - bounceAt) / 0.14f
                drawCircle(
                    pitch.copy(alpha = 0.55f * (1f - k)),
                    radius = r * (1.2f + k * 2.2f),
                    center = androidx.compose.ui.geometry.Offset(cx, groundY)
                )
            }
            drawCircle(ballRed, radius = r, center = androidx.compose.ui.geometry.Offset(cx, y))
            drawLine(
                Color.White.copy(alpha = 0.8f),
                androidx.compose.ui.geometry.Offset(cx - r * 0.7f, y),
                androidx.compose.ui.geometry.Offset(cx + r * 0.7f, y),
                strokeWidth = 0.9f * density
            )
        }
    }
}

@Composable
private fun HeroStat(label: String, value: String) {
    Text(
        buildAnnotatedString {
            withStyle(SpanStyle(color = HeroInk.copy(alpha = 0.62f), fontWeight = FontWeight.SemiBold, letterSpacing = 0.6.sp)) { append("$label  ") }
            withStyle(SpanStyle(color = HeroInk, fontWeight = FontWeight.Bold)) { append(value) }
        },
        fontSize = 10.5.sp
    )
}

/** Ink on the hero's pale blue wash — a deep navy so it stays in the blue family. */
private val HeroInk = Color(0xFF0F2A5C)
private val HeroHairline = Color(0xFFCFDDF6)
/** How long the card flashes on a 4, 6 or wicket before the umpire gives the signal. */
private const val HeroFlashMs = 1000L

// ─────────────────────────────────────────────
//  1. LIVE SCORE CARD
// ─────────────────────────────────────────────
@Composable
fun LiveScoreCard(state: MatchUiState, modifier: Modifier = Modifier) {
    val breathe = rememberInfiniteTransition(label = "breathe")
    val glow by breathe.animateFloat(
        initialValue = 0.15f, targetValue = 0.40f,
        animationSpec = infiniteRepeatable(tween(1600), RepeatMode.Reverse),
        label = "glow"
    )

    // ── Live-event reaction: when a new ball is a 4/6/W, the hero card flashes a colour
    // wash and gives a tiny scale "pop". The haptic itself is fired by ScoringRibbon, so
    // here we only add the visual payoff (keyed to the same thisOver signal). ──
    val pulse = remember { Animatable(0f) }
    var pulseColor by remember { mutableStateOf(Color.Transparent) }
    var firstBall by remember { mutableStateOf(true) }
    LaunchedEffect(state.thisOver) {
        if (firstBall) { firstBall = false; return@LaunchedEffect }
        val flash = when (UmpireCall.of(state.thisOver.lastOrNull().orEmpty())) {
            UmpireCall.FOUR -> CrexColors.FourBall
            UmpireCall.SIX -> Color(0xFF15803D)
            UmpireCall.OUT -> CrexColors.WicketBall
            else -> null
        }
        if (flash != null) {
            // One second of flash — two quick blinks, then a fade — and only then does the
            // umpire come up with the signal (he waits out [HeroFlashMs]).
            pulseColor = flash
            pulse.snapTo(0f)
            pulse.animateTo(1f, tween(80))
            pulse.animateTo(0.3f, tween(170))
            pulse.animateTo(1f, tween(100))
            pulse.animateTo(0f, tween(HeroFlashMs.toInt() - 350, easing = FastOutSlowInEasing))
        }
    }
    // 1f pulse → ~4% larger, settling back — a subtle heartbeat, not a bounce.
    val popScale = 1f + pulse.value * 0.02f

    Column(
        modifier = modifier
            .fillMaxWidth()
            .background(CrexColors.Background)
            .padding(horizontal = 14.dp, vertical = 8.dp)
    ) {
      val band = 18.dp
      Box(modifier = Modifier.fillMaxWidth()) {
        // White label band behind the card — the host for the scrolling event ribbon
        Spacer(
            modifier = Modifier
                .matchParentSize()
                .clip(RoundedCornerShape(RibbonCardRadius))
                .background(Color.White)
                .border(1.dp, Color(0xFFE2E8F0), RoundedCornerShape(RibbonCardRadius))
        )
        // Gradient hero card, inset by `band` so the white ring shows around it
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(band)
                .graphicsLayer { scaleX = popScale; scaleY = popScale }
                .clip(RoundedCornerShape(RibbonCardRadius - band))
                .background(
                    // Brand blue thinned with white: a soft wash that still reads as Haraan,
                    // not a solid slab of colour.
                    Brush.linearGradient(
                        0f to Color(0xFFF3F7FF),
                        1f to Color(0xFFE2ECFE),
                    )
                )
                .drawWithContent {
                    drawContent()
                    if (pulse.value > 0f) drawRect(pulseColor.copy(alpha = pulse.value * 0.42f))
                }
                .border(1.dp, HeroHairline, RoundedCornerShape(RibbonCardRadius - band))
                .padding(horizontal = 18.dp, vertical = 14.dp)
        ) {
                // Innings replayed from the ball log are the truth for who is in and what the
                // first side made; the flat score fields only guess the batting side from the
                // last over's tag, which in a chase can leave the first total on the wrong row.
                val cards = state.inningsCards
                val current = cards.lastOrNull()
                val battingIsTeam2 = (current?.battingTeam ?: state.battingTeam) == 2
                val firstInnings = if (current != null) cards.firstOrNull { it !== current && it.battingTeam != current.battingTeam } else null
                val battingScore = current?.let { "${it.runs}/${it.wickets}" } ?: sanitizeScore(state.score)
                val battingOvers = current?.overs?.ifBlank { null } ?: state.overs
                val fieldingScore = when {
                    firstInnings != null -> "${firstInnings.runs}/${firstInnings.wickets} (${firstInnings.overs})"
                    // Only one innings so far: the other side hasn't batted, whatever "0"
                    // the flat field carries.
                    current != null || state.innings <= 1 -> sanitizeScore(state.opponentScore).let {
                        if (it.trim() in setOf("", "0", "0/0")) "" else it
                    }
                    else -> sanitizeScore(state.opponentScore)
                }
                // The chase, worked from the same two innings the Live tab's chase card uses.
                val chase = if (firstInnings != null && current != null && state.isLive) {
                    val maxOvers = Regex("(\\d+)").find(state.competition)?.value?.toIntOrNull()?.takeIf { it > 0 } ?: 20
                    val bowled = current.overs.split(".").let { (it.getOrNull(0)?.toIntOrNull() ?: 0) * 6 + (it.getOrNull(1)?.toIntOrNull() ?: 0) }
                    val need = (firstInnings.runs + 1 - current.runs).coerceAtLeast(0)
                    (need to (maxOvers * 6 - bowled).coerceAtLeast(0)).takeIf { it.first > 0 }
                } else null
                // Format on the left, toss on the right — the small print above a TV score.
                Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                    Text(
                        state.competition.ifBlank { if (state.isLive) "Live" else state.status.ifBlank { "Match" } }.uppercase(),
                        color = HeroInk.copy(alpha = 0.72f), fontSize = 9.5.sp,
                        fontWeight = FontWeight.Bold, letterSpacing = 1.2.sp, maxLines = 1,
                        modifier = Modifier.weight(1f),
                    )
                    if (state.toss.isNotBlank()) {
                        Text(
                            "Toss: " + state.toss, color = HeroInk.copy(alpha = 0.72f),
                            fontSize = 10.5.sp, fontWeight = FontWeight.Medium,
                            maxLines = 1, overflow = TextOverflow.Ellipsis,
                        )
                    }
                }

                Spacer(modifier = Modifier.height(10.dp))

                // The batting side owns the card; the last ball sits beside its score.
                val stats = buildList {
                    if (state.crr.isNotBlank()) add("CRR" to state.crr)
                    if (state.rrr.isNotEmpty()) add("RRR" to state.rrr)
                    if (chase != null) add("NEED" to "${chase.first} off ${chase.second}")
                    else if (state.runsNeeded != null && state.ballsLeft != null) add("NEED" to "${state.runsNeeded} off ${state.ballsLeft}")
                }
                Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                    BattingScore(
                        modifier = Modifier.weight(1f),
                        name = if (battingIsTeam2) state.team2 else state.team1,
                        logo = if (battingIsTeam2) state.team2Logo else state.team1Logo,
                        score = battingScore,
                        overs = battingOvers,
                        stats = stats,
                        batting = state.isLive,
                    )
                    HeroLastBall(state = state)
                }

                Spacer(modifier = Modifier.height(12.dp))
                Box(Modifier.fillMaxWidth().height(1.dp).background(HeroInk.copy(alpha = 0.16f)))
                Spacer(modifier = Modifier.height(10.dp))

                FieldingLine(
                    name = if (battingIsTeam2) state.team1 else state.team2,
                    logo = if (battingIsTeam2) state.team1Logo else state.team2Logo,
                    score = fieldingScore,
                )

                if (state.isLive) {
                    Spacer(modifier = Modifier.height(10.dp))
                    ThisOverStrip(state.thisOver)
                }

        }
        ScoringRibbon(modifier = Modifier.matchParentSize(), state = state, band = band)
      }
    }
}

/**
 * Scrolling event word-mark that wraps the white band around the hero card, rotated per edge.
 * FOUR/SIX render green, WICKET red; otherwise a calm grey "HARAAN LIVE". The word alternates
 * with the brand monogram, cut in the same colour — see [drawSeamRibbon].
 */
@Composable
private fun ScoringRibbon(modifier: Modifier = Modifier, state: MatchUiState, band: Dp) {
    // The ribbon shows the boundary/wicket word while the MOST RECENT ball is a 4/6/W,
    // and reverts to the calm grey "HARAAN LIVE" on the next (non-boundary) ball.
    val (word, argb) = if (state.ballInPlay && state.isLive) {
        "BALL  IN  PLAY" to android.graphics.Color.rgb(37, 99, 235)
    } else when (state.thisOver.lastOrNull()) {
        "6" -> "SIX" to android.graphics.Color.rgb(22, 163, 74)
        "4" -> "FOUR" to android.graphics.Color.rgb(37, 99, 235)
        "W" -> "WICKET" to android.graphics.Color.rgb(214, 40, 40)
        else -> "HARAAN  LIVE" to android.graphics.Color.argb(135, 100, 116, 139)
    }
    val mark = rememberRibbonMark(word, argb)

    // Calm continuous crawl…
    val transition = rememberInfiniteTransition(label = "ribbon")
    val basePhase by transition.animateFloat(
        initialValue = 0f, targetValue = 1f,
        animationSpec = infiniteRepeatable(tween(4500, easing = LinearEasing), RepeatMode.Restart),
        label = "phase"
    )
    // …plus a one-shot fast burst synced to each new ball outcome. Visual only: the
    // PHYSICAL half of a boundary belongs to BoundaryBurst, which owns one haptic per
    // delivery with a signature per event. This used to fire its own confirm tick too,
    // and the two landed on the same ball a few milliseconds apart — on a real phone
    // that reads as one smeared buzz rather than two deliberate ones.
    val boost = remember { Animatable(0f) }
    var firstRun by remember { mutableStateOf(true) }
    LaunchedEffect(state.thisOver) {
        if (firstRun) { firstRun = false; return@LaunchedEffect }
        when (state.thisOver.lastOrNull()) {
            "4" -> boost.animateTo(boost.value + 2.2f, tween(950, easing = FastOutSlowInEasing))
            "6" -> boost.animateTo(boost.value + 3.0f, tween(1100, easing = FastOutSlowInEasing))
            "W" -> boost.animateTo(boost.value + 2.6f, tween(1000, easing = FastOutSlowInEasing))
            else -> {}
        }
        // Keep the accumulated offset bounded (period = 1 segment) so it never drifts off-path.
        boost.snapTo(boost.value.mod(1f))
    }

    Canvas(modifier = modifier) {
        drawSeamRibbon(
            word = word,
            argb = argb,
            band = band,
            phase = (basePhase + boost.value).mod(1f),
            mark = mark,
        )
    }
}
