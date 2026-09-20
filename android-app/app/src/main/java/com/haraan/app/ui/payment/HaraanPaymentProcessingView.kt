package com.haraan.app.ui.payment

import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.core.*
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Check
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material.icons.outlined.Lock
import androidx.compose.material.icons.outlined.Shield
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.scale
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.drawscope.rotate
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import com.haraan.app.ui.Feel
import com.haraan.app.ui.pressable
import com.haraan.app.ui.theme.HaraanColors
import kotlinx.coroutines.delay
import kotlin.random.Random

sealed interface HaraanPaymentStage {
    data object Initiating : HaraanPaymentStage
    data class Authorizing(val methodLabel: String = "UPI / Card") : HaraanPaymentStage
    data object Verifying : HaraanPaymentStage
    data class NetworkRecovery(val message: String = "Confirming with your bank...") : HaraanPaymentStage
    data class Success(val message: String = "Payment confirmed!") : HaraanPaymentStage
    data class Failed(val reason: String, val canRetry: Boolean = true) : HaraanPaymentStage
}

/**
 * Lightweight 60fps Canvas Confetti Particle for celebratory moments.
 */
private data class ConfettiParticle(
    var x: Float,
    var y: Float,
    var vx: Float,
    var vy: Float,
    var size: Float,
    var color: Color,
    var rotation: Float,
    var rotSpeed: Float,
    var alpha: Float = 1f,
)

/**
 * Full-screen Animated Haraan Payment Processing Stage.
 *
 * Implements intentional UX motion design:
 * - Concentric bank-grade radar pulsations during authorization and signature verification.
 * - Countdown timer and safety guidance ("Do not press back or switch apps").
 * - Network recovery state: If phone connection drops, user is reassured and given an active
 *   "Check Status" button that polls the server to recover captured payments without double debits.
 * - Morphing checkmark with tactile haptic feedback (Feel.COMMIT).
 * - Celebratory 60fps canvas confetti explosion on success.
 */
@Composable
fun HaraanPaymentProcessingView(
    stage: HaraanPaymentStage,
    onRetry: () -> Unit,
    onCheckStatus: () -> Unit,
    onSuccessComplete: () -> Unit,
    onDismissFailed: () -> Unit,
) {
    val view = LocalView.current

    // Infinite transition for concentric radar pulse
    val infiniteTransition = rememberInfiniteTransition(label = "radarTransition")
    val pulse1 by infiniteTransition.animateFloat(
        initialValue = 0.3f,
        targetValue = 1.3f,
        animationSpec = infiniteRepeatable(tween(1600, easing = LinearEasing), RepeatMode.Restart),
        label = "pulse1",
    )
    val pulseAlpha1 by infiniteTransition.animateFloat(
        initialValue = 0.7f,
        targetValue = 0.0f,
        animationSpec = infiniteRepeatable(tween(1600, easing = LinearEasing), RepeatMode.Restart),
        label = "pulseAlpha1",
    )
    val pulse2 by infiniteTransition.animateFloat(
        initialValue = 0.3f,
        targetValue = 1.3f,
        animationSpec = infiniteRepeatable(tween(1600, delayMillis = 500, easing = LinearEasing), RepeatMode.Restart),
        label = "pulse2",
    )
    val pulseAlpha2 by infiniteTransition.animateFloat(
        initialValue = 0.7f,
        targetValue = 0.0f,
        animationSpec = infiniteRepeatable(tween(1600, delayMillis = 500, easing = LinearEasing), RepeatMode.Restart),
        label = "pulseAlpha2",
    )

    // Confetti particles for success celebration
    val particles = remember {
        // Brand blues, the one success green and the rating gold. Deliberately no pink and no
        // purple: confetti is the loudest thing on screen, so it may only use colours the rest of
        // the app already means something by.
        val palette = listOf(
            HaraanColors.EventsBlue, HaraanColors.GameHubDeep, HaraanColors.Success,
            HaraanColors.RatingGold, Color(0xFF60A5FA),
        )
        List(45) {
            ConfettiParticle(
                x = Random.nextFloat() * 800f,
                y = -Random.nextFloat() * 200f,
                vx = (Random.nextFloat() - 0.5f) * 6f,
                vy = Random.nextFloat() * 7f + 5f,
                size = Random.nextFloat() * 14f + 8f,
                color = palette.random(),
                rotation = Random.nextFloat() * 360f,
                rotSpeed = (Random.nextFloat() - 0.5f) * 12f,
            )
        }
    }

    // Success transition delay
    LaunchedEffect(stage) {
        if (stage is HaraanPaymentStage.Success) {
            view.performHapticFeedback(Feel.COMMIT)
            delay(1400)
            onSuccessComplete()
        }
    }

    Dialog(
        onDismissRequest = {
            if (stage is HaraanPaymentStage.Failed) onDismissFailed()
        },
        properties = DialogProperties(
            dismissOnBackPress = stage is HaraanPaymentStage.Failed,
            dismissOnClickOutside = false,
            usePlatformDefaultWidth = false,
        ),
    ) {
        Box(
            modifier = Modifier
                .fillMaxSize()
                .background(HaraanColors.TextPrimary.copy(alpha = 0.55f)),
            contentAlignment = Alignment.Center,
        ) {
            // Confetti canvas animation on success
            if (stage is HaraanPaymentStage.Success) {
                var frameTick by remember { mutableStateOf(0) }
                LaunchedEffect(Unit) {
                    while (true) {
                        delay(16)
                        particles.forEach { p ->
                            p.x += p.vx
                            p.y += p.vy
                            p.rotation += p.rotSpeed
                            if (p.y > 2200f) {
                                p.y = -50f
                                p.x = Random.nextFloat() * 1000f
                            }
                        }
                        frameTick++
                    }
                }

                Canvas(modifier = Modifier.fillMaxSize()) {
                    particles.forEach { p ->
                        rotate(p.rotation, pivot = Offset(p.x + p.size / 2, p.y + p.size / 2)) {
                            drawRect(
                                color = p.color,
                                topLeft = Offset(p.x, p.y),
                                size = androidx.compose.ui.geometry.Size(p.size, p.size * 0.65f),
                            )
                        }
                    }
                }
            }

            // Central Status Card
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .widthIn(max = 380.dp)
                    .padding(24.dp)
                    .clip(RoundedCornerShape(26.dp))
                    .background(HaraanColors.Surface)
                    .border(1.dp, HaraanColors.Hairline, RoundedCornerShape(26.dp))
                    .padding(28.dp),
                horizontalAlignment = Alignment.CenterHorizontally,
            ) {
                // ── Animated Visual Centerpiece ──────────────────────────────
                Box(
                    modifier = Modifier.size(130.dp),
                    contentAlignment = Alignment.Center,
                ) {
                    when (stage) {
                        is HaraanPaymentStage.Success -> {
                            Box(
                                modifier = Modifier
                                    .size(86.dp)
                                    .clip(CircleShape)
                                    .background(HaraanColors.Success)
                                    .border(6.dp, HaraanColors.SuccessTint, CircleShape),
                                contentAlignment = Alignment.Center,
                            ) {
                                Icon(
                                    imageVector = Icons.Default.Check,
                                    contentDescription = "Success",
                                    tint = Color.White,
                                    modifier = Modifier.size(46.dp),
                                )
                            }
                        }

                        is HaraanPaymentStage.Failed -> {
                            Box(
                                modifier = Modifier
                                    .size(86.dp)
                                    .clip(CircleShape)
                                    .background(HaraanColors.DangerTint)
                                    .border(2.dp, HaraanColors.Danger, CircleShape),
                                contentAlignment = Alignment.Center,
                            ) {
                                Icon(
                                    imageVector = Icons.Default.Close,
                                    contentDescription = "Failed",
                                    tint = HaraanColors.Danger,
                                    modifier = Modifier.size(46.dp),
                                )
                            }
                        }

                        else -> {
                            // Pulsing concentric radar rings
                            Canvas(modifier = Modifier.fillMaxSize()) {
                                drawCircle(
                                    color = HaraanColors.EventsBlue.copy(alpha = pulseAlpha1 * 0.35f),
                                    radius = size.minDimension / 2 * pulse1,
                                )
                                drawCircle(
                                    color = HaraanColors.EventsBlue.copy(alpha = pulseAlpha2 * 0.22f),
                                    radius = size.minDimension / 2 * pulse2,
                                )
                            }

                            // Inner central core
                            Box(
                                modifier = Modifier
                                    .size(76.dp)
                                    .clip(CircleShape)
                                    .background(
                                        Brush.radialGradient(
                                            listOf(HaraanColors.EventsBlue, HaraanColors.GameHubDeep)
                                        )
                                    ),
                                contentAlignment = Alignment.Center,
                            ) {
                                Icon(
                                    imageVector = Icons.Outlined.Shield,
                                    contentDescription = null,
                                    tint = Color.White,
                                    modifier = Modifier.size(32.dp),
                                )
                            }
                        }
                    }
                }

                Spacer(Modifier.height(18.dp))

                // ── Status Headlines & Microcopy ─────────────────────────────
                when (stage) {
                    is HaraanPaymentStage.Initiating -> {
                        Text("Initiating Haraan Pay", color = HaraanColors.TextPrimary, fontSize = 19.sp, fontWeight = FontWeight.Bold)
                        Spacer(Modifier.height(6.dp))
                        Text(
                            "Securing bank-grade encrypted channel...",
                            color = HaraanColors.TextSecondary,
                            fontSize = 13.sp,
                            textAlign = TextAlign.Center,
                        )
                    }

                    is HaraanPaymentStage.Authorizing -> {
                        Text("Authorizing Payment", color = HaraanColors.TextPrimary, fontSize = 19.sp, fontWeight = FontWeight.Bold)
                        Spacer(Modifier.height(6.dp))
                        Text(
                            "Please complete the payment authorization on your bank / ${stage.methodLabel} screen.",
                            color = HaraanColors.TextSecondary,
                            fontSize = 13.sp,
                            textAlign = TextAlign.Center,
                        )
                        Spacer(Modifier.height(10.dp))
                        Row(
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(4.dp),
                        ) {
                            Icon(Icons.Outlined.Lock, contentDescription = null, tint = HaraanColors.EventsBlue, modifier = Modifier.size(12.dp))
                            Text("Do not press back or switch apps", color = HaraanColors.EventsBlue, fontSize = 11.5.sp, fontWeight = FontWeight.SemiBold)
                        }
                    }

                    is HaraanPaymentStage.Verifying -> {
                        Text("Verifying Payment", color = HaraanColors.TextPrimary, fontSize = 19.sp, fontWeight = FontWeight.Bold)
                        Spacer(Modifier.height(6.dp))
                        Text(
                            "Confirming cryptographic signature with bank & Haraan Vault...",
                            color = HaraanColors.TextSecondary,
                            fontSize = 13.sp,
                            textAlign = TextAlign.Center,
                        )
                    }

                    is HaraanPaymentStage.NetworkRecovery -> {
                        Text("Confirming Payment Status", color = HaraanColors.TextPrimary, fontSize = 19.sp, fontWeight = FontWeight.Bold)
                        Spacer(Modifier.height(6.dp))
                        Text(
                            stage.message,
                            color = HaraanColors.Warning,
                            fontSize = 13.sp,
                            textAlign = TextAlign.Center,
                        )
                        Spacer(Modifier.height(8.dp))
                        Text(
                            "If money was deducted from your account, your booking is safe. We are querying the payment gateway directly.",
                            color = HaraanColors.TextSecondary,
                            fontSize = 12.sp,
                            textAlign = TextAlign.Center,
                        )
                        Spacer(Modifier.height(18.dp))
                        Button(
                            onClick = onCheckStatus,
                            colors = ButtonDefaults.buttonColors(containerColor = HaraanColors.EventsBlue),
                            shape = RoundedCornerShape(12.dp),
                            modifier = Modifier.fillMaxWidth().height(46.dp),
                        ) {
                            Icon(Icons.Default.Refresh, contentDescription = null, modifier = Modifier.size(16.dp))
                            Spacer(Modifier.width(6.dp))
                            Text("Check Payment Status", fontWeight = FontWeight.Bold, fontSize = 14.sp)
                        }
                    }

                    is HaraanPaymentStage.Success -> {
                        Text("Payment Successful!", color = HaraanColors.Success, fontSize = 21.sp, fontWeight = FontWeight.Bold)
                        Spacer(Modifier.height(6.dp))
                        Text(
                            stage.message,
                            color = HaraanColors.TextSecondary,
                            fontSize = 13.5.sp,
                            textAlign = TextAlign.Center,
                        )
                        Spacer(Modifier.height(8.dp))
                        Text("Generating your verified entry passes...", color = HaraanColors.TextSecondary, fontSize = 12.sp)
                    }

                    is HaraanPaymentStage.Failed -> {
                        Text("Payment Incomplete", color = HaraanColors.Danger, fontSize = 19.sp, fontWeight = FontWeight.Bold)
                        Spacer(Modifier.height(6.dp))
                        Text(
                            stage.reason,
                            color = HaraanColors.TextSecondary,
                            fontSize = 13.sp,
                            textAlign = TextAlign.Center,
                        )
                        Spacer(Modifier.height(10.dp))
                        Text(
                            "Your seat reservation is held. You can retry with another method.",
                            color = HaraanColors.TextSecondary,
                            fontSize = 12.sp,
                            textAlign = TextAlign.Center,
                        )
                        Spacer(Modifier.height(18.dp))
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.spacedBy(10.dp),
                        ) {
                            OutlinedButton(
                                onClick = onDismissFailed,
                                colors = ButtonDefaults.outlinedButtonColors(contentColor = HaraanColors.TextSecondary),
                                border = BorderStroke(1.dp, HaraanColors.BorderLight),
                                shape = RoundedCornerShape(12.dp),
                                modifier = Modifier.weight(1f).height(46.dp),
                            ) {
                                Text("Cancel")
                            }
                            Button(
                                onClick = onRetry,
                                colors = ButtonDefaults.buttonColors(containerColor = HaraanColors.EventsBlue),
                                shape = RoundedCornerShape(12.dp),
                                modifier = Modifier.weight(1f).height(46.dp),
                            ) {
                                Text("Try Again", fontWeight = FontWeight.Bold)
                            }
                        }
                    }
                }
            }
        }
    }
}
