package com.haraan.partner

import android.content.Intent
import android.net.Uri
import androidx.compose.animation.AnimatedContent
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.slideInVertically
import androidx.compose.animation.slideOutVertically
import androidx.compose.animation.togetherWith
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowForward
import androidx.compose.material.icons.filled.EventAvailable
import androidx.compose.material.icons.filled.NearMe
import androidx.compose.material.icons.filled.Place
import androidx.compose.material.icons.filled.SportsBasketball
import androidx.compose.material.icons.filled.SportsCricket
import androidx.compose.material.icons.filled.SportsHandball
import androidx.compose.material.icons.filled.SportsKabaddi
import androidx.compose.material.icons.filled.SportsSoccer
import androidx.compose.material.icons.filled.SportsTennis
import androidx.compose.material.icons.filled.SportsVolleyball
import androidx.compose.material.icons.filled.Stadium
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
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.repeatOnLifecycle
import com.haraan.partner.ui.Haptics
import com.haraan.partner.ui.components.pressScale
import com.haraan.partner.ui.components.pressShade
import kotlinx.coroutines.delay

// Partner Matches tab: games being scored on (or right beside) the partner's courts.
// Read like a scoreboard at the counter — who's playing, the score, is it on now —
// and live scores tick in place while the tab is open.

private val Ink = Color(0xFF0F172A)
private val Muted = Color(0xFF64748B)
private val Faint = Color(0xFF94A3B8)
private val Accent = Color(0xFF2F6BFF)
private val AccentDeep = Color(0xFF1E50E6)
private val Live = Color(0xFFDC2626)
private val PageBg = Color(0xFFF7F8FB)
private val Hairline = Color(0x140F172A)

/** Scores on a live game refresh this often while the tab is on screen. */
private const val LIVE_REFRESH_MS = 20_000L

@Composable
internal fun MatchesScreen(api: PartnerApi, token: String, venueId: Long?) {
    // Bumped on a timer while a game is live, so the score updates in place (no
    // skeleton, and a failed refetch keeps what's shown).
    var tick by remember { mutableIntStateOf(0) }
    var anyLive by remember { mutableStateOf(false) }
    val lifecycle = androidx.lifecycle.compose.LocalLifecycleOwner.current.lifecycle
    LaunchedEffect(lifecycle) {
        // Only while the app is in front: a phone in a pocket shouldn't poll.
        lifecycle.repeatOnLifecycle(Lifecycle.State.RESUMED) {
            while (true) {
                delay(LIVE_REFRESH_MS)
                if (anyLive) tick++
            }
        }
    }

    RefreshableContent(token to venueId, load = { api.matches(token, venueId) }, reloadSignal = tick) { m ->
        val live = (m.confirmed + m.nearby).count { it.isLive }
        LaunchedEffect(live) { anyLive = live > 0 }

        if (m.isEmpty) {
            NoMatches()
            return@RefreshableContent
        }
        LazyColumn(
            Modifier.fillMaxSize().background(PageBg),
            contentPadding = PaddingValues(start = 16.dp, end = 16.dp, top = 8.dp, bottom = 32.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            item(key = "head") { MatchesHeader(live) }
            if (m.confirmed.isNotEmpty()) {
                item(key = "confirmed-head") {
                    GroupHeader(
                        icon = Icons.Filled.EventAvailable,
                        title = "On your courts",
                        note = "Booked through you, so these are yours for sure.",
                    )
                }
                items(m.confirmed, key = { "c-${it.id}" }) { ScoreCard(it) }
            }
            if (m.nearby.isNotEmpty()) {
                item(key = "nearby-head") {
                    GroupHeader(
                        icon = Icons.Filled.NearMe,
                        title = "Playing nearby",
                        // Said plainly: GPS puts these beside the venue; no booking ties them to it.
                        note = "Public games within 200 m. Not booked through you.",
                    )
                }
                items(m.nearby, key = { "n-${it.id}" }) { ScoreCard(it) }
            }
        }
    }
}

@Composable
private fun MatchesHeader(live: Int) {
    Column(Modifier.fillMaxWidth().padding(top = 8.dp, bottom = 2.dp)) {
        Text("Matches", fontSize = 26.sp, fontWeight = FontWeight.ExtraBold, color = Ink, letterSpacing = (-0.6).sp)
        Spacer(Modifier.height(4.dp))
        Row(verticalAlignment = Alignment.CenterVertically) {
            if (live > 0) {
                LiveDot(8.dp)
                Spacer(Modifier.width(8.dp))
                Text(
                    if (live == 1) "1 game live now" else "$live games live now",
                    fontSize = 13.5.sp, fontWeight = FontWeight.SemiBold, color = Ink,
                )
                Text("  ·  scores update on their own", fontSize = 13.sp, color = Muted)
            } else {
                Text("Nothing live right now", fontSize = 13.5.sp, color = Muted)
            }
        }
    }
}

@Composable
private fun GroupHeader(icon: ImageVector, title: String, note: String) {
    Column(Modifier.fillMaxWidth().padding(top = 10.dp)) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Box(
                Modifier.size(28.dp).clip(RoundedCornerShape(9.dp)).background(Color(0xFFEAF1FF)),
                contentAlignment = Alignment.Center,
            ) { Icon(icon, contentDescription = null, tint = AccentDeep, modifier = Modifier.size(16.dp)) }
            Spacer(Modifier.width(10.dp))
            Text(title, fontSize = 16.5.sp, fontWeight = FontWeight.ExtraBold, color = Ink, letterSpacing = (-0.2).sp)
        }
        Spacer(Modifier.height(4.dp))
        Text(note, fontSize = 12.5.sp, color = Muted, modifier = Modifier.padding(start = 38.dp))
    }
}

/**
 * One game as a scoreboard: sport and state on top, a row per side with its score
 * big on the right, and where it is at the bottom. Tapping opens the full live
 * scorecard on haraan.app.
 */
@Composable
private fun ScoreCard(m: VenueMatch) {
    val context = LocalContext.current
    val view = LocalView.current
    val interaction = remember { MutableInteractionSource() }
    val shape = RoundedCornerShape(20.dp)
    val cricket = m.sport.equals("cricket", ignoreCase = true)
    // The leader reads in full ink once a game is decided; the other side steps back.
    val lead = if (m.isFinished) leader(m.score1, m.score2) else 0

    Column(
        Modifier
            .fillMaxWidth()
            .pressScale(interaction, pressedScale = 0.98f)
            .clip(shape)
            .background(Color.White)
            .border(1.dp, if (m.isLive) Live.copy(alpha = 0.22f) else Hairline, shape)
            .pressShade(interaction)
            .clickable(interactionSource = interaction, indication = null) {
                Haptics.tick(view)
                openScorecard(context, m.id)
            }
            .semantics {
                contentDescription = "${m.home} ${m.score1} against ${m.away} ${m.score2}" +
                    (if (m.isLive) ", live" else "") + ". Opens the scorecard."
            }
            .padding(16.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Icon(sportIcon(m.sport), contentDescription = null, tint = Muted, modifier = Modifier.size(16.dp))
            Spacer(Modifier.width(6.dp))
            Text(
                m.sport.replaceFirstChar { it.uppercase() },
                fontSize = 12.5.sp, fontWeight = FontWeight.SemiBold, color = Muted,
            )
            Spacer(Modifier.weight(1f))
            if (m.isLive) LiveTag() else StateTag(m)
        }

        Spacer(Modifier.height(14.dp))
        // Cricket's overs belong to whichever side is batting — the server reads it off
        // the latest over, the same rule as the scorecard.
        val overs = m.overs.takeIf { cricket && it.isNotBlank() }?.let { "$it ov" }
        TeamLine(
            name = m.home,
            score = m.score1,
            sub = if (cricket) overs.takeIf { m.battingTeam != 2 } else m.rally1.takeIf { it.isNotBlank() }?.let { "$it in set" },
            dim = lead == 2,
            batting = cricket && m.isLive && m.battingTeam != 2,
        )
        Spacer(Modifier.height(10.dp))
        TeamLine(
            name = m.away,
            score = m.score2,
            sub = if (cricket) overs.takeIf { m.battingTeam == 2 } else m.rally2.takeIf { it.isNotBlank() }?.let { "$it in set" },
            dim = lead == 1,
            batting = cricket && m.isLive && m.battingTeam == 2,
        )

        Spacer(Modifier.height(14.dp))
        Box(Modifier.fillMaxWidth().height(1.dp).background(Hairline))
        Spacer(Modifier.height(10.dp))
        Row(verticalAlignment = Alignment.CenterVertically) {
            Icon(Icons.Filled.Place, contentDescription = null, tint = Faint, modifier = Modifier.size(14.dp))
            Spacer(Modifier.width(6.dp))
            Text(
                placeLine(m),
                fontSize = 12.5.sp, color = Muted, maxLines = 1, overflow = TextOverflow.Ellipsis,
                modifier = Modifier.weight(1f),
            )
            Spacer(Modifier.width(8.dp))
            Text("Scorecard", fontSize = 12.5.sp, fontWeight = FontWeight.Bold, color = AccentDeep)
            Spacer(Modifier.width(2.dp))
            Icon(Icons.AutoMirrored.Filled.ArrowForward, contentDescription = null, tint = AccentDeep, modifier = Modifier.size(14.dp))
        }
    }
}

@Composable
private fun TeamLine(name: String, score: String, sub: String?, dim: Boolean, batting: Boolean = false) {
    val ink = if (dim) Faint else Ink
    Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
        Monogram(name, dim)
        Spacer(Modifier.width(12.dp))
        Column(Modifier.weight(1f)) {
            Text(
                name.ifBlank { "—" },
                fontSize = 15.5.sp, fontWeight = FontWeight.Bold, color = ink,
                maxLines = 1, overflow = TextOverflow.Ellipsis,
            )
            if (batting) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Box(Modifier.size(5.dp).clip(RoundedCornerShape(99.dp)).background(Accent))
                    Spacer(Modifier.width(5.dp))
                    Text("Batting", fontSize = 11.sp, fontWeight = FontWeight.SemiBold, color = Accent)
                }
            }
        }
        Spacer(Modifier.width(12.dp))
        Column(horizontalAlignment = Alignment.End) {
            // A new run or point slides the old figure up and out, like a scoreboard flap.
            AnimatedContent(
                targetState = score.ifBlank { "0" },
                transitionSpec = {
                    (slideInVertically(tween(260)) { it } + fadeIn(tween(260))) togetherWith
                        (slideOutVertically(tween(200)) { -it } + fadeOut(tween(200)))
                },
                label = "score",
            ) { s ->
                Text(s, fontSize = 22.sp, fontWeight = FontWeight.ExtraBold, color = ink, letterSpacing = (-0.4).sp)
            }
            sub?.let { Text(it, fontSize = 11.5.sp, color = Muted) }
        }
    }
}

/** Initials on a tone picked from the name, so the same team always wears the same colour. */
@Composable
private fun Monogram(name: String, dim: Boolean) {
    val tones = listOf(
        Color(0xFF1E50E6), Color(0xFF0B1C46), Color(0xFF0F766E),
        Color(0xFFB45309), Color(0xFF7C3AED), Color(0xFFBE123C),
    )
    val tone = tones[Math.floorMod(name.trim().lowercase().hashCode(), tones.size)]
    val initials = name.trim().split(Regex("\\s+")).filter { it.isNotEmpty() }
        .let { words -> if (words.size >= 2) "${words[0][0]}${words[1][0]}" else name.trim().take(2) }
        .uppercase().ifBlank { "?" }
    Box(
        Modifier.size(36.dp).clip(RoundedCornerShape(11.dp))
            .background(tone.copy(alpha = if (dim) 0.07f else 0.12f)),
        contentAlignment = Alignment.Center,
    ) {
        Text(initials, fontSize = 13.sp, fontWeight = FontWeight.ExtraBold, color = if (dim) Faint else tone)
    }
}

@Composable
private fun LiveTag() {
    Row(
        Modifier.clip(RoundedCornerShape(999.dp)).background(Live.copy(alpha = 0.08f))
            .padding(horizontal = 10.dp, vertical = 4.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        LiveDot(6.dp)
        Spacer(Modifier.width(6.dp))
        Text("LIVE", fontSize = 10.5.sp, fontWeight = FontWeight.ExtraBold, color = Live, letterSpacing = 0.8.sp)
    }
}

@Composable
private fun StateTag(m: VenueMatch) {
    val label = when {
        m.isFinished -> "Result"
        m.time.isNotBlank() -> m.time
        m.status.isNotBlank() -> m.status.replaceFirstChar { it.uppercase() }
        else -> "Scheduled"
    }
    Text(
        label, fontSize = 11.5.sp, fontWeight = FontWeight.Bold, color = Muted,
        modifier = Modifier.clip(RoundedCornerShape(999.dp)).background(Color(0x0F0F172A))
            .padding(horizontal = 10.dp, vertical = 4.dp),
    )
}

/** A dot that breathes, the one moving thing on the screen, so live reads as live. */
@Composable
private fun LiveDot(size: androidx.compose.ui.unit.Dp) {
    val pulse = rememberInfiniteTransition(label = "live")
    val a by pulse.animateFloat(1f, 0.3f, infiniteRepeatable(tween(900), RepeatMode.Reverse), label = "live-a")
    Box(Modifier.size(size).clip(RoundedCornerShape(99.dp)).background(Live.copy(alpha = a)))
}

@Composable
private fun NoMatches() {
    Column(
        Modifier.fillMaxSize().background(PageBg).padding(32.dp),
        verticalArrangement = Arrangement.Center,
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Box(
            Modifier.size(64.dp).clip(RoundedCornerShape(20.dp)).background(Color(0xFFEAF1FF)),
            contentAlignment = Alignment.Center,
        ) { Icon(Icons.Filled.Stadium, contentDescription = null, tint = Accent, modifier = Modifier.size(30.dp)) }
        Spacer(Modifier.height(16.dp))
        Text("No games on your courts yet", fontSize = 16.sp, fontWeight = FontWeight.Bold, color = Ink, textAlign = TextAlign.Center)
        Spacer(Modifier.height(6.dp))
        Text(
            "When players score a match on Haraan at your venue, it shows up here with the live score.",
            fontSize = 13.sp, color = Muted, textAlign = TextAlign.Center,
        )
    }
}

private fun sportIcon(sport: String): ImageVector = when (sport.lowercase()) {
    "cricket" -> Icons.Filled.SportsCricket
    "football", "soccer", "futsal" -> Icons.Filled.SportsSoccer
    "volleyball" -> Icons.Filled.SportsVolleyball
    "basketball" -> Icons.Filled.SportsBasketball
    "tennis", "badminton", "table_tennis", "table tennis", "pickleball" -> Icons.Filled.SportsTennis
    "kabaddi" -> Icons.Filled.SportsKabaddi
    else -> Icons.Filled.SportsHandball
}

/** Where the game is, in the partner's words: their branch, or how far from it. */
private fun placeLine(m: VenueMatch): String {
    val where = m.branch.ifBlank { m.venueName }.ifBlank { m.typedVenue }
    val how = m.distanceM?.let { "$it m from your venue" } ?: "on your booking"
    return listOfNotNull(where.takeIf { it.isNotBlank() }, how).joinToString(" · ")
}

/** 1 or 2 for the side ahead (cricket "120/4" compares runs), 0 when level or unreadable. */
private fun leader(a: String, b: String): Int {
    val x = a.substringBefore('/').trim().toIntOrNull() ?: return 0
    val y = b.substringBefore('/').trim().toIntOrNull() ?: return 0
    return when {
        x > y -> 1
        y > x -> 2
        else -> 0
    }
}

private fun openScorecard(context: android.content.Context, id: Long) {
    val url = "${ApiConfig.BASE_URL}/gamehub/actionboard/match/$id"
    runCatching { context.startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(url))) }
}
