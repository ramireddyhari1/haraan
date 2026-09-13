package com.haraan.partner.pricing.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.partner.pricing.model.HourSlotRate
import com.haraan.partner.pricing.model.WeeklyPricingMatrix

@Composable
fun WeeklyPricingGridScreen(
    matrix: WeeklyPricingMatrix,
    availableCourts: List<Pair<Long, String>>,
    selectedCourtId: Long?,
    onSelectCourt: (Long) -> Unit,
    onSelectSlot: (HourSlotRate, String) -> Unit,
    onNewRule: () -> Unit,
    modifier: Modifier = Modifier
) {
    val days = listOf(
        "monday" to "Monday",
        "tuesday" to "Tuesday",
        "wednesday" to "Wednesday",
        "thursday" to "Thursday",
        "friday" to "Friday",
        "saturday" to "Saturday",
        "sunday" to "Sunday"
    )
    var selectedDayKey by remember { mutableStateOf("friday") }

    Column(
        modifier = modifier
            .fillMaxSize()
            .padding(horizontal = 16.dp)
    ) {
        Spacer(Modifier.height(12.dp))

        // Court selector chips
        Text("SELECT COURT", fontSize = 10.sp, fontWeight = FontWeight.Bold, color = PricingColors.AuthMuted)
        Spacer(Modifier.height(4.dp))
        LazyRow(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            items(availableCourts) { (cId, cName) ->
                val isSelected = selectedCourtId == cId
                FilterChip(
                    selected = isSelected,
                    onClick = { onSelectCourt(cId) },
                    label = { Text(cName, fontSize = 12.sp, fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Normal) },
                    colors = FilterChipDefaults.filterChipColors(
                        selectedContainerColor = PricingColors.AuthAccent,
                        selectedLabelColor = Color.White
                    ),
                    shape = RoundedCornerShape(16.dp)
                )
            }
        }

        Spacer(Modifier.height(12.dp))

        // Day of Week Tabs
        LazyRow(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
            items(days) { (key, label) ->
                val isSelected = selectedDayKey == key
                Surface(
                    shape = RoundedCornerShape(10.dp),
                    color = if (isSelected) PricingColors.AuthInk else PricingColors.SurfaceWhite,
                    border = if (!isSelected) androidx.compose.foundation.BorderStroke(1.dp, PricingColors.Hairline) else null,
                    modifier = Modifier.clickable { selectedDayKey = key }
                ) {
                    Text(
                        text = label.take(3).uppercase(),
                        fontSize = 11.sp,
                        fontWeight = FontWeight.Bold,
                        color = if (isSelected) Color.White else PricingColors.AuthMuted,
                        modifier = Modifier.padding(horizontal = 12.dp, vertical = 6.dp)
                    )
                }
            }
        }

        Spacer(Modifier.height(12.dp))

        // Pricing Legend
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                LegendPill("Peak / Surge", PricingColors.PeakText, PricingColors.PeakBg)
                LegendPill("Discount", PricingColors.DiscountText, PricingColors.DiscountBg)
                LegendPill("Standard", PricingColors.StandardText, PricingColors.StandardBg)
            }

            TextButton(onClick = onNewRule, contentPadding = PaddingValues(0.dp)) {
                Icon(Icons.Default.Add, contentDescription = null, modifier = Modifier.size(16.dp))
                Spacer(Modifier.width(4.dp))
                Text("+ New Rule", fontSize = 12.sp, fontWeight = FontWeight.Bold)
            }
        }

        Spacer(Modifier.height(8.dp))

        // Hourly Slots List
        val currentDaySchedule = matrix.matrix[selectedDayKey]
        val slots = currentDaySchedule?.slots ?: emptyList()

        if (slots.isEmpty()) {
            Box(modifier = Modifier.fillMaxSize().weight(1f), contentAlignment = Alignment.Center) {
                Text("Loading price grid for $selectedDayKey...", color = PricingColors.AuthMuted, fontSize = 13.sp)
            }
        } else {
            LazyColumn(
                verticalArrangement = Arrangement.spacedBy(8.dp),
                modifier = Modifier.weight(1f),
                contentPadding = PaddingValues(bottom = 24.dp)
            ) {
                items(slots, key = { it.hour }) { slot ->
                    HourSlotRow(
                        slot = slot,
                        onClick = { onSelectSlot(slot, selectedDayKey) }
                    )
                }
            }
        }
    }
}

@Composable
private fun LegendPill(label: String, textColor: Color, bgColor: Color) {
    Surface(
        shape = RoundedCornerShape(6.dp),
        color = bgColor
    ) {
        Text(
            text = label,
            fontSize = 9.sp,
            fontWeight = FontWeight.SemiBold,
            color = textColor,
            modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp)
        )
    }
}

@Composable
private fun HourSlotRow(
    slot: HourSlotRate,
    onClick: () -> Unit
) {
    Card(
        shape = RoundedCornerShape(10.dp),
        colors = CardDefaults.cardColors(containerColor = PricingColors.SurfaceWhite),
        elevation = CardDefaults.cardElevation(defaultElevation = 1.dp),
        modifier = Modifier
            .fillMaxWidth()
            .clickable { onClick() }
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 14.dp, vertical = 10.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Box(
                    modifier = Modifier
                        .size(36.dp)
                        .clip(RoundedCornerShape(8.dp))
                        .background(PricingColors.AuthPageBg),
                    contentAlignment = Alignment.Center
                ) {
                    Text(
                        text = "${slot.hour}:00",
                        fontSize = 11.sp,
                        fontWeight = FontWeight.Bold,
                        color = PricingColors.AuthInk
                    )
                }
                Spacer(Modifier.width(12.dp))
                Column {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Text(
                            text = "${slot.timeLabel} - ${String.format("%02d:00", slot.hour + 1)}",
                            fontSize = 13.sp,
                            fontWeight = FontWeight.Bold,
                            color = PricingColors.AuthInk
                        )
                        Spacer(Modifier.width(6.dp))
                        if (slot.ruleName != null) {
                            Surface(
                                shape = RoundedCornerShape(4.dp),
                                color = PricingColors.AuthAccent.copy(alpha = 0.1f)
                            ) {
                                Text(
                                    text = slot.ruleName,
                                    fontSize = 9.sp,
                                    fontWeight = FontWeight.SemiBold,
                                    color = PricingColors.AuthAccent,
                                    modifier = Modifier.padding(horizontal = 4.dp, vertical = 1.dp)
                                )
                            }
                        }
                    }
                    Text(
                        text = if (slot.rate != slot.baseRate) "Base: ₹${slot.baseRate}" else "Standard Rack Rate",
                        fontSize = 10.sp,
                        color = PricingColors.AuthMuted
                    )
                }
            }

            Surface(
                shape = RoundedCornerShape(8.dp),
                color = when (slot.tag) {
                    "peak" -> PricingColors.PeakBg
                    "discount" -> PricingColors.DiscountBg
                    else -> PricingColors.StandardBg
                },
                border = androidx.compose.foundation.BorderStroke(
                    1.dp,
                    when (slot.tag) {
                        "peak" -> PricingColors.PeakBorder
                        "discount" -> PricingColors.DiscountBorder
                        else -> PricingColors.StandardBorder
                    }
                )
            ) {
                Text(
                    text = "₹${slot.rate}",
                    fontSize = 13.sp,
                    fontWeight = FontWeight.Black,
                    color = when (slot.tag) {
                        "peak" -> PricingColors.PeakText
                        "discount" -> PricingColors.DiscountText
                        else -> PricingColors.StandardText
                    },
                    modifier = Modifier.padding(horizontal = 10.dp, vertical = 4.dp)
                )
            }
        }
    }
}
