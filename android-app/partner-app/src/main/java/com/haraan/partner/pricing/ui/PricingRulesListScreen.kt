package com.haraan.partner.pricing.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
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
import com.haraan.partner.pricing.model.PricingRuleItem

@Composable
fun PricingRulesListScreen(
    rules: List<PricingRuleItem>,
    onToggleRule: (Long) -> Unit,
    onDeleteRule: (Long) -> Unit,
    onNewRule: () -> Unit,
    modifier: Modifier = Modifier
) {
    var searchQuery by remember { mutableStateOf("") }

    val filteredRules = remember(rules, searchQuery) {
        if (searchQuery.isBlank()) rules
        else rules.filter { it.name.contains(searchQuery, ignoreCase = true) || it.courtName.contains(searchQuery, ignoreCase = true) }
    }

    Column(
        modifier = modifier
            .fillMaxSize()
            .padding(16.dp)
    ) {
        // Search & Add Bar
        Row(
            modifier = Modifier.fillMaxWidth(),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            OutlinedTextField(
                value = searchQuery,
                onValueChange = { searchQuery = it },
                placeholder = { Text("Search pricing rules...", fontSize = 13.sp) },
                leadingIcon = { Icon(Icons.Default.Search, contentDescription = null, tint = PricingColors.AuthMuted) },
                shape = RoundedCornerShape(12.dp),
                singleLine = true,
                modifier = Modifier.weight(1f)
            )

            Button(
                onClick = onNewRule,
                shape = RoundedCornerShape(12.dp),
                colors = ButtonDefaults.buttonColors(containerColor = PricingColors.AuthAccent),
                contentPadding = PaddingValues(horizontal = 12.dp)
            ) {
                Icon(Icons.Default.Add, contentDescription = null, modifier = Modifier.size(16.dp))
                Spacer(Modifier.width(4.dp))
                Text("New", fontWeight = FontWeight.Bold)
            }
        }

        Spacer(Modifier.height(14.dp))

        if (filteredRules.isEmpty()) {
            Box(
                modifier = Modifier.fillMaxSize().weight(1f),
                contentAlignment = Alignment.Center
            ) {
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Icon(Icons.Default.Tune, contentDescription = null, tint = PricingColors.AuthMuted.copy(alpha = 0.5f), modifier = Modifier.size(48.dp))
                    Spacer(Modifier.height(10.dp))
                    Text("No pricing rules configured", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = PricingColors.AuthInk)
                    Text("Create dynamic rules for weekend rush or off-peak discounts", fontSize = 12.sp, color = PricingColors.AuthMuted)
                    Spacer(Modifier.height(14.dp))
                    Button(onClick = onNewRule) { Text("Create First Rule") }
                }
            }
        } else {
            LazyColumn(
                verticalArrangement = Arrangement.spacedBy(10.dp),
                modifier = Modifier.weight(1f),
                contentPadding = PaddingValues(bottom = 24.dp)
            ) {
                items(filteredRules, key = { it.id }) { rule ->
                    PricingRuleCard(
                        rule = rule,
                        onToggle = { onToggleRule(rule.id) },
                        onDelete = { onDeleteRule(rule.id) }
                    )
                }
            }
        }
    }
}

@Composable
private fun PricingRuleCard(
    rule: PricingRuleItem,
    onToggle: () -> Unit,
    onDelete: () -> Unit
) {
    Card(
        shape = RoundedCornerShape(14.dp),
        colors = CardDefaults.cardColors(containerColor = PricingColors.SurfaceWhite),
        elevation = CardDefaults.cardElevation(defaultElevation = 1.5.dp),
        modifier = Modifier.fillMaxWidth()
    ) {
        Column(modifier = Modifier.padding(14.dp)) {
            // Header Row: Rule Name, Court Pill, and Toggle Switch
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Box(
                        modifier = Modifier
                            .size(36.dp)
                            .clip(CircleShape)
                            .background(
                                if (rule.isActive) PricingColors.AuthAccent.copy(alpha = 0.1f)
                                else Color.LightGray.copy(alpha = 0.2f)
                            ),
                        contentAlignment = Alignment.Center
                    ) {
                        Icon(
                            Icons.Default.Tune,
                            contentDescription = null,
                            tint = if (rule.isActive) PricingColors.AuthAccent else Color.Gray,
                            modifier = Modifier.size(18.dp)
                        )
                    }
                    Spacer(Modifier.width(10.dp))
                    Column {
                        Text(
                            text = rule.name,
                            fontSize = 14.sp,
                            fontWeight = FontWeight.Bold,
                            color = if (rule.isActive) PricingColors.AuthInk else Color.Gray
                        )
                        Text(
                            text = rule.courtName,
                            fontSize = 11.sp,
                            color = PricingColors.AuthMuted
                        )
                    }
                }

                Row(verticalAlignment = Alignment.CenterVertically) {
                    Switch(
                        checked = rule.isActive,
                        onCheckedChange = { onToggle() },
                        colors = SwitchDefaults.colors(checkedThumbColor = PricingColors.AuthAccent)
                    )
                    IconButton(onClick = onDelete) {
                        Icon(Icons.Default.DeleteOutline, contentDescription = "Delete", tint = Color.Gray, modifier = Modifier.size(18.dp))
                    }
                }
            }

            HorizontalDivider(color = PricingColors.Hairline, modifier = Modifier.padding(vertical = 10.dp))

            // Timing & Weekdays
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Column {
                    Text("ACTIVE WINDOW", fontSize = 9.sp, fontWeight = FontWeight.SemiBold, color = PricingColors.AuthMuted)
                    Text(
                        text = "${rule.startTime} - ${rule.endTime}",
                        fontSize = 12.sp,
                        fontWeight = FontWeight.Bold,
                        color = PricingColors.AuthInk
                    )
                }

                Surface(
                    shape = RoundedCornerShape(8.dp),
                    color = when (rule.pricingMode) {
                        "absolute" -> PricingColors.StandardBg
                        "delta" -> if (rule.amount >= 0) PricingColors.PeakBg else PricingColors.DiscountBg
                        "percentage" -> if (rule.amount >= 0) PricingColors.PeakBg else PricingColors.DiscountBg
                        else -> PricingColors.StandardBg
                    }
                ) {
                    Text(
                        text = when (rule.pricingMode) {
                            "absolute" -> "₹${rule.amount.toInt()} Flat"
                            "delta" -> if (rule.amount >= 0) "+₹${rule.amount.toInt()} Surge" else "-₹${Math.abs(rule.amount).toInt()} Off"
                            "percentage" -> if (rule.amount >= 0) "+${rule.amount.toInt()}% Surge" else "${rule.amount.toInt()}% Off"
                            else -> "₹${rule.amount}"
                        },
                        fontSize = 11.sp,
                        fontWeight = FontWeight.Bold,
                        color = when (rule.pricingMode) {
                            "absolute" -> PricingColors.AuthInk
                            "delta" -> if (rule.amount >= 0) PricingColors.RED else PricingColors.GREEN
                            "percentage" -> if (rule.amount >= 0) PricingColors.RED else PricingColors.GREEN
                            else -> PricingColors.AuthInk
                        },
                        modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp)
                    )
                }
            }

            Spacer(Modifier.height(8.dp))

            // Weekdays indicator row
            Row(horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                listOf("Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun").forEach { day ->
                    val isDayActive = rule.weekdays.any { it.startsWith(day, ignoreCase = true) }
                    Surface(
                        shape = RoundedCornerShape(4.dp),
                        color = if (isDayActive) PricingColors.AuthInk.copy(alpha = 0.08f) else Color.Transparent,
                        border = if (!isDayActive) androidx.compose.foundation.BorderStroke(0.5.dp, Color.LightGray.copy(alpha = 0.5f)) else null
                    ) {
                        Text(
                            text = day.take(1),
                            fontSize = 9.sp,
                            fontWeight = if (isDayActive) FontWeight.Bold else FontWeight.Normal,
                            color = if (isDayActive) PricingColors.AuthInk else Color.LightGray,
                            modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp)
                        )
                    }
                }
            }
        }
    }
}
