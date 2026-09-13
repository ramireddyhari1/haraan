package com.haraan.partner.pricing.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.partner.pricing.model.CreatePricingRuleRequest

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun PricingRuleEditorSheet(
    availableCourts: List<Pair<Long, String>>,
    isSubmitting: Boolean,
    initialCourtId: Long? = null,
    initialHour: Int? = null,
    onSubmit: (CreatePricingRuleRequest) -> Unit,
    onDismiss: () -> Unit
) {
    var ruleName by remember { mutableStateOf("") }
    var selectedCourtId by remember { mutableStateOf(initialCourtId) }

    val allWeekdays = listOf("monday", "tuesday", "wednesday", "thursday", "friday", "saturday", "sunday")
    var selectedWeekdays by remember { mutableStateOf(allWeekdays.toSet()) }

    var startTime by remember { mutableStateOf(initialHour?.let { String.format("%02d:00", it) } ?: "18:00") }
    var endTime by remember { mutableStateOf(initialHour?.let { String.format("%02d:00", (it + 1) % 24) } ?: "22:00") }

    var pricingMode by remember { mutableStateOf("absolute") } // absolute, delta, percentage
    var amountText by remember { mutableStateOf("1500") }
    var minPriceText by remember { mutableStateOf("") }
    var maxPriceText by remember { mutableStateOf("") }
    var priorityText by remember { mutableStateOf("10") }

    var validationError by remember { mutableStateOf<String?>(null) }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Create Pricing Rule", fontSize = 18.sp, fontWeight = FontWeight.Bold) },
                navigationIcon = {
                    IconButton(onClick = onDismiss) {
                        Icon(Icons.Default.Close, contentDescription = "Close")
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(containerColor = PricingColors.SurfaceWhite)
            )
        },
        containerColor = PricingColors.AuthPageBg
    ) { innerPadding ->
        LazyColumn(
            modifier = Modifier
                .fillMaxSize()
                .padding(innerPadding)
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(14.dp)
        ) {
            // Section 1: Rule Details
            item {
                Card(
                    shape = RoundedCornerShape(14.dp),
                    colors = CardDefaults.cardColors(containerColor = PricingColors.SurfaceWhite),
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Column(modifier = Modifier.padding(14.dp)) {
                        Text("RULE DETAILS", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = PricingColors.AuthMuted)
                        Spacer(Modifier.height(8.dp))

                        OutlinedTextField(
                            value = ruleName,
                            onValueChange = { ruleName = it },
                            label = { Text("Rule Name *") },
                            placeholder = { Text("e.g. Friday Prime Rush") },
                            singleLine = true,
                            modifier = Modifier.fillMaxWidth()
                        )

                        Spacer(Modifier.height(8.dp))

                        Text("Apply to Court", fontSize = 12.sp, color = PricingColors.AuthMuted)
                        LazyRow(horizontalArrangement = Arrangement.spacedBy(6.dp), modifier = Modifier.padding(vertical = 4.dp)) {
                            item {
                                FilterChip(
                                    selected = selectedCourtId == null,
                                    onClick = { selectedCourtId = null },
                                    label = { Text("All Courts") }
                                )
                            }
                            items(availableCourts) { (cId, cName) ->
                                FilterChip(
                                    selected = selectedCourtId == cId,
                                    onClick = { selectedCourtId = cId },
                                    label = { Text(cName) }
                                )
                            }
                        }
                    }
                }
            }

            // Section 2: Days & Hours
            item {
                Card(
                    shape = RoundedCornerShape(14.dp),
                    colors = CardDefaults.cardColors(containerColor = PricingColors.SurfaceWhite),
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Column(modifier = Modifier.padding(14.dp)) {
                        Text("SCHEDULE & WINDOW", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = PricingColors.AuthMuted)
                        Spacer(Modifier.height(8.dp))

                        Text("Active Weekdays", fontSize = 12.sp, color = PricingColors.AuthMuted)
                        LazyRow(horizontalArrangement = Arrangement.spacedBy(6.dp), modifier = Modifier.padding(vertical = 4.dp)) {
                            items(allWeekdays) { day ->
                                val isSelected = selectedWeekdays.contains(day)
                                FilterChip(
                                    selected = isSelected,
                                    onClick = {
                                        selectedWeekdays = if (isSelected) selectedWeekdays - day else selectedWeekdays + day
                                    },
                                    label = { Text(day.take(3).uppercase()) },
                                    colors = FilterChipDefaults.filterChipColors(
                                        selectedContainerColor = PricingColors.AuthAccent,
                                        selectedLabelColor = Color.White
                                    )
                                )
                            }
                        }

                        Spacer(Modifier.height(8.dp))

                        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                            OutlinedTextField(
                                value = startTime,
                                onValueChange = { startTime = it },
                                label = { Text("Start Time (HH:mm)") },
                                singleLine = true,
                                modifier = Modifier.weight(1f)
                            )
                            OutlinedTextField(
                                value = endTime,
                                onValueChange = { endTime = it },
                                label = { Text("End Time (HH:mm)") },
                                singleLine = true,
                                modifier = Modifier.weight(1f)
                            )
                        }
                    }
                }
            }

            // Section 3: Pricing Mode & Rate
            item {
                Card(
                    shape = RoundedCornerShape(14.dp),
                    colors = CardDefaults.cardColors(containerColor = PricingColors.SurfaceWhite),
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Column(modifier = Modifier.padding(14.dp)) {
                        Text("PRICING CALCULATION", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = PricingColors.AuthMuted)
                        Spacer(Modifier.height(8.dp))

                        Text("Calculation Mode", fontSize = 12.sp, color = PricingColors.AuthMuted)
                        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                            listOf(
                                "absolute" to "Flat ₹",
                                "delta" to "+/- ₹ Surge",
                                "percentage" to "+/- % Modifier"
                            ).forEach { (mode, label) ->
                                val isSelected = pricingMode == mode
                                Surface(
                                    shape = RoundedCornerShape(8.dp),
                                    color = if (isSelected) PricingColors.AuthAccent else PricingColors.AuthPageBg,
                                    modifier = Modifier
                                        .weight(1f)
                                        .clickable { pricingMode = mode }
                                ) {
                                    Box(modifier = Modifier.padding(vertical = 10.dp), contentAlignment = Alignment.Center) {
                                        Text(
                                            text = label,
                                            fontSize = 11.sp,
                                            fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Medium,
                                            color = if (isSelected) Color.White else PricingColors.AuthInk
                                        )
                                    }
                                }
                            }
                        }

                        Spacer(Modifier.height(10.dp))

                        OutlinedTextField(
                            value = amountText,
                            onValueChange = { amountText = it },
                            label = {
                                Text(
                                    when (pricingMode) {
                                        "absolute" -> "Rate per Hour (₹) *"
                                        "delta" -> "Adjustment (+/- ₹) *"
                                        else -> "Percentage (+/- %) *"
                                    }
                                )
                            },
                            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                            singleLine = true,
                            modifier = Modifier.fillMaxWidth()
                        )

                        Spacer(Modifier.height(8.dp))

                        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                            OutlinedTextField(
                                value = minPriceText,
                                onValueChange = { minPriceText = it },
                                label = { Text("Floor Price (₹)") },
                                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                                singleLine = true,
                                modifier = Modifier.weight(1f)
                            )
                            OutlinedTextField(
                                value = maxPriceText,
                                onValueChange = { maxPriceText = it },
                                label = { Text("Ceiling Price (₹)") },
                                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                                singleLine = true,
                                modifier = Modifier.weight(1f)
                            )
                        }
                    }
                }
            }

            // Section 4: Submit CTA
            item {
                validationError?.let {
                    Text(it, color = PricingColors.RED, fontSize = 12.sp, modifier = Modifier.padding(bottom = 6.dp))
                }

                Button(
                    onClick = {
                        if (ruleName.isBlank()) {
                            validationError = "Please enter rule name"
                            return@Button
                        }
                        if (selectedWeekdays.isEmpty()) {
                            validationError = "Select at least one weekday"
                            return@Button
                        }
                        val amt = amountText.toDoubleOrNull()
                        if (amt == null) {
                            validationError = "Enter valid amount"
                            return@Button
                        }

                        val req = CreatePricingRuleRequest(
                            name = ruleName.trim(),
                            venueCourtId = selectedCourtId,
                            weekdays = selectedWeekdays.toList(),
                            startTime = startTime.trim(),
                            endTime = endTime.trim(),
                            pricingMode = pricingMode,
                            amount = amt,
                            minPrice = minPriceText.toDoubleOrNull(),
                            maxPrice = maxPriceText.toDoubleOrNull(),
                            priority = priorityText.toIntOrNull() ?: 10
                        )
                        onSubmit(req)
                    },
                    shape = RoundedCornerShape(12.dp),
                    enabled = !isSubmitting,
                    colors = ButtonDefaults.buttonColors(containerColor = PricingColors.AuthAccent),
                    modifier = Modifier.fillMaxWidth().height(48.dp)
                ) {
                    if (isSubmitting) {
                        CircularProgressIndicator(color = Color.White, modifier = Modifier.size(18.dp), strokeWidth = 2.dp)
                        Spacer(Modifier.width(8.dp))
                        Text("Saving Rule...")
                    } else {
                        Text("Save Pricing Rule", fontWeight = FontWeight.Bold)
                    }
                }
            }
        }
    }
}
