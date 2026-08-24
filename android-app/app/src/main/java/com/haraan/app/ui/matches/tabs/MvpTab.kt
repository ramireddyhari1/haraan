package com.haraan.app.ui.matches.tabs

import androidx.compose.animation.animateContentSize
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.ui.matches.CrexColors
import com.haraan.app.ui.matches.MatchUiState
import com.haraan.app.ui.matches.MvpPlayer
import com.haraan.app.ui.matches.TeamLogo
import kotlinx.coroutines.launch
import kotlin.math.roundToInt

/**
 * MVP tab — who actually decided this match.
 *
 * The ranking is computed on the backend from the same replayed ball log as the scorecard
 * (see LiveMatchController::buildMvp), so the figures here can never drift from the
 * scorecard tab.
 *
 * The design is built on one judgement: **a cricketer is impressed by cricket, not by a
 * dashboard.** The previous version led with "IMPACT 87" — a unit that exists nowhere in
 * the sport, sized larger than the innings that earned it — and packed every fact into its
 * own bordered box, which is the house style of software that has nothing to say. So:
 *
 *  · The **innings leads**. `36* (12)` is set at display size, because that is the thing a
 *    player screenshots and sends to their group. Impact keeps its place as a ranking key
 *    but sits behind the card as a watermark, read second.
 *  · **Context, in words.** "48% of Kadapa Kings' runs" is worth more than any bar, and it
 *    is derived from the same innings cards on screen rather than asserted.
 *  · **Boundaries are drawn, not counted.** Four pips and six pips read as a shot map at a
 *    glance; "3x4 · 2x6" has to be parsed.
 *  · **One surface, hairline rules.** The chasing pack is a single card divided by rules,
 *    not eight floating boxes each with its own border.
 *  · **The face is the player's own.** A real photo when the squad was linked to a real
 *    account, a monogram struck in the team colour when it wasn't — never a stock
 *    silhouette, and never the team crest standing in for a person.
 */
@Composable
fun MvpTab(state: MatchUiState, modifier: Modifier = Modifier) {
    val players = state.mvp

    if (players.isEmpty()) {
        MvpEmpty(modifier)
        return
    }

    val leader = players.first()
    val topPoints = leader.points.coerceAtLeast(1)
    val chasing = players.drop(1)

    LazyColumn(
        modifier = modifier
            .fillMaxSize()
            .background(CrexColors.Background)
            .padding(horizontal = 16.dp),
        verticalArrangement = Arrangement.spacedBy(14.dp),
        contentPadding = PaddingValues(top = 14.dp, bottom = 28.dp)
    ) {
        item(key = "hero") { MvpHero(state, leader, live = state.isLive) }

        if (chasing.isNotEmpty()) {
            item(key = "chasing") {
                ChasingPack(state = state, players = chasing, topPoints = topPoints)
            }
        }

        item(key = "formula") { MvpFormula() }
    }
}

/** Team accent so a card reads as "which side" at a glance, matching the hero and keypad. */
private fun teamColor(state: MatchUiState, team: Int): Color =
    if (team == 2) state.team2Color else state.team1Color

// ─────────────────────────── Hero (rank 1) ───────────────────────────

@Composable
private fun MvpHero(state: MatchUiState, p: MvpPlayer, live: Boolean) {
    val accent = teamColor(state, p.team)
    val logoUrl = if (p.team == 2) state.team2Logo else state.team1Logo
    val teamCode = if (p.team == 2) state.team2 else state.team1

    Box(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(22.dp))
            .background(CrexColors.Surface)
            .border(1.dp, accent.copy(alpha = 0.22f), RoundedCornerShape(22.dp))
    ) {
        // The team colour arrives as light from the top-left and falls away, rather than
        // as a flat panel. A tint you notice is a tint that has taken over the card.
        Box(
            modifier = Modifier
                .matchParentSize()
                .background(
                    Brush.linearGradient(
                        0f to accent.copy(alpha = 0.16f),
                        0.55f to accent.copy(alpha = 0.03f),
                        1f to Color.Transparent
                    )
                )
        )

        // Impact, as a watermark. It is the ranking key, so it belongs on the card — but a
        // number the sport does not use has not earned the headline, and at 7% it reads
        // only once you look for it.
        Text(
            "${p.points}",
            color = accent.copy(alpha = 0.07f),
            fontSize = 96.sp,
            fontWeight = FontWeight.Black,
            style = TextStyle(fontFeatureSettings = "tnum"),
            modifier = Modifier
                .align(Alignment.TopEnd)
                .padding(end = 8.dp)
        )

        Column(modifier = Modifier.padding(18.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                    // While the match is live the lead can still change hands, so the card
                    // says who is ahead rather than crowning anyone early.
                    if (live) "LEADING THE MATCH" else "PLAYER OF THE MATCH",
                    color = accent,
                    fontSize = 10.sp,
                    fontWeight = FontWeight.ExtraBold,
                    letterSpacing = 1.4.sp,
                    modifier = Modifier.weight(1f)
                )
                Text(
                    p.roleLabel,
                    color = CrexColors.TextMuted,
                    fontSize = 9.sp,
                    fontWeight = FontWeight.ExtraBold,
                    letterSpacing = 0.9.sp
                )
            }

            Spacer(Modifier.height(16.dp))

            Row(verticalAlignment = Alignment.CenterVertically) {
                PlayerFace(
                    name = p.name,
                    photoUrl = p.photoUrl,
                    accent = accent,
                    size = 96.dp,
                    halo = true
                )
                Spacer(Modifier.width(14.dp))
                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        p.name,
                        color = CrexColors.TextPrimary,
                        fontSize = 22.sp,
                        fontWeight = FontWeight.Black,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis
                    )
                    Spacer(Modifier.height(5.dp))
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        TeamLogo(team = teamCode, logoUrl = logoUrl, modifier = Modifier.size(16.dp))
                        Spacer(Modifier.width(6.dp))
                        Text(
                            p.teamName.ifBlank { teamCode },
                            color = CrexColors.TextSecondary,
                            fontSize = 12.5.sp,
                            fontWeight = FontWeight.SemiBold,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis
                        )
                    }
                }
            }

            Spacer(Modifier.height(16.dp))
            Hairline()
            Spacer(Modifier.height(14.dp))

            // The innings itself, at display size. Two lanes only for a genuine
            // all-rounder, split by a rule rather than boxed into two panels.
            Row(modifier = Modifier.fillMaxWidth(), verticalAlignment = Alignment.Top) {
                if (p.didBat) {
                    HeroFigure(
                        modifier = Modifier.weight(1f),
                        label = "BATTING",
                        figure = p.batLine,
                        detail = "SR ${p.strikeRate}",
                        accent = CrexColors.TextPrimary
                    )
                }
                if (p.didBat && p.didBowl) {
                    Box(
                        Modifier
                            .padding(horizontal = 14.dp)
                            .width(1.dp)
                            .height(46.dp)
                            .background(CrexColors.Border)
                    )
                }
                if (p.didBowl) {
                    HeroFigure(
                        modifier = Modifier.weight(1f),
                        label = "BOWLING",
                        figure = p.bowlLine,
                        detail = buildString {
                            append("ECON ${p.econ}")
                            if (p.maidens > 0) append("  ·  ${p.maidens} MDN")
                        },
                        accent = CrexColors.TextPrimary
                    )
                }
            }

            // What they actually hit, drawn. Only for an innings with boundaries in it.
            if (p.didBat && (p.fours > 0 || p.sixes > 0)) {
                Spacer(Modifier.height(14.dp))
                BoundaryPips(fours = p.fours, sixes = p.sixes)
            }

            val context = contributionLine(state, p)
            if (context != null) {
                Spacer(Modifier.height(12.dp))
                Text(
                    context,
                    color = CrexColors.TextSecondary,
                    fontSize = 12.5.sp,
                    fontWeight = FontWeight.Medium
                )
            }

            if (p.canFollow) {
                Spacer(Modifier.height(16.dp))
                FollowPill(player = p)
            }
        }
    }
}

@Composable
private fun HeroFigure(
    modifier: Modifier = Modifier,
    label: String,
    figure: String,
    detail: String,
    accent: Color
) {
    Column(modifier = modifier) {
        Text(
            label,
            color = CrexColors.TextMuted,
            fontSize = 9.sp,
            fontWeight = FontWeight.ExtraBold,
            letterSpacing = 1.1.sp
        )
        Spacer(Modifier.height(6.dp))
        Text(
            figure,
            color = accent,
            fontSize = 29.sp,
            fontWeight = FontWeight.Black,
            maxLines = 1,
            style = TextStyle(fontFeatureSettings = "tnum")
        )
        Spacer(Modifier.height(4.dp))
        Text(
            detail,
            color = CrexColors.TextMuted,
            fontSize = 10.5.sp,
            fontWeight = FontWeight.Bold,
            letterSpacing = 0.5.sp,
            style = TextStyle(fontFeatureSettings = "tnum")
        )
    }
}

/**
 * Boundaries as marks rather than a tally. A six is drawn wider and in the six colour, so
 * the shape of the innings — five fours, or two enormous overs — is legible before any
 * number is read. Capped so a long innings cannot run the row off the card.
 */
@Composable
private fun BoundaryPips(fours: Int, sixes: Int) {
    val maxPips = 14
    val shownSixes = minOf(sixes, maxPips)
    val shownFours = minOf(fours, (maxPips - shownSixes).coerceAtLeast(0))
    val hidden = (fours - shownFours) + (sixes - shownSixes)

    Row(verticalAlignment = Alignment.CenterVertically) {
        repeat(shownSixes) {
            Box(
                Modifier
                    .padding(end = 4.dp)
                    .width(16.dp)
                    .height(6.dp)
                    .clip(RoundedCornerShape(3.dp))
                    .background(CrexColors.SixBall)
            )
        }
        repeat(shownFours) {
            Box(
                Modifier
                    .padding(end = 4.dp)
                    .width(10.dp)
                    .height(6.dp)
                    .clip(RoundedCornerShape(3.dp))
                    .background(CrexColors.FourBall)
            )
        }
        Spacer(Modifier.width(4.dp))
        Text(
            buildString {
                if (sixes > 0) append("$sixes six${if (sixes == 1) "" else "es"}")
                if (sixes > 0 && fours > 0) append("  ·  ")
                if (fours > 0) append("$fours four${if (fours == 1) "" else "s"}")
                if (hidden > 0) append("  ·  +$hidden more")
            },
            color = CrexColors.TextMuted,
            fontSize = 10.5.sp,
            fontWeight = FontWeight.Bold
        )
    }
}

/**
 * The player's share of the match, in words, computed from the innings cards already on
 * screen. Nothing is asserted that the scorecard cannot be checked against — and when the
 * share is too small to mean anything, the line is simply left out rather than padded.
 */
private fun contributionLine(state: MatchUiState, p: MvpPlayer): String? {
    val parts = mutableListOf<String>()

    if (p.didBat && p.runs > 0) {
        val teamRuns = state.inningsCards.filter { it.battingTeam == p.team }.sumOf { it.runs }
        if (teamRuns > 0) {
            val share = ((p.runs * 100f) / teamRuns).roundToInt()
            if (share >= 10) {
                parts += "$share% of ${possessive(p.teamName.ifBlank { "their side" })} runs"
            }
        }
    }

    if (p.didBowl && p.wickets > 0) {
        // Wickets that fell to the side this player was bowling AT.
        val fell = state.inningsCards.filter { it.battingTeam != p.team }.sumOf { it.wickets }
        parts += if (fell > 0) "${p.wickets} of the $fell wickets to fall" else
            "${p.wickets} wicket${if (p.wickets == 1) "" else "s"}"
    }

    return parts.takeIf { it.isNotEmpty() }?.joinToString("   ·   ")
}

// ─────────────────────────── The chasing pack ───────────────────────────

/**
 * Everyone else, as one surface split by rules.
 *
 * The old version gave each player their own bordered, rounded card, which made eight
 * players read as eight unrelated widgets rather than one ranking. A table is the right
 * shape for a ranking, so this is a table.
 */
@Composable
private fun ChasingPack(state: MatchUiState, players: List<MvpPlayer>, topPoints: Int) {
    Column {
        Text(
            "THE REST OF THE MATCH",
            color = CrexColors.TextMuted,
            fontSize = 10.sp,
            fontWeight = FontWeight.ExtraBold,
            letterSpacing = 1.3.sp,
            modifier = Modifier.padding(start = 4.dp, bottom = 8.dp)
        )
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .clip(RoundedCornerShape(18.dp))
                .background(CrexColors.Surface)
                .border(1.dp, CrexColors.Border, RoundedCornerShape(18.dp))
        ) {
            players.forEachIndexed { index, player ->
                if (index > 0) Hairline(inset = 14.dp)
                MvpRow(
                    state = state,
                    player = player,
                    rank = index + 2,   // the leader is the hero card above
                    fraction = (player.points.toFloat() / topPoints).coerceIn(0f, 1f)
                )
            }
        }
    }
}

@Composable
private fun MvpRow(state: MatchUiState, player: MvpPlayer, rank: Int, fraction: Float) {
    val accent = teamColor(state, player.team)
    // The bar grows into place and re-animates as points move, so a refresh mid-match
    // reads as movement rather than a jump.
    val width by animateFloatAsState(targetValue = fraction, label = "impactBar")

    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 14.dp, vertical = 12.dp),
        verticalAlignment = Alignment.CenterVertically
    ) {
        Text(
            "$rank",
            color = CrexColors.TextMuted,
            fontSize = 12.sp,
            fontWeight = FontWeight.Black,
            style = TextStyle(fontFeatureSettings = "tnum"),
            modifier = Modifier.width(18.dp)
        )
        PlayerFace(name = player.name, photoUrl = player.photoUrl, accent = accent, size = 40.dp)
        Spacer(Modifier.width(11.dp))

        Column(modifier = Modifier.weight(1f)) {
            Text(
                player.name,
                color = CrexColors.TextPrimary,
                fontSize = 14.sp,
                fontWeight = FontWeight.Bold,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis
            )
            Spacer(Modifier.height(3.dp))
            Text(
                // Only the lanes they actually featured in.
                listOfNotNull(
                    player.batLine.takeIf { it.isNotBlank() },
                    player.bowlLine.takeIf { it.isNotBlank() }
                ).joinToString("   ·   "),
                color = CrexColors.TextSecondary,
                fontSize = 11.5.sp,
                fontWeight = FontWeight.Medium,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
                style = TextStyle(fontFeatureSettings = "tnum")
            )
            Spacer(Modifier.height(7.dp))
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .height(3.dp)
                    .clip(RoundedCornerShape(2.dp))
                    .background(CrexColors.Border.copy(alpha = 0.7f))
            ) {
                Box(
                    modifier = Modifier
                        .fillMaxWidth(width)
                        .fillMaxHeight()
                        .clip(RoundedCornerShape(2.dp))
                        .background(accent)
                )
            }
        }

        Spacer(Modifier.width(12.dp))
        Text(
            "${player.points}",
            color = CrexColors.TextPrimary,
            fontSize = 16.sp,
            fontWeight = FontWeight.Black,
            style = TextStyle(fontFeatureSettings = "tnum")
        )
    }
}

// ─────────────────────────── Pieces ───────────────────────────

/**
 * A player's face.
 *
 * Deliberately NOT the team crest helper this used to borrow: that one looks up
 * `logos/{monogram}.png`, so a player whose initials happened to collide with a team code
 * was shown a club badge instead of a person. A monogram struck in the team colour is an
 * honest stand-in; someone else's crest is not.
 */
@Composable
private fun PlayerFace(
    name: String,
    photoUrl: String,
    accent: Color,
    size: Dp,
    /**
     * A soft ring of team colour set just outside the portrait. It reads as a spotlight
     * rather than a border, which is what lets the hero face carry the card at this size
     * without the flat pasted-in look a bare circle gets once it is large.
     */
    halo: Boolean = false
) {
    if (halo) {
        Box(
            modifier = Modifier
                .size(size + 10.dp)
                .clip(CircleShape)
                .background(accent.copy(alpha = 0.10f)),
            contentAlignment = Alignment.Center
        ) {
            PlayerFace(name = name, photoUrl = photoUrl, accent = accent, size = size)
        }
        return
    }

    Box(
        modifier = Modifier
            .size(size)
            .clip(CircleShape)
            .background(if (photoUrl.isBlank()) accent.copy(alpha = 0.10f) else Color.White)
            .border(1.5.dp, accent.copy(alpha = 0.30f), CircleShape),
        contentAlignment = Alignment.Center
    ) {
        if (photoUrl.isNotBlank()) {
            coil.compose.AsyncImage(
                model = photoUrl,
                contentDescription = name,
                contentScale = ContentScale.Crop,
                modifier = Modifier.fillMaxSize().clip(CircleShape)
            )
        } else {
            Text(
                initials(name),
                color = accent,
                fontSize = (size.value * 0.33f).sp,
                fontWeight = FontWeight.Black,
                letterSpacing = 0.5.sp
            )
        }
    }
}

/**
 * Follow, straight from the card.
 *
 * Optimistic, because a button that waits on a round trip before moving feels broken and
 * gets pressed twice — but it settles against what the SERVER returns and reverts if the
 * call fails, so the state on screen is never a hopeful guess.
 */
@Composable
private fun FollowPill(player: MvpPlayer) {
    val context = LocalContext.current
    val view = LocalView.current
    val scope = rememberCoroutineScope()

    // Re-seeded when the payload's own answer changes, so a refresh that reports the truth
    // is allowed to correct an optimistic guess.
    var following by remember(player.playerId, player.isFollowing) {
        mutableStateOf(player.isFollowing)
    }
    var busy by remember(player.playerId) { mutableStateOf(false) }

    val bg = if (following) Color.Transparent else CrexColors.AccentBlue
    val fg = if (following) CrexColors.TextSecondary else Color.White

    Row(
        modifier = Modifier
            .clip(RoundedCornerShape(22.dp))
            .background(bg)
            .border(
                1.dp,
                if (following) CrexColors.Border else Color.Transparent,
                RoundedCornerShape(22.dp)
            )
            .clickable(enabled = !busy) {
                view.performHapticFeedback(android.view.HapticFeedbackConstants.CONFIRM)
                val next = !following
                following = next          // move now
                busy = true
                scope.launch {
                    val token = com.haraan.app.data.TokenStore.getSignedInToken(context)
                    val settled = if (token == null) null else
                        com.haraan.app.data.PlayerRepository()
                            .setFollowing(token, player.playerId, next)
                    // Whatever the server says wins; a failure puts the button back.
                    following = settled ?: !next
                    busy = false
                }
            }
            .padding(horizontal = 22.dp, vertical = 10.dp),
        verticalAlignment = Alignment.CenterVertically
    ) {
        Text(
            if (following) "Following" else "Follow",
            color = fg,
            fontSize = 13.sp,
            fontWeight = FontWeight.Bold,
            letterSpacing = 0.2.sp
        )
    }
}

@Composable
private fun Hairline(inset: Dp = 0.dp) {
    Box(
        Modifier
            .fillMaxWidth()
            .padding(horizontal = inset)
            .height(1.dp)
            .background(CrexColors.Border)
    )
}

// ─────────────────────────── Footer / empty ───────────────────────────

/**
 * How the number was reached, folded away.
 *
 * It has to be available — a ranking that will not explain itself is just an assertion —
 * but it is reference material, and reference material pinned open under every match is
 * clutter. Closed by default, one tap from the answer.
 */
@Composable
private fun MvpFormula() {
    var open by remember { mutableStateOf(false) }

    Column(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(14.dp))
            .background(CrexColors.Surface)
            .border(1.dp, CrexColors.Border, RoundedCornerShape(14.dp))
            .clickable { open = !open }
            .animateContentSize()
            .padding(14.dp)
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text(
                "HOW IMPACT IS SCORED",
                color = CrexColors.TextMuted,
                fontSize = 9.5.sp,
                fontWeight = FontWeight.ExtraBold,
                letterSpacing = 1.1.sp,
                modifier = Modifier.weight(1f)
            )
            Text(
                if (open) "Hide" else "Show",
                color = CrexColors.AccentBlue,
                fontSize = 11.5.sp,
                fontWeight = FontWeight.Bold
            )
        }

        if (open) {
            Spacer(Modifier.height(12.dp))
            listOf(
                "Batting" to "1 per run, +1 per four, +2 per six, plus a strike-rate bonus after 10 balls faced.",
                "Bowling" to "20 per wicket, 8 per maiden, plus an economy bonus after a full over.",
                "Fielding" to "Not counted — the scorer doesn't record which fielder took the catch."
            ).forEachIndexed { i, (head, body) ->
                if (i > 0) Spacer(Modifier.height(7.dp))
                Row {
                    Text(
                        head, color = CrexColors.TextPrimary, fontSize = 11.sp,
                        fontWeight = FontWeight.Bold, modifier = Modifier.width(58.dp)
                    )
                    Text(body, color = CrexColors.TextSecondary, fontSize = 11.sp, modifier = Modifier.weight(1f))
                }
            }
            Spacer(Modifier.height(10.dp))
            Text(
                "Figures come from the same ball-by-ball log as the scorecard.",
                color = CrexColors.TextMuted, fontSize = 10.sp
            )
        }
    }
}

@Composable
private fun MvpEmpty(modifier: Modifier = Modifier) {
    Box(
        modifier = modifier
            .fillMaxSize()
            .background(CrexColors.Background)
            .padding(32.dp),
        contentAlignment = Alignment.Center
    ) {
        Column(horizontalAlignment = Alignment.CenterHorizontally) {
            Text(
                "No impact yet",
                color = CrexColors.TextPrimary,
                fontSize = 17.sp,
                fontWeight = FontWeight.Black
            )
            Spacer(Modifier.height(8.dp))
            Text(
                "Once the first ball is scored, every batter and bowler is ranked here by what they did with it.",
                color = CrexColors.TextSecondary,
                fontSize = 13.sp,
                textAlign = TextAlign.Center
            )
        }
    }
}

/**
 * "Kadapa Kings" -> "Kadapa Kings'", "Nellore XI" -> "Nellore XI's".
 *
 * Team names ending in s are the norm in cricket - Kings, Warriors, Strikers - and
 * "Kadapa Kings's runs" is the kind of detail that quietly tells a reader nobody looked at
 * this screen.
 */
private fun possessive(name: String): String =
    if (name.trim().endsWith("s", ignoreCase = true)) "${name.trim()}'" else "${name.trim()}'s"

/** "Siva Kumar" -> "SK"; a single name falls back to its first two letters. */
private fun initials(name: String): String {
    val parts = name.trim().split(Regex("\\s+")).filter { it.isNotBlank() }
    return when {
        parts.isEmpty() -> "?"
        parts.size == 1 -> parts[0].take(2).uppercase()
        else -> (parts[0].take(1) + parts[1].take(1)).uppercase()
    }
}
