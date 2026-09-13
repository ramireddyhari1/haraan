package com.haraan.partner.ui.components

import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.*
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Bolt
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.Payments
import androidx.compose.material.icons.filled.Share
import androidx.compose.material.icons.filled.Timer
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.partner.ui.theme.HaraanTheme
import com.haraan.partner.ui.theme.haraanCard

/**
 * Enterprise temporary slot hold card with centrally configurable duration
 * (defaults to 5 minutes), breathing pulse border, tabular non-jittering countdown,
 * and high-velocity 1-tap cashier actions.
 */
@Composable
fun HaraanHoldSlotTimerCard(
    courtName: String,
    date: String,
    timeSlot: String,
    price: Double,
    holdSecondsLeft: Int,
    isLoading: Boolean,
    onHoldAndSendLink: () -> Unit,
    onSendPaymentLink: () -> Unit,
    onMarkPaidCash: () -> Unit,
    onReleaseHold: () -> Unit,
    onDismiss: () -> Unit,
    modifier: Modifier = Modifier,
    holdDurationMinutes: Int = HaraanTheme.DEFAULT_HOLD_DURATION_MINUTES,
    sportName: String? = null
) {
    val isHoldActive = holdSecondsLeft > 0
    val totalHoldSeconds = (holdDurationMinutes * 60).coerceAtLeast(1)

    // Breathing pulse border animation for active hold
    val infiniteTransition = rememberInfiniteTransition(label = "pulseTransition")
    val pulseAlpha by infiniteTransition.animateFloat(
        initialValue = 0.4f,
        targetValue = 1.0f,
        animationSpec = infiniteRepeatable(
            animation = tween(durationMillis = 1000),
            repeatMode = RepeatMode.Reverse
        ),
        label = "pulseAlpha"
    )

    val cardBg = if (isHoldActive) HaraanTheme.colors.amberSubtle else HaraanTheme.colors.surfaceDefault
    val borderColor = if (isHoldActive) {
        HaraanTheme.colors.amberBorder.copy(alpha = pulseAlpha)
    } else {
        HaraanTheme.colors.borderHairline
    }

    Card(
        modifier = modifier
            .fillMaxWidth()
            .padding(horizontal = 12.dp, vertical = 6.dp)
            .haraanCard(radius = 16.dp, containerColor = cardBg, borderColor = borderColor),
        shape = HaraanTheme.shapes.card,
        colors = CardDefaults.cardColors(containerColor = cardBg)
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(14.dp),
            verticalArrangement = Arrangement.spacedBy(10.dp)
        ) {
            // Header Row: Status badge / Title & Dismiss
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(6.dp)
                ) {
                    Icon(
                        imageVector = if (isHoldActive) Icons.Filled.Timer else Icons.Filled.Bolt,
                        contentDescription = null,
                        tint = if (isHoldActive) HaraanTheme.colors.amberPrimary else HaraanTheme.colors.emeraldPrimary,
                        modifier = Modifier.size(18.dp)
                    )
                    Text(
                        text = if (isHoldActive) "$holdDurationMinutes-MINUTE HOLD ACTIVE" else "1-TAP BOOKING SUGGESTION",
                        fontSize = 11.sp,
                        fontWeight = FontWeight.Bold,
                        color = if (isHoldActive) HaraanTheme.colors.amberPrimary else HaraanTheme.colors.emeraldPrimary,
                        letterSpacing = 0.5.sp
                    )
                }

                if (isHoldActive) {
                    val mins = holdSecondsLeft / 60
                    val secs = holdSecondsLeft % 60
                    val timerStr = String.format("%02d:%02d", mins, secs)

                    // Tabular Non-Jittering Timer Pill
                    Row(
                        modifier = Modifier
                            .clip(HaraanTheme.shapes.pill)
                            .background(HaraanTheme.colors.amberPrimary)
                            .padding(horizontal = 10.dp, vertical = 4.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(4.dp)
                    ) {
                        Text(
                            text = "⏱️",
                            fontSize = 11.sp
                        )
                        Text(
                            text = "$timerStr left",
                            style = HaraanTheme.typography.tnumTimer,
                            color = Color.White
                        )
                    }
                } else {
                    IconButton(
                        onClick = onDismiss,
                        modifier = Modifier.size(24.dp)
                    ) {
                        Icon(
                            imageVector = Icons.Filled.Close,
                            contentDescription = "Dismiss",
                            tint = HaraanTheme.colors.textSecondary,
                            modifier = Modifier.size(16.dp)
                        )
                    }
                }
            }

            // Progress Bar for remaining hold time
            if (isHoldActive) {
                val progress = (holdSecondsLeft.toFloat() / totalHoldSeconds.toFloat()).coerceIn(0f, 1f)
                LinearProgressIndicator(
                    progress = { progress },
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(4.dp)
                        .clip(HaraanTheme.shapes.pill),
                    color = HaraanTheme.colors.amberPrimary,
                    trackColor = HaraanTheme.colors.amberBorder.copy(alpha = 0.5f)
                )
            }

            // Court & Time Details Row
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Column {
                    Text(
                        text = "$courtName (${sportName ?: "Turf"})",
                        fontSize = 15.sp,
                        fontWeight = FontWeight.Bold,
                        color = HaraanTheme.colors.textPrimary
                    )
                    Text(
                        text = "$date • $timeSlot",
                        fontSize = 12.sp,
                        color = HaraanTheme.colors.textSecondary
                    )
                }

                Text(
                    text = "₹${price.toInt()}",
                    style = HaraanTheme.typography.tnumMetricMedium,
                    color = HaraanTheme.colors.slateDark
                )
            }

            // Action Strip
            if (!isHoldActive) {
                HaraanButton(
                    text = "⚡ 1-Tap $holdDurationMinutes-Min Hold & Payment Link",
                    onClick = onHoldAndSendLink,
                    isLoading = isLoading,
                    style = HaraanButtonStyle.Primary,
                    modifier = Modifier.fillMaxWidth(),
                    height = 46.dp
                )
            } else {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    HaraanButton(
                        text = "Send Link",
                        onClick = onSendPaymentLink,
                        leadingIcon = Icons.Filled.Share,
                        isLoading = isLoading,
                        style = HaraanButtonStyle.Primary,
                        modifier = Modifier.weight(1f),
                        height = 44.dp
                    )

                    HaraanButton(
                        text = "Mark Paid",
                        onClick = onMarkPaidCash,
                        leadingIcon = Icons.Filled.Payments,
                        isLoading = isLoading,
                        style = HaraanButtonStyle.Executive,
                        modifier = Modifier.weight(1f),
                        height = 44.dp
                    )

                    OutlinedButton(
                        onClick = onReleaseHold,
                        enabled = !isLoading,
                        shape = HaraanTheme.shapes.medium,
                        modifier = Modifier
                            .width(44.dp)
                            .height(44.dp),
                        contentPadding = PaddingValues(0.dp),
                        colors = ButtonDefaults.outlinedButtonColors(
                            containerColor = HaraanTheme.colors.crimsonSubtle
                        ),
                        border = androidx.compose.foundation.BorderStroke(1.dp, HaraanTheme.colors.crimsonBorder)
                    ) {
                        Icon(
                            imageVector = Icons.Filled.Close,
                            contentDescription = "Release Hold",
                            tint = HaraanTheme.colors.crimsonPrimary,
                            modifier = Modifier.size(18.dp)
                        )
                    }
                }
            }
        }
    }
}
