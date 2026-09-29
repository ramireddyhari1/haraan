package com.haraan.app.ui.rewards

import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.spring
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Star
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil.compose.AsyncImage
import com.haraan.app.R
import com.haraan.app.data.ApiConfig
import com.haraan.app.ui.theme.PlusJakartaSans

/**
 * "I won." The match climax: sport and match tier, elevated team crests, sport-accurate
 * formatted scores, a dignified outcome pill, and the athlete's personal impact card.
 */
@Composable
internal fun ResultHeader(result: ResultUi, stage: Int, animate: Boolean) {
    val won = result.outcome == Outcome.WON
    Box(Modifier.fillMaxWidth()) {
        // Atmospheric stadium illumination behind the scores
        Canvas(Modifier.matchParentSize()) {
            val tint = if (won) Board.Green else Board.BlueBright
            drawCircle(
                Brush.radialGradient(
                    listOf(tint.copy(alpha = 0.12f), Color.Transparent),
                    center = Offset(size.width / 2, size.height * 0.38f),
                    radius = size.width * 0.65f,
                ),
                radius = size.width * 0.65f,
                center = Offset(size.width / 2, size.height * 0.38f),
            )
        }
        Column(
            Modifier.fillMaxWidth().padding(horizontal = 20.dp, vertical = 6.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            // Eyebrow
            Text(
                result.eyebrow,
                color = Board.InkFaint,
                fontFamily = PlusJakartaSans,
                fontWeight = FontWeight.Bold,
                fontSize = 11.sp,
                letterSpacing = 1.6.sp,
                modifier = Modifier.rise(stage >= 1),
            )
            Spacer(Modifier.height(4.dp))

            // The result as an object: drops in, lands on the haptic, rocks when tapped.
            ResultIllustration(
                outcome = result.outcome,
                sport = result.sport,
                play = stage >= 2,
                animate = animate,
                modifier = Modifier.fillMaxWidth().height(136.dp),
            )
            Spacer(Modifier.height(6.dp))

            // Outcome Hero Pill
            Column(
                horizontalAlignment = Alignment.CenterHorizontally,
                modifier = Modifier.rise(stage >= 2),
            ) {
                OutcomePill(result.outcome, result.word)
                result.resultLine?.let {
                    Spacer(Modifier.height(6.dp))
                    Text(
                        it,
                        color = Board.InkMuted,
                        fontFamily = PlusJakartaSans,
                        fontSize = 13.sp,
                        fontWeight = FontWeight.Medium,
                        textAlign = TextAlign.Center,
                    )
                }
            }

            Spacer(Modifier.height(18.dp))

            // Broadcast Match Scoreboard
            Row(
                Modifier.fillMaxWidth().padding(horizontal = 4.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                ClubColumn(
                    team = result.home,
                    modifier = Modifier.weight(1f).rise(stage >= 1),
                )

                // The score lands a beat after the picture, slightly oversized, and settles.
                val slam by animateFloatAsState(
                    if (!animate || stage >= 3) 1f else 1.22f,
                    spring(dampingRatio = 0.45f, stiffness = 420f),
                    label = "scoreSlam",
                )
                ScoreBanner(
                    home = result.home,
                    away = result.away,
                    modifier = Modifier.rise(stage >= 3).graphicsLayer { scaleX = slam; scaleY = slam },
                )

                ClubColumn(
                    team = result.away,
                    modifier = Modifier.weight(1f).rise(stage >= 1),
                )
            }

            // Athlete's Personal Match Impact
            result.impact?.let { impact ->
                Spacer(Modifier.height(20.dp))
                PlayerImpactCard(impact, shown = stage >= 3, animate = animate, modifier = Modifier.rise(stage >= 3))
            }
        }
    }
}


@Composable
private fun OutcomePill(outcome: Outcome, word: String) {
    val (bg, textColor) = when (outcome) {
        Outcome.WON -> Board.GreenWell to Board.Green
        Outcome.LOST -> Board.Raised to Board.InkMuted
        Outcome.TIED -> Board.AmberWell to Board.Amber
        else -> Board.Raised to Board.Ink
    }
    Row(
        Modifier
            .clip(RoundedCornerShape(50))
            .background(bg)
            .border(1.dp, textColor.copy(alpha = 0.25f), RoundedCornerShape(50))
            .padding(horizontal = 16.dp, vertical = 6.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        if (outcome == Outcome.WON) {
            Icon(Icons.Filled.Star, null, tint = Board.Green, modifier = Modifier.size(15.dp))
            Spacer(Modifier.width(6.dp))
        }
        Text(
            word.uppercase(),
            color = textColor,
            fontFamily = PlusJakartaSans,
            fontWeight = FontWeight.ExtraBold,
            fontSize = 13.sp,
            letterSpacing = 1.2.sp,
        )
    }
}

@Composable
private fun PlayerImpactCard(impact: PlayerImpactUi, shown: Boolean, animate: Boolean, modifier: Modifier = Modifier) {
    if (impact.isPotm) {
        PotmCard(impact, shown, animate, modifier)
    } else {
        PersonalImpactCard(impact, modifier)
    }
}

@Composable
private fun PersonalImpactCard(impact: PlayerImpactUi, modifier: Modifier = Modifier) {
    Column(
        modifier = modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(18.dp))
            .background(Board.Surface)
            .border(1.dp, Board.Line, RoundedCornerShape(18.dp))
            .padding(horizontal = 16.dp, vertical = 14.dp),
    ) {
        // Header
        Row(
            Modifier.fillMaxWidth(),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.SpaceBetween,
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Box(
                    Modifier
                        .size(22.dp)
                        .clip(CircleShape)
                        .background(Board.BlueWell),
                    contentAlignment = Alignment.Center,
                ) {
                    Icon(Icons.Filled.Star, null, tint = Board.BlueBright, modifier = Modifier.size(13.dp))
                }
                Spacer(Modifier.width(8.dp))
                Text(
                    "YOUR MATCH IMPACT",
                    color = Board.InkMuted,
                    fontFamily = PlusJakartaSans,
                    fontWeight = FontWeight.Bold,
                    fontSize = 11.sp,
                    letterSpacing = 1.2.sp,
                )
            }
            Text(
                impact.role.ifBlank { "CONTRIBUTOR" },
                color = Board.BlueBright,
                fontFamily = PlusJakartaSans,
                fontWeight = FontWeight.Bold,
                fontSize = 10.sp,
                letterSpacing = 0.8.sp,
            )
        }

        Spacer(Modifier.height(12.dp))

        // Athlete Row
        Row(
            Modifier.fillMaxWidth(),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Box(
                Modifier
                    .size(48.dp)
                    .clip(CircleShape)
                    .background(Board.Raised)
                    .border(1.dp, Board.Line, CircleShape),
                contentAlignment = Alignment.Center,
            ) {
                val photoUrl = ApiConfig.mediaUrl(impact.playerPhoto)
                if (!photoUrl.isNullOrBlank()) {
                    AsyncImage(
                        model = photoUrl,
                        contentDescription = impact.playerName,
                        contentScale = ContentScale.Crop,
                        modifier = Modifier.fillMaxSize().clip(CircleShape),
                    )
                } else {
                    Image(
                        painter = painterResource(R.drawable.ic_default_player_avatar),
                        contentDescription = impact.playerName,
                        contentScale = ContentScale.Crop,
                        modifier = Modifier.fillMaxSize().clip(CircleShape),
                    )
                }
            }

            Spacer(Modifier.width(12.dp))

            Column(Modifier.weight(1f)) {
                Text(
                    impact.playerName.ifBlank { "You" },
                    color = Board.Ink,
                    fontFamily = PlusJakartaSans,
                    fontWeight = FontWeight.Bold,
                    fontSize = 15.sp,
                )
                Spacer(Modifier.height(2.dp))
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Crest(impact.teamShort, impact.teamLogo, 16.dp)
                    Spacer(Modifier.width(5.dp))
                    Text(
                        impact.teamName.ifBlank { impact.teamShort },
                        color = Board.InkFaint,
                        fontFamily = PlusJakartaSans,
                        fontSize = 12.sp,
                    )
                }
            }
        }

        if (impact.battingLine != null || impact.bowlingLine != null) {
            Spacer(Modifier.height(12.dp))
            Box(Modifier.fillMaxWidth().height(1.dp).background(Board.Line))
            Spacer(Modifier.height(10.dp))
            Row(
                Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                impact.battingLine?.let { bat ->
                    Column(Modifier.weight(1f)) {
                        Text(
                            "BATTING",
                            color = Board.InkFaint,
                            fontFamily = PlusJakartaSans,
                            fontWeight = FontWeight.Bold,
                            fontSize = 9.sp,
                            letterSpacing = 1.sp,
                        )
                        Text(
                            "$bat runs",
                            color = Board.Ink,
                            fontFamily = PlusJakartaSans,
                            fontWeight = FontWeight.ExtraBold,
                            fontSize = 16.sp,
                        )
                        impact.battingDetail?.let {
                            Text(it, color = Board.Green, fontFamily = PlusJakartaSans, fontSize = 11.sp, fontWeight = FontWeight.SemiBold)
                        }
                    }
                }
                if (impact.battingLine != null && impact.bowlingLine != null) {
                    Box(Modifier.padding(horizontal = 8.dp).width(1.dp).height(30.dp).background(Board.Line))
                }
                impact.bowlingLine?.let { bowl ->
                    Column(Modifier.weight(1f)) {
                        Text(
                            "BOWLING",
                            color = Board.InkFaint,
                            fontFamily = PlusJakartaSans,
                            fontWeight = FontWeight.Bold,
                            fontSize = 9.sp,
                            letterSpacing = 1.sp,
                        )
                        Text(
                            bowl,
                            color = Board.Ink,
                            fontFamily = PlusJakartaSans,
                            fontWeight = FontWeight.ExtraBold,
                            fontSize = 16.sp,
                        )
                        impact.bowlingDetail?.let {
                            Text(it, color = Board.BlueBright, fontFamily = PlusJakartaSans, fontSize = 11.sp, fontWeight = FontWeight.SemiBold)
                        }
                    }
                }
            }
        } else if (impact.chips.isNotEmpty()) {
            Spacer(Modifier.height(10.dp))
            Row(
                Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(8.dp),
            ) {
                impact.chips.forEach { chip ->
                    StatTile(chip.label, chip.value, Modifier.weight(1f))
                }
            }
        }
    }
}

@Composable
private fun StatTile(label: String, value: String, modifier: Modifier = Modifier) {
    Column(
        modifier = modifier
            .clip(RoundedCornerShape(10.dp))
            .background(Board.Raised)
            .border(1.dp, Board.Line, RoundedCornerShape(10.dp))
            .padding(horizontal = 6.dp, vertical = 8.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Text(
            label,
            color = Board.InkFaint,
            fontFamily = PlusJakartaSans,
            fontWeight = FontWeight.Bold,
            fontSize = 9.5.sp,
            letterSpacing = 1.sp,
        )
        Spacer(Modifier.height(2.dp))
        Text(
            value,
            color = Board.Ink,
            fontFamily = PlusJakartaSans,
            fontWeight = FontWeight.ExtraBold,
            fontSize = if (value.length > 9) 12.sp else 13.5.sp,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
            textAlign = TextAlign.Center,
        )
    }
}

@Composable
private fun ClubColumn(team: TeamUi, modifier: Modifier = Modifier) {
    Column(modifier = modifier, horizontalAlignment = Alignment.CenterHorizontally) {
        Crest(team.short, team.logo, 52.dp, dimmed = !team.isWinner && !team.isViewer)
        Spacer(Modifier.height(8.dp))
        Text(
            team.name,
            color = if (team.isViewer) Board.Ink else Board.InkMuted,
            fontFamily = PlusJakartaSans,
            fontWeight = if (team.isViewer) FontWeight.Bold else FontWeight.Medium,
            fontSize = 13.sp,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
            textAlign = TextAlign.Center,
        )
        if (team.isViewer) {
            Spacer(Modifier.height(3.dp))
            Text(
                "YOUR TEAM",
                color = Board.BlueBright,
                fontFamily = PlusJakartaSans,
                fontWeight = FontWeight.Bold,
                fontSize = 9.sp,
                letterSpacing = 1.sp,
                modifier = Modifier
                    .clip(RoundedCornerShape(50))
                    .background(Board.BlueWell)
                    .padding(horizontal = 6.dp, vertical = 2.dp),
            )
        }
    }
}

@Composable
private fun ScoreBanner(home: TeamUi, away: TeamUi, modifier: Modifier = Modifier) {
    val homeParts = home.scoreFormatted.split(" ")
    val homeMain = homeParts.firstOrNull().orEmpty().ifBlank { home.score.toString() }
    val homeOvers = if (homeParts.size > 1) homeParts.drop(1).joinToString(" ") else null

    val awayParts = away.scoreFormatted.split(" ")
    val awayMain = awayParts.firstOrNull().orEmpty().ifBlank { away.score.toString() }
    val awayOvers = if (awayParts.size > 1) awayParts.drop(1).joinToString(" ") else null

    Column(modifier = modifier, horizontalAlignment = Alignment.CenterHorizontally) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text(
                homeMain,
                color = if (home.isWinner || home.isViewer) Board.Ink else Board.InkMuted,
                fontFamily = PlusJakartaSans,
                fontWeight = FontWeight.ExtraBold,
                fontSize = 28.sp,
                letterSpacing = (-0.8).sp,
            )
            Text(
                " – ",
                color = Board.InkFaint,
                fontFamily = PlusJakartaSans,
                fontWeight = FontWeight.Bold,
                fontSize = 24.sp,
                modifier = Modifier.padding(horizontal = 4.dp),
            )
            Text(
                awayMain,
                color = if (away.isWinner || away.isViewer) Board.Ink else Board.InkMuted,
                fontFamily = PlusJakartaSans,
                fontWeight = FontWeight.ExtraBold,
                fontSize = 28.sp,
                letterSpacing = (-0.8).sp,
            )
        }
        if (homeOvers != null || awayOvers != null) {
            Spacer(Modifier.height(2.dp))
            Text(
                "${homeOvers.orEmpty()}   ${awayOvers.orEmpty()}".trim(),
                color = Board.InkFaint,
                fontFamily = PlusJakartaSans,
                fontWeight = FontWeight.Medium,
                fontSize = 11.5.sp,
            )
        }
    }
}
