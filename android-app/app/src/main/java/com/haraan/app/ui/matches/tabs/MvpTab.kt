package com.haraan.app.ui.matches.tabs

import androidx.compose.animation.animateContentSize
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
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
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.draw.blur
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.TextUnit
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
        // ── The awards ──
        //
        // A match is remembered as a handful of NAMED performances, not as a leaderboard.
        // Best batter and best bowler are derived from the same batPoints/bowlPoints the
        // ranking already uses, so they cannot disagree with it.
        val bestBat = players.filter { it.didBat }.maxByOrNull { it.batPoints }
            ?.takeIf { it.batPoints > 0 }
        val bestBowl = players.filter { it.didBowl }.maxByOrNull { it.bowlPoints }
            ?.takeIf { it.bowlPoints > 0 }

        item(key = "heroes-label") {
            SectionLabel(if (state.isLive) "LEADING THE MATCH" else "HEROES OF THE MATCH")
        }
        item(key = "hero") {
            MvpHero(
                state = state,
                p = leader,
                live = state.isLive,
                // When the best batter or bowler IS the player of the match, say so on
                // their one card rather than printing the same face again further down.
                // The obvious version of this screen shows a matchwinning all-rounder
                // three times over and calls it three awards.
                alsoBest = listOfNotNull(
                    "batter".takeIf { bestBat?.name == leader.name },
                    "bowler".takeIf { bestBowl?.name == leader.name },
                ),
            )
        }

        if (bestBat != null && bestBat.name != leader.name) {
            item(key = "award-bat") { AwardCard(state, bestBat, Award.BATTER, players) }
        }
        if (bestBowl != null && bestBowl.name != leader.name) {
            item(key = "award-bowl") { AwardCard(state, bestBowl, Award.BOWLER, players) }
        }

        // ── Everyone else who did something ──
        val named = setOfNotNull(leader.name, bestBat?.name, bestBowl?.name)
        val rest = players.filter { it.name !in named }
        if (rest.isNotEmpty()) {
            item(key = "star-label") { SectionLabel("STAR PERFORMANCES") }
            items(rest.chunked(2), key = { it.first().name }) { pair ->
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    pair.forEach { player ->
                        StarCard(state, player, Modifier.weight(1f))
                    }
                    // A lone odd player keeps its half-width rather than stretching across
                    // the row and pretending to be a different kind of card.
                    if (pair.size == 1) Spacer(Modifier.weight(1f))
                }
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
private fun MvpHero(
    state: MatchUiState,
    p: MvpPlayer,
    live: Boolean,
    /** "batter" / "bowler" — awards this player ALSO won, folded in rather than repeated. */
    alsoBest: List<String> = emptyList(),
) {
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

        Column {
        // The headline award gets the same photo panel as the other two — it would be odd
        // for the biggest one to be the only card without a face.
        AwardPhotoPanel(
            name = p.name,
            photoUrl = p.photoUrl,
            accent = accent,
            // Live, the lead can still change hands, so it says who is ahead rather than
            // crowning anyone early.
            label = if (live) "Leading the match" else "Player of the match",
            height = 210.dp,
        )

        Column(modifier = Modifier.padding(18.dp)) {
            // The panel above already names the award, so the body carries only what it
            // does NOT say: which other awards this player also swept.
            Row(verticalAlignment = Alignment.CenterVertically) {
                Spacer(Modifier.weight(1f))
                Text(
                    // "BEST BATTER · BEST BOWLER" beats "ALL-ROUND" when they actually
                    // took both awards — the role label is a category, this is an honour.
                    if (alsoBest.isEmpty()) p.roleLabel
                    else alsoBest.joinToString("  ·  ") { "BEST ${it.uppercase()}" },
                    color = if (alsoBest.isEmpty()) CrexColors.TextMuted else accent,
                    fontSize = 9.sp,
                    fontWeight = FontWeight.ExtraBold,
                    letterSpacing = 0.9.sp
                )
            }

            Spacer(Modifier.height(16.dp))

            Row(verticalAlignment = Alignment.CenterVertically) {
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

/**
 * A player as a PORTRAIT PANEL rather than a circle.
 *
 * The reference design (CricHeroes) gives each award a 16:9 photo slab. It leads with
 * identity, which is right — a grassroots cricketer wants to see themselves, and a name in
 * a table does not do that. But a cinematic frame wrapped around what is usually a casual
 * profile picture promises more than the content delivers, and it costs a whole screen per
 * award, leaving the FIGURES — the thing players actually screenshot — crammed into a
 * strip underneath.
 *
 * So: a tall portrait down the side of the card. Big enough to be the reason you stop
 * scrolling, small enough that the innings keeps its weight and the card stays one screen.
 *
 * The no-photo case is designed FIRST, not patched on. Roughly two thirds of squad entries
 * carry no linked account (they were typed in, or the player has none), so initials on a
 * team-coloured panel is the COMMON case and has to look like a decision. A circle that is
 * empty reads as a failed image load; a panel with the player's initials and their team's
 * colour reads as a card.
 */
@Composable
private fun PlayerPortrait(
    name: String,
    photoUrl: String,
    accent: Color,
    width: Dp,
    height: Dp,
) {
    Box(
        modifier = Modifier
            .size(width = width, height = height)
            .clip(RoundedCornerShape(14.dp))
            .background(
                Brush.verticalGradient(
                    listOf(accent.copy(alpha = 0.28f), accent.copy(alpha = 0.12f))
                )
            )
            .border(1.dp, accent.copy(alpha = 0.35f), RoundedCornerShape(14.dp)),
        contentAlignment = Alignment.Center
    ) {
        if (photoUrl.isNotBlank()) {
            coil.compose.AsyncImage(
                model = photoUrl,
                contentDescription = name,
                contentScale = ContentScale.Crop,
                modifier = Modifier.fillMaxSize().clip(RoundedCornerShape(14.dp)),
            )
        } else {
            Text(
                initialsOf(name),
                color = accent,
                fontSize = (height.value * 0.30f).sp,
                fontWeight = FontWeight.Black,
                letterSpacing = 1.sp,
            )
        }
    }
}

/** "Suresh Pillai" -> "SP"; a single name falls back to its first two letters. */
private fun initialsOf(name: String): String {
    val parts = name.trim().split(Regex("[ ]+")).filter { it.isNotBlank() }
    return when {
        parts.isEmpty() -> "?"
        parts.size == 1 -> parts[0].take(2).uppercase()
        else -> (parts[0].take(1) + parts[1].take(1)).uppercase()
    }
}

/**
 * The award's photo panel — the thing that makes the card feel like a moment.
 *
 * Built the way broadcast and the reference apps do it: the player's own photo fills the
 * frame, with a BLURRED, darkened copy of the same image behind it so a portrait-shaped
 * upload can sit in a landscape frame without cropping someone's head off. The award name
 * sits on a scrim along the bottom, where it reads against any photo.
 *
 * `Modifier.blur` is a no-op below API 31; there the backdrop is simply the cropped,
 * darkened image, which still fills the frame and still reads. Nothing is conditional on
 * version — it degrades on its own.
 *
 * With no photo — two thirds of squad entries have no linked account — the frame becomes a
 * deep team-colour field carrying the player's initials at display size. That case was
 * designed, not defaulted to: it has to look like the card it is, not like a photo that
 * failed to load.
 */
@Composable
private fun AwardPhotoPanel(
    name: String,
    photoUrl: String,
    accent: Color,
    label: String,
    height: Dp = 188.dp,
    labelSize: TextUnit = 19.sp,
    scrimHeight: Dp = 74.dp,
    initialsSize: TextUnit = 64.sp,
) {
    Box(
        modifier = Modifier
            .fillMaxWidth()
            .height(height)
            .clip(RoundedCornerShape(topStart = 18.dp, topEnd = 18.dp))
            .background(accent.copy(alpha = 0.22f))
    ) {
        if (photoUrl.isNotBlank()) {
            // Backdrop: same image, blown up, blurred and dimmed so the frame is never
            // empty at the edges and the subject still reads.
            coil.compose.AsyncImage(
                model = photoUrl,
                contentDescription = null,
                contentScale = ContentScale.Crop,
                modifier = Modifier
                    .matchParentSize()
                    .blur(22.dp)
                    .alpha(0.55f),
            )
            // Subject: the whole photo, uncropped.
            coil.compose.AsyncImage(
                model = photoUrl,
                contentDescription = name,
                contentScale = ContentScale.Fit,
                modifier = Modifier.matchParentSize(),
            )
        } else {
            Box(
                modifier = Modifier
                    .matchParentSize()
                    .background(
                        Brush.linearGradient(
                            listOf(accent.copy(alpha = 0.42f), accent.copy(alpha = 0.16f))
                        )
                    ),
                contentAlignment = Alignment.Center
            ) {
                Text(
                    initialsOf(name),
                    color = accent,
                    fontSize = initialsSize,
                    fontWeight = FontWeight.Black,
                    letterSpacing = 2.sp,
                )
            }
        }

        // The label needs to survive a bright photo AND a pale monogram panel, so it rides
        // its own gradient rather than trusting whatever is behind it.
        Box(
            modifier = Modifier
                .align(Alignment.BottomStart)
                .fillMaxWidth()
                .height(scrimHeight)
                .background(
                    Brush.verticalGradient(
                        listOf(Color.Transparent, Color(0xF2000000))
                    )
                )
        )
        Text(
            label,
            color = Color.White,
            fontSize = labelSize,
            fontWeight = FontWeight.Black,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
            modifier = Modifier
                .align(Alignment.BottomStart)
                .padding(start = 12.dp, end = 10.dp, bottom = 10.dp)
        )
    }
}

// ─────────────────────────── Awards ───────────────────────────

/** The two awards handed out beside the player of the match. */
private enum class Award { BATTER, BOWLER }

@Composable
private fun SectionLabel(text: String) {
    Text(
        text,
        color = CrexColors.TextMuted,
        fontSize = 10.sp,
        fontWeight = FontWeight.ExtraBold,
        letterSpacing = 1.4.sp,
        modifier = Modifier.padding(start = 2.dp, top = 2.dp)
    )
}

/**
 * Best batter / best bowler.
 *
 * The caption is DERIVED, never decorative. "Most runs in the match" is only printed when
 * they actually scored the most; otherwise it falls back to the weaker but still true
 * "highest impact with the bat". Writing "the bat did all the talking" under whoever
 * happened to rank first is the kind of copy that reads well once and is wrong by the
 * second match.
 */
@Composable
private fun AwardCard(
    state: MatchUiState,
    p: MvpPlayer,
    award: Award,
    all: List<MvpPlayer>,
) {
    val accent = teamColor(state, p.team)
    val topRuns = all.maxOfOrNull { it.runs } ?: 0
    val topWkts = all.maxOfOrNull { it.wickets } ?: 0

    val label = if (award == Award.BATTER) "Best batter" else "Best bowler"
    // Derived, never canned. "Top score of the match" is only printed when they actually
    // scored the most; a fixed line like "the bat did all the talking" reads well once and
    // is wrong by the second match.
    val caption = when (award) {
        Award.BATTER ->
            if (p.runs > 0 && p.runs == topRuns) "Top score of the match"
            else "Highest impact with the bat"
        Award.BOWLER ->
            if (p.wickets > 0 && p.wickets == topWkts) "Best figures of the match"
            else "Highest impact with the ball"
    }
    val figure = if (award == Award.BATTER) p.batLine else p.bowlLine
    val support = if (award == Award.BATTER) {
        buildString {
            append("SR ${p.strikeRate}")
            if (p.fours > 0) append("  ·  ${p.fours}x4")
            if (p.sixes > 0) append("  ·  ${p.sixes}x6")
        }
    } else {
        buildString {
            append("ER ${p.econ}")
            if (p.maidens > 0) append("  ·  ${p.maidens} mdn")
        }
    }

    Column(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(18.dp))
            .background(CrexColors.Surface)
            .border(1.dp, CrexColors.Border, RoundedCornerShape(18.dp))
    ) {
        AwardPhotoPanel(name = p.name, photoUrl = p.photoUrl, accent = accent, label = label)

        // Caption strip, in the award's colour — the band that makes the panel above read
        // as a card rather than a photo with text on it.
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .background(accent.copy(alpha = 0.10f))
                .padding(horizontal = 16.dp, vertical = 11.dp)
        ) {
            Text(
                caption, color = CrexColors.TextPrimary, fontSize = 13.sp,
                fontWeight = FontWeight.SemiBold
            )
        }

        Column(Modifier.padding(16.dp)) {
            Row(verticalAlignment = Alignment.Top) {
                Column(Modifier.weight(1f)) {
                    Text(
                        p.name, color = accent, fontSize = 18.sp,
                        fontWeight = FontWeight.Black, maxLines = 1,
                        overflow = TextOverflow.Ellipsis
                    )
                    Spacer(Modifier.height(2.dp))
                    Text(
                        p.teamName.ifBlank { if (p.team == 2) state.team2 else state.team1 },
                        color = CrexColors.TextSecondary, fontSize = 12.sp,
                        fontStyle = androidx.compose.ui.text.font.FontStyle.Italic,
                        maxLines = 1, overflow = TextOverflow.Ellipsis
                    )
                }
                Column(horizontalAlignment = Alignment.End) {
                    Text(
                        "${p.points}", color = CrexColors.TextPrimary, fontSize = 20.sp,
                        fontWeight = FontWeight.Black,
                        style = TextStyle(fontFeatureSettings = "tnum")
                    )
                    Text(
                        "IMPACT", color = CrexColors.TextMuted, fontSize = 8.5.sp,
                        fontWeight = FontWeight.ExtraBold, letterSpacing = 1.sp
                    )
                }
            }

            Spacer(Modifier.height(12.dp))
            Row(verticalAlignment = Alignment.Bottom) {
                Text(
                    figure, color = CrexColors.TextPrimary, fontSize = 25.sp,
                    fontWeight = FontWeight.Black,
                    style = TextStyle(fontFeatureSettings = "tnum")
                )
                Spacer(Modifier.width(10.dp))
                Text(
                    support, color = CrexColors.TextSecondary, fontSize = 11.5.sp,
                    fontWeight = FontWeight.SemiBold,
                    modifier = Modifier.padding(bottom = 4.dp)
                )
            }
        }
    }
}

/**
 * Everyone else who did something worth a line.
 *
 * Half-width so two sit side by side: these are contributions, not headlines, and giving
 * each of them a full-width card would flatten the difference between the match's best
 * performance and its sixth-best.
 */
@Composable
private fun StarCard(state: MatchUiState, p: MvpPlayer, modifier: Modifier = Modifier) {
    val accent = teamColor(state, p.team)
    val lines = listOfNotNull(
        p.batLine.takeIf { it.isNotBlank() },
        p.bowlLine.takeIf { it.isNotBlank() },
    )

    Column(
        modifier = modifier
            .clip(RoundedCornerShape(16.dp))
            .background(CrexColors.Surface)
            .border(1.dp, CrexColors.Border, RoundedCornerShape(16.dp))
    ) {
        // Same panel as the awards, one scale down — the player's face with their name on
        // the scrim, so a tile is recognisably the same object as the card above it.
        AwardPhotoPanel(
            name = p.name,
            photoUrl = p.photoUrl,
            accent = accent,
            label = p.name,
            height = 148.dp,
            labelSize = 14.sp,
            scrimHeight = 56.dp,
            initialsSize = 40.sp,
        )
        Column(Modifier.padding(horizontal = 12.dp, vertical = 11.dp)) {
            lines.forEachIndexed { i, line ->
                if (i > 0) Spacer(Modifier.height(4.dp))
                Text(
                    line, color = CrexColors.TextPrimary, fontSize = 14.sp,
                    fontWeight = FontWeight.Black,
                    style = TextStyle(fontFeatureSettings = "tnum")
                )
            }
            // A player with no line at all still gets a tile rather than vanishing — they
            // are in the ranking because they did something.
            if (lines.isEmpty()) {
                Text(
                    "${p.points} impact", color = CrexColors.TextSecondary, fontSize = 13.sp,
                    fontWeight = FontWeight.SemiBold
                )
            }
        }
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
