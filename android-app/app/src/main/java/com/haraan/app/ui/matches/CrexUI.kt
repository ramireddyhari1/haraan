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
                fontFamily = com.haraan.app.theme.ArchivoDisplay,
                letterSpacing = (-1).sp,
                style = androidx.compose.ui.text.TextStyle(fontFeatureSettings = "tnum")
            )
            if (wkts.isNotEmpty()) {
                Text(
                    wkts, color = wktColor, fontSize = 18.sp,
                    fontFamily = com.haraan.app.theme.ArchivoDisplay,
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

        Box(contentAlignment = Alignment.Center) {
            Text(lastBall, color = c.copy(alpha = 0.12f), fontSize = 56.sp, fontWeight = FontWeight.Black)
            Text(lastBall, color = c, fontSize = 38.sp, fontWeight = FontWeight.Black)
            // Overlaid, so the burst can spill past the centre column without moving any
            // of the hero's layout — a score that shifted sideways on every six would be
            // worse than no animation.
            BoundaryBurst(
                kind = burst,
                modifier = Modifier.size(190.dp),
                onFinished = { burst = null },
            )
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
 * FOUR/SIX render green, WICKET red; otherwise a calm grey "HARAAN LIVE". The word alternates
 * with the brand monogram, cut in the same colour — see [drawSeamRibbon].
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
