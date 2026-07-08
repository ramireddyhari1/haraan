package com.example.thanna.ui.matches

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
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.graphics.nativeCanvas
import android.graphics.BitmapFactory
import kotlinx.coroutines.delay

// ─────────────────────────────────────────────
//  DESIGN TOKENS
// ─────────────────────────────────────────────
object CrexColors {
    val Background      = Color(0xFFF4F7FB) // Custom premium slate-tinted background (was 0xFF0A0E14)
    val Surface         = Color(0xFFFFFFFF) // Pure white cards (was 0xFF1C2229)
    val SurfaceElevated = Color(0xFFFFFFFF) 
    val Border          = Color(0xFFE2E8F0) // Clean divider borders (was 0x1AFFFFFF)
    val AccentGreen     = Color(0xFF00C853) // Haraan Green/Mint
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
    val emblemRes = com.example.thanna.ui.matches.create.emblemDrawableFor(logoUrl)
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
    val emblemRes = com.example.thanna.ui.matches.create.emblemDrawableFor(iconRef)
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
private fun HeroTeamColumn(
    modifier: Modifier,
    monogram: String,
    score: String,
    overs: String,
    runsColor: Color,
    wktColor: Color,
    alignEnd: Boolean,
    iconRef: String = ""
) {
    val align = if (alignEnd) Alignment.End else Alignment.Start
    // Always render a compact code in the hero — never the long raw team name.
    val code = teamShortCode(monogram)
    Column(modifier = modifier, horizontalAlignment = align) {
        HeroCrest(code, Modifier.size(42.dp), iconRef = iconRef)
        Spacer(Modifier.height(8.dp))
        Text(code, color = Color(0xFF1E293B), fontSize = 14.sp, fontWeight = FontWeight.SemiBold)
        Spacer(Modifier.height(2.dp))
        val slash = score.indexOf('/')
        val runs = if (slash >= 0) score.substring(0, slash) else score
        val wkts = if (slash >= 0) score.substring(slash) else ""
        // Roll the runs up to the new total instead of hard-swapping, so a boundary reads
        // as the number *climbing*. Non-numeric scores ("Yet to bat") fall through as-is.
        val runsInt = runs.toIntOrNull()
        val animatedRuns by androidx.compose.animation.core.animateIntAsState(
            targetValue = runsInt ?: 0,
            animationSpec = androidx.compose.animation.core.tween(550, easing = androidx.compose.animation.core.FastOutSlowInEasing),
            label = "runsRoll"
        )
        val runsText = if (runsInt != null) animatedRuns.toString() else runs
        Row(verticalAlignment = Alignment.Bottom) {
            Text(
                runsText, color = runsColor, fontSize = 34.sp,
                fontFamily = com.example.thanna.theme.ArchivoDisplay,
                letterSpacing = (-1).sp,
                style = androidx.compose.ui.text.TextStyle(fontFeatureSettings = "tnum")
            )
            if (wkts.isNotEmpty()) {
                Text(
                    wkts, color = wktColor, fontSize = 18.sp,
                    fontFamily = com.example.thanna.theme.ArchivoDisplay,
                    modifier = Modifier.padding(bottom = 3.dp),
                    style = androidx.compose.ui.text.TextStyle(fontFeatureSettings = "tnum")
                )
            }
        }
        Spacer(Modifier.height(2.dp))
        Text(overs, color = Color(0xFF64748B), fontSize = 11.sp)
    }
}

@Composable
private fun HeroLastBall(state: MatchUiState) {
    Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        modifier = Modifier.padding(horizontal = 6.dp)
    ) {
        Text(
            "LAST BALL",
            color = Color(0xFF334155).copy(alpha = 0.5f),
            fontSize = 9.sp, fontWeight = FontWeight.Bold, letterSpacing = 1.5.sp
        )
        Spacer(Modifier.height(4.dp))
        val lastBall = state.thisOver.lastOrNull() ?: "•"
        val c = when (lastBall) {
            "6" -> CrexColors.SixBall
            "4" -> CrexColors.AccentGreen
            "W" -> CrexColors.WicketBall
            "•", "0" -> Color(0xFF0F172A).copy(alpha = 0.25f)
            else -> Color(0xFF0F172A)
        }
        Box(contentAlignment = Alignment.Center) {
            Text(lastBall, color = c.copy(alpha = 0.12f), fontSize = 56.sp, fontWeight = FontWeight.Black)
            Text(lastBall, color = c, fontSize = 38.sp, fontWeight = FontWeight.Black)
        }
        Spacer(Modifier.height(6.dp))
        Box(
            modifier = Modifier.size(30.dp).clip(CircleShape).background(Color(0xFF1E293B)),
            contentAlignment = Alignment.Center
        ) {
            Text("VS", color = Color.White, fontSize = 10.sp, fontWeight = FontWeight.Bold)
        }
    }
}

@Composable
private fun HeroStat(label: String, value: String) {
    Text(
        buildAnnotatedString {
            withStyle(SpanStyle(color = Color(0xFF64748B), fontWeight = FontWeight.Medium)) { append("$label ") }
            withStyle(SpanStyle(color = Color(0xFF0F172A), fontWeight = FontWeight.Bold)) { append(value) }
        },
        fontSize = 10.sp
    )
}

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
        val flash = when (state.thisOver.lastOrNull()) {
            "4", "6" -> CrexColors.AccentGreen
            "W" -> CrexColors.WicketBall
            else -> null
        }
        if (flash != null) {
            pulseColor = flash
            pulse.snapTo(1f)
            pulse.animateTo(0f, tween(900, easing = FastOutSlowInEasing))
        }
    }
    // 1f pulse → ~4% larger, settling back — a subtle heartbeat, not a bounce.
    val popScale = 1f + pulse.value * 0.04f

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
                .clip(RoundedCornerShape(26.dp))
                .background(Color.White)
                .border(1.dp, Color(0xFFE2E8F0), RoundedCornerShape(26.dp))
        )
        // Gradient hero card, inset by `band` so the white ring shows around it
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(band)
                .graphicsLayer { scaleX = popScale; scaleY = popScale }
                .clip(RoundedCornerShape(12.dp))
                .background(
                    Brush.verticalGradient(
                        listOf(Color(0xFFD3EAF8), Color(0xFFAFD2EC))
                    )
                )
                .drawWithContent {
                    drawContent()
                    if (pulse.value > 0f) drawRect(pulseColor.copy(alpha = pulse.value * 0.28f))
                }
                .padding(horizontal = 18.dp, vertical = 14.dp)
        ) {
                // Meta line — real format/status, no placeholders.
                Text(
                    text = state.competition.ifBlank { if (state.isLive) "Live" else state.status.ifBlank { "Match" } },
                    color = Color(0xFF334155).copy(alpha = 0.78f),
                    fontSize = 11.sp,
                    fontWeight = FontWeight.SemiBold,
                    textAlign = TextAlign.Center,
                    modifier = Modifier.fillMaxWidth()
                )

                Spacer(modifier = Modifier.height(10.dp))

                // Teams and Scores row — each side shows its own real score. `score` is the
                // batting side; `opponentScore` is the other. Overs only show for the side
                // that's actually batting.
                val battingIsTeam2 = state.battingTeam == 2
                val team1Score = sanitizeScore(if (battingIsTeam2) state.opponentScore else state.score)
                val team2Score = sanitizeScore(if (battingIsTeam2) state.score else state.opponentScore)
                val oversLabel = if (state.overs.isNotBlank()) "${state.overs} ov" else ""
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.Top
                ) {
                    HeroTeamColumn(
                        modifier = Modifier.weight(1f),
                        monogram = state.team1,
                        score = team1Score,
                        overs = if (battingIsTeam2) "" else oversLabel,
                        runsColor = Color(0xFF0D47A1),
                        wktColor = Color(0xFF0D47A1).copy(alpha = 0.42f),
                        alignEnd = false,
                        iconRef = state.team1Logo
                    )

                    HeroLastBall(state = state)

                    HeroTeamColumn(
                        modifier = Modifier.weight(1f),
                        monogram = state.team2,
                        score = team2Score,
                        overs = if (battingIsTeam2) oversLabel else "",
                        runsColor = Color(0xFF475569),
                        wktColor = Color(0xFF94A3B8),
                        alignEnd = true,
                        iconRef = state.team2Logo
                    )
                }

                Spacer(modifier = Modifier.height(14.dp))

                // CRR, RRR, Toss info row — only render stats we actually have.
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Row(horizontalArrangement = Arrangement.spacedBy(16.dp)) {
                        if (state.crr.isNotBlank()) HeroStat("CRR", state.crr)
                        if (state.rrr.isNotEmpty()) HeroStat("RRR", state.rrr)
                    }
                    if (state.toss.isNotBlank()) HeroStat("TOSS", state.toss)
                }

        }
        ScoringRibbon(modifier = Modifier.matchParentSize(), state = state, band = band)
      }
    }
}

/**
 * Scrolling event word-mark that wraps the white band around the hero card, rotated per edge.
 * FOUR/SIX render green, WICKET red; otherwise a calm grey "HARAAN LIVE".
 */
@Composable
private fun ScoringRibbon(modifier: Modifier = Modifier, state: MatchUiState, band: Dp) {
    // The ribbon shows the boundary/wicket word while the MOST RECENT ball is a 4/6/W,
    // and reverts to the calm grey "HARAAN LIVE" on the next (non-boundary) ball.
    val (word, argb) = when (state.thisOver.lastOrNull()) {
        "6" -> "SIX" to android.graphics.Color.rgb(22, 163, 74)
        "4" -> "FOUR" to android.graphics.Color.rgb(37, 99, 235)
        "W" -> "WICKET" to android.graphics.Color.rgb(214, 40, 40)
        else -> "HARAAN  LIVE" to android.graphics.Color.argb(135, 100, 116, 139)
    }

    // Calm continuous crawl…
    val transition = rememberInfiniteTransition(label = "ribbon")
    val basePhase by transition.animateFloat(
        initialValue = 0f, targetValue = 1f,
        animationSpec = infiniteRepeatable(tween(4500, easing = LinearEasing), RepeatMode.Restart),
        label = "phase"
    )
    // …plus a one-shot fast burst + haptic synced to each new ball outcome.
    val boost = remember { Animatable(0f) }
    val view = LocalView.current
    var firstRun by remember { mutableStateOf(true) }
    LaunchedEffect(state.thisOver) {
        if (firstRun) { firstRun = false; return@LaunchedEffect }
        when (state.thisOver.lastOrNull()) {
            "4" -> { fireScoreHaptic(view, wicket = false); boost.animateTo(boost.value + 2.2f, tween(950, easing = FastOutSlowInEasing)) }
            "6" -> { fireScoreHaptic(view, wicket = false); delay(80); fireScoreHaptic(view, wicket = false); boost.animateTo(boost.value + 3.0f, tween(1100, easing = FastOutSlowInEasing)) }
            "W" -> { fireScoreHaptic(view, wicket = true); boost.animateTo(boost.value + 2.6f, tween(1000, easing = FastOutSlowInEasing)) }
            else -> {}
        }
        // Keep the accumulated offset bounded (period = 1 segment) so it never drifts off-path.
        boost.snapTo(boost.value.mod(1f))
    }

    Canvas(modifier = modifier) {
        val center = band.toPx() / 2f
        val radius = (26.dp.toPx() - center).coerceAtLeast(0f)
        val paint = android.graphics.Paint().apply {
            isAntiAlias = true
            color = argb
            textSize = 10.5.dp.toPx()
            letterSpacing = 0.1f
            typeface = android.graphics.Typeface.create(android.graphics.Typeface.DEFAULT_BOLD, android.graphics.Typeface.BOLD)
        }
        val segment = "$word        "
        val segWidth = paint.measureText(segment).coerceAtLeast(1f)
        val perimeter = 2f * ((size.width - 2 * center) + (size.height - 2 * center))
        val count = (perimeter / segWidth).toInt().coerceIn(2, 80) + 2
        val text = segment.repeat(count)
        val path = android.graphics.Path().apply {
            addRoundRect(
                center, center, size.width - center, size.height - center,
                radius, radius, android.graphics.Path.Direction.CW
            )
        }
        val fm = paint.fontMetrics
        val vOffset = -(fm.ascent + fm.descent) / 2f  // centre the glyphs on the path line
        // Wrap the offset into one segment period so the repeated text always fully covers the path.
        val phase = (basePhase + boost.value).mod(1f)
        drawContext.canvas.nativeCanvas.drawTextOnPath(text, path, -phase * segWidth, vOffset, paint)
    }
}

/** Boundary = crisp confirm tick; wicket = sharp reject buzz. Falls back on pre-API-30. */
private fun fireScoreHaptic(view: android.view.View, wicket: Boolean) {
    val constant = if (android.os.Build.VERSION.SDK_INT >= 30) {
        if (wicket) android.view.HapticFeedbackConstants.REJECT else android.view.HapticFeedbackConstants.CONFIRM
    } else {
        android.view.HapticFeedbackConstants.LONG_PRESS
    }
    view.performHapticFeedback(constant)
}



@Composable
private fun CrexTeamFlag(emoji: String, size: Dp) {
    Box(
        modifier = Modifier
            .size(size)
            .clip(CircleShape)
            .background(CrexColors.SurfaceElevated)
            .border(1.dp, CrexColors.Border, CircleShape),
        contentAlignment = Alignment.Center
    ) {
        Text(emoji, fontSize = (size.value * 0.55f).sp)
    }
}

// ─────────────────────────────────────────────
//  2. MATCH SUMMARY CARD
// ─────────────────────────────────────────────
@Composable
fun MatchSummaryCard(modifier: Modifier = Modifier) {
    Card(
        modifier = modifier.fillMaxWidth(),
        shape = RoundedCornerShape(20.dp),
        colors = CardDefaults.cardColors(containerColor = Color.Transparent),
        border = BorderStroke(1.dp, CrexColors.Border)
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .background(
                    Brush.verticalGradient(
                        listOf(Color(0xFF111827), Color(0xFF0D1320))
                    )
                )
        ) {
            // Gradient header banner
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .height(56.dp)
                    .background(
                        Brush.linearGradient(
                            listOf(Color(0xFF1A2A4A), Color(0xFF0D1B2E))
                        )
                    )
                    .padding(horizontal = 16.dp),
                contentAlignment = Alignment.CenterStart
            ) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Text("MATCH RESULT", color = CrexColors.AccentYellow, fontSize = 10.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = 2.sp)
                    Box(
                        modifier = Modifier
                            .clip(RoundedCornerShape(20.dp))
                            .background(CrexColors.AccentGreen.copy(alpha = 0.15f))
                            .padding(horizontal = 12.dp, vertical = 4.dp)
                    ) {
                        Text("COMPLETED", color = CrexColors.AccentGreen, fontSize = 9.sp, fontWeight = FontWeight.Bold, letterSpacing = 1.5.sp)
                    }
                }
            }

            Column(modifier = Modifier.padding(16.dp)) {
                // Teams + scores
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    TeamScoreColumn(flag = "🇮🇳", name = "India", score = "186/3", overs = "20.0", isWinner = true)
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        Text("VS", color = CrexColors.TextMuted, fontSize = 11.sp, fontWeight = FontWeight.Black)
                    }
                    TeamScoreColumn(flag = "🇦🇺", name = "Australia", score = "172/8", overs = "20.0", isWinner = false)
                }

                Spacer(modifier = Modifier.height(14.dp))

                // Result banner
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .clip(RoundedCornerShape(12.dp))
                        .background(
                            Brush.horizontalGradient(
                                listOf(CrexColors.AccentGreen.copy(0.2f), CrexColors.AccentBlue.copy(0.1f))
                            )
                        )
                        .padding(vertical = 10.dp),
                    contentAlignment = Alignment.Center
                ) {
                    Text(
                        "🏆  India won by 14 runs",
                        color = CrexColors.TextPrimary,
                        fontSize = 14.sp,
                        fontWeight = FontWeight.Bold
                    )
                }

                Spacer(modifier = Modifier.height(16.dp))
                HorizontalDivider(color = CrexColors.Border)
                Spacer(modifier = Modifier.height(14.dp))

                // Key performers
                Text("KEY PERFORMERS", color = CrexColors.TextMuted, fontSize = 9.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = 2.sp)
                Spacer(modifier = Modifier.height(10.dp))

                Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                    PerformerChip(modifier = Modifier.weight(1f), label = "TOP BAT", name = "V. Kohli", stat = "82 (54)", color = CrexColors.AccentYellow)
                    PerformerChip(modifier = Modifier.weight(1f), label = "TOP BOWL", name = "B. Kumar", stat = "3/24", color = CrexColors.AccentBlue)
                    PerformerChip(modifier = Modifier.weight(1f), label = "POTM", name = "V. Kohli", stat = "82 • 1ct", color = CrexColors.AccentGreen)
                }

                Spacer(modifier = Modifier.height(14.dp))
                HorizontalDivider(color = CrexColors.Border)
                Spacer(modifier = Modifier.height(12.dp))

                // Fall of wickets
                Text("FALL OF WICKETS", color = CrexColors.TextMuted, fontSize = 9.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = 2.sp)
                Spacer(modifier = Modifier.height(8.dp))
                FallOfWicketsBar(wickets = listOf(28, 64, 112, 140, 155, 158, 162, 168, 172))
                Spacer(modifier = Modifier.height(4.dp))
                Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                    Text("0", color = CrexColors.TextMuted, fontSize = 9.sp)
                    Text("172", color = CrexColors.TextMuted, fontSize = 9.sp)
                }
            }
        }
    }
}

@Composable
private fun TeamScoreColumn(flag: String, name: String, score: String, overs: String, isWinner: Boolean) {
    Column(horizontalAlignment = Alignment.CenterHorizontally) {
        CrexTeamFlag(emoji = flag, size = 40.dp)
        Spacer(modifier = Modifier.height(6.dp))
        Text(name, color = if (isWinner) CrexColors.TextPrimary else CrexColors.TextSecondary, fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
        Spacer(modifier = Modifier.height(2.dp))
        Text(score, color = if (isWinner) CrexColors.AccentGreen else CrexColors.TextPrimary, fontSize = 22.sp, fontWeight = FontWeight.Black)
        Text("($overs ov)", color = CrexColors.TextMuted, fontSize = 10.sp)
        if (isWinner) {
            Spacer(modifier = Modifier.height(4.dp))
            Text("🏆 Winner", color = CrexColors.AccentYellow, fontSize = 10.sp, fontWeight = FontWeight.Bold)
        }
    }
}

@Composable
private fun PerformerChip(modifier: Modifier, label: String, name: String, stat: String, color: Color) {
    Box(
        modifier = modifier
            .clip(RoundedCornerShape(12.dp))
            .background(color.copy(alpha = 0.1f))
            .border(1.dp, color.copy(alpha = 0.3f), RoundedCornerShape(12.dp))
            .padding(10.dp)
    ) {
        Column {
            Text(label, color = color, fontSize = 8.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = 1.sp)
            Spacer(modifier = Modifier.height(4.dp))
            Text(name, color = CrexColors.TextPrimary, fontSize = 11.sp, fontWeight = FontWeight.Bold)
            Text(stat, color = CrexColors.TextSecondary, fontSize = 10.sp)
        }
    }
}

// FIX: Draw gradient FIRST, then wicket lines on top
@Composable
private fun FallOfWicketsBar(wickets: List<Int>) {
    val totalRuns = 172f
    Box(
        modifier = Modifier
            .fillMaxWidth()
            .height(18.dp)
            .clip(RoundedCornerShape(9.dp))
            .background(CrexColors.SurfaceElevated)
    ) {
        Canvas(modifier = Modifier.fillMaxSize()) {
            // Draw gradient background first
            drawRoundRect(
                brush = Brush.horizontalGradient(
                    listOf(CrexColors.AccentBlue.copy(0.4f), CrexColors.AccentGreen.copy(0.4f))
                ),
                cornerRadius = CornerRadius(9.dp.toPx())
            )
            // Then draw wicket markers on top
            wickets.forEach { run ->
                val x = (run / totalRuns) * size.width
                drawLine(
                    color = CrexColors.WicketBall,
                    start = Offset(x, 0f),
                    end = Offset(x, size.height),
                    strokeWidth = 2f
                )
            }
        }
    }
}

// ─────────────────────────────────────────────
//  3. PLAYER STATS CARD
// ─────────────────────────────────────────────
@Composable
fun PlayerStatsCard(modifier: Modifier = Modifier) {
    Card(
        modifier = modifier.fillMaxWidth(),
        shape = RoundedCornerShape(20.dp),
        colors = CardDefaults.cardColors(containerColor = Color.Transparent),
        border = BorderStroke(1.dp, CrexColors.Border)
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .background(
                    Brush.verticalGradient(
                        listOf(Color(0xFF0F1C30), Color(0xFF0A0E1A))
                    )
                )
        ) {
            // Player header
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .height(80.dp)
                    .background(
                        Brush.linearGradient(
                            listOf(Color(0xFF1A2A4A), Color(0xFF0D1B2E), Color(0xFF0A0E1A))
                        )
                    )
            ) {
                Row(
                    modifier = Modifier.padding(16.dp).fillMaxSize(),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    // Avatar
                    Box(
                        modifier = Modifier
                            .size(50.dp)
                            .clip(CircleShape)
                            .background(
                                Brush.radialGradient(
                                    listOf(Color(0xFF1E3A5F), Color(0xFF0D1B2E))
                                )
                            )
                            .border(2.dp, CrexColors.AccentBlue.copy(0.5f), CircleShape),
                        contentAlignment = Alignment.Center
                    ) {
                        Text("VK", color = CrexColors.AccentBlue, fontSize = 16.sp, fontWeight = FontWeight.Black)
                    }
                    Column {
                        Text("Virat Kohli", color = CrexColors.TextPrimary, fontSize = 16.sp, fontWeight = FontWeight.Black)
                        Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                            RoleBadge("BAT", CrexColors.AccentYellow)
                            RoleBadge("IND", CrexColors.AccentBlue)
                            RoleBadge("RHB", CrexColors.AccentGreen)
                        }
                    }
                    Spacer(modifier = Modifier.weight(1f))
                    Column(horizontalAlignment = Alignment.End) {
                        Text("ICC Rank", color = CrexColors.TextMuted, fontSize = 9.sp)
                        Text("#3", color = CrexColors.AccentYellow, fontSize = 20.sp, fontWeight = FontWeight.Black)
                    }
                }
            }

            Column(modifier = Modifier.padding(16.dp)) {
                // Format tabs
                var selectedFormat by remember { mutableStateOf(0) }
                val formats = listOf("T20I", "ODI", "TEST")
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .clip(RoundedCornerShape(10.dp))
                        .background(CrexColors.SurfaceElevated)
                        .padding(3.dp),
                    horizontalArrangement = Arrangement.spacedBy(3.dp)
                ) {
                    formats.forEachIndexed { idx, fmt ->
                        Box(
                            modifier = Modifier
                                .weight(1f)
                                .clip(RoundedCornerShape(8.dp))
                                .background(
                                    if (selectedFormat == idx)
                                        Brush.horizontalGradient(listOf(CrexColors.AccentBlue.copy(0.3f), CrexColors.AccentGreen.copy(0.3f)))
                                    else Brush.horizontalGradient(listOf(Color.Transparent, Color.Transparent))
                                )
                                .clickable { selectedFormat = idx }
                                .padding(vertical = 8.dp),
                            contentAlignment = Alignment.Center
                        ) {
                            Text(
                                fmt,
                                color = if (selectedFormat == idx) CrexColors.TextPrimary else CrexColors.TextSecondary,
                                fontSize = 12.sp,
                                fontWeight = if (selectedFormat == idx) FontWeight.Bold else FontWeight.Normal
                            )
                        }
                    }
                }

                Spacer(modifier = Modifier.height(16.dp))

                // FIX: Use Column+Row instead of LazyVerticalGrid (no nested scrolling issue)
                val t20Stats = listOf(
                    Triple("Matches", "125", CrexColors.AccentBlue),
                    Triple("Innings", "118", CrexColors.AccentGreen),
                    Triple("Runs", "4188", CrexColors.AccentYellow),
                    Triple("Average", "52.35", CrexColors.AccentGreen),
                    Triple("Strike Rate", "139.6", CrexColors.AccentBlue),
                    Triple("50s / 100s", "38 / 1", CrexColors.AccentYellow)
                )
                // Row 1
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    t20Stats.take(3).forEach { (label, value, color) ->
                        StatBox(modifier = Modifier.weight(1f), label = label, value = value, accent = color)
                    }
                }
                Spacer(modifier = Modifier.height(8.dp))
                // Row 2
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    t20Stats.drop(3).forEach { (label, value, color) ->
                        StatBox(modifier = Modifier.weight(1f), label = label, value = value, accent = color)
                    }
                }

                Spacer(modifier = Modifier.height(16.dp))
                HorizontalDivider(color = CrexColors.Border)
                Spacer(modifier = Modifier.height(14.dp))

                // Form guide (last 5)
                Text("RECENT FORM", color = CrexColors.TextMuted, fontSize = 9.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = 2.sp)
                Spacer(modifier = Modifier.height(8.dp))
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    listOf(
                        Pair(82, true), Pair(14, false), Pair(67, true),
                        Pair(4, false), Pair(91, true)
                    ).forEach { (runs, good) ->
                        FormDot(runs = runs.toString(), isGood = good)
                    }
                }

                Spacer(modifier = Modifier.height(14.dp))

                // Batting profile bars
                Text("BATTING PROFILE", color = CrexColors.TextMuted, fontSize = 9.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = 2.sp)
                Spacer(modifier = Modifier.height(8.dp))
                listOf(
                    Triple("Technique", 0.92f, CrexColors.AccentGreen),
                    Triple("Power", 0.74f, CrexColors.AccentYellow),
                    Triple("Consistency", 0.88f, CrexColors.AccentBlue),
                    Triple("Temperament", 0.95f, CrexColors.AccentGreen)
                ).forEach { (skill, value, color) ->
                    SkillBar(label = skill, progress = value, color = color)
                    Spacer(modifier = Modifier.height(6.dp))
                }
            }
        }
    }
}

@Composable
private fun RoleBadge(text: String, color: Color) {
    Box(
        modifier = Modifier
            .clip(RoundedCornerShape(4.dp))
            .background(color.copy(alpha = 0.15f))
            .padding(horizontal = 6.dp, vertical = 2.dp)
    ) {
        Text(text, color = color, fontSize = 9.sp, fontWeight = FontWeight.Bold)
    }
}

@Composable
private fun StatBox(modifier: Modifier = Modifier, label: String, value: String, accent: Color) {
    Box(
        modifier = modifier
            .clip(RoundedCornerShape(10.dp))
            .background(CrexColors.SurfaceElevated)
            .border(1.dp, accent.copy(0.2f), RoundedCornerShape(10.dp))
            .padding(10.dp)
    ) {
        Column {
            Text(label, color = CrexColors.TextMuted, fontSize = 9.sp, maxLines = 1)
            Spacer(modifier = Modifier.height(3.dp))
            Text(value, color = CrexColors.TextPrimary, fontSize = 15.sp, fontWeight = FontWeight.Black)
        }
    }
}

@Composable
private fun FormDot(runs: String, isGood: Boolean) {
    val color = if (isGood) CrexColors.AccentGreen else CrexColors.AccentRed
    Box(
        modifier = Modifier
            .size(44.dp)
            .clip(RoundedCornerShape(10.dp))
            .background(color.copy(0.1f))
            .border(1.dp, color.copy(0.4f), RoundedCornerShape(10.dp)),
        contentAlignment = Alignment.Center
    ) {
        Column(horizontalAlignment = Alignment.CenterHorizontally) {
            Text(runs, color = color, fontSize = 13.sp, fontWeight = FontWeight.Black)
            Text("runs", color = CrexColors.TextMuted, fontSize = 7.sp)
        }
    }
}

@Composable
private fun SkillBar(label: String, progress: Float, color: Color) {
    val animProg by animateFloatAsState(
        targetValue = progress,
        animationSpec = tween(1000, easing = EaseOutCubic),
        label = "bar"
    )
    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(10.dp)) {
        Text(label, color = CrexColors.TextSecondary, fontSize = 10.sp, modifier = Modifier.width(90.dp))
        Box(
            modifier = Modifier
                .weight(1f)
                .height(5.dp)
                .clip(RoundedCornerShape(3.dp))
                .background(CrexColors.SurfaceElevated)
        ) {
            Box(
                modifier = Modifier
                    .fillMaxHeight()
                    .fillMaxWidth(animProg)
                    .clip(RoundedCornerShape(3.dp))
                    .background(
                        Brush.horizontalGradient(listOf(color.copy(0.7f), color))
                    )
            )
        }
        Text("${(progress * 100).toInt()}", color = color, fontSize = 10.sp, fontWeight = FontWeight.Bold)
    }
}

// ─────────────────────────────────────────────
//  4. CREX SCORECARD TABLE (fixed alignment)
// ─────────────────────────────────────────────
@Composable
fun CrexScorecardTable(modifier: Modifier = Modifier) {
    val batsmen = listOf(
        listOf("V. Kohli", "c Maxwell b Hazlewood", "82", "54", "7", "3", "151.8"),
        listOf("R. Sharma", "b Starc", "14", "11", "2", "0", "127.2"),
        listOf("S. Yadav", "not out", "34", "18", "2", "3", "188.8"),
        listOf("H. Pandya", "not out", "28", "16", "1", "3", "175.0"),
    )
    Card(
        modifier = modifier.fillMaxWidth(),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = CrexColors.Surface),
        border = BorderStroke(1.dp, CrexColors.Border)
    ) {
        Column(modifier = Modifier.padding(12.dp)) {
            // Header
            Row(modifier = Modifier.fillMaxWidth()) {
                Text("BATTER", color = CrexColors.TextMuted, fontSize = 9.sp, modifier = Modifier.weight(2f))
                listOf("R", "B", "4s", "6s", "SR").forEach {
                    Text(it, color = CrexColors.TextMuted, fontSize = 9.sp, modifier = Modifier.weight(0.6f), textAlign = TextAlign.End)
                }
            }
            Spacer(modifier = Modifier.height(6.dp))
            HorizontalDivider(color = CrexColors.Border)

            // FIX: Batter name + stats in same Row for proper alignment
            batsmen.forEach { row ->
                Spacer(modifier = Modifier.height(8.dp))
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Column(modifier = Modifier.weight(2f)) {
                        Text(row[0], color = CrexColors.TextPrimary, fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
                        Text(row[1], color = CrexColors.TextMuted, fontSize = 9.sp)
                    }
                    listOf(row[2], row[3], row[4], row[5], row[6]).forEach { value ->
                        Text(value, color = CrexColors.TextSecondary, fontSize = 11.sp, modifier = Modifier.weight(0.6f), textAlign = TextAlign.End)
                    }
                }
                HorizontalDivider(color = CrexColors.Border.copy(0.5f), modifier = Modifier.padding(top = 8.dp))
            }
        }
    }
}

// ─────────────────────────────────────────────
//  5. SERIES CARD
// ─────────────────────────────────────────────
@Composable
fun CrexSeriesCard(modifier: Modifier = Modifier) {
    Card(
        modifier = modifier.fillMaxWidth(),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = CrexColors.Surface),
        border = BorderStroke(1.dp, CrexColors.Border)
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            Text("DL20 Trophy 2025", color = CrexColors.AccentYellow, fontSize = 16.sp, fontWeight = FontWeight.Black)
            Text("T20I Series • 5 Matches", color = CrexColors.TextSecondary, fontSize = 12.sp)
            Spacer(modifier = Modifier.height(14.dp))

            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Text("🇮🇳", fontSize = 24.sp)
                    Text("India", color = CrexColors.TextPrimary, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                    Text("2", color = CrexColors.AccentGreen, fontSize = 28.sp, fontWeight = FontWeight.Black)
                    Text("wins", color = CrexColors.TextMuted, fontSize = 10.sp)
                }
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Row(horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                        val matchResults = listOf(CrexColors.AccentGreen, CrexColors.AccentGreen, CrexColors.AccentRed, CrexColors.AccentRed, CrexColors.SurfaceElevated)
                        matchResults.forEach { dotColor ->
                            Box(
                                modifier = Modifier
                                    .size(10.dp)
                                    .clip(CircleShape)
                                    .background(dotColor)
                                    .border(1.dp, CrexColors.Border, CircleShape)
                            )
                        }
                    }
                    Spacer(modifier = Modifier.height(6.dp))
                    Text("3 of 5 played", color = CrexColors.TextMuted, fontSize = 10.sp)
                }
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Text("🇦🇺", fontSize = 24.sp)
                    Text("Australia", color = CrexColors.TextPrimary, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                    Text("1", color = CrexColors.AccentRed, fontSize = 28.sp, fontWeight = FontWeight.Black)
                    Text("wins", color = CrexColors.TextMuted, fontSize = 10.sp)
                }
            }
        }
    }
}

// ─────────────────────────────────────────────
//  MINI MATCH CARD (for lists)
// ─────────────────────────────────────────────
@Composable
fun CrexMiniMatchCard(
    team1: String, score1: String,
    team2: String, score2: String,
    status: String, statusColor: Color,
    modifier: Modifier = Modifier
) {
    Card(
        modifier = modifier.fillMaxWidth(),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = CrexColors.Surface),
        border = BorderStroke(1.dp, CrexColors.Border)
    ) {
        Row(
            modifier = Modifier.padding(14.dp).fillMaxWidth(),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.SpaceBetween
        ) {
            Column(modifier = Modifier.weight(1f)) {
                Text(team1, color = CrexColors.TextPrimary, fontSize = 13.sp, fontWeight = FontWeight.SemiBold)
                Spacer(modifier = Modifier.height(2.dp))
                Text(team2, color = CrexColors.TextSecondary, fontSize = 13.sp)
            }
            Column(horizontalAlignment = Alignment.End) {
                Text(score1, color = CrexColors.TextPrimary, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                Spacer(modifier = Modifier.height(2.dp))
                Text(score2, color = CrexColors.TextSecondary, fontSize = 12.sp)
            }
            Spacer(modifier = Modifier.width(12.dp))
            Box(
                modifier = Modifier
                    .clip(RoundedCornerShape(8.dp))
                    .background(statusColor.copy(0.15f))
                    .padding(horizontal = 8.dp, vertical = 4.dp)
            ) {
                Text(status, color = statusColor, fontSize = 9.sp, fontWeight = FontWeight.Bold)
            }
        }
    }
}
