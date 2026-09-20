package com.haraan.app.ui.rewards

import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.animateDpAsState
import androidx.compose.animation.core.tween
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
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.EmojiEvents
import androidx.compose.material.icons.filled.Star
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
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
            Spacer(Modifier.height(12.dp))

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

                ScoreBanner(
                    home = result.home,
                    away = result.away,
                    modifier = Modifier.rise(stage >= 2),
                )

                ClubColumn(
                    team = result.away,
                    modifier = Modifier.weight(1f).rise(stage >= 1),
                )
            }

            // Athlete's Personal Match Impact
            result.impact?.let { impact ->
                Spacer(Modifier.height(20.dp))
                PlayerImpactCard(impact, Modifier.rise(stage >= 3))
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
private fun PlayerImpactCard(impact: PlayerImpactUi, modifier: Modifier = Modifier) {
    if (impact.isPotm) {
        PotmPrestigeCard(impact, modifier)
    } else {
        PersonalImpactCard(impact, modifier)
    }
}

@Composable
private fun PotmPrestigeCard(impact: PlayerImpactUi, modifier: Modifier = Modifier) {
    val goldRimBrush = Brush.linearGradient(
        listOf(
            Color(0xFFF59E0B),
            Color(0x80FDE68A),
            Color(0xFFD97706),
            Color(0x40F59E0B),
        )
    )
    val cardBg = Brush.linearGradient(
        listOf(
            Color(0xFF0B1220),
            Color(0xFF141E33),
            Color(0xFF0F172A),
        )
    )

    Box(
        modifier = modifier
            .fillMaxWidth()
            .shadow(14.dp, RoundedCornerShape(20.dp), ambientColor = Color(0x35F59E0B), spotColor = Color(0x30000000))
            .clip(RoundedCornerShape(20.dp))
            .background(cardBg)
            .border(1.5.dp, goldRimBrush, RoundedCornerShape(20.dp))
    ) {
        // Stadium illumination spotlight behind athlete portrait
        Canvas(Modifier.matchParentSize()) {
            drawCircle(
                Brush.radialGradient(
                    listOf(Color(0x25F59E0B), Color.Transparent),
                    center = Offset(size.width * 0.16f, size.height * 0.40f),
                    radius = size.width * 0.55f,
                ),
                radius = size.width * 0.55f,
                center = Offset(size.width * 0.16f, size.height * 0.40f),
            )
        }

        Column(
            Modifier
                .fillMaxWidth()
                .padding(horizontal = 18.dp, vertical = 16.dp)
        ) {
            // Crown / Eyebrow Row
            Row(
                Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.SpaceBetween,
            ) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Box(
                        Modifier
                            .size(24.dp)
                            .clip(CircleShape)
                            .background(Color(0x33F59E0B))
                            .border(1.dp, Color(0x99F59E0B), CircleShape),
                        contentAlignment = Alignment.Center,
                    ) {
                        Icon(
                            Icons.Filled.EmojiEvents,
                            contentDescription = "POTM",
                            tint = Color(0xFFFDE68A),
                            modifier = Modifier.size(14.dp),
                        )
                    }
                    Spacer(Modifier.width(8.dp))
                    Text(
                        "PLAYER OF THE MATCH",
                        color = Color(0xFFFDE68A),
                        fontFamily = PlusJakartaSans,
                        fontWeight = FontWeight.ExtraBold,
                        fontSize = 11.sp,
                        letterSpacing = 1.6.sp,
                    )
                }

                val badgeText = when {
                    !impact.impactScore.isNullOrBlank() -> "★ ${impact.impactScore} IMPACT"
                    else -> "MATCH WINNER"
                }
                Box(
                    Modifier
                        .clip(RoundedCornerShape(50))
                        .background(Color(0x26F59E0B))
                        .border(1.dp, Color(0x55F59E0B), RoundedCornerShape(50))
                        .padding(horizontal = 9.dp, vertical = 3.dp),
                ) {
                    Text(
                        badgeText,
                        color = Color(0xFFFBBF24),
                        fontFamily = PlusJakartaSans,
                        fontWeight = FontWeight.Bold,
                        fontSize = 10.sp,
                        letterSpacing = 0.8.sp,
                    )
                }
            }

            Spacer(Modifier.height(14.dp))

            // Athlete Showcase Row
            Row(
                Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                // Athlete Portrait Medallion
                Box(
                    Modifier.size(62.dp),
                    contentAlignment = Alignment.Center,
                ) {
                    // Outer metallic gold rim
                    Box(
                        Modifier
                            .size(62.dp)
                            .clip(CircleShape)
                            .background(
                                Brush.sweepGradient(
                                    listOf(
                                        Color(0xFFF59E0B),
                                        Color(0xFFFDE68A),
                                        Color(0xFFD97706),
                                        Color(0xFFF59E0B),
                                    )
                                )
                            )
                            .padding(2.dp)
                            .clip(CircleShape)
                            .background(Color(0xFF0F172A)),
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
                                painter = painterResource(R.drawable.player_gold),
                                contentDescription = impact.playerName,
                                contentScale = ContentScale.Crop,
                                modifier = Modifier.fillMaxSize().clip(CircleShape),
                            )
                        }
                    }

                    // Gold star badge pinned at bottom-end
                    Box(
                        Modifier
                            .size(19.dp)
                            .align(Alignment.BottomEnd)
                            .offset(x = 2.dp, y = 2.dp)
                            .clip(CircleShape)
                            .background(Color(0xFFF59E0B))
                            .border(1.5.dp, Color(0xFF0F172A), CircleShape),
                        contentAlignment = Alignment.Center,
                    ) {
                        Icon(
                            Icons.Filled.Star,
                            null,
                            tint = Color(0xFF0F172A),
                            modifier = Modifier.size(11.dp),
                        )
                    }
                }

                Spacer(Modifier.width(14.dp))

                // Name and Team affiliation
                Column(Modifier.weight(1f)) {
                    Text(
                        impact.playerName.ifBlank { "Player of the Match" },
                        color = Color.White,
                        fontFamily = PlusJakartaSans,
                        fontWeight = FontWeight.ExtraBold,
                        fontSize = 17.5.sp,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                    )
                    Spacer(Modifier.height(4.dp))
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Crest(impact.teamShort, impact.teamLogo, 18.dp)
                        Spacer(Modifier.width(6.dp))
                        Text(
                            impact.teamName.ifBlank { impact.teamShort },
                            color = Color(0xFFCBD5E1),
                            fontFamily = PlusJakartaSans,
                            fontWeight = FontWeight.SemiBold,
                            fontSize = 12.5.sp,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis,
                        )
                        Text(
                            " · ",
                            color = Color(0xFF64748B),
                            fontFamily = PlusJakartaSans,
                            fontWeight = FontWeight.Bold,
                            fontSize = 12.sp,
                        )
                        Text(
                            impact.role.ifBlank { "ALL-ROUNDER" },
                            color = Color(0xFFF59E0B),
                            fontFamily = PlusJakartaSans,
                            fontWeight = FontWeight.Bold,
                            fontSize = 11.sp,
                            letterSpacing = 0.6.sp,
                        )
                    }
                }
            }

            Spacer(Modifier.height(14.dp))

            // Subtle gold hairline separator
            Box(
                Modifier
                    .fillMaxWidth()
                    .height(1.dp)
                    .background(
                        Brush.horizontalGradient(
                            listOf(
                                Color(0x00F59E0B),
                                Color(0x40F59E0B),
                                Color(0x18FFFFFF),
                                Color(0x00FFFFFF),
                            )
                        )
                    )
            )

            Spacer(Modifier.height(12.dp))

            // Sports Performance Scorecard
            val hasBat = impact.battingLine != null
            val hasBowl = impact.bowlingLine != null

            if (hasBat || hasBowl) {
                Row(
                    Modifier.fillMaxWidth(),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    if (hasBat) {
                        Column(Modifier.weight(1f)) {
                            Text(
                                "BATTING",
                                color = Color(0xFF94A3B8),
                                fontFamily = PlusJakartaSans,
                                fontWeight = FontWeight.Bold,
                                fontSize = 9.5.sp,
                                letterSpacing = 1.2.sp,
                            )
                            Spacer(Modifier.height(2.dp))
                            Row(verticalAlignment = Alignment.Bottom) {
                                Text(
                                    impact.battingLine.orEmpty(),
                                    color = Color.White,
                                    fontFamily = PlusJakartaSans,
                                    fontWeight = FontWeight.Black,
                                    fontSize = 21.sp,
                                )
                                Text(
                                    " runs",
                                    color = Color(0xFFCBD5E1),
                                    fontFamily = PlusJakartaSans,
                                    fontWeight = FontWeight.Medium,
                                    fontSize = 12.sp,
                                    modifier = Modifier.padding(bottom = 2.dp, start = 2.dp),
                                )
                            }
                            impact.battingDetail?.let {
                                Spacer(Modifier.height(2.dp))
                                Text(
                                    it,
                                    color = Color(0xFF34D399),
                                    fontFamily = PlusJakartaSans,
                                    fontWeight = FontWeight.SemiBold,
                                    fontSize = 11.sp,
                                )
                            }
                        }
                    }

                    if (hasBat && hasBowl) {
                        Box(
                            Modifier
                                .padding(horizontal = 12.dp)
                                .width(1.dp)
                                .height(38.dp)
                                .background(Color(0x22FFFFFF))
                        )
                    }

                    if (hasBowl) {
                        Column(Modifier.weight(1f)) {
                            Text(
                                "BOWLING",
                                color = Color(0xFF94A3B8),
                                fontFamily = PlusJakartaSans,
                                fontWeight = FontWeight.Bold,
                                fontSize = 9.5.sp,
                                letterSpacing = 1.2.sp,
                            )
                            Spacer(Modifier.height(2.dp))
                            Text(
                                impact.bowlingLine.orEmpty(),
                                color = Color.White,
                                fontFamily = PlusJakartaSans,
                                fontWeight = FontWeight.Black,
                                fontSize = 21.sp,
                            )
                            impact.bowlingDetail?.let {
                                Spacer(Modifier.height(2.dp))
                                Text(
                                    it,
                                    color = Color(0xFF60A5FA),
                                    fontFamily = PlusJakartaSans,
                                    fontWeight = FontWeight.SemiBold,
                                    fontSize = 11.sp,
                                )
                            }
                        }
                    } else if (hasBat) {
                        Box(
                            Modifier
                                .padding(horizontal = 12.dp)
                                .width(1.dp)
                                .height(38.dp)
                                .background(Color(0x22FFFFFF))
                        )
                        Column(Modifier.weight(0.8f)) {
                            Text(
                                "IMPACT",
                                color = Color(0xFF94A3B8),
                                fontFamily = PlusJakartaSans,
                                fontWeight = FontWeight.Bold,
                                fontSize = 9.5.sp,
                                letterSpacing = 1.2.sp,
                            )
                            Spacer(Modifier.height(2.dp))
                            Text(
                                "${impact.impactScore ?: "85.0"} pts",
                                color = Color(0xFFFDE68A),
                                fontFamily = PlusJakartaSans,
                                fontWeight = FontWeight.ExtraBold,
                                fontSize = 18.sp,
                            )
                            Spacer(Modifier.height(2.dp))
                            Text(
                                "Matchwinning",
                                color = Color(0xFFCBD5E1),
                                fontFamily = PlusJakartaSans,
                                fontWeight = FontWeight.Medium,
                                fontSize = 11.sp,
                            )
                        }
                    }
                }
            } else if (impact.chips.isNotEmpty()) {
                Row(
                    Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                ) {
                    impact.chips.forEach { chip ->
                        DarkStatTile(chip.label, chip.value, Modifier.weight(1f))
                    }
                }
            }
        }
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
private fun DarkStatTile(label: String, value: String, modifier: Modifier = Modifier) {
    Column(
        modifier = modifier
            .clip(RoundedCornerShape(10.dp))
            .background(Color(0x1AFFFFFF))
            .border(1.dp, Color(0x20FFFFFF), RoundedCornerShape(10.dp))
            .padding(horizontal = 6.dp, vertical = 8.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Text(
            label,
            color = Color(0xFF94A3B8),
            fontFamily = PlusJakartaSans,
            fontWeight = FontWeight.Bold,
            fontSize = 9.5.sp,
            letterSpacing = 1.sp,
        )
        Spacer(Modifier.height(2.dp))
        Text(
            value,
            color = Color.White,
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
