package com.example.thanna.ui.main.eventdetail

import android.widget.Toast
import androidx.compose.animation.*
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.hapticfeedback.HapticFeedbackType
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalHapticFeedback
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.example.thanna.data.BookingRepository
import com.example.thanna.data.BookingResult
import com.example.thanna.data.EventTicketType
import com.example.thanna.data.TokenStore
import com.example.thanna.ui.theme.HaraanColors
import com.example.thanna.ui.theme.HaraanRadius
import com.example.thanna.ui.theme.HaraanSpacing
import com.example.thanna.ui.theme.HaraanTypography
import kotlinx.coroutines.launch

/**
 * Sticky bottom booking bar — a dark floating pill showing the "from" price and
 * a white "Book tickets" button. Tier selection + quantity + the real booking
 * call live in a bottom sheet opened by that button.
 */
@Composable
fun EventStickyBookingBar(
    price: String,
    barRise: Float,
    modifier: Modifier = Modifier,
    eventId: Int? = null,
    ticketTypes: List<EventTicketType> = emptyList(),
) {
    val flatPrice = remember(price) { price.filter { it.isDigit() }.toDoubleOrNull() ?: 0.0 }
    val minPrice = remember(ticketTypes, flatPrice) {
        (ticketTypes.mapNotNull { it.price.takeIf { p -> p > 0.0 } }.minOrNull()) ?: flatPrice
    }
    val priceLabel = if (minPrice <= 0.0) "Free" else "₹${minPrice.toInt()}"
    val showOnwards = ticketTypes.size > 1 || (ticketTypes.isEmpty() && minPrice > 0.0)

    var showSheet by remember { mutableStateOf(false) }
    val haptics = LocalHapticFeedback.current

    if (showSheet) {
        BookingSheet(
            eventId = eventId,
            ticketTypes = ticketTypes,
            flatPrice = flatPrice,
            onDismiss = { showSheet = false }
        )
    }

    Box(
        modifier = modifier
            .fillMaxWidth()
            .graphicsLayer { translationY = barRise * 200.dp.toPx() }
            .navigationBarsPadding()
            .padding(horizontal = HaraanSpacing.Medium, vertical = HaraanSpacing.Compact)
    ) {
        Surface(
            color = HaraanColors.EventsBlue,
            shape = RoundedCornerShape(28.dp),
            shadowElevation = 12.dp,
            modifier = Modifier.fillMaxWidth()
        ) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(start = 22.dp, end = 6.dp, top = 6.dp, bottom = 6.dp),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                Row(verticalAlignment = Alignment.Bottom) {
                    Text(
                        text = priceLabel,
                        style = HaraanTypography.TitleLarge.copy(
                            fontSize = 22.sp,
                            color = Color.White,
                            fontWeight = FontWeight.ExtraBold
                        )
                    )
                    if (showOnwards) {
                        Text(
                            text = " onwards",
                            style = HaraanTypography.BodyMedium.copy(
                                fontSize = 13.sp,
                                color = Color.White.copy(alpha = 0.7f)
                            ),
                            modifier = Modifier.padding(bottom = 3.dp)
                        )
                    }
                }

                Surface(
                    onClick = {
                        haptics.performHapticFeedback(HapticFeedbackType.LongPress)
                        showSheet = true
                    },
                    shape = RoundedCornerShape(22.dp),
                    color = Color.White,
                    modifier = Modifier.height(44.dp)
                ) {
                    Box(
                        modifier = Modifier.padding(horizontal = 28.dp),
                        contentAlignment = Alignment.Center
                    ) {
                        Text(
                            text = "Book tickets",
                            style = HaraanTypography.TitleMedium.copy(
                                color = HaraanColors.EventsBlue,
                                fontSize = 16.sp,
                                fontWeight = FontWeight.Bold
                            )
                        )
                    }
                }
            }
        }
    }
}

/**
 * Ticket selection sheet — tier picker, quantity stepper and the real
 * /api/bookings CTA. Opened from the "Book tickets" pill.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun BookingSheet(
    eventId: Int?,
    ticketTypes: List<EventTicketType>,
    flatPrice: Double,
    onDismiss: () -> Unit,
) {
    val context = LocalContext.current
    val haptics = LocalHapticFeedback.current
    val scope = rememberCoroutineScope()
    val sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true)

    var quantity by remember { mutableStateOf(1) }
    var selectedTierId by remember(ticketTypes) { mutableStateOf(ticketTypes.firstOrNull()?.id) }
    var booking by remember { mutableStateOf(false) }
    var booked by remember { mutableStateOf(false) }

    val selectedTier = ticketTypes.firstOrNull { it.id == selectedTierId }
    val unitPrice = selectedTier?.price ?: flatPrice
    val totalPrice = (unitPrice * quantity).toInt()

    val maxQty = selectedTier?.remaining?.coerceAtLeast(0) ?: 10
    LaunchedEffect(maxQty) { if (quantity > maxQty && maxQty > 0) quantity = maxQty }
    val soldOut = selectedTier?.remaining == 0

    ModalBottomSheet(
        onDismissRequest = onDismiss,
        sheetState = sheetState,
        containerColor = HaraanColors.Surface,
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = HaraanSpacing.Large)
                .padding(bottom = 28.dp),
            verticalArrangement = Arrangement.spacedBy(HaraanSpacing.Medium)
        ) {
            Text(
                text = "Select tickets",
                style = HaraanTypography.TitleLarge.copy(
                    fontSize = 20.sp,
                    color = HaraanColors.TextPrimary,
                    fontWeight = FontWeight.Bold
                )
            )

            if (ticketTypes.isNotEmpty()) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .horizontalScroll(rememberScrollState()),
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    ticketTypes.forEach { tier ->
                        TierChip(
                            tier = tier,
                            selected = tier.id == selectedTierId,
                            onClick = {
                                selectedTierId = tier.id
                                quantity = 1
                                haptics.performHapticFeedback(HapticFeedbackType.TextHandleMove)
                            }
                        )
                    }
                }
            }

            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Column {
                    Text(
                        text = "₹$totalPrice",
                        style = HaraanTypography.TitleLarge.copy(
                            fontSize = 24.sp,
                            color = HaraanColors.TextPrimary,
                            fontWeight = FontWeight.ExtraBold
                        )
                    )
                    Text(
                        text = selectedTier?.name?.let { "$it · per ticket" } ?: "per ticket",
                        style = HaraanTypography.BodyMedium.copy(
                            color = HaraanColors.TextMuted,
                            fontSize = 12.sp
                        )
                    )
                }

                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    modifier = Modifier
                        .background(HaraanColors.Background, RoundedCornerShape(HaraanRadius.Small))
                        .border(1.dp, HaraanColors.BorderLight, RoundedCornerShape(HaraanRadius.Small))
                        .padding(4.dp)
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
                    val canIncrement = quantity < maxQty
                    Text(
                        text = "+",
                        style = HaraanTypography.TitleMedium.copy(
                            fontSize = 18.sp,
                            color = if (canIncrement) HaraanColors.TextPrimary else HaraanColors.TextMuted
                        ),
                        modifier = Modifier
                            .clickable(enabled = canIncrement) {
                                quantity++
                                haptics.performHapticFeedback(HapticFeedbackType.TextHandleMove)
                            }
                            .padding(horizontal = 12.dp, vertical = 4.dp)
                    )
                }
            }

            val ctaEnabled = !booking && !booked && !soldOut
            val ctaLabel = when {
                booked -> "Booked ✓"
                soldOut -> "Sold out"
                else -> "Continue →"
            }
            Surface(
                onClick = {
                    if (!ctaEnabled) return@Surface
                    haptics.performHapticFeedback(HapticFeedbackType.LongPress)

                    val token = TokenStore.getToken(context)
                    if (token.isNullOrBlank()) {
                        Toast.makeText(context, "Please sign in to book tickets.", Toast.LENGTH_LONG).show()
                        return@Surface
                    }
                    if (eventId == null) {
                        Toast.makeText(context, "This event isn't bookable yet.", Toast.LENGTH_LONG).show()
                        return@Surface
                    }

                    booking = true
                    scope.launch {
                        val result = BookingRepository().createBooking(
                            token = token,
                            eventId = eventId,
                            quantity = quantity,
                            ticketTypeId = selectedTier?.id,
                        )
                        booking = false
                        when (result) {
                            is BookingResult.Success -> {
                                booked = true
                                Toast.makeText(
                                    context,
                                    "${result.message} · ${result.quantity} ticket(s)",
                                    Toast.LENGTH_LONG
                                ).show()
                                onDismiss()
                            }
                            is BookingResult.Error ->
                                Toast.makeText(context, result.message, Toast.LENGTH_LONG).show()
                        }
                    }
                },
                shape = RoundedCornerShape(HaraanRadius.Medium),
                color = if (ctaEnabled) HaraanColors.EventsBlue else HaraanColors.TextMuted,
                modifier = Modifier
                    .fillMaxWidth()
                    .height(54.dp)
            ) {
                Box(contentAlignment = Alignment.Center) {
                    if (booking) {
                        CircularProgressIndicator(
                            color = Color.White,
                            strokeWidth = 2.dp,
                            modifier = Modifier.size(22.dp)
                        )
                    } else {
                        Text(
                            text = ctaLabel,
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
}

/** A single selectable ticket tier: name, price, and a low-stock cue. */
@Composable
private fun TierChip(
    tier: EventTicketType,
    selected: Boolean,
    onClick: () -> Unit,
) {
    val border = if (selected) HaraanColors.EventsBlue else HaraanColors.BorderLight
    val bg = if (selected) HaraanColors.EventsBlue.copy(alpha = 0.08f) else HaraanColors.Surface
    val soldOut = tier.remaining == 0

    Column(
        modifier = Modifier
            .clip(RoundedCornerShape(HaraanRadius.Small))
            .background(bg)
            .border(
                if (selected) 1.5.dp else 1.dp,
                border,
                RoundedCornerShape(HaraanRadius.Small)
            )
            .clickable(enabled = !soldOut, onClick = onClick)
            .padding(horizontal = 12.dp, vertical = 8.dp),
        verticalArrangement = Arrangement.spacedBy(2.dp)
    ) {
        Text(
            text = tier.name,
            style = HaraanTypography.LabelSmall.copy(
                color = if (selected) HaraanColors.EventsBlue else HaraanColors.TextPrimary,
                fontWeight = FontWeight.Bold
            )
        )
        Text(
            text = when {
                tier.kind == "donation" -> "Your choice"
                tier.price <= 0.0 -> "Free"
                else -> "₹${tier.price.toInt()}"
            },
            style = HaraanTypography.BodyMedium.copy(
                fontSize = 12.sp,
                color = HaraanColors.TextSecondary
            )
        )
        if (tier.admits > 1) {
            Text(
                text = "Admits ${tier.admits}",
                style = HaraanTypography.LabelSmall.copy(fontSize = 10.sp, color = HaraanColors.EventsBlue)
            )
        }
        if (soldOut) {
            Text(
                text = "Sold out",
                style = HaraanTypography.LabelSmall.copy(fontSize = 10.sp, color = HaraanColors.LiveRed)
            )
        } else if (tier.remaining != null && tier.remaining <= 10) {
            Text(
                text = "${tier.remaining} left",
                style = HaraanTypography.LabelSmall.copy(fontSize = 10.sp, color = HaraanColors.LiveRed)
            )
        }
    }
}
