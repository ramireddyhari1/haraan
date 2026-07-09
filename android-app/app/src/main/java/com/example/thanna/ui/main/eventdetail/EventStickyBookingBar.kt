package com.example.thanna.ui.main.eventdetail

import android.widget.Toast
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
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
import com.example.thanna.OrderLine
import com.example.thanna.data.EventTicketType
import com.example.thanna.ui.theme.HaraanColors
import com.example.thanna.ui.theme.HaraanRadius
import com.example.thanna.ui.theme.HaraanSpacing
import com.example.thanna.ui.theme.HaraanTypography

/**
 * Sticky bottom booking bar — a floating pill showing the "from" price and a
 * "Book tickets" button. Tapping it opens a cart sheet where the buyer can add
 * quantities across ticket tiers (Kid / Adult / Couple / Group) and see dynamic
 * price phases. "Continue" hands the cart to the Order Summary page.
 */
@Composable
fun EventStickyBookingBar(
    price: String,
    barRise: Float,
    modifier: Modifier = Modifier,
    eventId: Int? = null,
    ticketTypes: List<EventTicketType> = emptyList(),
    // Handoff to the Order Summary page: the chosen cart of order lines.
    onCheckout: (List<OrderLine>) -> Unit = {},
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
            onDismiss = { showSheet = false },
            onCheckout = onCheckout,
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
 * Ticket cart sheet — a per-tier quantity picker with dynamic-price schedules.
 * "Continue" hands the cart to the Order Summary page (the real /api/bookings
 * call happens there). Opened from the "Book tickets" pill.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun BookingSheet(
    eventId: Int?,
    ticketTypes: List<EventTicketType>,
    flatPrice: Double,
    onDismiss: () -> Unit,
    onCheckout: (List<OrderLine>) -> Unit,
) {
    val context = LocalContext.current
    val haptics = LocalHapticFeedback.current
    val sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true)

    // Quantity per tier id; flat-price events use the sentinel key -1.
    val FLAT_ID = -1
    val quantities = remember(ticketTypes) { mutableStateMapOf<Int, Int>() }

    val totalQty = quantities.values.sum()
    val totalPrice = remember(quantities.toMap(), ticketTypes, flatPrice) {
        if (ticketTypes.isEmpty()) {
            (quantities[FLAT_ID] ?: 0) * flatPrice
        } else {
            ticketTypes.sumOf { t -> (quantities[t.id] ?: 0) * t.price }
        }
    }.toInt()

    ModalBottomSheet(
        onDismissRequest = onDismiss,
        sheetState = sheetState,
        containerColor = HaraanColors.Surface,
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = HaraanSpacing.Large)
                .padding(bottom = 20.dp),
            verticalArrangement = Arrangement.spacedBy(HaraanSpacing.Compact)
        ) {
            Text(
                text = "Select tickets",
                style = HaraanTypography.TitleLarge.copy(
                    fontSize = 20.sp,
                    color = HaraanColors.TextPrimary,
                    fontWeight = FontWeight.Bold
                )
            )

            // Tier rows (or a single flat-price row) — scrolls when the list is long.
            Column(
                modifier = Modifier
                    .heightIn(max = 420.dp)
                    .verticalScroll(rememberScrollState()),
                verticalArrangement = Arrangement.spacedBy(HaraanSpacing.Small)
            ) {
                if (ticketTypes.isEmpty()) {
                    TicketRow(
                        name = "General admission",
                        priceLabel = if (flatPrice <= 0.0) "Free" else "₹${flatPrice.toInt()}",
                        admits = 1,
                        remaining = null,
                        phases = emptyList(),
                        quantity = quantities[FLAT_ID] ?: 0,
                        onQuantityChange = { quantities[FLAT_ID] = it },
                        haptics = haptics,
                    )
                } else {
                    ticketTypes.forEach { tier ->
                        TicketRow(
                            name = tier.name,
                            priceLabel = when {
                                tier.kind == "donation" -> "Your choice"
                                tier.price <= 0.0 -> "Free"
                                else -> "₹${tier.price.toInt()}"
                            },
                            admits = tier.admits,
                            remaining = tier.remaining,
                            phases = tier.phases,
                            quantity = quantities[tier.id] ?: 0,
                            onQuantityChange = { quantities[tier.id] = it },
                            haptics = haptics,
                        )
                    }
                }
            }

            Box(Modifier.fillMaxWidth().height(1.dp).background(HaraanColors.BorderLight))

            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Column {
                    Text(
                        text = if (totalPrice <= 0 && totalQty == 0) "—" else "₹$totalPrice",
                        style = HaraanTypography.TitleLarge.copy(
                            fontSize = 24.sp,
                            color = HaraanColors.TextPrimary,
                            fontWeight = FontWeight.ExtraBold
                        )
                    )
                    Text(
                        text = if (totalQty == 0) "No tickets selected"
                        else "$totalQty ticket${if (totalQty == 1) "" else "s"}",
                        style = HaraanTypography.BodyMedium.copy(color = HaraanColors.TextMuted, fontSize = 12.sp)
                    )
                }

                val ctaEnabled = totalQty > 0
                Surface(
                    onClick = {
                        if (!ctaEnabled) return@Surface
                        haptics.performHapticFeedback(HapticFeedbackType.LongPress)
                        if (eventId == null) {
                            Toast.makeText(context, "This event isn't bookable yet.", Toast.LENGTH_LONG).show()
                            return@Surface
                        }

                        val lines = if (ticketTypes.isEmpty()) {
                            val q = quantities[FLAT_ID] ?: 0
                            if (q > 0) listOf(
                                OrderLine(
                                    ticketTypeId = -1,
                                    name = "General admission",
                                    unitPrice = flatPrice,
                                    quantity = q,
                                    admits = 1,
                                )
                            ) else emptyList()
                        } else {
                            ticketTypes.mapNotNull { t ->
                                val q = quantities[t.id] ?: 0
                                if (q <= 0) null else OrderLine(
                                    ticketTypeId = t.id,
                                    name = t.name,
                                    unitPrice = t.price,
                                    quantity = q,
                                    admits = t.admits,
                                    phaseLabel = t.phases.firstOrNull { it.current }?.label ?: "",
                                )
                            }
                        }
                        if (lines.isEmpty()) return@Surface

                        onCheckout(lines)
                        onDismiss()
                    },
                    shape = RoundedCornerShape(24.dp),
                    color = if (ctaEnabled) HaraanColors.EventsBlue else HaraanColors.TextMuted,
                    modifier = Modifier.height(52.dp)
                ) {
                    Box(
                        modifier = Modifier.padding(horizontal = 28.dp),
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
}

/**
 * One ticket tier in the cart: identity + price on the left, a quantity stepper
 * (or "Add") on the right, and — for dynamic tiers — an inline price schedule.
 */
@Composable
private fun TicketRow(
    name: String,
    priceLabel: String,
    admits: Int,
    remaining: Int?,
    phases: List<com.example.thanna.data.PricingPhase>,
    quantity: Int,
    onQuantityChange: (Int) -> Unit,
    haptics: androidx.compose.ui.hapticfeedback.HapticFeedback,
) {
    val soldOut = remaining == 0
    val maxQty = remaining?.coerceAtLeast(0) ?: 20

    Column(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(HaraanRadius.Medium))
            .border(1.dp, HaraanColors.BorderLight, RoundedCornerShape(HaraanRadius.Medium))
            .padding(HaraanSpacing.Compact),
        verticalArrangement = Arrangement.spacedBy(HaraanSpacing.Small)
    ) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.SpaceBetween
        ) {
            Column(Modifier.weight(1f)) {
                Text(
                    text = name.uppercase(),
                    style = HaraanTypography.TitleMedium.copy(
                        color = HaraanColors.TextPrimary,
                        fontWeight = FontWeight.Bold,
                        fontSize = 15.sp
                    )
                )
                Text(
                    text = priceLabel,
                    style = HaraanTypography.TitleMedium.copy(
                        color = HaraanColors.TextPrimary,
                        fontWeight = FontWeight.ExtraBold,
                        fontSize = 18.sp
                    ),
                    modifier = Modifier.padding(top = 1.dp)
                )
                Row(
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                    modifier = Modifier.padding(top = 2.dp)
                ) {
                    if (admits > 1) {
                        Text(
                            "Admits $admits",
                            style = HaraanTypography.LabelSmall.copy(fontSize = 10.sp, color = HaraanColors.EventsBlue)
                        )
                    }
                    when {
                        soldOut -> Text(
                            "Sold out",
                            style = HaraanTypography.LabelSmall.copy(fontSize = 10.sp, color = HaraanColors.LiveRed)
                        )
                        remaining != null && remaining <= 10 -> Text(
                            "$remaining left",
                            style = HaraanTypography.LabelSmall.copy(fontSize = 10.sp, color = HaraanColors.LiveRed)
                        )
                    }
                }
            }

            if (soldOut) {
                Text(
                    "Sold out",
                    style = HaraanTypography.LabelSmall.copy(color = HaraanColors.TextMuted, fontSize = 11.sp)
                )
            } else if (quantity <= 0) {
                // "+ Add" pill (screenshot 2) — first tap adds one.
                Surface(
                    onClick = {
                        onQuantityChange(1)
                        haptics.performHapticFeedback(HapticFeedbackType.TextHandleMove)
                    },
                    shape = RoundedCornerShape(HaraanRadius.Small),
                    color = HaraanColors.EventsBlue,
                    modifier = Modifier.height(40.dp)
                ) {
                    Box(Modifier.padding(horizontal = 22.dp), contentAlignment = Alignment.Center) {
                        Text(
                            "+  Add",
                            style = HaraanTypography.TitleMedium.copy(
                                color = Color.White,
                                fontWeight = FontWeight.Bold,
                                fontSize = 15.sp
                            )
                        )
                    }
                }
            } else {
                Stepper(
                    quantity = quantity,
                    canIncrement = quantity < maxQty,
                    onDecrement = {
                        onQuantityChange(quantity - 1)
                        haptics.performHapticFeedback(HapticFeedbackType.TextHandleMove)
                    },
                    onIncrement = {
                        onQuantityChange(quantity + 1)
                        haptics.performHapticFeedback(HapticFeedbackType.TextHandleMove)
                    },
                )
            }
        }

        if (phases.isNotEmpty()) {
            PricingScheduleCard(phases)
        }
    }
}

/** The – n + quantity control. */
@Composable
private fun Stepper(
    quantity: Int,
    canIncrement: Boolean,
    onDecrement: () -> Unit,
    onIncrement: () -> Unit,
) {
    Row(
        verticalAlignment = Alignment.CenterVertically,
        modifier = Modifier
            .background(HaraanColors.Background, RoundedCornerShape(HaraanRadius.Small))
            .border(1.dp, HaraanColors.BorderLight, RoundedCornerShape(HaraanRadius.Small))
            .padding(4.dp)
    ) {
        Text(
            text = "–",
            style = HaraanTypography.TitleMedium.copy(fontSize = 18.sp, color = HaraanColors.TextPrimary),
            modifier = Modifier
                .clickable(onClick = onDecrement)
                .padding(horizontal = 12.dp, vertical = 4.dp)
        )
        Text(
            text = quantity.toString(),
            style = HaraanTypography.TitleMedium.copy(color = HaraanColors.TextPrimary, fontWeight = FontWeight.Bold),
            textAlign = TextAlign.Center,
            modifier = Modifier.widthIn(min = 28.dp)
        )
        Text(
            text = "+",
            style = HaraanTypography.TitleMedium.copy(
                fontSize = 18.sp,
                color = if (canIncrement) HaraanColors.TextPrimary else HaraanColors.TextMuted
            ),
            modifier = Modifier
                .clickable(enabled = canIncrement, onClick = onIncrement)
                .padding(horizontal = 12.dp, vertical = 4.dp)
        )
    }
}

/** Dynamic price schedule (screenshot 1): each phase, its spot range, and the live one. */
@Composable
private fun PricingScheduleCard(phases: List<com.example.thanna.data.PricingPhase>) {
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(HaraanRadius.Small))
            .background(HaraanColors.Background)
            .padding(HaraanSpacing.Compact),
        verticalArrangement = Arrangement.spacedBy(6.dp)
    ) {
        Text(
            "Pricing schedule",
            style = HaraanTypography.LabelSmall.copy(color = HaraanColors.TextSecondary, fontSize = 10.sp)
        )
        phases.forEach { p ->
            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                    Text(
                        p.label.ifBlank { "Phase" },
                        style = HaraanTypography.BodyMedium.copy(
                            color = if (p.soldOut) HaraanColors.TextMuted else HaraanColors.TextPrimary,
                            fontSize = 12.sp,
                            fontWeight = if (p.current) FontWeight.Bold else FontWeight.Normal
                        )
                    )
                    Text(
                        "(${p.from}–${p.to})",
                        style = HaraanTypography.BodyMedium.copy(color = HaraanColors.TextMuted, fontSize = 11.sp)
                    )
                    if (p.current) {
                        Box(
                            modifier = Modifier
                                .clip(RoundedCornerShape(6.dp))
                                .background(HaraanColors.GameHubGreen.copy(alpha = 0.14f))
                                .padding(horizontal = 6.dp, vertical = 1.dp)
                        ) {
                            Text(
                                "Current",
                                style = HaraanTypography.LabelSmall.copy(color = HaraanColors.GameHubGreen, fontSize = 9.sp)
                            )
                        }
                    }
                }
                Text(
                    "₹${p.price.toInt()}",
                    style = HaraanTypography.BodyMedium.copy(
                        color = if (p.soldOut) HaraanColors.TextMuted else HaraanColors.TextPrimary,
                        fontSize = 12.sp,
                        fontWeight = if (p.current) FontWeight.Bold else FontWeight.Medium
                    )
                )
            }
        }
    }
}
