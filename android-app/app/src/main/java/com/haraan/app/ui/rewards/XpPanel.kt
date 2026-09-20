package com.haraan.app.ui.rewards

import android.view.HapticFeedbackConstants
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Check
import androidx.compose.material.icons.outlined.CheckCircle
import androidx.compose.material.icons.outlined.Schedule
import androidx.compose.material.icons.outlined.Share
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.ui.theme.PlusJakartaSans
import kotlinx.coroutines.delay

internal val CardShape = RoundedCornerShape(20.dp)

internal fun Modifier.card(): Modifier =
    clip(CardShape).background(Board.Surface).border(1.dp, Board.Line, CardShape)

/**
 * "I earned something." Competitive XP ranks you on leaderboards; Bonus XP is commercial reward currency.
 * Pending XP is presented positively as "Projected XP" without an anxiety-inducing infinite spinner.
 */
@Composable
internal fun XpPanel(xp: XpUi, verification: VerificationUi?, countUp: Boolean, onRemind: () -> Unit) {
    Column(Modifier.fillMaxWidth().card().padding(20.dp)) {
        Eyebrow("Match XP")
        Spacer(Modifier.height(12.dp))

        when (xp.state) {
            XpState.SETTLED -> SettledXp(xp, countUp)
            XpState.NOT_ELIGIBLE -> Text(
                "Private matches don't earn leaderboard XP.",
                color = Board.InkMuted,
                fontFamily = PlusJakartaSans,
                fontSize = 14.sp,
            )
            XpState.PENDING -> PendingXp(xp)
        }

        verification?.let {
            Spacer(Modifier.height(16.dp))
            Box(Modifier.fillMaxWidth().height(1.dp).background(Board.Line))
            Spacer(Modifier.height(14.dp))
            VerificationSteps(it, onRemind)
        }

        if (xp.bonus > 0) {
            Spacer(Modifier.height(14.dp))
            Row(
                Modifier
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(10.dp))
                    .background(Board.Raised)
                    .padding(horizontal = 12.dp, vertical = 8.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Text(
                    "+${xp.bonus}",
                    color = Board.Ink,
                    fontFamily = PlusJakartaSans,
                    fontWeight = FontWeight.ExtraBold,
                    fontSize = 14.sp,
                )
                Spacer(Modifier.width(6.dp))
                Text(
                    "bonus points · redeemable perks (separate from ranking)",
                    color = Board.InkMuted,
                    fontFamily = PlusJakartaSans,
                    fontSize = 12.sp,
                )
            }
        }
    }
}

@Composable
private fun SettledXp(xp: XpUi, countUp: Boolean) {
    val view = LocalView.current
    val target = xp.xp ?: 0
    var shown by remember { mutableIntStateOf(if (countUp) 0 else target) }
    LaunchedEffect(countUp, target) {
        if (!countUp || target <= 0) {
            shown = target
            return@LaunchedEffect
        }
        delay(100)
        val a = Animatable(0f)
        var last = 0
        a.animateTo(target.toFloat(), tween(650, easing = FastOutSlowInEasing)) {
            shown = value.toInt()
            if (shown - last >= maxOf(1, target / 8)) {
                last = shown
                view.performHapticFeedback(HapticFeedbackConstants.CLOCK_TICK)
            }
        }
        shown = target
    }
    Row(verticalAlignment = Alignment.Bottom) {
        Text(
            "+$shown",
            color = Board.Green,
            fontFamily = PlusJakartaSans,
            fontWeight = FontWeight.ExtraBold,
            fontSize = 44.sp,
            letterSpacing = (-1.5).sp,
        )
        Spacer(Modifier.width(8.dp))
        Text(
            "XP",
            color = Board.Ink,
            fontFamily = PlusJakartaSans,
            fontWeight = FontWeight.ExtraBold,
            fontSize = 18.sp,
            modifier = Modifier.padding(bottom = 8.dp),
        )
        Spacer(Modifier.weight(1f))
        Text(
            if (xp.ranked) "RANKED" else "CASUAL",
            color = if (xp.ranked) Board.Blue else Board.InkMuted,
            fontFamily = PlusJakartaSans,
            fontWeight = FontWeight.Bold,
            fontSize = 10.5.sp,
            letterSpacing = 1.2.sp,
            modifier = Modifier
                .padding(bottom = 10.dp)
                .clip(RoundedCornerShape(50))
                .background(if (xp.ranked) Board.BlueWell else Board.Raised)
                .padding(horizontal = 9.dp, vertical = 3.dp),
        )
    }
    Spacer(Modifier.height(2.dp))
    Text(
        if (xp.ranked) "Applied to district, state and India competitive leaderboards." else "Added to your casual record.",
        color = Board.InkMuted,
        fontFamily = PlusJakartaSans,
        fontSize = 12.5.sp,
    )
}

@Composable
private fun PendingXp(xp: XpUi) {
    Row(
        Modifier.fillMaxWidth(),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(
            Modifier
                .size(44.dp)
                .clip(CircleShape)
                .background(Board.AmberWell),
            contentAlignment = Alignment.Center,
        ) {
            Icon(Icons.Outlined.Schedule, "Pending", tint = Board.Amber, modifier = Modifier.size(22.dp))
        }
        Spacer(Modifier.width(14.dp))
        Column(Modifier.weight(1f)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                    "Scorecard in Verification",
                    color = Board.Ink,
                    fontFamily = PlusJakartaSans,
                    fontWeight = FontWeight.Bold,
                    fontSize = 16.sp,
                )
                Spacer(Modifier.width(8.dp))
                Text(
                    "PROJECTED",
                    color = Board.Amber,
                    fontFamily = PlusJakartaSans,
                    fontWeight = FontWeight.ExtraBold,
                    fontSize = 9.5.sp,
                    letterSpacing = 1.sp,
                    modifier = Modifier
                        .clip(RoundedCornerShape(50))
                        .background(Board.AmberWell)
                        .padding(horizontal = 7.dp, vertical = 2.dp),
                )
            }
            Spacer(Modifier.height(2.dp))
            Text(
                "Your leaderboard XP confirms once both captains verify the result.",
                color = Board.InkMuted,
                fontFamily = PlusJakartaSans,
                fontSize = 12.5.sp,
                lineHeight = 17.sp,
            )
        }
    }
}

/** Verification progress without nagging checklist styling. */
@Composable
private fun VerificationSteps(v: VerificationUi, onRemind: () -> Unit) {
    Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
        if (v.needsOrganiser) {
            StepRow("Organiser / Venue Verification", done = false)
        } else {
            StepRow("Your captain's confirmation", done = v.yourCaptain)
            StepRow("Opposition captain's confirmation", done = v.opposition)
        }
        if (!(v.yourCaptain && v.opposition) && !v.needsOrganiser) {
            Spacer(Modifier.height(2.dp))
            ActionButton("Remind captains to sign off", primary = false, icon = Icons.Outlined.Share, compact = true, onClick = onRemind)
        }
    }
}

@Composable
private fun StepRow(label: String, done: Boolean) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        Box(
            Modifier
                .size(20.dp)
                .clip(CircleShape)
                .then(
                    if (done) Modifier.background(Board.Green)
                    else Modifier.border(1.5.dp, Board.Line, CircleShape),
                ),
            contentAlignment = Alignment.Center,
        ) {
            if (done) Icon(Icons.Filled.Check, null, tint = Color.White, modifier = Modifier.size(13.dp))
        }
        Spacer(Modifier.width(10.dp))
        Text(
            label,
            color = if (done) Board.Ink else Board.InkMuted,
            fontFamily = PlusJakartaSans,
            fontSize = 13.sp,
            fontWeight = if (done) FontWeight.SemiBold else FontWeight.Normal,
        )
    }
}
