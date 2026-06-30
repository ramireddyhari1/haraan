package com.example.thanna.ui.main.eventdetail

import android.widget.Toast
import androidx.compose.animation.*
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.tween
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.hapticfeedback.HapticFeedbackType
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalHapticFeedback
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.example.thanna.ui.animations.pressScale
import com.example.thanna.ui.theme.HaraanColors
import com.example.thanna.ui.theme.HaraanRadius
import com.example.thanna.ui.theme.HaraanSpacing
import com.example.thanna.ui.theme.HaraanTypography
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.interaction.collectIsPressedAsState

@Composable
fun EventStickyBookingBar(
    price: String,
    barRise: Float,
    modifier: Modifier = Modifier
) {
    val context = LocalContext.current
    val haptics = LocalHapticFeedback.current
    var quantity by remember { mutableStateOf(1) }
    val basePrice = remember(price) {
        price.filter { it.isDigit() }.toDoubleOrNull() ?: 2999.0
    }
    val totalPrice = (basePrice * quantity).toInt()

    // Individual digit scale-pop — Apple Wallet style
    val priceScale = remember { Animatable(1f) }
    LaunchedEffect(totalPrice) {
        priceScale.snapTo(1.06f)
        priceScale.animateTo(1f, tween(200))
    }

    // CTA button interaction
    val ctaInteraction = remember { MutableInteractionSource() }
    val ctaPressed by ctaInteraction.collectIsPressedAsState()

    Surface(
        color = HaraanColors.Surface,
        border = BorderStroke(1.dp, HaraanColors.BorderLight),
        shadowElevation = 8.dp,
        modifier = modifier
            .fillMaxWidth()
            .graphicsLayer { translationY = barRise * 200.dp.toPx() }
            .navigationBarsPadding()
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(HaraanSpacing.Medium),
            verticalArrangement = Arrangement.spacedBy(HaraanSpacing.Compact)
        ) {
            // Row: Price + Quantity stepper
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                // Price — dominant
                Column {
                    Row(verticalAlignment = Alignment.Bottom) {
                        Text(
                            text = "₹",
                            style = HaraanTypography.TitleLarge.copy(
                                fontSize = 18.sp,
                                color = HaraanColors.TextPrimary
                            )
                        )
                        // Animated digits with vertical slide per digit
                        AnimatedPriceDigits(
                            price = totalPrice,
                            modifier = Modifier.graphicsLayer {
                                scaleX = priceScale.value
                                scaleY = priceScale.value
                            }
                        )
                    }
                    Text(
                        text = "per ticket",
                        style = HaraanTypography.BodyMedium.copy(
                            color = HaraanColors.TextMuted,
                            fontSize = 12.sp
                        )
                    )
                }

                // Quantity stepper
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    modifier = Modifier
                        .background(
                            HaraanColors.Background,
                            RoundedCornerShape(HaraanRadius.Small)
                        )
                        .border(
                            1.dp,
                            HaraanColors.BorderLight,
                            RoundedCornerShape(HaraanRadius.Small)
                        )
                        .padding(horizontal = 4.dp, vertical = 4.dp)
                ) {
                    Text(
                        text = "–",
                        style = HaraanTypography.TitleMedium.copy(
                            fontSize = 18.sp,
                            color = if (quantity > 1) HaraanColors.TextPrimary else HaraanColors.TextMuted
                        ),
                        modifier = Modifier
                            .clickable(enabled = quantity > 1) {
                                quantity--
                                haptics.performHapticFeedback(HapticFeedbackType.TextHandleMove)
                            }
                            .padding(horizontal = 12.dp, vertical = 4.dp)
                    )
                    Text(
                        text = quantity.toString(),
                        style = HaraanTypography.TitleMedium.copy(
                            color = HaraanColors.TextPrimary,
                            fontWeight = FontWeight.Bold
                        ),
                        textAlign = TextAlign.Center,
                        modifier = Modifier.widthIn(min = 32.dp)
                    )
                    Text(
                        text = "+",
                        style = HaraanTypography.TitleMedium.copy(
                            fontSize = 18.sp,
                            color = HaraanColors.TextPrimary
                        ),
                        modifier = Modifier
                            .clickable {
                                quantity++
                                haptics.performHapticFeedback(HapticFeedbackType.TextHandleMove)
                            }
                            .padding(horizontal = 12.dp, vertical = 4.dp)
                    )
                }
            }

            // Full-width CTA — "Continue →"
            Surface(
                onClick = {
                    haptics.performHapticFeedback(HapticFeedbackType.LongPress)
                    Toast.makeText(
                        context,
                        "Booking ₹$totalPrice • $quantity tickets",
                        Toast.LENGTH_LONG
                    ).show()
                },
                interactionSource = ctaInteraction,
                shape = RoundedCornerShape(HaraanRadius.Medium),
                color = Color.Transparent,
                modifier = Modifier
                    .fillMaxWidth()
                    .height(52.dp)
                    .pressScale(ctaInteraction)
                    .shadow(
                        elevation = if (ctaPressed) 2.dp else 0.dp,
                        shape = RoundedCornerShape(HaraanRadius.Medium),
                        clip = false
                    )
            ) {
                Box(
                    modifier = Modifier
                        .fillMaxSize()
                        .background(
                            // Events lane commits in brand blue, not GameHub green.
                            brush = Brush.horizontalGradient(
                                colors = listOf(
                                    HaraanColors.EventsBlue,
                                    HaraanColors.EventsBlue.copy(alpha = 0.88f)
                                )
                            ),
                            shape = RoundedCornerShape(HaraanRadius.Medium)
                        ),
                    contentAlignment = Alignment.Center
                ) {
                    Text(
                        text = "Continue →",
                        style = HaraanTypography.TitleMedium.copy(
                            color = Color.White,
                            fontSize = 16.sp,
                            fontWeight = FontWeight.Bold
                        )
                    )
                }
            }
        }
    }
}

/**
 * Apple Wallet-style animated digits — each digit slides vertically when it changes.
 */
@Composable
private fun AnimatedPriceDigits(
    price: Int,
    modifier: Modifier = Modifier
) {
    val digits = price.toString()

    Row(modifier = modifier) {
        digits.forEach { digit ->
            AnimatedContent(
                targetState = digit,
                transitionSpec = {
                    (slideInVertically { -it } + fadeIn(tween(300))) togetherWith
                            (slideOutVertically { it } + fadeOut(tween(300)))
                },
                label = "digit"
            ) { targetDigit ->
                Text(
                    text = targetDigit.toString(),
                    style = HaraanTypography.TitleLarge.copy(
                        fontSize = 22.sp,
                        color = HaraanColors.TextPrimary,
                        fontWeight = FontWeight.ExtraBold
                    )
                )
            }
        }
    }
}
