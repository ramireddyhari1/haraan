package com.haraan.app.ui.rewards

import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.EmojiEvents
import androidx.compose.material.icons.filled.MilitaryTech
import androidx.compose.material.icons.filled.Shield
import androidx.compose.material.icons.filled.SportsCricket
import androidx.compose.material.icons.filled.Star
import androidx.compose.material.icons.filled.TrendingUp
import androidx.compose.material.icons.filled.Whatshot
import androidx.compose.material.icons.filled.WorkspacePremium
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.ui.theme.PlusJakartaSans

/**
 * "I'm making progress." Athletic career journey: weekly streak, progress to the closest badge,
 * and medals unlocked in this match.
 */
@Composable
internal fun ProgressPanel(progress: ProgressUi, reveal: Boolean) {
    Column(Modifier.fillMaxWidth().card().padding(20.dp)) {
        Streak(progress)
        progress.nextBadge?.let {
            Divider()
            NextBadge(it, reveal)
        }
        if (progress.newBadges.isNotEmpty()) {
            Divider()
            Eyebrow("Unlocked this match")
            Spacer(Modifier.height(14.dp))
            Row(horizontalArrangement = Arrangement.spacedBy(16.dp)) {
                progress.newBadges.take(4).forEach { b ->
                    Column(horizontalAlignment = Alignment.CenterHorizontally, modifier = Modifier.width(76.dp)) {
                        Medal(b.icon, b.tier, 48.dp)
                        Spacer(Modifier.height(6.dp))
                        Text(
                            b.name,
                            color = Board.Ink,
                            fontFamily = PlusJakartaSans,
                            fontWeight = FontWeight.SemiBold,
                            fontSize = 12.sp,
                            textAlign = TextAlign.Center,
                            maxLines = 2,
                            overflow = TextOverflow.Ellipsis,
                        )
                    }
                }
            }
        }
    }
}

@Composable
private fun Divider() {
    Spacer(Modifier.height(16.dp))
    Box(Modifier.fillMaxWidth().height(1.dp).background(Board.Line))
    Spacer(Modifier.height(16.dp))
}

@Composable
private fun Streak(p: ProgressUi) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        Column(Modifier.weight(1f)) {
            Eyebrow("Weekly Streak")
            Spacer(Modifier.height(4.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                    when (p.streakWeeks) {
                        0 -> "No streak yet"
                        1 -> "1 Week Active"
                        else -> "${p.streakWeeks} Weeks Active"
                    },
                    color = Board.Ink,
                    fontFamily = PlusJakartaSans,
                    fontWeight = FontWeight.ExtraBold,
                    fontSize = 22.sp,
                    letterSpacing = (-0.5).sp,
                )
                if (p.streakWeeks > 0) {
                    Spacer(Modifier.width(6.dp))
                    Text("🔥", fontSize = 18.sp)
                }
            }
            if (p.bestWeeks > p.streakWeeks) {
                Spacer(Modifier.height(2.dp))
                Text("Personal best: ${p.bestWeeks} consecutive weeks", color = Board.InkFaint, fontFamily = PlusJakartaSans, fontSize = 12.sp)
            }
        }
        WeekDots(p.streakWeeks, p.playedThisWeek)
    }
}

/**
 * Up to eight week pills, oldest → newest. Filled = counted week. Last pill = this week.
 */
@Composable
private fun WeekDots(weeks: Int, playedThisWeek: Boolean) {
    val filled = weeks.coerceIn(0, 8)
    val slots = if (playedThisWeek) filled.coerceAtLeast(1) else (filled + 1).coerceAtMost(8)
    Row(horizontalArrangement = Arrangement.spacedBy(5.dp), verticalAlignment = Alignment.Bottom) {
        repeat(slots) { i ->
            val isThisWeek = i == slots - 1
            val done = i < filled
            Box(
                Modifier
                    .width(10.dp)
                    .height(if (isThisWeek) 26.dp else 18.dp)
                    .clip(RoundedCornerShape(5.dp))
                    .then(
                        when {
                            done && isThisWeek -> Modifier.background(Board.Green)
                            done -> Modifier.background(Board.Blue.copy(alpha = 0.35f + 0.65f * (i + 1).toFloat() / slots))
                            else -> Modifier.border(1.5.dp, Board.Blue.copy(alpha = 0.5f), RoundedCornerShape(5.dp))
                        }
                    ),
            )
        }
    }
}

@Composable
private fun NextBadge(next: NextBadgeUi, reveal: Boolean) {
    val fill by animateFloatAsState(
        if (reveal) next.fraction else 0f,
        tween(700, delayMillis = 150, easing = FastOutSlowInEasing),
        label = "bar",
    )
    Row(verticalAlignment = Alignment.CenterVertically) {
        Medal(next.badge.icon, next.badge.tier, 44.dp, locked = true)
        Spacer(Modifier.width(14.dp))
        Column(Modifier.weight(1f)) {
            Eyebrow("Next Milestone")
            Spacer(Modifier.height(3.dp))
            Text(
                next.badge.name,
                color = Board.Ink,
                fontFamily = PlusJakartaSans,
                fontWeight = FontWeight.Bold,
                fontSize = 15.sp,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
            )
            Spacer(Modifier.height(7.dp))
            Box(
                Modifier
                    .fillMaxWidth()
                    .height(6.dp)
                    .clip(RoundedCornerShape(3.dp))
                    .background(Board.Raised),
            ) {
                Box(
                    Modifier
                        .fillMaxWidth(fill)
                        .fillMaxHeight()
                        .clip(RoundedCornerShape(3.dp))
                        .background(Board.Blue),
                )
            }
            Spacer(Modifier.height(5.dp))
            Text(next.progressLabel, color = Board.InkMuted, fontFamily = PlusJakartaSans, fontSize = 12.sp)
        }
    }
}

internal fun badgeIcon(key: String): ImageVector = when (key) {
    "Star" -> Icons.Filled.Star
    "WorkspacePremium" -> Icons.Filled.WorkspacePremium
    "MilitaryTech" -> Icons.Filled.MilitaryTech
    "Whatshot" -> Icons.Filled.Whatshot
    "Shield" -> Icons.Filled.Shield
    "TrendingUp" -> Icons.Filled.TrendingUp
    "SportsCricket" -> Icons.Filled.SportsCricket
    else -> Icons.Filled.EmojiEvents
}

/** Modern vector medal: clean layered disc with tier-colored iconography. */
@Composable
internal fun Medal(icon: String, tier: String, size: Dp, locked: Boolean = false) {
    val (primary, bg) = if (locked) {
        Board.InkFaint to Board.Raised
    } else {
        when (tier.lowercase()) {
            "gold" -> Board.Gold to Board.GoldWell
            "silver" -> Color(0xFF64748B) to Color(0xFFF1F5F9)
            else -> Color(0xFFB45309) to Color(0xFFFEF3C7) // bronze
        }
    }
    Box(
        Modifier
            .size(size)
            .clip(CircleShape)
            .background(bg)
            .border(1.5.dp, primary.copy(alpha = 0.4f), CircleShape),
        contentAlignment = Alignment.Center,
    ) {
        Icon(badgeIcon(icon), null, tint = primary, modifier = Modifier.size(size * 0.48f))
    }
}
