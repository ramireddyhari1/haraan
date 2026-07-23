package com.haraan.app.ui.matches

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
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.Schedule
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import kotlinx.coroutines.launch

private val Surface = Color(0xFFFFFFFF)
private val Blue = Color(0xFF2563EB)
private val Green = Color(0xFF16A34A)
private val Text1 = Color(0xFF111827)
private val Text2 = Color(0xFF5A5A6A)
private val Stroke = Color(0xFFE2E8F0)
private val BlueTint = Color(0xFFEFF4FF)
private val GreenTint = Color(0xFFE9F7EF)

private fun trustLabel(trust: String): String = when (trust.lowercase()) {
    "verified" -> "Verified"
    "high" -> "High trust"
    "medium" -> "Medium trust"
    else -> "Low trust"
}

/**
 * Result-verification affordance shared by every sport's match view. It renders
 * only once a match is Completed and in/after the verification window:
 *  • the viewer can confirm → a "Confirm result" button (drives the match toward
 *    Medium trust + ranked XP once both sides confirm);
 *  • already confirmed / waiting on the other side → an "awaiting" note;
 *  • settled → the trust it landed at;
 *  • expired → window-closed note.
 * Returns nothing to draw (an empty box) for Scheduled/Live matches.
 *
 * [onConfirm] performs the network call; this composable owns the busy/refresh UX.
 */
@Composable
fun ResultVerificationBar(
    state: MatchUiState,
    onConfirm: suspend () -> Unit,
    modifier: Modifier = Modifier,
) {
    val status = state.verificationStatus
    // Nothing to surface before a match reaches the verification pipeline.
    if (!state.canConfirm && status !in setOf("pending", "settled", "expired")) return

    var busy by remember { mutableStateOf(false) }
    val scope = rememberCoroutineScope()

    Column(
        modifier = modifier
            .fillMaxWidth()
            .background(
                when {
                    status == "settled" -> GreenTint
                    else -> BlueTint
                },
                RoundedCornerShape(14.dp),
            )
            .border(
                1.dp,
                (if (status == "settled") Green else Blue).copy(alpha = 0.3f),
                RoundedCornerShape(14.dp),
            )
            .padding(14.dp),
    ) {
        when {
            state.canConfirm -> {
                Text("Confirm the result", color = Blue, fontSize = 14.sp, fontWeight = FontWeight.Bold)
                Spacer(Modifier.height(4.dp))
                Text(
                    "Both captains confirm to settle this match at Medium trust and unlock ranked XP.",
                    color = Text2, fontSize = 13.sp, lineHeight = 18.sp,
                )
                Spacer(Modifier.height(12.dp))
                Button(
                    onClick = {
                        if (!busy) {
                            busy = true
                            scope.launch {
                                runCatching { onConfirm() }
                                busy = false
                            }
                        }
                    },
                    enabled = !busy,
                    modifier = Modifier.fillMaxWidth().height(46.dp),
                    shape = RoundedCornerShape(12.dp),
                    colors = ButtonDefaults.buttonColors(containerColor = Blue, contentColor = Color.White),
                ) {
                    if (busy) {
                        CircularProgressIndicator(modifier = Modifier.size(18.dp), strokeWidth = 2.dp, color = Color.White)
                    } else {
                        Text("Confirm result", fontSize = 15.sp, fontWeight = FontWeight.Bold)
                    }
                }
            }
            status == "settled" -> InfoRow(
                icon = { Icon(Icons.Filled.CheckCircle, null, tint = Green, modifier = Modifier.size(18.dp)) },
                title = "Result verified · ${trustLabel(state.trustLevel)}",
                body = "XP has settled for the registered players.",
                titleColor = Green,
            )
            status == "expired" -> InfoRow(
                icon = { Icon(Icons.Filled.Schedule, null, tint = Text2, modifier = Modifier.size(18.dp)) },
                title = "Verification window closed",
                body = "It wasn't confirmed in time, so it settled at Low trust.",
                titleColor = Text1,
            )
            else -> InfoRow( // pending, but this viewer can't (or already did) confirm
                icon = { Icon(Icons.Filled.Schedule, null, tint = Blue, modifier = Modifier.size(18.dp)) },
                title = "Awaiting confirmation",
                body = if (state.homeConfirmed || state.awayConfirmed)
                    "One side has confirmed — waiting for the other captain."
                else
                    "Both captains need to confirm the result to settle it.",
                titleColor = Blue,
            )
        }
    }
}

@Composable
private fun InfoRow(
    icon: @Composable () -> Unit,
    title: String,
    body: String,
    titleColor: Color,
) {
    Row(verticalAlignment = Alignment.Top, horizontalArrangement = Arrangement.spacedBy(10.dp)) {
        Box(Modifier.padding(top = 1.dp)) { icon() }
        Column {
            Text(title, color = titleColor, fontSize = 14.sp, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(2.dp))
            Text(body, color = Text2, fontSize = 13.sp, lineHeight = 18.sp)
        }
    }
}
